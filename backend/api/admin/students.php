<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/auth.php';

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

// Handle status change
if ($method === 'GET' && isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    if ($action === 'activate')   $status = 'active';
    elseif ($action === 'suspend') $status = 'suspended';
    else jsonError('Invalid action');

    $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=? AND role='student'");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute() ? jsonSuccess([], 'Student status updated') : jsonError('Failed to update status');
    $stmt->close();
}

// Delete student
if ($method === 'GET' && isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $r1 = $conn->query("SELECT COUNT(*) as c FROM course_enrollments WHERE student_id=$id")->fetch_assoc()['c'];
    $r2 = $conn->query("SELECT COUNT(*) as c FROM internship_applications WHERE student_id=$id")->fetch_assoc()['c'];
    if ($r1 > 0 || $r2 > 0) {
        jsonError("Cannot delete: student has $r1 enrollment(s) and $r2 application(s)");
    }
    $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND role='student'");
    $stmt->bind_param("i", $id);
    $stmt->execute() ? jsonSuccess([], 'Student deleted') : jsonError('Failed to delete student');
    $stmt->close();
}

// List students
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$sql = "SELECT id, full_name, email, phone, cnic, address, profile_pic, status, role, created_at FROM users WHERE role='student'";
if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $sql .= " AND (full_name LIKE '%$s%' OR email LIKE '%$s%' OR phone LIKE '%$s%')";
}
if ($status_filter !== 'all') {
    $sf = $conn->real_escape_string($status_filter);
    $sql .= " AND status='$sf'";
}
$sql .= " ORDER BY created_at DESC";

$result = $conn->query($sql);
$students = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) $students[] = $row;
}

// Counts
$counts = ['active' => 0, 'suspended' => 0];
$cr = $conn->query("SELECT status, COUNT(*) as c FROM users WHERE role='student' GROUP BY status");
if ($cr) { while ($row = $cr->fetch_assoc()) $counts[$row['status']] = $row['c']; }

echo json_encode(['success' => true, 'students' => $students, 'counts' => $counts]);
?>
