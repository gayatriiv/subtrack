<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Function to get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Function to set logged in user
function setLoggedInUser($userId) {
    $_SESSION['user_id'] = $userId;
}

// Function to logout user
function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}
?> 