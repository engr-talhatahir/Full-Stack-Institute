<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/auth.php';

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

// Add certificate
if ($method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $student_id     = intval($_POST['student_id'] ?? 0);
    $course_id      = !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;
    $total_marks    = intval($_POST['total_marks'] ?? 0);
    $obtained_marks = intval($_POST['obtained_marks'] ?? 0);
    $percentage     = ($total_marks > 0) ? ($obtained_marks / $total_marks) * 100 : 0;
    $grade          = trim($_POST['grade'] ?? '');
    $status         = trim($_POST['status'] ?? 'Pending');
    $issue_date     = trim($_POST['issue_date'] ?? '');

    $check = $conn->prepare("SELECT id FROM student_certificates WHERE student_id=?");
    $check->bind_param("i", $student_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { jsonError('This student already has a certificate'); }
    $check->close();

    $cert_code = generateCertificateCode($student_id, $course_id);
    $stmt = $conn->prepare("INSERT INTO student_certificates (student_id, course_id, certificate_code, total_marks, obtained_marks, percentage, grade, status, issue_date) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("iisiddsss", $student_id, $course_id, $cert_code, $total_marks, $obtained_marks, $percentage, $grade, $status, $issue_date);
    if ($stmt->execute()) {
        jsonSuccess(['certificate_code' => $cert_code], 'Certificate added! Code: ' . $cert_code);
    } else {
        jsonError('Failed to add certificate: ' . $stmt->error);
    }
    $stmt->close();
}

// Update certificate
if ($method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $cert_id        = intval($_POST['cert_id'] ?? 0);
    $total_marks    = intval($_POST['total_marks'] ?? 0);
    $obtained_marks = intval($_POST['obtained_marks'] ?? 0);
    $percentage     = ($total_marks > 0) ? ($obtained_marks / $total_marks) * 100 : 0;
    $grade          = trim($_POST['grade'] ?? '');
    $status         = trim($_POST['status'] ?? 'Pending');
    $issue_date     = trim($_POST['issue_date'] ?? '');

    $stmt = $conn->prepare("UPDATE student_certificates SET total_marks=?, obtained_marks=?, percentage=?, grade=?, status=?, issue_date=? WHERE id=?");
    $stmt->bind_param("iidsssi", $total_marks, $obtained_marks, $percentage, $grade, $status, $issue_date, $cert_id);
    $stmt->execute() ? jsonSuccess([], 'Certificate updated') : jsonError('Failed to update certificate');
    $stmt->close();
}

// Delete certificate
if ($method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $cert_id = intval($_POST['cert_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM student_certificates WHERE id=?");
    $stmt->bind_param("i", $cert_id);
    $stmt->execute() ? jsonSuccess([], 'Certificate deleted') : jsonError('Failed to delete certificate');
    $stmt->close();
}

// Get students and courses for dropdowns
$students_list = [];
$sr = $conn->query("SELECT id, full_name, email FROM users WHERE role='student' ORDER BY full_name");
if ($sr) { while ($row = $sr->fetch_assoc()) $students_list[] = $row; }

$courses_list = [];
$cr = $conn->query("SELECT id, title FROM courses ORDER BY title");
if ($cr) { while ($row = $cr->fetch_assoc()) $courses_list[] = $row; }

// List certificates
$sql = "SELECT sc.*, u.full_name, u.email, c.title as course_title FROM student_certificates sc JOIN users u ON sc.student_id=u.id LEFT JOIN courses c ON sc.course_id=c.id ORDER BY sc.created_at DESC";
$result = $conn->query($sql);
$certificates = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) $certificates[] = $row;
}

echo json_encode(['success' => true, 'certificates' => $certificates, 'students' => $students_list, 'courses' => $courses_list]);
?>
