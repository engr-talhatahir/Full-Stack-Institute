<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/auth.php';

requireAdmin();

// Stats
$stats = [];
$queries = [
    'total_students'      => "SELECT COUNT(*) as c FROM users WHERE role='student'",
    'active_students'     => "SELECT COUNT(*) as c FROM users WHERE role='student' AND status='active'",
    'total_courses'       => "SELECT COUNT(*) as c FROM courses",
    'active_courses'      => "SELECT COUNT(*) as c FROM courses WHERE status='active'",
    'total_internships'   => "SELECT COUNT(*) as c FROM internships",
    'open_internships'    => "SELECT COUNT(*) as c FROM internships WHERE status='open'",
    'pending_enrollments' => "SELECT COUNT(*) as c FROM course_enrollments WHERE status='pending'",
    'pending_applications'=> "SELECT COUNT(*) as c FROM internship_applications WHERE status='pending'",
    'total_certificates'  => "SELECT COUNT(*) as c FROM student_certificates",
    'pending_certificates'=> "SELECT COUNT(*) as c FROM student_certificates WHERE status='Pending'",
];
foreach ($queries as $key => $sql) {
    $r = $conn->query($sql);
    $stats[$key] = $r ? intval($r->fetch_assoc()['c']) : 0;
}

// Total ratings
$r1 = $conn->query("SELECT COUNT(*) as c FROM course_ratings");
$r2 = $conn->query("SELECT COUNT(*) as c FROM internship_ratings");
$stats['total_ratings'] = ($r1 ? intval($r1->fetch_assoc()['c']) : 0) + ($r2 ? intval($r2->fetch_assoc()['c']) : 0);

// Recent enrollments
$recent_enrollments = [];
$sql = "SELECT ce.*, u.full_name, u.email, c.title as course_title FROM course_enrollments ce JOIN users u ON ce.student_id=u.id JOIN courses c ON ce.course_id=c.id ORDER BY ce.applied_at DESC LIMIT 8";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) $recent_enrollments[] = $row;
}

// Recent internship applications
$recent_applications = [];
$sql = "SELECT ia.*, u.full_name, u.email, i.title as internship_title FROM internship_applications ia JOIN users u ON ia.student_id=u.id JOIN internships i ON ia.internship_id=i.id ORDER BY ia.applied_at DESC LIMIT 8";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) $recent_applications[] = $row;
}

// Top rated courses
$top_courses = [];
$sql = "SELECT c.id, c.title, AVG(cr.rating) as avg_rating, COUNT(cr.id) as rating_count FROM courses c LEFT JOIN course_ratings cr ON c.id=cr.course_id GROUP BY c.id HAVING avg_rating IS NOT NULL ORDER BY avg_rating DESC LIMIT 5";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $row['avg_rating'] = round($row['avg_rating'], 1);
        $top_courses[] = $row;
    }
}

echo json_encode([
    'success'             => true,
    'stats'               => $stats,
    'recent_enrollments'  => $recent_enrollments,
    'recent_applications' => $recent_applications,
    'top_courses'         => $top_courses,
]);
?>
