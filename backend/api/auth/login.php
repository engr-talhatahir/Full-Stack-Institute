<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once '../../config/db.php';
require_once '../../helpers/functions.php';

// Already logged in?
if (isset($_SESSION['user_id'])) {
    jsonSuccess([
        'redirect' => $_SESSION['role'] === 'admin'
            ? '/PHP/Institute_project/frontend/admin/dashboard.html'
            : '/PHP/Institute_project/frontend/student/dashboard.html'
    ], 'Already logged in');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email    = isset($input['email'])    ? trim($input['email'])    : '';
$password = isset($input['password']) ? $input['password']       : '';

if (empty($email) || empty($password)) {
    jsonError('Please fill all fields');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Invalid email address');
}

$stmt = $conn->prepare("SELECT id, full_name, email, password, role, status, profile_pic FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    jsonError('Invalid email or password');
}

$user = $result->fetch_assoc();
$stmt->close();

// Plain-text comparison (as in original code)
if ($password !== $user['password']) {
    jsonError('Invalid email or password');
}

if ($user['status'] === 'suspended') {
    jsonError('Your account has been suspended. Please contact admin.');
}

$_SESSION['user_id']    = $user['id'];
$_SESSION['full_name']  = $user['full_name'];
$_SESSION['email']      = $user['email'];
$_SESSION['role']       = $user['role'];
$_SESSION['profile_pic']= $user['profile_pic'];

$redirect = $user['role'] === 'admin'
    ? '/PHP/Institute_project/frontend/admin/dashboard.html'
    : '/PHP/Institute_project/frontend/student/dashboard.html';

jsonSuccess([
    'user' => [
        'id'          => $user['id'],
        'full_name'   => $user['full_name'],
        'email'       => $user['email'],
        'role'        => $user['role'],
        'profile_pic' => $user['profile_pic'],
    ],
    'redirect' => $redirect
], 'Welcome back, ' . $user['full_name'] . '!');
?>
