<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/auth.php';

requireStudent();

$user_id = $_SESSION['user_id'];
$method  = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $code  = trim($input['certificate_code'] ?? '');

    if (empty($code)) jsonError('Please enter your certificate code');

    $stmt = $conn->prepare("SELECT sc.*, u.full_name, u.email, c.title as course_title FROM student_certificates sc JOIN users u ON sc.student_id=u.id LEFT JOIN courses c ON sc.course_id=c.id WHERE sc.student_id=? AND sc.certificate_code=? AND sc.status='Certified'");
    $stmt->bind_param("is", $user_id, $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        jsonSuccess(['certificate' => $result->fetch_assoc()], 'Certificate found!');
    } else {
        jsonError('Invalid certificate code or verification is pending from Admin.');
    }
    $stmt->close();
    exit;
}

// GET - check if student has any certificate
$stmt = $conn->prepare("SELECT sc.*, c.title as course_title FROM student_certificates sc LEFT JOIN courses c ON sc.course_id=c.id WHERE sc.student_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cert = $result->num_rows > 0 ? $result->fetch_assoc() : null;
$stmt->close();

echo json_encode(['success' => true, 'certificate' => $cert]);
?>
