<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/auth.php';

requireStudent();

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT id, full_name, email, phone, cnic, address, profile_pic, status, created_at FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Enrolled courses count
$r = $conn->prepare("SELECT COUNT(*) as c FROM course_enrollments WHERE student_id=? AND status='approved'");
$r->bind_param("i", $user_id); $r->execute();
$enrolled_courses = $r->get_result()->fetch_assoc()['c']; $r->close();

// Internship applications count
$r = $conn->prepare("SELECT COUNT(*) as c FROM internship_applications WHERE student_id=?");
$r->bind_param("i", $user_id); $r->execute();
$total_applications = $r->get_result()->fetch_assoc()['c']; $r->close();

// Has certificate
$r = $conn->prepare("SELECT id FROM student_certificates WHERE student_id=? AND status='Certified'");
$r->bind_param("i", $user_id); $r->execute();
$has_certificate = $r->get_result()->num_rows > 0; $r->close();

// Recent enrolled courses
$recent_courses = [];
$sql = "SELECT ce.*, c.title, c.thumbnail, c.duration FROM course_enrollments ce JOIN courses c ON ce.course_id=c.id WHERE ce.student_id=$user_id ORDER BY ce.applied_at DESC LIMIT 3";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) { while ($row = $res->fetch_assoc()) $recent_courses[] = $row; }

// Recent applications
$recent_apps = [];
$sql = "SELECT ia.*, i.title, i.company_name FROM internship_applications ia JOIN internships i ON ia.internship_id=i.id WHERE ia.student_id=$user_id ORDER BY ia.applied_at DESC LIMIT 3";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) { while ($row = $res->fetch_assoc()) $recent_apps[] = $row; }

echo json_encode([
    'success'            => true,
    'user'               => $user,
    'stats'              => [
        'enrolled_courses'   => $enrolled_courses,
        'total_applications' => $total_applications,
        'has_certificate'    => $has_certificate,
    ],
    'recent_courses'     => $recent_courses,
    'recent_applications'=> $recent_apps,
]);
?>
