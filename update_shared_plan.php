<?php
require_once 'config.php';
require_once 'session.php';
requireLogin();

$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planId = $_POST['plan_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $cost = $_POST['cost'] ?? '';

    try {
        // Verify ownership
        $stmt = $pdo->prepare("
            SELECT owner_id 
            FROM shared_plans 
            WHERE id = ?
        ");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();

        if (!$plan || $plan['owner_id'] !== $userId) {
            throw new Exception("You don't have permission to edit this subscription.");
        }

        // Update the plan
        $stmt = $pdo->prepare("
            UPDATE shared_plans 
            SET name = ?, 
                total_cost = ?
            WHERE id = ? 
            AND owner_id = ?
        ");
        
        $stmt->execute([$name, $cost, $planId, $userId]);

        $_SESSION['success'] = "Shared subscription updated successfully.";
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

header("Location: shared.php");
exit(); 