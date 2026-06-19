<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/config.php';

// Check if user is logged in and is student
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

if ($_SESSION['role'] != 'student') {
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle rating submission via AJAX
if (isset($_POST['action']) && $_POST['action'] == 'submit_rating') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login to rate']);
        exit;
    }
    
    $course_id = intval($_POST['course_id']);
    $rating = intval($_POST['rating']);
    $review = isset($_POST['review']) ? trim($_POST['review']) : '';
    
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid rating']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Check if already rated
    $check_stmt = $conn->prepare("SELECT id FROM course_ratings WHERE course_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $course_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing rating
        $stmt = $conn->prepare("UPDATE course_ratings SET rating = ?, review = ? WHERE course_id = ? AND user_id = ?");
        $stmt->bind_param("isii", $rating, $review, $course_id, $user_id);
    } else {
        // Insert new rating
        $stmt = $conn->prepare("INSERT INTO course_ratings (course_id, user_id, rating, review) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $course_id, $user_id, $rating, $review);
    }
    
    if ($stmt->execute()) {
        // Get updated average rating
        $avg_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM course_ratings WHERE course_id = ?");
        $avg_stmt->bind_param("i", $course_id);
        $avg_stmt->execute();
        $avg_result = $avg_stmt->get_result();
        $avg_data = $avg_result->fetch_assoc();
        $avg_rating = round($avg_data['avg_rating'], 1);
        $total_ratings = $avg_data['total'];
        $avg_stmt->close();
        
        // Generate star HTML
        $stars_html = '';
        $full_stars = floor($avg_rating);
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $full_stars) {
                $stars_html .= '<i class="fas fa-star" style="color: #ffd700;"></i>';
            } else {
                $stars_html .= '<i class="far fa-star" style="color: #ffd700;"></i>';
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Rating submitted successfully!',
            'avg_rating' => $avg_rating,
            'total_ratings' => $total_ratings,
            'stars_html' => $stars_html,
            'user_rating' => $rating
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit rating']);
    }
    $stmt->close();
    $check_stmt->close();
    exit;
}

// Handle course enrollment
if (isset($_GET['enroll']) && is_numeric($_GET['enroll'])) {
    $course_id = intval($_GET['enroll']);
    
    $check_stmt = $conn->prepare("SELECT id, status FROM course_enrollments WHERE student_id = ? AND course_id = ?");
    $check_stmt->bind_param("ii", $user_id, $course_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $enrollment = $check_result->fetch_assoc();
        if ($enrollment['status'] == 'pending') {
            $error = "You have already requested enrollment for this course. Waiting for approval.";
        } elseif ($enrollment['status'] == 'approved') {
            $error = "You are already enrolled in this course.";
        } elseif ($enrollment['status'] == 'rejected') {
            $error = "Your previous enrollment request was rejected. Please contact admin.";
        }
    } else {
        $course_stmt = $conn->prepare("SELECT total_seats, enrolled_seats FROM courses WHERE id = ? AND status = 'active'");
        $course_stmt->bind_param("i", $course_id);
        $course_stmt->execute();
        $course_result = $course_stmt->get_result();
        
        if ($course_result->num_rows > 0) {
            $course = $course_result->fetch_assoc();
            $available_seats = $course['total_seats'] - $course['enrolled_seats'];
            
            if ($available_seats > 0) {
                $insert_stmt = $conn->prepare("INSERT INTO course_enrollments (student_id, course_id, status) VALUES (?, ?, 'pending')");
                $insert_stmt->bind_param("ii", $user_id, $course_id);
                
                if ($insert_stmt->execute()) {
                    $message = "Enrollment request submitted successfully! Waiting for admin approval.";
                } else {
                    $error = "Failed to submit enrollment request. Please try again.";
                }
                $insert_stmt->close();
            } else {
                $error = "No seats available for this course.";
            }
        } else {
            $error = "Course not found or inactive.";
        }
        $course_stmt->close();
    }
    $check_stmt->close();
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$min_fee = isset($_GET['min_fee']) ? intval($_GET['min_fee']) : 0;
$max_fee = isset($_GET['max_fee']) ? intval($_GET['max_fee']) : 500000;
$duration = isset($_GET['duration']) ? $_GET['duration'] : '';

// Build query
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id AND student_id = $user_id) as is_enrolled,
        (SELECT status FROM course_enrollments WHERE course_id = c.id AND student_id = $user_id) as enrollment_status
        FROM courses c 
        WHERE c.status = 'active'";

if (!empty($search)) {
    $sql .= " AND (c.title LIKE '%$search%' OR c.description LIKE '%$search%')";
}
if ($min_fee > 0) {
    $sql .= " AND c.fee >= $min_fee";
}
if ($max_fee < 500000) {
    $sql .= " AND c.fee <= $max_fee";
}
if (!empty($duration)) {
    $sql .= " AND c.duration = '$duration'";
}

$sql .= " ORDER BY c.created_at DESC";

$result = $conn->query($sql);

// Get unique durations for filter
$durations = array();
$dur_result = $conn->query("SELECT DISTINCT duration FROM courses WHERE status = 'active' AND duration IS NOT NULL");
if ($dur_result && $dur_result->num_rows > 0) {
    while ($row = $dur_result->fetch_assoc()) {
        $durations[] = $row['duration'];
    }
}

$pageTitle = "Browse Courses - ITsimplera";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg-dark: #0a0a0a;
            --bg-card: rgba(255,255,255,0.03);
            --neon-green: #00ff88;
            --neon-green-dark: #00cc6a;
            --text-white: #ffffff;
            --text-gray: rgba(255,255,255,0.6);
            --border-light: rgba(255,255,255,0.05);
            --warning: #ffaa00;
            --success: #00ff88;
            --danger: #ff4444;
            --star-color: #ffd700;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-dark);
            color: var(--text-white);
        }
        .bg-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: linear-gradient(45deg, #1a1a1a 2%, transparent 2%), linear-gradient(-45deg, #1a1a1a 2%, transparent 2%);
            background-size: 40px 40px;
            opacity: 0.3;
            z-index: -1;
        }
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px;
            background: rgba(10,10,10,0.95);
            border-right: 1px solid var(--border-light);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-header { padding: 25px; border-bottom: 1px solid var(--border-light); text-align: center; }
        .sidebar-header h3 { background: linear-gradient(135deg, var(--neon-green), #00cc6a); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .sidebar-nav { padding: 20px 0; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: var(--text-gray);
            text-decoration: none;
            transition: 0.3s;
            gap: 12px;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: var(--bg-card);
            color: var(--neon-green);
            border-left: 3px solid var(--neon-green);
        }
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-light);
        }
        .welcome h2 { font-size: 1.5rem; margin-bottom: 5px; }
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid var(--neon-green);
        }
        .filter-section {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            color: var(--text-gray);
        }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 10px 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            color: var(--text-white);
        }
        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: var(--neon-green);
        }
        .btn-filter {
            padding: 10px 24px;
            background: var(--neon-green);
            color: #0a0a0a;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-reset {
            padding: 10px 24px;
            background: transparent;
            border: 1px solid var(--border-light);
            color: var(--text-gray);
            border-radius: 8px;
            cursor: pointer;
        }
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
        }
        .course-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            overflow: hidden;
            transition: 0.3s;
        }
        .course-card:hover {
            transform: translateY(-5px);
            border-color: var(--neon-green);
        }
        .course-image {
            height: 180px;
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            overflow: hidden;
        }
        .course-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .course-content { padding: 20px; }
        .course-title { font-size: 1.2rem; margin-bottom: 10px; }
        .course-desc {
            color: var(--text-gray);
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        .rating-section {
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
        }
        .rating-display {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .rating-stars {
            display: inline-flex;
            gap: 5px;
            cursor: pointer;
        }
        .rating-stars i {
            font-size: 1rem;
            transition: 0.2s;
        }
        .rating-stars i:hover {
            transform: scale(1.1);
        }
        .rating-value {
            font-weight: 600;
            color: var(--star-color);
            font-size: 0.85rem;
        }
        .rating-count {
            font-size: 0.75rem;
            opacity: 0.6;
        }
        .rating-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .rating-modal.active {
            display: flex;
        }
        .rating-modal-content {
            background: #1a1a1a;
            border-radius: 16px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            border: 1px solid var(--border-light);
        }
        .rating-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .close-rating-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-gray);
            cursor: pointer;
        }
        .star-selector {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }
        .star-selector input {
            display: none;
        }
        .star-selector label {
            font-size: 2.5rem;
            color: #ddd;
            cursor: pointer;
            transition: 0.2s;
        }
        .star-selector label:hover,
        .star-selector label:hover ~ label,
        .star-selector input:checked ~ label {
            color: #ffd700;
            transform: scale(1.1);
        }
        .rating-modal textarea {
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            color: var(--text-white);
            min-height: 100px;
            margin: 15px 0;
            font-family: inherit;
        }
        .course-meta {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-top: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
            margin: 15px 0;
            font-size: 0.85rem;
            flex-wrap: wrap;
            gap: 10px;
        }
        .price {
            color: var(--neon-green);
            font-weight: 700;
            font-size: 1.1rem;
        }
        .btn-enroll, .btn-disabled {
            width: 100%;
            padding: 10px;
            border-radius: 30px;
            text-align: center;
            display: block;
            margin-top: 15px;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-enroll {
            background: var(--neon-green);
            color: #0a0a0a;
        }
        .btn-enroll:hover { background: var(--neon-green-dark); transform: translateY(-2px); }
        .btn-disabled {
            background: rgba(255,255,255,0.1);
            color: var(--text-gray);
            cursor: not-allowed;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success { background: rgba(0,255,136,0.2); border: 1px solid var(--success); color: var(--success); }
        .alert-danger { background: rgba(255,68,68,0.2); border: 1px solid var(--danger); color: var(--danger); }
        .toast-message {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            background: var(--neon-green);
            color: #0a0a0a;
            border-radius: 8px;
            z-index: 2001;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .no-results {
            text-align: center;
            padding: 60px;
            background: var(--bg-card);
            border-radius: 16px;
        }
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-white);
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: 0.3s; z-index: 1000; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-menu-btn { display: block; }
            .filter-form { flex-direction: column; }
            .filter-group { width: 100%; }
            .courses-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="bg-pattern"></div>

<div class="dashboard-container">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header"><h3>ITSimplera.Institute</h3><p style="font-size: 0.8rem;">Student Portal</p></div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
            <a href="certificate.php"><i class="fas fa-award"></i> My Certificate</a>
            <a href="courses.php"class="active"><i class="fas fa-book"></i> Browse Courses</a>
            <a href="my-courses.php"><i class="fas fa-graduation-cap"></i> My Courses</a>
            <a href="internships.php"><i class="fas fa-briefcase"></i> Internships</a>
            <a href="my-applications.php"><i class="fas fa-file-alt"></i> My Applications</a>
            <a href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
            <div class="welcome"><h2>Browse Courses</h2><p>Discover and enroll in our expert-led courses</p></div>
            <img src="<?php echo ASSETS_URL; ?>uploads/profiles/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" class="user-avatar">
        </div>

        <?php if($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" placeholder="Search by title or description..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-dollar-sign"></i> Min Fee (Rs)</label>
                    <input type="number" name="min_fee" placeholder="Min" value="<?php echo $min_fee > 0 ? $min_fee : ''; ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-dollar-sign"></i> Max Fee (Rs)</label>
                    <input type="number" name="max_fee" placeholder="Max" value="<?php echo $max_fee < 500000 ? $max_fee : ''; ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-clock"></i> Duration</label>
                    <select name="duration">
                        <option value="">All Durations</option>
                        <?php foreach($durations as $dur): ?>
                            <option value="<?php echo htmlspecialchars($dur); ?>" <?php echo $duration == $dur ? 'selected' : ''; ?>><?php echo htmlspecialchars($dur); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                    <a href="courses.php" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>

        <div class="courses-grid">
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($course = $result->fetch_assoc()): ?>
                    <?php
                    // Get course rating
                    $avg_rating = 0;
                    $total_ratings = 0;
                    $user_rating = 0;
                    
                    $rating_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM course_ratings WHERE course_id = ?");
                    $rating_stmt->bind_param("i", $course['id']);
                    $rating_stmt->execute();
                    $rating_result = $rating_stmt->get_result();
                    if ($rating_row = $rating_result->fetch_assoc()) {
                        $avg_rating = round($rating_row['avg_rating'], 1);
                        $total_ratings = $rating_row['total'];
                    }
                    $rating_stmt->close();
                    
                    // Get user's rating
                    $user_stmt = $conn->prepare("SELECT rating FROM course_ratings WHERE course_id = ? AND user_id = ?");
                    $user_stmt->bind_param("ii", $course['id'], $user_id);
                    $user_stmt->execute();
                    $user_result = $user_stmt->get_result();
                    if ($user_row = $user_result->fetch_assoc()) {
                        $user_rating = $user_row['rating'];
                    }
                    $user_stmt->close();
                    ?>
                    <div class="course-card" data-course-id="<?php echo $course['id']; ?>">
                        <div class="course-image">
                            <?php if(!empty($course['thumbnail'])): ?>
                                <img src="<?php echo ASSETS_URL; ?>uploads/thumbnails/<?php echo htmlspecialchars($course['thumbnail']); ?>">
                            <?php else: ?>
                                📚
                            <?php endif; ?>
                        </div>
                        <div class="course-content">
                            <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                            <p class="course-desc"><?php echo substr(htmlspecialchars($course['description']), 0, 120); ?>...</p>
                            
                            <!-- Rating Section -->
                            <div class="rating-section">
                                <div class="rating-display">
                                    <div class="rating-stars" onclick="openRatingModal(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['title']); ?>')">
                                        <?php
                                        $full_stars = floor($avg_rating);
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $full_stars) {
                                                echo '<i class="fas fa-star"></i>';
                                            } else {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                        }
                                        ?>
                                    </div>
                                    <div>
                                        <span class="rating-value"><?php echo $avg_rating; ?></span>
                                        <span class="rating-count">(<?php echo $total_ratings; ?> reviews)</span>
                                    </div>
                                </div>
                                <?php if($user_rating > 0): ?>
                                    <div style="font-size: 0.7rem; margin-top: 5px; color: var(--success);">
                                        <i class="fas fa-check-circle"></i> You rated this: <?php echo $user_rating; ?> stars
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="course-meta">
                                <span><i class="far fa-clock"></i> <?php echo htmlspecialchars($course['duration']); ?></span>
                                <span><i class="fas fa-users"></i> <?php echo $course['total_seats'] - $course['enrolled_seats']; ?> seats left</span>
                            </div>
                            <div class="course-meta">
                                <span class="price">💰 Rs. <?php echo number_format($course['fee']); ?></span>
                                <span><i class="far fa-calendar-alt"></i> Starts: <?php echo date('M d, Y', strtotime($course['start_date'])); ?></span>
                            </div>
                            
                            <?php if($course['is_enrolled'] > 0): ?>
                                <?php if($course['enrollment_status'] == 'pending'): ?>
                                    <div class="btn-disabled"><i class="fas fa-clock"></i> Request Pending Approval</div>
                                <?php elseif($course['enrollment_status'] == 'approved'): ?>
                                    <div class="btn-disabled"><i class="fas fa-check-circle"></i> Already Enrolled</div>
                                <?php elseif($course['enrollment_status'] == 'rejected'): ?>
                                    <div class="btn-disabled"><i class="fas fa-times-circle"></i> Request Rejected</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php $available_seats = $course['total_seats'] - $course['enrolled_seats']; ?>
                                <?php if($available_seats > 0): ?>
                                    <a href="?enroll=<?php echo $course['id']; ?>" class="btn-enroll" onclick="return confirm('Are you sure you want to enroll in this course?')"><i class="fas fa-graduation-cap"></i> Enroll Now</a>
                                <?php else: ?>
                                    <div class="btn-disabled"><i class="fas fa-ban"></i> No Seats Available</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-results" style="grid-column: 1/-1;">
                    <i class="fas fa-book-open" style="font-size: 3rem; opacity: 0.5; margin-bottom: 15px; display: block;"></i>
                    <h3>No courses found</h3>
                    <p style="color: var(--text-gray);">Try adjusting your search or filter criteria</p>
                    <a href="courses.php" class="btn-filter" style="display: inline-block; margin-top: 15px;">View All Courses</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Rating Modal -->
<div class="rating-modal" id="ratingModal">
    <div class="rating-modal-content">
        <div class="rating-modal-header">
            <h3 id="ratingModalTitle">Rate this Course</h3>
            <button class="close-rating-modal" onclick="closeRatingModal()">&times;</button>
        </div>
        <div class="star-selector" id="starSelector">
            <input type="radio" id="star5" name="modal_rating" value="5">
            <label for="star5">★</label>
            <input type="radio" id="star4" name="modal_rating" value="4">
            <label for="star4">★</label>
            <input type="radio" id="star3" name="modal_rating" value="3">
            <label for="star3">★</label>
            <input type="radio" id="star2" name="modal_rating" value="2">
            <label for="star2">★</label>
            <input type="radio" id="star1" name="modal_rating" value="1">
            <label for="star1">★</label>
        </div>
        <textarea id="ratingReview" placeholder="Write your review (optional)..."></textarea>
        <button onclick="submitRating()" class="btn-filter" style="width:100%;">Submit Rating</button>
    </div>
</div>

<script>
    let currentCourseId = null;
    let currentCourseTitle = null;
    
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    if(mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    function openRatingModal(courseId, courseTitle) {
        currentCourseId = courseId;
        currentCourseTitle = courseTitle;
        document.getElementById('ratingModalTitle').innerText = 'Rate: ' + courseTitle;
        document.getElementById('ratingModal').classList.add('active');
        // Clear previous selection
        const radios = document.querySelectorAll('#starSelector input');
        radios.forEach(radio => radio.checked = false);
        document.getElementById('ratingReview').value = '';
    }
    
    function closeRatingModal() {
        document.getElementById('ratingModal').classList.remove('active');
    }
    
    function showToast(message, isSuccess = true) {
        const toast = document.createElement('div');
        toast.className = 'toast-message';
        toast.style.background = isSuccess ? '#00ff88' : '#ff4444';
        toast.style.color = '#0a0a0a';
        toast.innerHTML = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    function submitRating() {
        const selectedRating = document.querySelector('#starSelector input:checked');
        if (!selectedRating) {
            showToast('Please select a rating', false);
            return;
        }
        
        const rating = selectedRating.value;
        const review = document.getElementById('ratingReview').value;
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=submit_rating&course_id=' + currentCourseId + '&rating=' + rating + '&review=' + encodeURIComponent(review)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message);
                closeRatingModal();
                // Update the rating display on the course card
                const courseCard = document.querySelector(`.course-card[data-course-id="${currentCourseId}"]`);
                if (courseCard) {
                    const ratingStarsDiv = courseCard.querySelector('.rating-stars');
                    if (ratingStarsDiv) {
                        ratingStarsDiv.innerHTML = data.stars_html;
                    }
                    const ratingValueSpan = courseCard.querySelector('.rating-value');
                    if (ratingValueSpan) {
                        ratingValueSpan.innerText = data.avg_rating;
                    }
                    const ratingCountSpan = courseCard.querySelector('.rating-count');
                    if (ratingCountSpan) {
                        ratingCountSpan.innerText = '(' + data.total_ratings + ' reviews)';
                    }
                    // Add user rating indicator
                    const ratingSection = courseCard.querySelector('.rating-section');
                    if (ratingSection && !courseCard.querySelector('.user-rating-indicator')) {
                        const userIndicator = document.createElement('div');
                        userIndicator.className = 'user-rating-indicator';
                        userIndicator.style.fontSize = '0.7rem';
                        userIndicator.style.marginTop = '5px';
                        userIndicator.style.color = '#00ff88';
                        userIndicator.innerHTML = '<i class="fas fa-check-circle"></i> You rated this: ' + rating + ' stars';
                        ratingSection.appendChild(userIndicator);
                    }
                }
            } else {
                showToast(data.message, false);
            }
        })
        .catch(error => {
            showToast('Error submitting rating', false);
        });
    }
    
    // Close modal on outside click
    document.getElementById('ratingModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRatingModal();
        }
    });
</script>
</body>
</html>