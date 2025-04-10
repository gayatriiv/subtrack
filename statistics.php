<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'session.php';
requireLogin();

$userId = getCurrentUserId();

// Fetch monthly spending trends for the last 6 months
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(next_payment_date, '%Y-%m') as month,
            SUM(CASE 
                WHEN billing_cycle = 'weekly' THEN cost * 4
                WHEN billing_cycle = 'monthly' THEN cost
                WHEN billing_cycle = 'quarterly' THEN cost / 3
                WHEN billing_cycle = 'yearly' THEN cost / 12
                ELSE cost
            END) as monthly_cost
        FROM subscriptions 
        WHERE user_id = ? 
        AND next_payment_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(next_payment_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$userId]);
    $monthlyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching monthly trends: " . $e->getMessage());
    $monthlyTrends = [];
}

try {
    // Get spending by category
    $stmt = $pdo->prepare("
        SELECT 
            c.name as category_name,
            SUM(CASE 
                WHEN s.billing_cycle = 'weekly' THEN s.cost * 4
                WHEN s.billing_cycle = 'monthly' THEN s.cost
                WHEN s.billing_cycle = 'quarterly' THEN s.cost / 3
                WHEN s.billing_cycle = 'yearly' THEN s.cost / 12
                ELSE s.cost
            END) as monthly_cost
        FROM subscriptions s
        LEFT JOIN categories c ON s.category_id = c.id
        WHERE s.user_id = ? AND s.status = 'active'
        GROUP BY c.name
        ORDER BY monthly_cost DESC
    ");
    $stmt->execute([$userId]);
    $categorySpending = $stmt->fetchAll();

    // Get upcoming payments
    $stmt = $pdo->prepare("
        SELECT 
            name,
            cost,
            billing_cycle,
            next_payment_date
        FROM subscriptions 
        WHERE user_id = ? 
            AND status = 'active'
            AND next_payment_date >= CURRENT_DATE
            AND next_payment_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
        ORDER BY next_payment_date ASC
    ");
    $stmt->execute([$userId]);
    $upcomingPayments = $stmt->fetchAll();

    // Get spending trends (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(next_payment_date, '%Y-%m') as month,
            SUM(CASE 
                WHEN billing_cycle = 'weekly' THEN cost * 4
                WHEN billing_cycle = 'monthly' THEN cost
                WHEN billing_cycle = 'quarterly' THEN cost / 3
                WHEN billing_cycle = 'yearly' THEN cost / 12
                ELSE cost
            END) as monthly_cost
        FROM subscriptions
        WHERE user_id = ? 
            AND status = 'active'
            AND next_payment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(next_payment_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$userId]);
    $spendingTrends = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $categorySpending = [];
    $upcomingPayments = [];
    $spendingTrends = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - SubsTrack</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            background:rgb(0, 0, 0);
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        .sidebar {
            width: 250px;
            min-width: 250px;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
        }

        .main-content {
            width: calc(100% - 250px);
            margin-left: 250px;
            padding: 20px;
            box-sizing: border-box;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            width: 100%;
            box-sizing: border-box;
        }

        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 1100px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            height: auto;
            width: 100%;
            box-sizing: border-box;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
            margin-bottom: 20px;
        }

        .category-list {
            margin-top: 20px;
        }

        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .category-item:last-child {
            border-bottom: none;
        }

        .category-name {
            color: white;
        }

        .category-amount {
            color: #4CAF50;
        }

        .upcoming-payment {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .upcoming-payment:last-child {
            border-bottom: none;
        }

        .payment-info {
            display: flex;
            flex-direction: column;
        }

        .payment-name {
            color: white;
            font-weight: 500;
        }

        .payment-date {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9em;
        }

        .payment-amount {
            color: #4CAF50;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="stats-grid">
                <div class="card">
                    <h3>Spending by Category</h3>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                    <div class="category-list">
                        <?php foreach ($categorySpending as $category): ?>
                            <div class="category-item">
                                <span class="category-name"><?php echo htmlspecialchars($category['category_name'] ?? 'Uncategorized'); ?></span>
                                <span class="category-amount">₹<?php echo number_format($category['monthly_cost'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="card">
                    <h3>Monthly Spending Trends</h3>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
                
                <div class="card">
                    <h3>Upcoming Payments (Next 30 Days)</h3>
                    <div class="upcoming-payments">
                        <?php foreach ($upcomingPayments as $payment): ?>
                            <div class="upcoming-payment">
                                <div class="payment-info">
                                    <div class="payment-name"><?php echo htmlspecialchars($payment['name']); ?></div>
                                    <div class="payment-details">
                                        <?php echo ucfirst($payment['billing_cycle']); ?> billing
                                    </div>
                                </div>
                                <div class="payment-right">
                                    <div class="payment-amount">₹<?php echo number_format($payment['cost'], 2); ?></div>
                                    <div class="payment-date">
                                        <?php echo date('M j, Y', strtotime($payment['next_payment_date'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($upcomingPayments)): ?>
                            <p>No upcoming payments in the next 30 days.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Category Spending Chart
        const categoryData = <?php echo json_encode(array_map(function($cat) {
            return [
                'category' => $cat['category_name'] ?? 'Uncategorized',
                'amount' => floatval($cat['monthly_cost'])
            ];
        }, $categorySpending)); ?>;

        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: categoryData.map(d => d.category),
                datasets: [{
                    data: categoryData.map(d => d.amount),
                    backgroundColor: [
                        '#4CAF50', '#2196F3', '#FFC107', '#9C27B0', '#FF5722',
                        '#607D8B', '#795548', '#E91E63', '#00BCD4', '#8BC34A'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'white'
                        }
                    }
                }
            }
        });

        // Monthly Trend Chart
            // Monthly Spending Trends Chart
    const trendData = <?php echo json_encode(array_map(function($month) {
        return [
            'month' => $month['month'],
            'amount' => floatval($month['monthly_cost'])
        ];
    }, $spendingTrends)); ?>;

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: trendData.map(d => d.month),
            datasets: [{
                label: 'Monthly Spending (₹)',
                data: trendData.map(d => d.amount),
                fill: false,
                borderColor: '#4CAF50',
                backgroundColor: '#4CAF50',
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    ticks: { color: 'white' },
                    grid: { color: 'rgba(255,255,255,0.1)' }
                },
                y: {
                    ticks: { color: 'white' },
                    grid: { color: 'rgba(255,255,255,0.1)' }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: 'white'
                    }
                }
            }
        }
    });
</script>
