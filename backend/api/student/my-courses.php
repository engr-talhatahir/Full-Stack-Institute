<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/auth.php';

requireStudent();

$user_id = $_SESSION['user_id'];

$sql = "SELECT ce.*, c.title, c.description, c.thumbnail, c.duration, c.fee, c.start_date, c.end_date, (SELECT AVG(rating) FROM course_ratings WHERE course_id=c.id) as avg_rating, (SELECT COUNT(*) FROM course_ratings WHERE course_id=c.id) as rating_count, (SELECT rating FROM course_ratings WHERE course_id=c.id AND user_id=$user_id LIMIT 1) as my_rating FROM course_enrollments ce JOIN courses c ON ce.course_id=c.id WHERE ce.student_id=$user_id ORDER BY ce.applied_at DESC";

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
