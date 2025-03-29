<!-- Sidebar Component -->
<div class="sidebar">
    <div class="sidebar-logo">SubsTrack</div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php" <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'class="active"' : ''; ?>><i class="fas fa-th-large"></i> Dashboard</a></li>
        <li><a href="add_subscription.php" <?php echo basename($_SERVER['PHP_SELF']) == 'add_subscription.php' ? 'class="active"' : ''; ?>><i class="fas fa-plus"></i> Add Subscription</a></li>
        <li><a href="budget.php" <?php echo basename($_SERVER['PHP_SELF']) == 'budget.php' ? 'class="active"' : ''; ?>><i class="fas fa-wallet"></i> Budget</a></li>
        <li><a href="statistics.php" <?php echo basename($_SERVER['PHP_SELF']) == 'statistics.php' ? 'class="active"' : ''; ?>><i class="fas fa-chart-bar"></i> Statistics</a></li>
        <li><a href="shared.php" <?php echo basename($_SERVER['PHP_SELF']) == 'shared.php' ? 'class="active"' : ''; ?>><i class="fas fa-users"></i> Shared Plans</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div> 