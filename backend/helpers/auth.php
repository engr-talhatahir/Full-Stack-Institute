<?php
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.', 'redirect' => '/PHP/Institute_project/frontend/login.html']);
        exit;
    }
}

function requireAdmin() {
    requireAuth();
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
        exit;
    }
}

function requireStudent() {
    requireAuth();
    if ($_SESSION['role'] !== 'student') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Students only.']);
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}
?>
