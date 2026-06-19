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
            'stars_html' => $stars_html
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit rating']);
    }
    $stmt->close();
    $check_stmt->close();
    exit;
}

// Get filter status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query
$sql = "SELECT ce.*, c.id as course_id, c.title, c.description, c.duration, c.fee, c.thumbnail, c.start_date, c.end_date,
        (SELECT COUNT(*) FROM course_ratings WHERE course_id = c.id AND user_id = $user_id) as user_rated
        FROM course_enrollments ce 
        JOIN courses c ON ce.course_id = c.id 
        WHERE ce.student_id = $user_id";

if ($status_filter != 'all') {
    $sql .= " AND ce.status = '$status_filter'";
}

$sql .= " ORDER BY ce.applied_at DESC";

$result = $conn->query($sql);

// Get counts for tabs
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;

$count_sql = "SELECT status, COUNT(*) as count FROM course_enrollments WHERE student_id = $user_id GROUP BY status";
$count_result = $conn->query($count_sql);
if ($count_result && $count_result->num_rows > 0) {
    while ($row = $count_result->fetch_assoc()) {
        if ($row['status'] == 'pending') $pending_count = $row['count'];
        if ($row['status'] == 'approved') $approved_count = $row['count'];
        if ($row['status'] == 'rejected') $rejected_count = $row['count'];
    }
}

$pageTitle = "My Courses - ITsimplera";
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
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .tab {
            padding: 10px 24px;
            background: transparent;
            border: 1px solid var(--border-light);
            border-radius: 30px;
            color: var(--text-gray);
            text-decoration: none;
            transition: 0.3s;
            font-size: 0.9rem;
        }
        .tab:hover, .tab.active {
            background: var(--neon-green);
            color: #0a0a0a;
            border-color: var(--neon-green);
        }
        .tab-count {
            background: rgba(255,255,255,0.1);
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 8px;
            font-size: 0.7rem;
        }
        .tab.active .tab-count {
            background: rgba(0,0,0,0.2);
        }
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
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
            height: 160px;
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            overflow: hidden;
            position: relative;
        }
        .course-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .status-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-pending { background: var(--warning); color: #0a0a0a; }
        .status-approved { background: var(--success); color: #0a0a0a; }
        .status-rejected { background: var(--danger); color: white; }
        .course-content { padding: 20px; }
        .course-title { font-size: 1.2rem; margin-bottom: 10px; }
        .course-desc {
            color: var(--text-gray);
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        .rating-section {
            margin: 10px 0;
        }
        .rating-stars {
            display: inline-flex;
            gap: 3px;
            cursor: pointer;
        }
        .rating-stars i {
            font-size: 0.85rem;
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
        }
        .btn-rate, .btn-view {
            padding: 8px 16px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
        }
        .btn-rate {
            background: rgba(255,215,0,0.2);
            border: 1px solid #ffd700;
            color: #ffd700;
        }
        .btn-rate:hover {
            background: #ffd700;
            color: #0a0a0a;
        }
        .btn-view {
            background: var(--neon-green);
            color: #0a0a0a;
        }
        .btn-view:hover {
            background: var(--neon-green-dark);
        }
        .rated-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            background: rgba(0,255,136,0.1);
            border-radius: 20px;
            font-size: 0.75rem;
            color: var(--success);
        }
        .no-results {
            text-align: center;
            padding: 60px;
            background: var(--bg-card);
            border-radius: 16px;
            grid-column: 1/-1;
        }
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-white);
            cursor: pointer;
        }
        /* Rating Modal */
        .rating-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .rating-modal.active { display: flex; }
        .rating-modal-content {
            background: #1a1a1a;
            border-radius: 24px;
            max-width: 450px;
            width: 90%;
            padding: 30px;
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
            cursor: pointer;
            color: var(--text-gray);
        }
        .close-rating-modal:hover { color: var(--danger); }
        .star-selector {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        .star-selector input { display: none; }
        .star-selector label {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
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
            margin: 20px 0;
            font-family: inherit;
        }
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
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: 0.3s; z-index: 1000; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-menu-btn { display: block; }
            .courses-grid { grid-template-columns: 1fr; }
            .tabs { justify-content: center; }
        }
    </style>
</head>
<body>
<div class="bg-pattern"></div>

<!-- Rating Modal -->
<div class="rating-modal" id="ratingModal">
    <div class="rating-modal-content">
        <div class="rating-modal-header">
            <h3 id="modalTitle">Rate this Course</h3>
            <button class="close-rating-modal" onclick="closeRatingModal()">&times;</button>
        </div>
        <div class="star-selector" id="starSelector">
            <input type="radio" id="modal_star5" name="modal_rating" value="5">
            <label for="modal_star5">★</label>
            <input type="radio" id="modal_star4" name="modal_rating" value="4">
            <label for="modal_star4">★</label>
            <input type="radio" id="modal_star3" name="modal_rating" value="3">
            <label for="modal_star3">★</label>
            <input type="radio" id="modal_star2" name="modal_rating" value="2">
            <label for="modal_star2">★</label>
            <input type="radio" id="modal_star1" name="modal_rating" value="1">
            <label for="modal_star1">★</label>
        </div>
        <textarea id="ratingReview" placeholder="Write your review (optional)..."></textarea>
        <button onclick="submitRating()" class="btn-rate" style="background: var(--neon-green); color: #0a0a0a; width: 100%; justify-content: center;">Submit Rating</button>
    </div>
</div>

<div class="dashboard-container">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header"><h3>ITSimplera.Institute</h3><p style="font-size: 0.8rem;">Student Portal</p></div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
            <a href="certificate.php"><i class="fas fa-award"></i> My Certificate</a>
            <a href="courses.php"><i class="fas fa-book"></i> Browse Courses</a>
            <a href="my-courses.php"class="active"><i class="fas fa-graduation-cap"></i> My Courses</a>
            <a href="internships.php"><i class="fas fa-briefcase"></i> Internships</a>
            <a href="my-applications.php"><i class="fas fa-file-alt"></i> My Applications</a>
            <a href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
            <div class="welcome"><h2>My Courses</h2><p>Track your course enrollments and progress</p></div>
            <img src="<?php echo ASSETS_URL; ?>uploads/profiles/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" class="user-avatar">
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <a href="?status=all" class="tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                All <span class="tab-count"><?php echo $pending_count + $approved_count + $rejected_count; ?></span>
            </a>
            <a href="?status=pending" class="tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                Pending <span class="tab-count"><?php echo $pending_count; ?></span>
            </a>
            <a href="?status=approved" class="tab <?php echo $status_filter == 'approved' ? 'active' : ''; ?>">
                Approved <span class="tab-count"><?php echo $approved_count; ?></span>
            </a>
            <a href="?status=rejected" class="tab <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">
                Rejected <span class="tab-count"><?php echo $rejected_count; ?></span>
            </a>
        </div>

        <!-- Courses Grid -->
        <div class="courses-grid">
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($course = $result->fetch_assoc()): ?>
                    <?php
                    // Get course rating
                    $avg_rating = 0;
                    $total_ratings = 0;
                    $rating_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM course_ratings WHERE course_id = ?");
                    $rating_stmt->bind_param("i", $course['course_id']);
                    $rating_stmt->execute();
                    $rating_result = $rating_stmt->get_result();
                    if ($rating_row = $rating_result->fetch_assoc()) {
                        $avg_rating = round($rating_row['avg_rating'], 1);
                        $total_ratings = $rating_row['total'];
                    }
                    $rating_stmt->close();
                    ?>
                    <div class="course-card" data-course-id="<?php echo $course['course_id']; ?>">
                        <div class="course-image">
                            <?php if(!empty($course['thumbnail'])): ?>
                                <img src="<?php echo ASSETS_URL; ?>uploads/thumbnails/<?php echo htmlspecialchars($course['thumbnail']); ?>">
                            <?php else: ?>
                                📚
                            <?php endif; ?>
                            <div class="status-overlay status-<?php echo $course['status']; ?>">
                                <?php echo ucfirst($course['status']); ?>
                            </div>
                        </div>
                        <div class="course-content">
                            <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                            <p class="course-desc"><?php echo substr(htmlspecialchars($course['description']), 0, 100); ?>...</p>
                            
                            <!-- Rating Display - Clickable -->
                            <div class="rating-section">
                                <div class="rating-stars" onclick="openRatingModal(<?php echo $course['course_id']; ?>, '<?php echo htmlspecialchars($course['title']); ?>')">
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
                                <span class="rating-value"><?php echo $avg_rating; ?></span>
                                <span style="font-size: 0.7rem; opacity: 0.6;">(<?php echo $total_ratings; ?> reviews)</span>
                            </div>
                            
                            <div class="course-meta">
                                <span><i class="far fa-clock"></i> <?php echo htmlspecialchars($course['duration']); ?></span>
                                <span class="price">💰 Rs. <?php echo number_format($course['fee']); ?></span>
                            </div>
                            <div class="course-meta">
                                <span><i class="far fa-calendar-alt"></i> Applied: <?php echo date('M d, Y', strtotime($course['applied_at'])); ?></span>
                                <?php if($course['status'] == 'approved' && !empty($course['start_date'])): ?>
                                    <span><i class="fas fa-play-circle"></i> Starts: <?php echo date('M d, Y', strtotime($course['start_date'])); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                                <?php if($course['status'] == 'approved'): ?>
                                    <?php if($course['user_rated'] > 0): ?>
                                        <span class="rated-badge"><i class="fas fa-check-circle"></i> You rated this course</span>
                                    <?php else: ?>
                                        <button onclick="openRatingModal(<?php echo $course['course_id']; ?>, '<?php echo htmlspecialchars($course['title']); ?>')" class="btn-rate">
                                            <i class="fas fa-star"></i> Rate this course
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if($course['status'] == 'approved'): ?>
                                    <a href="#" class="btn-view"><i class="fas fa-play"></i> Continue Learning</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-graduation-cap" style="font-size: 3rem; opacity: 0.5; margin-bottom: 15px; display: block;"></i>
                    <h3>No courses found</h3>
                    <p style="color: var(--text-gray); margin-bottom: 20px;">You haven't enrolled in any courses yet.</p>
                    <a href="courses.php" class="btn-view" style="display: inline-block;">Browse Courses</a>
                </div>
            <?php endif; ?>
        </div>
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
    
    function showToast(message, isSuccess = true) {
        const toast = document.createElement('div');
        toast.className = 'toast-message';
        toast.style.background = isSuccess ? '#00ff88' : '#ff4444';
        toast.style.color = '#0a0a0a';
        toast.innerHTML = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    function openRatingModal(courseId, courseTitle) {
        currentCourseId = courseId;
        currentCourseTitle = courseTitle;
        document.getElementById('modalTitle').innerText = 'Rate Course: ' + courseTitle;
        
        // Clear previous selection
        const radios = document.querySelectorAll('#starSelector input');
        radios.forEach(radio => radio.checked = false);
        document.getElementById('ratingReview').value = '';
        
        document.getElementById('ratingModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeRatingModal() {
        document.getElementById('ratingModal').classList.remove('active');
        document.body.style.overflow = '';
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
                        // Re-attach click event
                        ratingStarsDiv.onclick = function() { openRatingModal(currentCourseId, currentCourseTitle); };
                    }
                    const ratingValueSpan = courseCard.querySelector('.rating-value');
                    if (ratingValueSpan) {
                        ratingValueSpan.innerText = data.avg_rating;
                    }
                    const ratingCountSpan = courseCard.querySelector('.rating-section span:last-child');
                    if (ratingCountSpan) {
                        ratingCountSpan.innerText = '(' + data.total_ratings + ' reviews)';
                    }
                    // Replace rate button with rated badge
                    const rateBtn = courseCard.querySelector('.btn-rate');
                    if (rateBtn && !courseCard.querySelector('.rated-badge')) {
                        const ratedBadge = document.createElement('span');
                        ratedBadge.className = 'rated-badge';
                        ratedBadge.innerHTML = '<i class="fas fa-check-circle"></i> You rated this course';
                        rateBtn.parentNode.insertBefore(ratedBadge, rateBtn);
                        rateBtn.remove();
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