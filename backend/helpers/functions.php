<?php
function sanitize($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function jsonSuccess($data = [], $message = 'Success') {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}

function jsonError($message = 'Error', $code = 400) {
    jsonResponse(['success' => false, 'message' => $message], $code);
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        if (function_exists('random_bytes')) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        } else {
            $_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true)) . md5(uniqid(mt_rand(), true));
        }
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] == $token;
}

function uploadFile($file, $targetDir, $allowedTypes = ['jpg','jpeg','png','gif','pdf']) {
    if ($file['error'] != UPLOAD_ERR_OK) return false;
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedTypes)) return false;
    if ($file['size'] > 5 * 1024 * 1024) return false;
    $fileName = uniqid() . '.' . $fileExt;
    if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
    if (move_uploaded_file($file['tmp_name'], $targetDir . '/' . $fileName)) return $fileName;
    return false;
}

function getCourseRating($course_id, $conn) {
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM course_ratings WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return ['avg' => round($row['avg_rating'], 1), 'total' => $row['total']];
}

function getInternshipRating($internship_id, $conn) {
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM internship_ratings WHERE internship_id = ?");
    $stmt->bind_param("i", $internship_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return ['avg' => round($row['avg_rating'], 1), 'total' => $row['total']];
}

function generateCertificateCode($student_id, $course_id) {
    return 'ITS-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6)) . '-' . $student_id;
}
?>
