<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: shared.php");
    exit();
}

// Get the current user's ID
$userId = getCurrentUserId();

// Validate and sanitize input
$subscriptionName = trim($_POST['subscription_name'] ?? '');
$totalCost = floatval($_POST['total_cost'] ?? 0);
$participantEmails = trim($_POST['participant_emails'] ?? '');

// Validate required fields
if (empty($subscriptionName) || $totalCost <= 0 || empty($participantEmails)) {
    $_SESSION['error'] = "Please fill in all required fields.";
    header("Location: shared.php");
    exit();
}

// Process participant emails
$emails = array_map('trim', explode(',', $participantEmails));
$emails = array_filter($emails, function($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
});

if (empty($emails)) {
    $_SESSION['error'] = "Please provide valid email addresses.";
    header("Location: shared.php");
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // First get the user's email
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userEmail = $stmt->fetchColumn();

    if (!$userEmail) {
        throw new PDOException("Could not find user email");
    }

    // Validate total cost
    if (!is_numeric($totalCost) || $totalCost <= 0) {
        throw new PDOException("Invalid total cost amount");
    }

    // Log the data we're about to insert
    error_log("Creating shared plan with name: " . $subscriptionName);
    error_log("Total cost: " . $totalCost);
    error_log("Owner ID: " . $userId);
    error_log("Participant emails: " . implode(", ", $emails));

    // Create the shared plan
    $stmt = $pdo->prepare("
        INSERT INTO shared_plans (
            name, 
            total_cost, 
            owner_id, 
            created_at
        ) VALUES (?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $subscriptionName,
        $totalCost,
        $userId
    ]);
    
    $sharedPlanId = $pdo->lastInsertId();
    error_log("Created shared plan with ID: " . $sharedPlanId);

    // Add members
    $stmt = $pdo->prepare("
        INSERT INTO shared_members (
            shared_plan_id,
            email,
            status,
            created_at
        ) VALUES (?, ?, 'pending', NOW())
    ");

    // Add all participants except the owner
    foreach ($emails as $email) {
        if ($email !== $userEmail) { // Don't add the owner as a member
            try {
                $stmt->execute([$sharedPlanId, $email]);
                error_log("Added member: " . $email);
            } catch (PDOException $e) {
                error_log("Error adding member " . $email . ": " . $e->getMessage());
                throw $e;
            }
        }
    }

    // Commit transaction
    $pdo->commit();
    error_log("Transaction committed successfully");
    
    // Clear any existing error messages
    unset($_SESSION['error']);
    $_SESSION['success'] = "Shared subscription created successfully. Invitations have been sent to participants.";

} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    error_log("Error creating shared plan: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    // Clear any existing success messages
    unset($_SESSION['success']);
    $_SESSION['error'] = "Failed to create shared subscription. Please try again. Error: " . $e->getMessage();
}

header("Location: shared.php");
exit(); 