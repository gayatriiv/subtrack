<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'session.php';
requireLogin();

$userId = getCurrentUserId();

// Clear any budget-related messages
if (isset($_SESSION['success']) && strpos($_SESSION['success'], 'Budget') !== false) {
    unset($_SESSION['success']);
}

// Only keep shared subscription related messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';

// Clear session messages after retrieving them
unset($_SESSION['success']);
unset($_SESSION['error']);

try {
    // Fetch user's shared subscriptions
    $stmt = $pdo->prepare("
        SELECT 
            sp.id,
            sp.name,
            sp.total_cost as cost,
            sp.owner_id,
            (SELECT COUNT(*) FROM shared_members WHERE shared_plan_id = sp.id) as participants,
            sp.total_cost / (SELECT COUNT(*) + 1 FROM shared_members WHERE shared_plan_id = sp.id) as share_amount,
            u.email as creator_email
        FROM shared_plans sp
        JOIN users u ON sp.owner_id = u.id
        WHERE sp.owner_id = ? OR EXISTS (
            SELECT 1 FROM shared_members sm 
            WHERE sm.shared_plan_id = sp.id 
            AND sm.email = (SELECT email FROM users WHERE id = ?)
            AND sm.status = 'accepted'
        )
    ");
    $stmt->execute([$userId, $userId]);
    $sharedSubscriptions = $stmt->fetchAll();

    // Fetch pending invites (available shared subscriptions to join)
    $stmt = $pdo->prepare("
        SELECT 
            sp.id,
            sp.name,
            sp.total_cost as cost,
            sp.total_cost / (SELECT COUNT(*) + 1 FROM shared_members WHERE shared_plan_id = sp.id) as share_amount,
            u.email as creator_email,
            (SELECT COUNT(*) FROM shared_members WHERE shared_plan_id = sp.id) as participants
        FROM shared_plans sp
        JOIN users u ON sp.owner_id = u.id
        JOIN shared_members sm ON sp.id = sm.shared_plan_id
        WHERE sm.email = (SELECT email FROM users WHERE id = ?)
        AND sm.status = 'pending'
    ");
    $stmt->execute([$userId]);
    $availableSubscriptions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $sharedSubscriptions = [];
    $availableSubscriptions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Plans - SubsTrack</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            background:rgb(0, 0, 0);
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        .sidebar {
            width: 250px;
            min-width: 250px;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
        }

        .main-content {
            width: calc(100% - 250px);
            margin-left: 250px;
            padding: 20px;
            box-sizing: border-box;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            padding: 20px;
        }

        .card {
            background: #1e1e1e;
            border-radius: 8px;
            padding: 20px;
            min-height: 400px; /* Fixed minimum height */
            display: flex;
            flex-direction: column;
        }

        .card h2 {
            color: #4CAF50;
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #ffffff;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #333;
            border-radius: 4px;
            background: #2d2d2d;
            color: #ffffff;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            margin-top: auto;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        .subscription-list {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }

        .subscription-item {
            background: #2d2d2d;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .subscription-item h3 {
            color: #ffffff;
            margin: 0 0 10px 0;
        }

        .subscription-details {
            color: #aaaaaa;
            font-size: 0.9em;
        }

        .subscription-details p {
            margin: 5px 0;
        }

        .empty-state {
            color: #666;
            text-align: center;
            padding: 20px;
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .message-container {
            grid-column: 1 / -1;
            margin-bottom: 20px;
        }

        .alert {
            padding: 15px;
            margin: 0;
            border-radius: 4px;
            text-align: center;
            font-weight: 500;
        }

        .alert-error {
            background: rgba(255, 59, 48, 0.15);
            border: 1px solid rgba(255, 59, 48, 0.3);
            color: #ff3b30;
        }

        .alert-success {
            background: rgba(52, 199, 89, 0.15);
            border: 1px solid rgba(52, 199, 89, 0.3);
            color: #34c759;
        }

        .subscription-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        .edit-btn, .delete-btn {
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            border: none;
            display: flex;
            align-items: center;
            gap: 5px;
            color: white;
            transition: background-color 0.2s;
        }

        .edit-btn {
            background: #4a90e2;
        }

        .delete-btn {
            background: #e74c3c;
        }

        .edit-btn:hover {
            background: #357abd;
        }

        .delete-btn:hover {
            background: #c0392b;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #2d2d2d;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close-modal {
            background: none;
            border: none;
            color: #aaa;
            font-size: 24px;
            cursor: pointer;
        }

        .close-modal:hover {
            color: white;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="grid-container">
                <!-- Create New Shared Subscription -->
                <div class="card">
                    <h2>Create New Shared Subscription</h2>
                    <form action="process_shared_plan.php" method="POST">
                        <div class="form-group">
                            <label for="subscription_name">Subscription Name *</label>
                            <input type="text" id="subscription_name" name="subscription_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="cost">Total Cost (₹) *</label>
                            <input type="number" id="cost" name="cost" step="0.01" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="participant_emails">Participant Emails * (comma-separated)</label>
                            <textarea id="participant_emails" name="participant_emails" class="form-control" required></textarea>
                            <small class="form-text">Enter email addresses separated by commas</small>
                        </div>

                        <button type="submit" class="btn-primary">Create Shared Plan</button>
                    </form>
                </div>

                <!-- My Shared Subscriptions -->
                <div class="card">
                    <h2>My Shared Subscriptions</h2>
                    <?php if (empty($sharedSubscriptions)): ?>
                        <div class="empty-state">No shared subscriptions yet.</div>
                    <?php else: ?>
                        <div class="subscription-list">
                            <?php foreach ($sharedSubscriptions as $sub): ?>
                                <div class="subscription-item">
                                    <h3><?php echo htmlspecialchars($sub['name']); ?></h3>
                                    <div class="subscription-details">
                                        <p>Total Cost: ₹<?php echo number_format($sub['cost'], 2); ?></p>
                                        <p>My Share: ₹<?php echo number_format($sub['share_amount'], 2); ?></p>
                                        <p>Participants: <?php echo $sub['participants']; ?></p>
                                        <p>Created by: <?php echo htmlspecialchars($sub['creator_email']); ?></p>
                                    </div>
                                    <?php if ($sub['owner_id'] == $userId): ?>
                                    <div class="subscription-actions">
                                        <button class="edit-btn" onclick="openEditModal(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['name'], ENT_QUOTES); ?>', <?php echo $sub['cost']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="delete-btn" onclick="confirmDelete(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['name'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Available Shared Subscriptions -->
                <div class="card">
                    <h2>Available Shared Subscriptions</h2>
                    <?php if (!empty($availableSubscriptions)): ?>
                        <ul class="subscription-list">
                            <?php foreach ($availableSubscriptions as $sub): ?>
                                <li class="subscription-item">
                                    <h3><?php echo htmlspecialchars($sub['name']); ?></h3>
                                    <div class="subscription-details">
                                        <p>Total Cost: ₹<?php echo number_format($sub['cost'], 2); ?></p>
                                        <p>Your Share: ₹<?php echo number_format($sub['share_amount'], 2); ?></p>
                                        <p>Participants: <?php echo $sub['participants']; ?></p>
                                        <p>Created by: <?php echo htmlspecialchars($sub['creator_email']); ?></p>
                                    </div>
                                    <form action="process_shared_plan.php" method="POST" style="margin-top: 10px;">
                                        <input type="hidden" name="action" value="accept_invite">
                                        <input type="hidden" name="plan_id" value="<?php echo $sub['id']; ?>">
                                        <button type="submit" class="accept-btn"><i class="fas fa-check"></i> Accept</button>
                                    </form>
                                    <form action="process_shared_plan.php" method="POST" style="margin-top: 5px;">
                                        <input type="hidden" name="action" value="reject_invite">
                                        <input type="hidden" name="plan_id" value="<?php echo $sub['id']; ?>">
                                        <button type="submit" class="reject-btn"><i class="fas fa-times"></i> Reject</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-state">
                            No available shared subscriptions to join.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Shared Subscription</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="editForm" action="update_shared_plan.php" method="POST">
                <input type="hidden" id="edit_plan_id" name="plan_id">
                <div class="form-group">
                    <label for="edit_name">Subscription Name</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_cost">Total Cost ($)</label>
                    <input type="number" id="edit_cost" name="cost" class="form-control" step="0.01" min="0.01" required>
                </div>
                <button type="submit" class="btn-primary">Update Subscription</button>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, name, cost) {
            document.getElementById('edit_plan_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_cost').value = cost;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function confirmDelete(id, name) {
            if (confirm(`Are you sure you want to delete the shared subscription "${name}"?`)) {
                window.location.href = `delete_shared_plan.php?id=${id}`;
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('editModal')) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>