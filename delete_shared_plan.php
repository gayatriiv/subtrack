<?php
require_once 'config.php';
require_once 'session.php';
requireLogin();

$userId = getCurrentUserId();
$planId = $_GET['id'] ?? '';

try {
    if (!$planId) {
        throw new Exception("No subscription specified.");
    }

    // Start transaction
    $pdo->beginTransaction();

    // Verify ownership
    $stmt = $pdo->prepare("
        SELECT owner_id 
        FROM shared_plans 
        WHERE id = ?
    ");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();

    // Convert both values to integers for comparison
    if (!$plan || intval($plan['owner_id']) !== intval($userId)) {
        throw new Exception("You don't have permission to delete this subscription.");
    }

    // Delete members first (due to foreign key constraint)
    $stmt = $pdo->prepare("
        DELETE FROM shared_members 
        WHERE shared_plan_id = ?
    ");
    $stmt->execute([$planId]);

    // Delete the plan
    $stmt = $pdo->prepare("
        DELETE FROM shared_plans 
        WHERE id = ? 
        AND owner_id = ?
    ");
    $stmt->execute([$planId, $userId]);

    // Commit transaction
    $pdo->commit();
    $_SESSION['success'] = "Shared subscription deleted successfully.";

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = $e->getMessage();
}

header("Location: shared.php");
exit();
?> 