<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log all POST data
    error_log("POST data received: " . print_r($_POST, true));
    
    $userId = getCurrentUserId();
    $name = $_POST['name'] ?? '';
    $cost = $_POST['cost'] ?? '';
    $category_id = $_POST['category'] ?? '';
    $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
    $next_payment_date = $_POST['next_payment_date'] ?? '';

    // Debug logging
    error_log("Processing subscription - User ID: " . $userId);
    error_log("Subscription details - Name: " . $name . ", Cost: " . $cost . ", Category ID: " . $category_id . ", Billing Cycle: " . $billing_cycle . ", Next Payment: " . $next_payment_date);

    if (empty($name) || empty($cost) || empty($category_id) || empty($next_payment_date)) {
        $missing = [];
        if (empty($name)) $missing[] = 'name';
        if (empty($cost)) $missing[] = 'cost';
        if (empty($category_id)) $missing[] = 'category';
        if (empty($next_payment_date)) $missing[] = 'next_payment_date';
        
        error_log("Missing required fields: " . implode(', ', $missing));
        $_SESSION['error'] = 'Please fill in all required fields: ' . implode(', ', $missing);
        header('Location: add_subscription.php');
        exit();
    }

    try {
        // First verify the user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            error_log("Invalid user ID: " . $userId);
            $_SESSION['error'] = 'Invalid user ID';
            header('Location: add_subscription.php');
            exit();
        }

        // Verify category exists
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        if (!$stmt->fetch()) {
            error_log("Invalid category ID: " . $category_id);
            $_SESSION['error'] = 'Invalid category selected';
            header('Location: add_subscription.php');
            exit();
        }

        // Check if status column exists
        $columns = [];
        $stmt = $pdo->query("DESCRIBE subscriptions");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }
        
        // Insert the subscription
        if (in_array('status', $columns)) {
            $sql = "
                INSERT INTO subscriptions (user_id, name, cost, category_id, billing_cycle, next_payment_date, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ";
            $params = [$userId, $name, $cost, $category_id, $billing_cycle, $next_payment_date];
        } else {
            $sql = "
                INSERT INTO subscriptions (user_id, name, cost, category_id, billing_cycle, next_payment_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ";
            $params = [$userId, $name, $cost, $category_id, $billing_cycle, $next_payment_date];
        }
        
        error_log("Executing SQL: " . $sql);
        error_log("With parameters: " . implode(', ', $params));
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $newId = $pdo->lastInsertId();
            error_log("Subscription added successfully with ID: " . $newId);
            
            // Verify the subscription was added
            $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ?");
            $stmt->execute([$newId]);
            $newSub = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("New subscription data: " . print_r($newSub, true));
            
            $_SESSION['success'] = 'Subscription added successfully';
            header('Location: dashboard.php');
            exit();
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("Failed to add subscription - PDO error info: " . print_r($errorInfo, true));
            $_SESSION['error'] = 'Failed to add subscription: ' . $errorInfo[2];
            header('Location: add_subscription.php');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error adding subscription: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        header('Location: add_subscription.php');
        exit();
    }
} else {
    header('Location: add_subscription.php');
    exit();
} 