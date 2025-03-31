<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'session.php';
requireLogin();

$userId = getCurrentUserId();

try {
    // Fetch all active subscriptions for the user
    $stmt = $pdo->prepare("
        SELECT 
            s.name as subscription_name,
            s.cost,
            s.billing_cycle,
            s.next_payment_date,
            c.name as category_name,
            CASE 
                WHEN s.billing_cycle = 'weekly' THEN s.cost * 4
                WHEN s.billing_cycle = 'monthly' THEN s.cost
                WHEN s.billing_cycle = 'quarterly' THEN s.cost / 3
                WHEN s.billing_cycle = 'yearly' THEN s.cost / 12
                ELSE s.cost
            END as monthly_cost
        FROM subscriptions s
        LEFT JOIN categories c ON s.category_id = c.id
        WHERE s.user_id = ? AND s.status = 'active'
        ORDER BY s.name ASC
    ");
    
    $stmt->execute([$userId]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="substrack_subscriptions_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for proper Excel encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, [
        'Subscription Name',
        'Category',
        'Cost',
        'Billing Cycle',
        'Next Payment Date',
        'Monthly Cost'
    ]);
    
    // Add data rows
    foreach ($subscriptions as $subscription) {
        fputcsv($output, [
            $subscription['subscription_name'],
            $subscription['category_name'] ?? 'Uncategorized',
            '$' . number_format($subscription['cost'], 2),
            ucfirst($subscription['billing_cycle']),
            date('Y-m-d', strtotime($subscription['next_payment_date'])),
            '$' . number_format($subscription['monthly_cost'], 2)
        ]);
    }
    
    // Close the output stream
    fclose($output);
    exit();

} catch (PDOException $e) {
    error_log("Error exporting CSV: " . $e->getMessage());
    $_SESSION['error'] = "Failed to export subscriptions. Please try again.";
    header("Location: dashboard.php");
    exit();
} 