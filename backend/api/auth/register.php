<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once '../../config/db.php';
require_once '../../helpers/functions.php';

if (isset($_SESSION['user_id'])) {
    jsonError('Already logged in', 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Support multipart/form-data (for file upload) and JSON
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    $input = $_POST;
}

$full_name        = isset($input['full_name'])        ? trim($input['full_name'])        : '';
$email            = isset($input['email'])            ? trim($input['email'])            : '';
$password         = isset($input['password'])         ? $input['password']               : '';
$confirm_password = isset($input['confirm_password']) ? $input['confirm_password']       : '';
$phone            = isset($input['phone'])            ? trim($input['phone'])            : '';
$cnic             = isset($input['cnic'])             ? trim($input['cnic'])             : '';
$address          = isset($input['address'])          ? trim($input['address'])          : '';

if (empty($full_name) || empty($email) || empty($password)) {
    jsonError('Please fill all required fields');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Please enter a valid email address');
}
if ($password !== $confirm_password) {
    jsonError('Passwords do not match');
}
if (strlen($password) < 6) {
    jsonError('Password must be at least 6 characters');
}
if (!empty($phone) && !preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
    jsonError('Please enter a valid phone number');
}
if (!empty($cnic) && !preg_match('/^[0-9]{5}-[0-9]{7}-[0-9]$/', $cnic)) {
    jsonError('Please enter valid CNIC (format: 12345-1234567-1)');
}

// Check email exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) { jsonError('Email already registered'); }
$stmt->close();

// Handle profile picture
$profile_pic = 'default-avatar.png';
if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
    $upload_dir = dirname(__DIR__, 3) . '/assets/uploads/profiles/';
    $uploaded = uploadFile($_FILES['profile_pic'], $upload_dir, ['jpg','jpeg','png','gif']);
    if ($uploaded) $profile_pic = $uploaded;
}

$stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, cnic, address, profile_pic, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'student', 'active')");
$stmt->bind_param("sssssss", $full_name, $email, $password, $phone, $cnic, $address, $profile_pic);

if ($stmt->execute()) {
    $stmt->close();
    jsonSuccess(['redirect' => '/PHP/Institute_project/frontend/login.html'], 'Registration successful! Please login.');
} else {
    $stmt->close();
    jsonError('Registration failed. Please try again.');
}
?>
