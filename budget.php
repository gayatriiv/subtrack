<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'session.php';
requireLogin();

$userId = getCurrentUserId();

// Get user's budget and spending information
try {
    // Get user's budget
    $stmt = $pdo->prepare("
        SELECT monthly_budget 
        FROM budget_goals 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $monthlyBudget = $stmt->fetchColumn() ?: 0;

    // Get total spending
    $stmt = $pdo->prepare("
        SELECT 
            s.cost,
            s.billing_cycle
        FROM subscriptions s 
        WHERE s.user_id = ? AND s.status = 'active'
    ");
    $stmt->execute([$userId]);
    $subscriptions = $stmt->fetchAll();

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

    // Get potential savings (subscriptions with same category)
    $stmt = $pdo->prepare("
        SELECT 
            s.name,
            s.cost,
            s.billing_cycle,
            c.name as category_name,
            CASE 
                WHEN s.billing_cycle = 'yearly' THEN s.cost / 12
                WHEN s.billing_cycle = 'quarterly' THEN s.cost / 3
                WHEN s.billing_cycle = 'weekly' THEN s.cost * 4
                ELSE s.cost 
            END as monthly_cost
        FROM subscriptions s
        LEFT JOIN categories c ON s.category_id = c.id
        WHERE s.user_id = ? AND s.status = 'active'
        ORDER BY c.name, s.cost DESC
    ");
    $stmt->execute([$userId]);
    $allSubscriptions = $stmt->fetchAll();

    // Initialize potential savings array
    $potentialSavings = [];

    if ($monthlyTotal > $monthlyBudget) {
        // Group subscriptions by category
        $categorySpending = [];
        $categorySubscriptions = [];
        
        foreach ($allSubscriptions as $sub) {
            $category = $sub['category_name'] ?: 'Uncategorized';
            if (!isset($categorySpending[$category])) {
                $categorySpending[$category] = 0;
                $categorySubscriptions[$category] = [];
            }
            $categorySpending[$category] += $sub['monthly_cost'];
            $categorySubscriptions[$category][] = $sub;
        }

        // Generate savings suggestions
        arsort($categorySpending);
        foreach ($categorySpending as $category => $spending) {
            if ($spending > ($monthlyBudget * 0.3)) { // If category spending is more than 30% of budget
                $potentialSavings[] = [
                    'category' => $category,
                    'spending' => $spending,
                    'subscriptions' => $categorySubscriptions[$category],
                    'suggestion' => "Consider reviewing your $category subscriptions as they make up " . 
                                  round(($spending / $monthlyTotal) * 100) . "% of your monthly spending"
                ];
            }
        }

        // Look for duplicate services
        $serviceTypes = [
            'Streaming' => ['netflix', 'hulu', 'disney', 'prime', 'hbo', 'paramount', 'peacock'],
            'Music' => ['spotify', 'apple music', 'tidal', 'youtube music', 'pandora', 'deezer'],
            'Cloud Storage' => ['dropbox', 'google drive', 'onedrive', 'icloud', 'box'],
            'Gaming' => ['xbox', 'playstation', 'nintendo', 'game pass', 'ea play', 'ubisoft+']
        ];

        foreach ($serviceTypes as $type => $keywords) {
            $matches = [];
            foreach ($allSubscriptions as $sub) {
                foreach ($keywords as $keyword) {
                    if (stripos($sub['name'], $keyword) !== false) {
                        $matches[] = $sub;
                        break;
                    }
                }
            }
            if (count($matches) > 1) {
                $totalCost = array_sum(array_column($matches, 'monthly_cost'));
                $potentialSavings[] = [
                    'category' => $type,
                    'spending' => $totalCost,
                    'subscriptions' => $matches,
                    'suggestion' => "You have multiple $type services costing \${$totalCost}/month. Consider consolidating to save money."
                ];
            }
        }
    }

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $monthlyBudget = 0;
    $monthlyTotal = 0;
    $potentialSavings = [];
}

$remaining = $monthlyBudget - $monthlyTotal;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Management - SubsTrack</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .budget-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .alert {
            background: rgba(244, 67, 54, 0.1);
            border-left: 4px solid #f44336;
            color: #f44336;
            padding: 15px 20px;
            margin: 20px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert i {
            font-size: 20px;
        }

        .budget-card {
            background: #2d2d2d;
            border-radius: 8px;
            padding: 20px;
        }

        .budget-card h2 {
            color: #4CAF50;
            font-size: 20px;
            margin-bottom: 20px;
        }

        .budget-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            margin-bottom: 10px;
        }

        .budget-stat:last-child {
            margin-bottom: 0;
        }

        .stat-label {
            color: #aaaaaa;
        }

        .stat-value {
            font-weight: 500;
        }

        .stat-value.positive {
            color: #4CAF50;
        }

        .budget-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            color: #aaaaaa;
            font-size: 14px;
        }

        .form-group input {
            padding: 10px;
            background: #1a1a1a;
            border: 1px solid #3d3d3d;
            border-radius: 6px;
            color: #ffffff;
            font-size: 16px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4CAF50;
        }

        .submit-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .submit-btn:hover {
            background: #45a049;
        }

        .savings-card {
            color: #aaaaaa;
            text-align: left;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .savings-card:last-child {
            margin-bottom: 0;
        }

        .savings-card.positive {
            color: #4CAF50;
            text-align: center;
        }

        .suggestion-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .suggestion-header i {
            color: #ffd700;
        }

        .suggestion-header h4 {
            color: #ffffff;
            margin: 0;
        }

        .related-subscriptions {
            margin-top: 15px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
        }

        .subscription-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            color: #aaaaaa;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .subscription-item:last-child {
            border-bottom: none;
        }

        .stat-value.negative {
            color: #f44336;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <?php if ($monthlyBudget > 0 && $monthlyTotal > $monthlyBudget): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    Warning: Your current spending exceeds your monthly budget by $<?php echo number_format($monthlyTotal - $monthlyBudget, 2); ?>
                </div>
            <?php endif; ?>

            <div class="budget-grid">
                <!-- Monthly Budget Overview -->
                <div class="budget-card">
                    <h2>Monthly Budget Overview</h2>
                    <div class="budget-stat">
                        <span class="stat-label">Total Budget:</span>
                        <span class="stat-value">$<?php echo number_format($monthlyBudget, 2); ?></span>
                    </div>
                    <div class="budget-stat">
                        <span class="stat-label">Total Spending:</span>
                        <span class="stat-value">$<?php echo number_format($monthlyTotal, 2); ?></span>
                    </div>
                    <div class="budget-stat">
                        <span class="stat-label">Remaining:</span>
                        <span class="stat-value <?php echo $remaining < 0 ? 'negative' : 'positive'; ?>">
                            $<?php echo number_format($remaining, 2); ?>
                        </span>
                    </div>
                </div>

                <!-- Set Monthly Budget -->
                <div class="budget-card">
                    <h2>Set Monthly Budget</h2>
                    <form action="update_budget.php" method="POST" class="budget-form">
                        <div class="form-group">
                            <label for="monthly_budget">Monthly Budget Amount ($)</label>
                            <input type="number" id="monthly_budget" name="monthly_budget" step="0.01" min="0" 
                                   value="<?php echo $monthlyBudget; ?>" required>
                        </div>
                        <button type="submit" class="submit-btn">Update Budget</button>
                    </form>
                </div>

                <!-- Potential Savings -->
                <div class="budget-card">
                    <h2>Potential Savings</h2>
                    <?php if (empty($potentialSavings)): ?>
                        <div class="savings-card positive">
                            No savings suggestions at this time. You're within your budget!
                        </div>
                    <?php else: ?>
                        <?php foreach ($potentialSavings as $saving): ?>
                            <div class="savings-card">
                                <div class="suggestion-header">
                                    <i class="fas fa-lightbulb"></i>
                                    <h4><?php echo htmlspecialchars($saving['category']); ?></h4>
                                </div>
                                <p><?php echo htmlspecialchars($saving['suggestion']); ?></p>
                                <?php if (isset($saving['subscriptions'])): ?>
                                    <div class="related-subscriptions">
                                        <?php foreach ($saving['subscriptions'] as $sub): ?>
                                            <div class="subscription-item">
                                                <span><?php echo htmlspecialchars($sub['name']); ?></span>
                                                <span>$<?php echo number_format($sub['monthly_cost'], 2); ?>/mo</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add any JavaScript for interactivity here
    </script>
</body>
</html> 