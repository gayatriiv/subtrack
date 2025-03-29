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
    $stmt = $pdo->prepare("SELECT monthly_budget FROM users WHERE id = ?");
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
            c.name as category_name
        FROM subscriptions s
        LEFT JOIN categories c ON s.category_id = c.id
        WHERE s.user_id = ? AND s.status = 'active'
        ORDER BY c.name, s.cost DESC
    ");
    $stmt->execute([$userId]);
    $potentialSavings = $stmt->fetchAll();

    // Add this after fetching user's subscriptions
    $potentialSavings = [];

    if ($monthlyTotal > $monthlyBudget) {
        // Get subscriptions sorted by cost
        $stmt = $pdo->prepare("
            SELECT 
                s.*, 
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
            ORDER BY monthly_cost DESC
        ");
        $stmt->execute([$userId]);
        $expensiveSubscriptions = $stmt->fetchAll();

        // Group by category for analysis
        $categorySpending = [];
        foreach ($expensiveSubscriptions as $sub) {
            if (!isset($categorySpending[$sub['category_name']])) {
                $categorySpending[$sub['category_name']] = 0;
            }
            $categorySpending[$sub['category_name']] += $sub['monthly_cost'];
        }

        // Generate savings suggestions
        arsort($categorySpending);
        foreach ($categorySpending as $category => $spending) {
            $subs = array_filter($expensiveSubscriptions, function($sub) use ($category) {
                return $sub['category_name'] === $category;
            });
            
            if ($spending > ($monthlyBudget * 0.3)) { // If category spending is more than 30% of budget
                $potentialSavings[] = [
                    'category' => $category,
                    'spending' => $spending,
                    'subscriptions' => $subs,
                    'suggestion' => "Consider reviewing your $category subscriptions as they make up " . 
                                  round(($spending / $monthlyTotal) * 100) . "% of your spending"
                ];
            }
        }

        // Look for duplicate services
        $serviceTypes = [
            'Streaming' => ['netflix', 'hulu', 'disney', 'prime', 'hbo'],
            'Music' => ['spotify', 'apple music', 'tidal', 'youtube music'],
            'Cloud Storage' => ['dropbox', 'google drive', 'onedrive', 'icloud']
        ];

        foreach ($serviceTypes as $type => $keywords) {
            $matches = [];
            foreach ($expensiveSubscriptions as $sub) {
                foreach ($keywords as $keyword) {
                    if (stripos($sub['name'], $keyword) !== false) {
                        $matches[] = $sub;
                    }
                }
            }
            if (count($matches) > 1) {
                $potentialSavings[] = [
                    'category' => $type,
                    'subscriptions' => $matches,
                    'suggestion' => "You have multiple $type services. Consider consolidating to save money."
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
            margin-top: 20px;
        }

        .budget-overview {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .budget-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .budget-item:last-child {
            border-bottom: none;
        }

        .budget-label {
            color: rgba(255,255,255,0.7);
        }

        .budget-value {
            font-weight: 500;
        }

        .budget-value.negative {
            color: #f44336;
        }

        .budget-value.positive {
            color: #4CAF50;
        }

        .budget-form {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .budget-input {
            flex: 1;
        }

        .savings-item {
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .savings-header {
            color: #4CAF50;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .savings-details {
            color: rgba(255,255,255,0.7);
            font-size: 14px;
        }

        .progress-bar-container {
            width: 100%;
            height: 6px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            margin-top: 15px;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .progress-bar-fill.under-budget {
            background: #4CAF50;
        }

        .progress-bar-fill.over-budget {
            background: #f44336;
        }

        .saving-suggestion {
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
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

        .related-subscriptions {
            margin-top: 10px;
            padding: 10px;
            background: rgba(0,0,0,0.2);
            border-radius: 4px;
        }

        .subscription-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            color: rgba(255,255,255,0.7);
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
                <div class="card">
                    <h3>Monthly Budget Overview</h3>
                    <div class="budget-overview">
                        <div class="budget-item">
                            <span class="budget-label">Total Budget:</span>
                            <span class="budget-value">$<?php echo number_format($monthlyBudget, 2); ?></span>
                        </div>
                        <div class="budget-item">
                            <span class="budget-label">Total Spending:</span>
                            <span class="budget-value">$<?php echo number_format($monthlyTotal, 2); ?></span>
                        </div>
                        <div class="budget-item">
                            <span class="budget-label">Remaining:</span>
                            <span class="budget-value <?php echo $remaining < 0 ? 'negative' : 'positive'; ?>">
                                $<?php echo number_format($remaining, 2); ?>
                            </span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill <?php echo $monthlyTotal > $monthlyBudget ? 'over-budget' : 'under-budget'; ?>"
                                 style="width: <?php echo $monthlyBudget > 0 ? min(($monthlyTotal / $monthlyBudget) * 100, 100) : 0; ?>%">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Set Monthly Budget -->
                <div class="card">
                    <h3>Set Monthly Budget</h3>
                    <form action="update_budget.php" method="POST">
                        <div class="form-group">
                            <label for="monthly_budget">Monthly Budget Amount ($)</label>
                            <input type="number" 
                                   id="monthly_budget" 
                                   name="monthly_budget" 
                                   class="form-control" 
                                   value="<?php echo $monthlyBudget; ?>" 
                                   step="0.01" 
                                   min="0" 
                                   required>
                        </div>
                        <button type="submit" class="action-btn add-btn">Update Budget</button>
                    </form>
                </div>

                <!-- Potential Savings -->
                <div class="card">
                    <h3>Potential Savings</h3>
                    <?php if (empty($potentialSavings)): ?>
                        <p>No savings suggestions at this time. You're within your budget!</p>
                    <?php else: ?>
                        <?php foreach ($potentialSavings as $saving): ?>
                            <div class="saving-suggestion">
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