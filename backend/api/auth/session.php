<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once '../../helpers/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'loggedIn' => false, 'message' => 'Not authenticated']);
    exit;
}

echo json_encode([
    'success'  => true,
    'loggedIn' => true,
    'user' => [
        'id'          => $_SESSION['user_id'],
        'full_name'   => $_SESSION['full_name'],
        'email'       => $_SESSION['email'],
        'role'        => $_SESSION['role'],
        'profile_pic' => $_SESSION['profile_pic'] ?? 'default-avatar.png',
    ]
]);
?>
