<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = getCurrentUserId();
    $name = $_POST['name'] ?? '';
    $cost = $_POST['cost'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
    $next_payment_date = $_POST['next_payment_date'] ?? '';

    if (empty($name) || empty($cost) || empty($category_id) || empty($next_payment_date)) {
        $_SESSION['error'] = 'Please fill in all required fields';
        header('Location: add_subscription.php');
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO subscriptions (user_id, name, cost, category_id, billing_cycle, next_payment_date, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");
        
        if ($stmt->execute([$userId, $name, $cost, $category_id, $billing_cycle, $next_payment_date])) {
            $_SESSION['success'] = 'Subscription added successfully';
            header('Location: dashboard.php');
            exit();
        } else {
            $_SESSION['error'] = 'Failed to add subscription';
            header('Location: add_subscription.php');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error adding subscription: " . $e->getMessage());
        $_SESSION['error'] = 'Error adding subscription';
        header('Location: add_subscription.php');
        exit();
    }
} else {
    header('Location: add_subscription.php');
    exit();
} 