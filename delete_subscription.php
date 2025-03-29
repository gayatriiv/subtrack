<?php
require_once 'config.php';
require_once 'session.php';

// Require login to access this page
requireLogin();

if (isset($_GET['id'])) {
    $subscriptionId = $_GET['id'];
    $userId = getCurrentUserId();

    try {
        // First verify that this subscription belongs to the current user
        $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE id = ? AND user_id = ?");
        $stmt->execute([$subscriptionId, $userId]);
        
        if ($stmt->rowCount() > 0) {
            // Delete the subscription
            $stmt = $pdo->prepare("DELETE FROM subscriptions WHERE id = ? AND user_id = ?");
            $stmt->execute([$subscriptionId, $userId]);
            
            $_SESSION['success_message'] = "Subscription deleted successfully.";
        } else {
            $_SESSION['error_message'] = "You don't have permission to delete this subscription.";
        }
    } catch (PDOException $e) {
        error_log("Error deleting subscription: " . $e->getMessage());
        $_SESSION['error_message'] = "Error deleting subscription. Please try again.";
    }
}

// Redirect back to dashboard
header('Location: dashboard.php');
exit(); 