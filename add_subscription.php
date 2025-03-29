<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'session.php';
requireLogin();

$userId = getCurrentUserId();

// Get categories
try {
    $stmt = $pdo->prepare("
        SELECT id, name 
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
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Subscription - SubsTrack</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Add Subscription specific styles */
        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .submit-btn {
            background: #4CAF50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
            width: 100%;
        }
        
        .submit-btn:hover {
            background: #45a049;
        }

        select.form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            padding: 8px 12px;
            color: white;
            font-size: 14px;
            width: 100%;
            cursor: pointer;
            appearance: auto;
        }

        select.form-control option {
            background: #2a2a2a;
            color: white;
            padding: 8px;
        }

        select.form-control:focus {
            outline: none;
            border-color: #4CAF50;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h2>Add New Subscription</h2>
            </div>
            
            <div class="card">
                <form action="process_subscription.php" method="POST" class="form-container">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Subscription Name</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="cost">Cost</label>
                            <input type="number" id="cost" name="cost" step="0.01" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select name="category" id="category" class="form-control" required>
                                <option value="" disabled selected>Select Category</option>
                                <option value="streaming">Streaming</option>
                                <option value="fitness">Fitness</option>
                                <option value="music">Music</option>
                                <option value="software">Software</option>
                                <option value="news">News</option>
                                <option value="utilities">Utilities</option>
                                <option value="magazines">Magazines</option>
                                <option value="education">Education</option>
                                <option value="gaming">Gaming</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="billing_cycle">Billing Cycle</label>
                            <select id="billing_cycle" name="billing_cycle" class="form-control" required>
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="next_payment">Next Payment Date</label>
                        <input type="date" id="next_payment" name="next_payment_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-plus"></i> Add Subscription
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 