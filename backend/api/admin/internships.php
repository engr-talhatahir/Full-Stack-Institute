<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/auth.php';

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

// Toggle status
if ($method === 'GET' && isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $new_status = $_GET['toggle'] === 'open' ? 'open' : 'closed';
    $stmt = $conn->prepare("UPDATE internships SET status=? WHERE id=?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute() ? jsonSuccess([], 'Internship status updated') : jsonError('Failed to update status');
    $stmt->close();
}

// Delete
if ($method === 'GET' && isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $r = $conn->query("SELECT COUNT(*) as c FROM internship_applications WHERE internship_id=$id")->fetch_assoc()['c'];
    if ($r > 0) jsonError("Cannot delete: internship has $r application(s)");
    $stmt = $conn->prepare("DELETE FROM internships WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute() ? jsonSuccess([], 'Internship deleted') : jsonError('Failed to delete internship');
    $stmt->close();
}

// Add internship
if ($method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title        = trim($_POST['title'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $location     = trim($_POST['location'] ?? '');
    $duration     = trim($_POST['duration'] ?? '');
    $stipend      = trim($_POST['stipend'] ?? '');
    $deadline     = trim($_POST['deadline'] ?? '');
    $total_slots  = intval($_POST['total_slots'] ?? 0);

    if (empty($title) || empty($company_name) || empty($description) || empty($location) || empty($duration) || empty($deadline) || $total_slots <= 0) {
        jsonError('Please fill all required fields');
    }

    $admin_id = $_SESSION['user_id'];
    $status = 'open';
    $stmt = $conn->prepare("INSERT INTO internships (title, company_name, description, requirements, location, duration, stipend, deadline, total_slots, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssssssis", $title, $company_name, $description, $requirements, $location, $duration, $stipend, $deadline, $total_slots, $status, $admin_id);
    // Fix: bind with correct count
    $stmt = $conn->prepare("INSERT INTO internships (title, company_name, description, requirements, location, duration, stipend, deadline, total_slots, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssssssisi", $title, $company_name, $description, $requirements, $location, $duration, $stipend, $deadline, $total_slots, $status, $admin_id);
    if ($stmt->execute()) {
        $stmt->close();
        jsonSuccess(['id' => $conn->insert_id], 'Internship added successfully!');
    } else {
        jsonError('Failed to add internship: ' . $stmt->error);
    }
}

// Edit internship
if ($method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id           = intval($_POST['internship_id'] ?? 0);
    $title        = trim($_POST['title'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $location     = trim($_POST['location'] ?? '');
    $duration     = trim($_POST['duration'] ?? '');
    $stipend      = trim($_POST['stipend'] ?? '');
    $deadline     = trim($_POST['deadline'] ?? '');
    $total_slots  = intval($_POST['total_slots'] ?? 0);
    $status       = trim($_POST['status'] ?? 'open');

    $stmt = $conn->prepare("UPDATE internships SET title=?, company_name=?, description=?, requirements=?, location=?, duration=?, stipend=?, deadline=?, total_slots=?, status=? WHERE id=?");
    $stmt->bind_param("sssssssssii", $title, $company_name, $description, $requirements, $location, $duration, $stipend, $deadline, $total_slots, $status, $id);
    // Correct bind
    $stmt = $conn->prepare("UPDATE internships SET title=?, company_name=?, description=?, requirements=?, location=?, duration=?, stipend=?, deadline=?, total_slots=?, status=? WHERE id=?");
    $stmt->bind_param("ssssssssisi", $title, $company_name, $description, $requirements, $location, $duration, $stipend, $deadline, $total_slots, $status, $id);
    $stmt->execute() ? jsonSuccess([], 'Internship updated successfully!') : jsonError('Failed to update internship');
    $stmt->close();
}

// Single internship
if ($method === 'GET' && isset($_GET['id']) && !isset($_GET['toggle']) && !isset($_GET['delete'])) {
    $id = intval($_GET['id']);
    $r = $conn->query("SELECT * FROM internships WHERE id=$id");
    if ($r && $r->num_rows > 0) echo json_encode(['success' => true, 'internship' => $r->fetch_assoc()]);
    else jsonError('Internship not found', 404);
    exit;
}

// List internships
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sql = "SELECT i.*, (SELECT COUNT(*) FROM internship_applications WHERE internship_id=i.id) as applications_count, (SELECT AVG(rating) FROM internship_ratings WHERE internship_id=i.id) as avg_rating, (SELECT COUNT(*) FROM internship_ratings WHERE internship_id=i.id) as rating_count FROM internships i WHERE 1=1";
if (!empty($search)) { $s=$conn->real_escape_string($search); $sql.=" AND (i.title LIKE '%$s%' OR i.company_name LIKE '%$s%')"; }
if ($status_filter!=='all') { $sf=$conn->real_escape_string($status_filter); $sql.=" AND i.status='$sf'"; }
$sql .= " ORDER BY i.created_at DESC";

$result = $conn->query($sql);
$internships = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['avg_rating'] = round($row['avg_rating'], 1);
        $internships[] = $row;
    }
}
$counts = ['open' => 0, 'closed' => 0];
$cr = $conn->query("SELECT status, COUNT(*) as c FROM internships GROUP BY status");
if ($cr) { while ($row = $cr->fetch_assoc()) $counts[$row['status']] = $row['c']; }

echo json_encode(['success' => true, 'internships' => $internships, 'counts' => $counts]);
?>
