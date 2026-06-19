<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/auth.php';

requireStudent();

$user_id = $_SESSION['user_id'];
$method  = $_SERVER['REQUEST_METHOD'];

// Submit rating (POST JSON)
if ($method === 'POST') {
    $input     = json_decode(file_get_contents('php://input'), true);
    $action    = $input['action'] ?? '';
    $course_id = intval($input['course_id'] ?? 0);
    $rating    = intval($input['rating'] ?? 0);
    $review    = trim($input['review'] ?? '');

    if ($action === 'submit_rating') {
        if ($rating < 1 || $rating > 5) jsonError('Invalid rating');
        $check = $conn->prepare("SELECT id FROM course_ratings WHERE course_id=? AND user_id=?");
        $check->bind_param("ii", $course_id, $user_id); $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE course_ratings SET rating=?, review=? WHERE course_id=? AND user_id=?");
            $stmt->bind_param("isii", $rating, $review, $course_id, $user_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO course_ratings (course_id, user_id, rating, review) VALUES (?,?,?,?)");
            $stmt->bind_param("iiis", $course_id, $user_id, $rating, $review);
        }
        if ($stmt->execute()) {
            $avg = $conn->query("SELECT AVG(rating) as a, COUNT(*) as t FROM course_ratings WHERE course_id=$course_id")->fetch_assoc();
            jsonSuccess(['avg_rating' => round($avg['a'],1), 'total_ratings' => $avg['t']], 'Rating submitted!');
        } else { jsonError('Failed to submit rating'); }
        $stmt->close(); $check->close();
    }

    // Enroll in course
    if ($action === 'enroll') {
        // Check already enrolled
        $ch = $conn->prepare("SELECT id FROM course_enrollments WHERE student_id=? AND course_id=?");
        $ch->bind_param("ii", $user_id, $course_id); $ch->execute();
        if ($ch->get_result()->num_rows > 0) jsonError('You have already applied for this course');
        $ch->close();
        // Check seats
        $cr = $conn->query("SELECT total_seats, enrolled_seats FROM courses WHERE id=$course_id")->fetch_assoc();
        if (!$cr) jsonError('Course not found', 404);
        if ($cr['enrolled_seats'] >= $cr['total_seats']) jsonError('No seats available');
        $stmt = $conn->prepare("INSERT INTO course_enrollments (student_id, course_id, status) VALUES (?,?,'pending')");
        $stmt->bind_param("ii", $user_id, $course_id);
        $stmt->execute() ? jsonSuccess([], 'Enrollment request submitted!') : jsonError('Failed to enroll');
        $stmt->close();
    }
    exit;
}

// List courses
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT c.*, (SELECT COUNT(*) FROM course_enrollments WHERE course_id=c.id AND status='approved') as enrolled_count, (SELECT AVG(rating) FROM course_ratings WHERE course_id=c.id) as avg_rating, (SELECT COUNT(*) FROM course_ratings WHERE course_id=c.id) as rating_count, (SELECT status FROM course_enrollments WHERE student_id=$user_id AND course_id=c.id LIMIT 1) as my_enrollment_status, (SELECT rating FROM course_ratings WHERE course_id=c.id AND user_id=$user_id LIMIT 1) as my_rating FROM courses c WHERE c.status='active'";
if (!empty($search)) { $s=$conn->real_escape_string($search); $sql.=" AND (c.title LIKE '%$s%' OR c.description LIKE '%$s%')"; }
$sql .= " ORDER BY c.created_at DESC";

$result = $conn->query($sql);
$courses = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['avg_rating'] = round($row['avg_rating'], 1);
        $courses[] = $row;
    }
}
echo json_encode(['success' => true, 'courses' => $courses]);
?>
