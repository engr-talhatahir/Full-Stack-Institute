<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/auth.php';

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

// Update application status
if ($method === 'GET' && isset($_GET['action']) && isset($_GET['id'])) {
    $id     = intval($_GET['id']);
    $action = $_GET['action'];
    $valid  = ['shortlist' => 'shortlisted', 'select' => 'selected', 'reject' => 'rejected'];
    if (!isset($valid[$action])) jsonError('Invalid action');
    $status = $valid[$action];

    $stmt = $conn->prepare("UPDATE internship_applications SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute() ? jsonSuccess([], "Application $status successfully!") : jsonError('Failed to update status');
    $stmt->close();
}

// Get internships list for filter
$internships_list = [];
$ir = $conn->query("SELECT id, title FROM internships ORDER BY title");
if ($ir) { while ($row = $ir->fetch_assoc()) $internships_list[] = $row; }

// Filters
$status_filter     = isset($_GET['status'])     ? $_GET['status']             : 'all';
$internship_filter = isset($_GET['internship']) ? intval($_GET['internship']) : 0;
$search            = isset($_GET['search'])     ? trim($_GET['search'])       : '';

$sql = "SELECT ia.*, u.full_name, u.email, u.phone, u.profile_pic, i.title as internship_title, i.company_name, i.location, i.deadline FROM internship_applications ia JOIN users u ON ia.student_id=u.id JOIN internships i ON ia.internship_id=i.id WHERE 1=1";
if ($status_filter !== 'all') { $sf=$conn->real_escape_string($status_filter); $sql.=" AND ia.status='$sf'"; }
if ($internship_filter > 0) $sql .= " AND ia.internship_id=$internship_filter";
if (!empty($search)) { $s=$conn->real_escape_string($search); $sql.=" AND (u.full_name LIKE '%$s%' OR u.email LIKE '%$s%' OR i.title LIKE '%$s%')"; }
$sql .= " ORDER BY ia.applied_at DESC";

$result = $conn->query($sql);
$applications = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) $applications[] = $row;
}

$counts = ['pending' => 0, 'shortlisted' => 0, 'selected' => 0, 'rejected' => 0];
$cr = $conn->query("SELECT status, COUNT(*) as c FROM internship_applications GROUP BY status");
if ($cr) { while ($row = $cr->fetch_assoc()) $counts[$row['status']] = $row['c']; }

echo json_encode(['success' => true, 'applications' => $applications, 'counts' => $counts, 'internships' => $internships_list]);
?>
