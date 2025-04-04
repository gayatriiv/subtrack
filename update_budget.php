<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = getCurrentUserId();
    $monthlyBudget = $_POST['monthly_budget'] ?? '';

    if (empty($monthlyBudget) || !is_numeric($monthlyBudget) || $monthlyBudget < 0) {
        $_SESSION['error'] = 'Please enter a valid budget amount';
        header('Location: budget.php');
        exit();
    }

    try {
        // Insert new budget goal
        $stmt = $pdo->prepare("
            INSERT INTO budget_goals (user_id, monthly_budget) 
            VALUES (?, ?)
        ");
        
        if ($stmt->execute([$userId, $monthlyBudget])) {
            $_SESSION['success'] = 'Budget updated successfully';
        } else {
            $_SESSION['error'] = 'Failed to update budget';
        }
    } catch (PDOException $e) {
        error_log("Error updating budget: " . $e->getMessage());
        $_SESSION['error'] = 'Error updating budget';
    }
}

header('Location: budget.php');
exit(); 