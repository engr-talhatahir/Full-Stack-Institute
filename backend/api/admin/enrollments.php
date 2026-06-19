<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/auth.php';

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

// Approve or reject enrollment
if ($method === 'GET' && isset($_GET['action']) && isset($_GET['id'])) {
    $id     = intval($_GET['id']);
    $action = $_GET['action'];
    if ($action === 'approve')     $status = 'approved';
    elseif ($action === 'reject')  $status = 'rejected';
    else jsonError('Invalid action');

    // Get course_id
    $g = $conn->prepare("SELECT course_id FROM course_enrollments WHERE id=?");
    $g->bind_param("i", $id);
    $g->execute();
    $enrollment = $g->get_result()->fetch_assoc();
    $g->close();
    if (!$enrollment) jsonError('Enrollment not found', 404);

    $conn->begin_transaction();
    try {
        $u = $conn->prepare("UPDATE course_enrollments SET status=? WHERE id=?");
        $u->bind_param("si", $status, $id);
        $u->execute(); $u->close();
        if ($status === 'approved') {
            $s = $conn->prepare("UPDATE courses SET enrolled_seats=enrolled_seats+1 WHERE id=?");
            $s->bind_param("i", $enrollment['course_id']);
            $s->execute(); $s->close();
        }
        $conn->commit();
        jsonSuccess([], 'Enrollment ' . ($status === 'approved' ? 'approved' : 'rejected') . ' successfully!');
    } catch (Exception $e) {
        $conn->rollback();
        jsonError('Failed to update enrollment');
    }
}

// Get courses list for filter
$courses_list = [];
$cr = $conn->query("SELECT id, title FROM courses ORDER BY title");
if ($cr) { while ($row = $cr->fetch_assoc()) $courses_list[] = $row; }

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$course_filter = isset($_GET['course']) ? intval($_GET['course']) : 0;
$search        = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT ce.*, u.full_name, u.email, u.phone, c.title as course_title, c.fee, c.duration FROM course_enrollments ce JOIN users u ON ce.student_id=u.id JOIN courses c ON ce.course_id=c.id WHERE 1=1";
if ($status_filter !== 'all') { $sf=$conn->real_escape_string($status_filter); $sql.=" AND ce.status='$sf'"; }
if ($course_filter > 0) $sql .= " AND ce.course_id=$course_filter";
if (!empty($search)) { $s=$conn->real_escape_string($search); $sql.=" AND (u.full_name LIKE '%$s%' OR u.email LIKE '%$s%' OR c.title LIKE '%$s%')"; }
$sql .= " ORDER BY ce.applied_at DESC";

$result = $conn->query($sql);
$enrollments = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) $enrollments[] = $row;
}

$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$cr = $conn->query("SELECT status, COUNT(*) as c FROM course_enrollments GROUP BY status");
if ($cr) { while ($row = $cr->fetch_assoc()) $counts[$row['status']] = $row['c']; }

echo json_encode(['success' => true, 'enrollments' => $enrollments, 'counts' => $counts, 'courses' => $courses_list]);
?>
