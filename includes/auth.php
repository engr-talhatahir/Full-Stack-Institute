<?php
session_start();
require_once 'db.php';

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function checkAdmin() {
    checkAuth();
    if ($_SESSION['role'] != 'admin') {
        header('Location: /student/dashboard.php');
        exit;
    }
}

function checkStudent() {
    checkAuth();
    if ($_SESSION['role'] != 'student') {
        header('Location: /admin/dashboard.php');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'student';
}
?>