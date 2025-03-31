<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

try {
    // Create users table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create categories table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            description TEXT
        )
    ");

    // Create subscriptions table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            cost DECIMAL(10,2) NOT NULL,
            category_id INT NOT NULL,
            billing_cycle VARCHAR(20) NOT NULL,
            next_payment_date DATE NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id)
        )
    ");

    // Clean up duplicate categories
    $pdo->beginTransaction();
    
    try {
        // First, update any subscriptions using duplicate categories to use the lowest ID for each category
        $stmt = $pdo->query("
            SELECT name, MIN(id) as main_id, GROUP_CONCAT(id) as duplicate_ids
            FROM categories
            GROUP BY name
            HAVING COUNT(*) > 1
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ids = explode(',', $row['duplicate_ids']);
            $main_id = $row['main_id'];
            
            // Update subscriptions to use the main category ID
            foreach ($ids as $id) {
                if ($id != $main_id) {
                    $pdo->prepare("
                        UPDATE subscriptions 
                        SET category_id = ? 
                        WHERE category_id = ?
                    ")->execute([$main_id, $id]);
                }
            }
            
            // Delete duplicate categories
            foreach ($ids as $id) {
                if ($id != $main_id) {
                    $pdo->prepare("
                        DELETE FROM categories 
                        WHERE id = ?
                    ")->execute([$id]);
                }
            }
        }
        
        // Re-insert default categories
        $categories = [
            'Streaming' => 'Video and music streaming services',
            'Gaming' => 'Gaming subscriptions and services',
            'News' => 'News and magazine subscriptions',
            'Software' => 'Software and app subscriptions',
            'Fitness' => 'Fitness and wellness subscriptions',
            'Education' => 'Educational platform subscriptions',
            'Other' => 'Other miscellaneous subscriptions'
        ];

        foreach ($categories as $name => $description) {
            $pdo->prepare("
                INSERT INTO categories (name, description)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE description = VALUES(description)
            ")->execute([$name, $description]);
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    echo "Database update completed successfully!<br>";
    
    // Show current tables structure
    echo "<pre>";
    echo "\nUsers table structure:\n";
    $stmt = $pdo->query("DESCRIBE users");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\nCategories table structure:\n";
    $stmt = $pdo->query("DESCRIBE categories");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\nSubscriptions table structure:\n";
    $stmt = $pdo->query("DESCRIBE subscriptions");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\nUsers in database:\n";
    $stmt = $pdo->query("SELECT id, name, email FROM users");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\nCategories after cleanup:\n";
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\nSubscriptions in database:\n";
    $stmt = $pdo->query("SELECT * FROM subscriptions");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";

} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
    error_log("Database update error: " . $e->getMessage());
} 