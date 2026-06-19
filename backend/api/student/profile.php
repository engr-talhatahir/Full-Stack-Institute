<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/auth.php';

requireStudent();

$user_id = $_SESSION['user_id'];
$method  = $_SERVER['REQUEST_METHOD'];

// Update profile
if ($method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_POST;
    }

    $action = $input['action'] ?? 'update_profile';

    // Change password
    if ($action === 'change_password') {
        $old_password     = $input['old_password'] ?? '';
        $new_password     = $input['new_password'] ?? '';
        $confirm_password = $input['confirm_password'] ?? '';

        if (strlen($new_password) < 6) jsonError('Password must be at least 6 characters');
        if ($new_password !== $confirm_password) jsonError('Passwords do not match');

        $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc(); $stmt->close();

        if ($old_password !== $user['password']) jsonError('Old password is incorrect');

        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $new_password, $user_id);
        $stmt->execute() ? jsonSuccess([], 'Password changed successfully!') : jsonError('Failed to change password');
        $stmt->close();
    }

    // Update profile info
    $full_name = trim($input['full_name'] ?? '');
    $phone     = trim($input['phone'] ?? '');
    $cnic      = trim($input['cnic'] ?? '');
    $address   = trim($input['address'] ?? '');

    if (empty($full_name)) jsonError('Full name is required');

    // Fetch current pic
    $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $profile_pic = $current['profile_pic'];

    // Handle file upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = dirname(__DIR__, 3) . '/assets/uploads/profiles/';
        $uploaded = uploadFile($_FILES['profile_pic'], $upload_dir, ['jpg','jpeg','png','gif']);
        if ($uploaded) {
            if ($profile_pic !== 'default-avatar.png' && file_exists($upload_dir . $profile_pic)) {
                unlink($upload_dir . $profile_pic);
            }
            $profile_pic = $uploaded;
        }
    }

    $stmt = $conn->prepare("UPDATE users SET full_name=?, phone=?, cnic=?, address=?, profile_pic=? WHERE id=?");
    $stmt->bind_param("sssssi", $full_name, $phone, $cnic, $address, $profile_pic, $user_id);
    if ($stmt->execute()) {
        $_SESSION['full_name']  = $full_name;
        $_SESSION['profile_pic']= $profile_pic;
        $stmt->close();
        jsonSuccess(['profile_pic' => $profile_pic, 'full_name' => $full_name], 'Profile updated successfully!');
    } else {
        $stmt->close();
        jsonError('Failed to update profile');
    }
}

// GET - return profile data
$stmt = $conn->prepare("SELECT id, full_name, email, phone, cnic, address, profile_pic, status, created_at FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode(['success' => true, 'user' => $user]);
?>
