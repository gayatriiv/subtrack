<?php
require_once 'config.php';
require_once 'session.php';

// Require login to access this page
requireLogin();

$error = '';
$success = '';
$subscription = null;

// Get subscription ID from URL
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: dashboard.php');
    exit();
}

// Fetch categories for the dropdown
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Fetch the subscription details
try {
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, getCurrentUserId()]);
    $subscription = $stmt->fetch();

    if (!$subscription) {
        header('Location: dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    $error = 'Error fetching subscription details.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $cost = $_POST['cost'] ?? '';
    $billing_cycle = $_POST['billing_cycle'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $next_payment_date = $_POST['next_payment_date'] ?? '';

    if (empty($name) || empty($cost) || empty($billing_cycle) || empty($category_id) || empty($next_payment_date)) {
        $error = 'Please fill in all required fields';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE subscriptions SET name = ?, cost = ?, billing_cycle = ?, category_id = ?, next_payment_date = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([
                $name,
                $cost,
                $billing_cycle,
                $category_id,
                $next_payment_date,
                $id,
                getCurrentUserId()
            ]);
            $success = 'Subscription updated successfully!';
            header('Location: dashboard.php');
            exit();
        } catch (PDOException $e) {
            $error = 'Error updating subscription. Please try again. Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Subscription - SubsTrack</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        body {
            min-height: 100vh;
            background: #1a1a1a;
            color: white;
        }
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        nav {
            background: rgba(39, 39, 39, 0.8);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        nav h1 {
            color: #4CAF50;
        }
        nav ul {
            list-style: none;
            display: flex;
            gap: 20px;
        }
        nav ul li a {
            color: white;
            text-decoration: none;
            transition: 0.3s;
        }
        nav ul li a:hover {
            color: #4CAF50;
        }
        .form-container {
            background: rgba(39, 39, 39, 0.8);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            margin-top: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255,255,255,0.8);
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: white;
            font-size: 16px;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4CAF50;
        }
        .error-message {
            background: rgba(255, 0, 0, 0.2);
            color: #fff;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .success-message {
            background: rgba(76, 175, 80, 0.2);
            color: #fff;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(45deg, #4CAF50, #81c784);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s ease;
        }
        .submit-btn:hover {
            opacity: 0.9;
        }
        .delete-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(45deg, #f44336, #e57373);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s ease;
            margin-top: 10px;
        }
        .delete-btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <nav>
        <h1>SubsTrack</h1>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="add_subscription.php">Add Subscription</a></li>
            <li><a href="budget.php">Budget</a></li>
            <li><a href="statistics.php">Statistics</a></li>
            <li><a href="shared.php">Shared Plans</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="container">
        <div class="form-container">
            <h2>Edit Subscription</h2>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="name">Subscription Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($subscription['name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="cost">Monthly Cost ($) *</label>
                    <input type="number" id="cost" name="cost" step="0.01" value="<?php echo htmlspecialchars($subscription['cost']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="billing_cycle">Billing Cycle *</label>
                    <select id="billing_cycle" name="billing_cycle" required>
                        <option value="monthly" <?php echo $subscription['billing_cycle'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="quarterly" <?php echo $subscription['billing_cycle'] === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                        <option value="yearly" <?php echo $subscription['billing_cycle'] === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category_id">Category *</label>
                    <select id="category_id" name="category_id" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['id']); ?>" 
                                <?php echo $subscription['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="next_payment_date">Next Payment Date *</label>
                    <input type="date" id="next_payment_date" name="next_payment_date" 
                        value="<?php echo htmlspecialchars($subscription['next_payment_date']); ?>" required>
                </div>

                <button type="submit" class="submit-btn">Update Subscription</button>
            </form>

            <form method="POST" action="delete_subscription.php" onsubmit="return confirm('Are you sure you want to delete this subscription?');">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                <button type="submit" class="delete-btn">Delete Subscription</button>
            </form>
        </div>
    </div>
</body>
</html> 