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
    $new_status = $_GET['toggle'] === 'active' ? 'active' : 'inactive';
    $stmt = $conn->prepare("UPDATE courses SET status=? WHERE id=?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute() ? jsonSuccess([], 'Course status updated') : jsonError('Failed to update status');
    $stmt->close();
}

// Delete
if ($method === 'GET' && isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $r = $conn->query("SELECT COUNT(*) as c FROM course_enrollments WHERE course_id=$id")->fetch_assoc()['c'];
    if ($r > 0) jsonError("Cannot delete: course has $r enrollment(s)");
    // Delete thumbnail
    $tr = $conn->query("SELECT thumbnail FROM courses WHERE id=$id");
    if ($tr && $row = $tr->fetch_assoc()) {
        $thumb = dirname(__DIR__, 3) . '/assets/uploads/thumbnails/' . $row['thumbnail'];
        if (!empty($row['thumbnail']) && file_exists($thumb)) unlink($thumb);
    }
    $stmt = $conn->prepare("DELETE FROM courses WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute() ? jsonSuccess([], 'Course deleted') : jsonError('Failed to delete course');
    $stmt->close();
}

// Add course (POST multipart)
if ($method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration    = trim($_POST['duration'] ?? '');
    $fee         = floatval($_POST['fee'] ?? 0);
    $total_seats = intval($_POST['total_seats'] ?? 0);
    $start_date  = trim($_POST['start_date'] ?? '');
    $end_date    = trim($_POST['end_date'] ?? '');

    if (empty($title) || empty($description) || empty($duration) || $fee <= 0 || $total_seats <= 0 || empty($start_date) || empty($end_date)) {
        jsonError('Please fill all required fields');
    }
    if (strtotime($end_date) < strtotime($start_date)) jsonError('End date must be after start date');

    $thumbnail = '';
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = dirname(__DIR__, 3) . '/assets/uploads/thumbnails/';
        $uploaded = uploadFile($_FILES['thumbnail'], $upload_dir, ['jpg','jpeg','png','gif','webp']);
        if (!$uploaded) jsonError('Failed to upload thumbnail');
        $thumbnail = $uploaded;
    } else {
        jsonError('Please upload a course thumbnail');
    }

    $admin_id = $_SESSION['user_id'];
    $status   = 'active';
    $enrolled = 0;
    $t = $conn->real_escape_string($title);
    $d = $conn->real_escape_string($description);
    $du= $conn->real_escape_string($duration);
    $th= $conn->real_escape_string($thumbnail);
    $sql = "INSERT INTO courses (title, description, duration, fee, total_seats, enrolled_seats, start_date, end_date, thumbnail, status, created_by) VALUES ('$t','$d','$du',$fee,$total_seats,$enrolled,'$start_date','$end_date','$th','$status',$admin_id)";
    if ($conn->query($sql)) {
        jsonSuccess(['id' => $conn->insert_id], 'Course added successfully!');
    } else {
        jsonError('Failed to add course: ' . $conn->error);
    }
}

// Edit course (POST multipart)
if ($method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id          = intval($_POST['course_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration    = trim($_POST['duration'] ?? '');
    $fee         = floatval($_POST['fee'] ?? 0);
    $total_seats = intval($_POST['total_seats'] ?? 0);
    $start_date  = trim($_POST['start_date'] ?? '');
    $end_date    = trim($_POST['end_date'] ?? '');
    $status      = trim($_POST['status'] ?? 'active');

    if (empty($title) || empty($description) || empty($duration) || $fee <= 0 || $total_seats <= 0) {
        jsonError('Please fill all required fields');
    }

    // Get existing thumbnail
    $tr = $conn->query("SELECT thumbnail FROM courses WHERE id=$id");
    $existing_thumb = '';
    if ($tr && $row = $tr->fetch_assoc()) $existing_thumb = $row['thumbnail'];

    $thumbnail = $existing_thumb;
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = dirname(__DIR__, 3) . '/assets/uploads/thumbnails/';
        $uploaded = uploadFile($_FILES['thumbnail'], $upload_dir, ['jpg','jpeg','png','gif','webp']);
        if ($uploaded) {
            // Delete old
            if (!empty($existing_thumb) && file_exists($upload_dir . $existing_thumb)) unlink($upload_dir . $existing_thumb);
            $thumbnail = $uploaded;
        }
    }

    $t = $conn->real_escape_string($title);
    $d = $conn->real_escape_string($description);
    $du= $conn->real_escape_string($duration);
    $th= $conn->real_escape_string($thumbnail);
    $st= $conn->real_escape_string($status);
    $sql = "UPDATE courses SET title='$t', description='$d', duration='$du', fee=$fee, total_seats=$total_seats, start_date='$start_date', end_date='$end_date', thumbnail='$th', status='$st' WHERE id=$id";
    $conn->query($sql) ? jsonSuccess([], 'Course updated successfully!') : jsonError('Failed to update course');
}

// Single course (for edit page)
if ($method === 'GET' && isset($_GET['id']) && !isset($_GET['toggle']) && !isset($_GET['delete'])) {
    $id = intval($_GET['id']);
    $r = $conn->query("SELECT * FROM courses WHERE id=$id");
    if ($r && $r->num_rows > 0) {
        echo json_encode(['success' => true, 'course' => $r->fetch_assoc()]);
    } else {
        jsonError('Course not found', 404);
    }
    exit;
}

// List courses
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$sql = "SELECT c.*, (SELECT COUNT(*) FROM course_enrollments WHERE course_id=c.id) as enrolled_count, (SELECT AVG(rating) FROM course_ratings WHERE course_id=c.id) as avg_rating, (SELECT COUNT(*) FROM course_ratings WHERE course_id=c.id) as rating_count FROM courses c WHERE 1=1";
if (!empty($search)) { $s=$conn->real_escape_string($search); $sql.=" AND (c.title LIKE '%$s%' OR c.description LIKE '%$s%')"; }
if ($status_filter!=='all') { $sf=$conn->real_escape_string($status_filter); $sql.=" AND c.status='$sf'"; }
$sql .= " ORDER BY c.created_at DESC";

$result = $conn->query($sql);
$courses = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['avg_rating'] = round($row['avg_rating'], 1);
        $courses[] = $row;
    }
}

$counts = ['active' => 0, 'inactive' => 0];
$cr = $conn->query("SELECT status, COUNT(*) as c FROM courses GROUP BY status");
if ($cr) { while ($row = $cr->fetch_assoc()) $counts[$row['status']] = $row['c']; }

echo json_encode(['success' => true, 'courses' => $courses, 'counts' => $counts]);
?>
