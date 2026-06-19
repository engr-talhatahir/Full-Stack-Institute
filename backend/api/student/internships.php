<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/auth.php';

requireStudent();

$user_id = $_SESSION['user_id'];
$method  = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input          = json_decode(file_get_contents('php://input'), true);
    $action         = $input['action'] ?? '';
    $internship_id  = intval($input['internship_id'] ?? 0);
    $rating         = intval($input['rating'] ?? 0);
    $review         = trim($input['review'] ?? '');

    if ($action === 'submit_rating') {
        if ($rating < 1 || $rating > 5) jsonError('Invalid rating');
        $check = $conn->prepare("SELECT id FROM internship_ratings WHERE internship_id=? AND user_id=?");
        $check->bind_param("ii", $internship_id, $user_id); $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE internship_ratings SET rating=?, review=? WHERE internship_id=? AND user_id=?");
            $stmt->bind_param("isii", $rating, $review, $internship_id, $user_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO internship_ratings (internship_id, user_id, rating, review) VALUES (?,?,?,?)");
            $stmt->bind_param("iiis", $internship_id, $user_id, $rating, $review);
        }
        if ($stmt->execute()) {
            $avg = $conn->query("SELECT AVG(rating) as a, COUNT(*) as t FROM internship_ratings WHERE internship_id=$internship_id")->fetch_assoc();
            jsonSuccess(['avg_rating' => round($avg['a'],1), 'total_ratings' => $avg['t']], 'Rating submitted!');
        } else { jsonError('Failed to submit rating'); }
        $stmt->close(); $check->close();
    }

    if ($action === 'apply') {
        $cover_letter = trim($input['cover_letter'] ?? '');
        // Check already applied
        $ch = $conn->prepare("SELECT id FROM internship_applications WHERE student_id=? AND internship_id=?");
        $ch->bind_param("ii", $user_id, $internship_id); $ch->execute();
        if ($ch->get_result()->num_rows > 0) jsonError('You have already applied for this internship');
        $ch->close();
        $stmt = $conn->prepare("INSERT INTO internship_applications (student_id, internship_id, cover_letter, status) VALUES (?,?,?,'pending')");
        $stmt->bind_param("iis", $user_id, $internship_id, $cover_letter);
        $stmt->execute() ? jsonSuccess([], 'Application submitted successfully!') : jsonError('Failed to submit application');
        $stmt->close();
    }
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT i.*, (SELECT COUNT(*) FROM internship_applications WHERE internship_id=i.id) as applications_count, (SELECT AVG(rating) FROM internship_ratings WHERE internship_id=i.id) as avg_rating, (SELECT COUNT(*) FROM internship_ratings WHERE internship_id=i.id) as rating_count, (SELECT status FROM internship_applications WHERE student_id=$user_id AND internship_id=i.id LIMIT 1) as my_application_status, (SELECT rating FROM internship_ratings WHERE internship_id=i.id AND user_id=$user_id LIMIT 1) as my_rating FROM internships i WHERE i.status='open'";
if (!empty($search)) { $s=$conn->real_escape_string($search); $sql.=" AND (i.title LIKE '%$s%' OR i.company_name LIKE '%$s%')"; }
$sql .= " ORDER BY i.created_at DESC";

$result = $conn->query($sql);
$internships = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['avg_rating'] = round($row['avg_rating'], 1);
        $internships[] = $row;
    }
}
echo json_encode(['success' => true, 'internships' => $internships]);
?>
