<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/auth.php';

requireStudent();

$user_id = $_SESSION['user_id'];

$sql = "SELECT ia.*, i.title, i.company_name, i.location, i.duration, i.stipend, i.deadline, (SELECT AVG(rating) FROM internship_ratings WHERE internship_id=i.id) as avg_rating, (SELECT COUNT(*) FROM internship_ratings WHERE internship_id=i.id) as rating_count, (SELECT rating FROM internship_ratings WHERE internship_id=i.id AND user_id=$user_id LIMIT 1) as my_rating FROM internship_applications ia JOIN internships i ON ia.internship_id=i.id WHERE ia.student_id=$user_id ORDER BY ia.applied_at DESC";

$result = $conn->query($sql);
$applications = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['avg_rating'] = round($row['avg_rating'], 1);
        $applications[] = $row;
    }
}
echo json_encode(['success' => true, 'applications' => $applications]);
?>
