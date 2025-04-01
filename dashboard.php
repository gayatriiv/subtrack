<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'session.php';
requireLogin();

$userId = getCurrentUserId();

// Get user's name and budget
$stmt = $pdo->prepare("
    SELECT u.name, COALESCE(bg.monthly_budget, 0) as monthly_budget 
    FROM users u 
    LEFT JOIN budget_goals bg ON u.id = bg.user_id 
    WHERE u.id = ? 
    ORDER BY bg.created_at DESC 
    LIMIT 1
");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
$userName = $userData['name'];
$monthlyBudget = $userData['monthly_budget'];

// Get user's subscriptions with proper error handling
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.name,
            s.cost,
            s.billing_cycle,
            s.next_payment_date,
            s.category_id,
            COALESCE(s.status, 'active') as status,
            COALESCE(c.name, 'Other') as category_name,
            COALESCE(s.created_at, CURRENT_TIMESTAMP) as created_at
        FROM subscriptions s 
        LEFT JOIN categories c ON s.category_id = c.id 
        WHERE s.user_id = ? AND s.status = 'active'
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$userId]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total monthly spending
    $monthlyTotal = 0;
    foreach ($subscriptions as $sub) {
        switch ($sub['billing_cycle']) {
            case 'weekly':
                $monthlyTotal += $sub['cost'] * 4;
                break;
            case 'monthly':
                $monthlyTotal += $sub['cost'];
                break;
            case 'quarterly':
                $monthlyTotal += $sub['cost'] / 3;
                break;
            case 'yearly':
                $monthlyTotal += $sub['cost'] / 12;
                break;
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching subscriptions: " . $e->getMessage());
    $subscriptions = [];
    $monthlyTotal = 0;
}

// Get categories for filter
$categories = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT id, name 
        FROM categories 
        WHERE name IN ('Education', 'Fitness', 'Gaming', 'Music', 'News', 'Software', 'Streaming', 'Utilities', 'Other')
        ORDER BY 
            CASE name 
                WHEN 'Education' THEN 1
                WHEN 'Fitness' THEN 2
                WHEN 'Gaming' THEN 3
                WHEN 'Music' THEN 4
                WHEN 'News' THEN 5
                WHEN 'Software' THEN 6
                WHEN 'Streaming' THEN 7
                WHEN 'Utilities' THEN 8
                WHEN 'Other' THEN 9
            END
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SubsTrack</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Dashboard specific styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 0 20px;
        }

        .header h2 {
            font-size: 24px;
        }

        .header-buttons {
            display: flex;
            gap: 10px;
        }

        .budget-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 16px;
            color: rgba(255,255,255,0.9);
        }

        .progress-container {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            height: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(to right, #4CAF50, #81c784);
            transition: width 0.3s ease;
        }

        .subscription-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 0 20px;
        }

        .subscription-card {
            background: #2d2d2d;
            border-radius: 8px;
            padding: 20px;
            position: relative;
            transition: transform 0.3s ease;
        }

        .subscription-card:hover {
            transform: translateY(-5px);
        }

        .subscription-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .subscription-name {
            font-weight: 500;
            font-size: 18px;
        }

        .subscription-category {
            font-size: 12px;
            padding: 4px 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            color: rgba(255, 255, 255, 0.7);
        }

        .subscription-cost {
            font-size: 24px;
            color: #4CAF50;
            margin: 10px 0;
        }

        .subscription-details {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            margin-top: 10px;
        }

        .subscription-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: none;
            gap: 10px;
        }

        .subscription-card:hover .subscription-actions {
            display: flex;
        }

        .action-icon {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 18px;
            transition: color 0.3s;
            cursor: pointer;
            padding: 5px;
        }

        .edit-icon:hover {
            color: #4CAF50;
        }

        .delete-icon:hover {
            color: #f44336;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: rgba(255, 255, 255, 0.7);
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="header">
                <h2>Welcome, <?php echo htmlspecialchars($userName); ?></h2>
                <div class="header-buttons">
                    <a href="add_subscription.php" class="btn btn-primary">
                        <i class='bx bx-plus'></i> Add Subscription
                    </a>
                    <a href="export_csv.php" class="btn btn-secondary">
                        <i class='bx bx-download'></i> Export CSV
                    </a>
                </div>
            </div>

            <?php if ($monthlyBudget > 0 && $monthlyTotal > $monthlyBudget): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    Warning: Your current spending exceeds your monthly budget by $<?php echo number_format($monthlyTotal - $monthlyBudget, 2); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <h3>Monthly Budget Overview</h3>
                <div class="budget-info">
                    <span>Monthly Spending: $<?php echo number_format($monthlyTotal, 2); ?></span>
                    <span>Budget: $<?php echo number_format($monthlyBudget, 2); ?></span>
                </div>
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?php echo $monthlyBudget > 0 ? min(($monthlyTotal / $monthlyBudget) * 100, 100) : 0; ?>%"></div>
                </div>
            </div>

            <div class="card">
                <h3>Active Subscriptions</h3>
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success" style="background: rgba(76, 175, 80, 0.1); color: #4CAF50; padding: 10px; border-radius: 4px; margin-bottom: 15px; border: 1px solid rgba(76, 175, 80, 0.2);">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); ?>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="subscription-grid">
                    <?php if (empty($subscriptions)): ?>
                        <div class="subscription-card" style="display: flex; justify-content: center; align-items: center; text-align: center;">
                            <div>
                                <p style="margin-bottom: 15px; color: rgba(255,255,255,0.7);">No subscriptions added yet.</p>
                                <a href="add_subscription.php" class="action-btn add-btn">Add Your First Subscription</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($subscriptions as $subscription): ?>
                            <div class="subscription-card">
                                <div class="subscription-actions">
                                    <a href="edit_subscription.php?id=<?php echo $subscription['id']; ?>" class="action-icon edit-icon" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_subscription.php?id=<?php echo $subscription['id']; ?>" 
                                       class="action-icon delete-icon" 
                                       title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this subscription?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                                <div class="subscription-header">
                                    <div class="subscription-name"><?php echo htmlspecialchars($subscription['name']); ?></div>
                                    <div class="subscription-category"><?php echo htmlspecialchars($subscription['category_name']); ?></div>
                                </div>
                                <div class="subscription-cost">
                                    $<?php echo number_format($subscription['cost'], 2); ?>
                                    <span style="font-size: 14px; color: rgba(255,255,255,0.7)">
                                        /<?php echo $subscription['billing_cycle']; ?>
                                    </span>
                                </div>
                                <div class="subscription-details">
                                    Next payment: <?php echo date('M j, Y', strtotime($subscription['next_payment_date'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 