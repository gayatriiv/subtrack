<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'session.php';

echo "<pre>";

// Check table structure
echo "Table Structures:\n";
echo "\nCategories table structure:\n";
$stmt = $pdo->query("DESCRIBE categories");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\nSubscriptions table structure:\n";
$stmt = $pdo->query("DESCRIBE subscriptions");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

// Check categories
echo "\nCategories in database:\n";
$stmt = $pdo->query("SELECT * FROM categories");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($categories);

// Check if any subscriptions exist
echo "\nAll subscriptions in database:\n";
$stmt = $pdo->query("SELECT s.*, c.name as category_name, u.name as user_name 
                     FROM subscriptions s 
                     LEFT JOIN categories c ON s.category_id = c.id 
                     LEFT JOIN users u ON s.user_id = u.id");
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($subscriptions);

// Check users
echo "\nUsers in database:\n";
$stmt = $pdo->query("SELECT id, name, email FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($users);

// Get current user ID
echo "\nCurrent user ID: " . getCurrentUserId() . "\n";

// Check subscriptions for current user
echo "\nSubscriptions for current user:\n";
$stmt = $pdo->prepare("SELECT s.*, c.name as category_name 
                       FROM subscriptions s 
                       LEFT JOIN categories c ON s.category_id = c.id 
                       WHERE s.user_id = ?");
$stmt->execute([getCurrentUserId()]);
$userSubscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($userSubscriptions);

echo "</pre>"; 