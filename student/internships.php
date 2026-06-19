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
    
    $internship_id = intval($_POST['internship_id']);
    $rating = intval($_POST['rating']);
    $review = isset($_POST['review']) ? trim($_POST['review']) : '';
    
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid rating']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Check if already rated
    $check_stmt = $conn->prepare("SELECT id FROM internship_ratings WHERE internship_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $internship_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing rating
        $stmt = $conn->prepare("UPDATE internship_ratings SET rating = ?, review = ? WHERE internship_id = ? AND user_id = ?");
        $stmt->bind_param("isii", $rating, $review, $internship_id, $user_id);
    } else {
        // Insert new rating
        $stmt = $conn->prepare("INSERT INTO internship_ratings (internship_id, user_id, rating, review) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $internship_id, $user_id, $rating, $review);
    }
    
    if ($stmt->execute()) {
        // Get updated average rating
        $avg_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM internship_ratings WHERE internship_id = ?");
        $avg_stmt->bind_param("i", $internship_id);
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

// Handle internship application
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_internship'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $internship_id = intval($_POST['internship_id']);
        
        // Check if already applied
        $check_stmt = $conn->prepare("SELECT id, status FROM internship_applications WHERE student_id = ? AND internship_id = ?");
        $check_stmt->bind_param("ii", $user_id, $internship_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $application = $check_result->fetch_assoc();
            if ($application['status'] == 'pending') {
                $error = "You have already applied for this internship. Waiting for response.";
            } elseif ($application['status'] == 'shortlisted') {
                $error = "You have been shortlisted for this internship! Check your email.";
            } elseif ($application['status'] == 'selected') {
                $error = "You have already been selected for this internship!";
            } elseif ($application['status'] == 'rejected') {
                $error = "Your application for this internship was rejected.";
            }
        } else {
            // Check if internship is still open
            $internship_stmt = $conn->prepare("SELECT total_slots, applied_count, deadline FROM internships WHERE id = ? AND status = 'open'");
            $internship_stmt->bind_param("i", $internship_id);
            $internship_stmt->execute();
            $internship_result = $internship_stmt->get_result();
            
            if ($internship_result->num_rows > 0) {
                $internship = $internship_result->fetch_assoc();
                $available_slots = $internship['total_slots'] - $internship['applied_count'];
                $deadline = strtotime($internship['deadline']);
                $today = time();
                
                if ($deadline < $today) {
                    $error = "Application deadline has passed for this internship.";
                } elseif ($available_slots <= 0) {
                    $error = "No slots available for this internship.";
                } else {
                    // Handle resume upload
                    $resume_path = '';
                    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == UPLOAD_ERR_OK) {
                        $upload_dir = '../assets/uploads/resumes/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $uploaded_file = uploadFile($_FILES['resume'], $upload_dir, array('pdf', 'doc', 'docx'));
                        if ($uploaded_file) {
                            $resume_path = $uploaded_file;
                        } else {
                            $error = "Failed to upload resume. Please upload PDF or DOC file.";
                        }
                    } else {
                        $error = "Please upload your resume.";
                    }
                    
                    if (empty($error)) {
                        $cover_letter = trim($_POST['cover_letter']);
                        
                        $insert_stmt = $conn->prepare("INSERT INTO internship_applications (student_id, internship_id, cover_letter, resume_path, status) VALUES (?, ?, ?, ?, 'pending')");
                        $insert_stmt->bind_param("iiss", $user_id, $internship_id, $cover_letter, $resume_path);
                        
                        if ($insert_stmt->execute()) {
                            // Update applied count
                            $update_stmt = $conn->prepare("UPDATE internships SET applied_count = applied_count + 1 WHERE id = ?");
                            $update_stmt->bind_param("i", $internship_id);
                            $update_stmt->execute();
                            $update_stmt->close();
                            
                            $message = "Application submitted successfully! You will be notified via email.";
                        } else {
                            $error = "Failed to submit application. Please try again.";
                        }
                        $insert_stmt->close();
                    }
                }
            } else {
                $error = "Internship not found or no longer open.";
            }
            $internship_stmt->close();
        }
        $check_stmt->close();
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';

// Build query
$sql = "SELECT i.*, 
        (SELECT COUNT(*) FROM internship_applications WHERE internship_id = i.id AND student_id = $user_id) as has_applied,
        (SELECT status FROM internship_applications WHERE internship_id = i.id AND student_id = $user_id) as application_status,
        (SELECT COUNT(*) FROM internship_ratings WHERE internship_id = i.id AND user_id = $user_id) as user_rated
        FROM internships i 
        WHERE i.status = 'open' AND i.deadline >= CURDATE()";

if (!empty($search)) {
    $sql .= " AND (i.title LIKE '%$search%' OR i.description LIKE '%$search%' OR i.company_name LIKE '%$search%')";
}
if (!empty($location)) {
    $sql .= " AND i.location LIKE '%$location%'";
}

$sql .= " ORDER BY i.deadline ASC";

$result = $conn->query($sql);

// Get unique locations for filter
$locations = array();
$loc_result = $conn->query("SELECT DISTINCT location FROM internships WHERE status = 'open' AND location IS NOT NULL");
if ($loc_result && $loc_result->num_rows > 0) {
    while ($row = $loc_result->fetch_assoc()) {
        $locations[] = $row['location'];
    }
}

$csrf_token = generateCSRFToken();
$pageTitle = "Internships - ITsimplera";
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
            --info: #00ccff;
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
            min-width: 180px;
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
        .internships-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
        }
        .internship-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            overflow: hidden;
            transition: 0.3s;
        }
        .internship-card:hover {
            transform: translateY(-5px);
            border-color: var(--neon-green);
        }
        .internship-header {
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            padding: 20px;
            border-bottom: 1px solid var(--border-light);
        }
        .company {
            color: var(--neon-green);
            font-size: 0.85rem;
            margin-bottom: 8px;
        }
        .internship-title {
            font-size: 1.3rem;
            margin-bottom: 10px;
        }
        .deadline {
            font-size: 0.75rem;
            color: var(--warning);
        }
        .internship-content { padding: 20px; }
        .internship-desc {
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
        .details {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            font-size: 0.8rem;
            color: var(--text-gray);
            flex-wrap: wrap;
        }
        .slots {
            font-size: 0.75rem;
            color: var(--info);
        }
        .btn-apply, .btn-applied, .btn-disabled {
            width: 100%;
            padding: 10px;
            border-radius: 30px;
            text-align: center;
            display: block;
            margin-top: 15px;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-apply {
            background: var(--neon-green);
            color: #0a0a0a;
            cursor: pointer;
            border: none;
        }
        .btn-apply:hover { background: var(--neon-green-dark); transform: translateY(-2px); }
        .btn-applied {
            background: rgba(0,255,136,0.2);
            color: var(--success);
            cursor: default;
        }
        .btn-disabled {
            background: rgba(255,255,255,0.1);
            color: var(--text-gray);
            cursor: not-allowed;
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
            margin-top: 10px;
        }
        /* Modals */
        .modal {
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
        .modal.active { display: flex; }
        .modal-content {
            background: #1a1a1a;
            border-radius: 24px;
            max-width: 500px;
            width: 90%;
            padding: 30px;
            border: 1px solid var(--border-light);
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-gray);
        }
        .close-modal:hover { color: var(--danger); }
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
        .modal textarea {
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            color: var(--text-white);
            min-height: 120px;
            margin: 15px 0;
            font-family: inherit;
        }
        .modal input[type="file"] {
            width: 100%;
            padding: 10px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            color: var(--text-white);
            margin: 15px 0;
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
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: 0.3s; z-index: 1000; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-menu-btn { display: block; }
            .filter-form { flex-direction: column; }
            .filter-group { width: 100%; }
            .internships-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="bg-pattern"></div>

<!-- Rating Modal -->
<div class="modal" id="ratingModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Rate this Internship</h3>
            <button class="close-modal" onclick="closeRatingModal()">&times;</button>
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
        <button onclick="submitRating()" class="btn-apply" style="margin-top: 10px;">Submit Rating</button>
    </div>
</div>

<!-- Application Modal -->
<div class="modal" id="applicationModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Apply for Internship</h3>
            <button class="close-modal" onclick="closeApplicationModal()">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data" id="applicationForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="internship_id" id="internshipId">
            <input type="hidden" name="apply_internship" value="1">
            
            <div class="form-group">
                <label>Cover Letter</label>
                <textarea name="cover_letter" placeholder="Tell us why you're interested in this internship and why you're a good fit..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Resume/CV (PDF or DOC)</label>
                <input type="file" name="resume" accept=".pdf,.doc,.docx" required>
                <p style="font-size: 0.7rem; color: var(--text-gray); margin-top: 5px;">Upload your resume (PDF, DOC, DOCX - Max 5MB)</p>
            </div>
            
            <button type="submit" class="btn-apply" style="margin-top: 10px;">Submit Application</button>
        </form>
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
            <a href="my-courses.php"><i class="fas fa-graduation-cap"></i> My Courses</a>
            <a href="internships.php" class="active"><i class="fas fa-briefcase"></i> Internships</a>
            <a href="my-applications.php"><i class="fas fa-file-alt"></i> My Applications</a>
            <a href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
            <div class="welcome"><h2>Internship Opportunities</h2><p>Find and apply for internships at top companies</p></div>
            <img src="<?php echo ASSETS_URL; ?>uploads/profiles/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" class="user-avatar">
        </div>

        <?php if($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" placeholder="Search by title, company, description..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-map-marker-alt"></i> Location</label>
                    <select name="location">
                        <option value="">All Locations</option>
                        <?php foreach($locations as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $location == $loc ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                    <a href="internships.php" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>

        <!-- Internships Grid -->
        <div class="internships-grid">
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($internship = $result->fetch_assoc()): ?>
                    <?php
                    // Get internship rating
                    $avg_rating = 0;
                    $total_ratings = 0;
                    $rating_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM internship_ratings WHERE internship_id = ?");
                    $rating_stmt->bind_param("i", $internship['id']);
                    $rating_stmt->execute();
                    $rating_result = $rating_stmt->get_result();
                    if ($rating_row = $rating_result->fetch_assoc()) {
                        $avg_rating = round($rating_row['avg_rating'], 1);
                        $total_ratings = $rating_row['total'];
                    }
                    $rating_stmt->close();
                    
                    $available_slots = $internship['total_slots'] - $internship['applied_count'];
                    $deadline_passed = strtotime($internship['deadline']) < time();
                    ?>
                    <div class="internship-card" data-internship-id="<?php echo $internship['id']; ?>">
                        <div class="internship-header">
                            <div class="company"><i class="fas fa-building"></i> <?php echo htmlspecialchars($internship['company_name']); ?></div>
                            <h3 class="internship-title"><?php echo htmlspecialchars($internship['title']); ?></h3>
                            <div class="deadline"><i class="far fa-calendar-alt"></i> Deadline: <?php echo date('M d, Y', strtotime($internship['deadline'])); ?></div>
                        </div>
                        <div class="internship-content">
                            <p class="internship-desc"><?php echo substr(htmlspecialchars($internship['description']), 0, 120); ?>...</p>
                            
                            <!-- Rating Display - Clickable -->
                            <div class="rating-section">
                                <div class="rating-stars" onclick="openRatingModal(<?php echo $internship['id']; ?>, '<?php echo htmlspecialchars($internship['title']); ?>')">
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
                            
                            <?php if($internship['user_rated'] > 0): ?>
                                <div class="rated-badge">
                                    <i class="fas fa-check-circle"></i> You rated this internship
                                </div>
                            <?php endif; ?>
                            
                            <div class="details">
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($internship['location']); ?></span>
                                <span><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($internship['stipend']); ?></span>
                            </div>
                            <div class="details">
                                <span><i class="far fa-clock"></i> <?php echo htmlspecialchars($internship['duration']); ?></span>
                                <span class="slots"><i class="fas fa-users"></i> <?php echo $available_slots; ?> / <?php echo $internship['total_slots']; ?> slots</span>
                            </div>
                            
                            <?php if($deadline_passed): ?>
                                <div class="btn-disabled"><i class="fas fa-ban"></i> Deadline Passed</div>
                            <?php elseif($internship['has_applied'] > 0): ?>
                                <?php if($internship['application_status'] == 'pending'): ?>
                                    <div class="btn-applied"><i class="fas fa-clock"></i> Application Under Review</div>
                                <?php elseif($internship['application_status'] == 'shortlisted'): ?>
                                    <div class="btn-applied"><i class="fas fa-star"></i> Shortlisted! Check Email</div>
                                <?php elseif($internship['application_status'] == 'selected'): ?>
                                    <div class="btn-applied"><i class="fas fa-trophy"></i> Selected! Congratulations!</div>
                                <?php elseif($internship['application_status'] == 'rejected'): ?>
                                    <div class="btn-disabled"><i class="fas fa-times-circle"></i> Application Rejected</div>
                                <?php endif; ?>
                            <?php elseif($available_slots <= 0): ?>
                                <div class="btn-disabled"><i class="fas fa-ban"></i> No Slots Available</div>
                            <?php else: ?>
                                <button onclick="openApplicationModal(<?php echo $internship['id']; ?>, '<?php echo htmlspecialchars($internship['title']); ?>')" class="btn-apply">
                                    <i class="fas fa-paper-plane"></i> Apply Now
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-briefcase" style="font-size: 3rem; opacity: 0.5; margin-bottom: 15px; display: block;"></i>
                    <h3>No internships available</h3>
                    <p style="color: var(--text-gray);">Check back later for new opportunities</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    let currentInternshipId = null;
    let currentInternshipTitle = null;
    
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
    
    // Rating Modal Functions
    function openRatingModal(internshipId, internshipTitle) {
        currentInternshipId = internshipId;
        currentInternshipTitle = internshipTitle;
        document.getElementById('modalTitle').innerText = 'Rate Internship: ' + internshipTitle;
        
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
            body: 'action=submit_rating&internship_id=' + currentInternshipId + '&rating=' + rating + '&review=' + encodeURIComponent(review)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message);
                closeRatingModal();
                // Update the rating display on the internship card
                const internshipCard = document.querySelector(`.internship-card[data-internship-id="${currentInternshipId}"]`);
                if (internshipCard) {
                    const ratingStarsDiv = internshipCard.querySelector('.rating-stars');
                    if (ratingStarsDiv) {
                        ratingStarsDiv.innerHTML = data.stars_html;
                        ratingStarsDiv.onclick = function() { openRatingModal(currentInternshipId, currentInternshipTitle); };
                    }
                    const ratingValueSpan = internshipCard.querySelector('.rating-value');
                    if (ratingValueSpan) {
                        ratingValueSpan.innerText = data.avg_rating;
                    }
                    const ratingCountSpan = internshipCard.querySelector('.rating-section span:last-child');
                    if (ratingCountSpan) {
                        ratingCountSpan.innerText = '(' + data.total_ratings + ' reviews)';
                    }
                    // Add rated badge if not exists
                    if (!internshipCard.querySelector('.rated-badge')) {
                        const ratedBadge = document.createElement('div');
                        ratedBadge.className = 'rated-badge';
                        ratedBadge.innerHTML = '<i class="fas fa-check-circle"></i> You rated this internship';
                        internshipCard.querySelector('.rating-section').after(ratedBadge);
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
    
    // Application Modal Functions
    function openApplicationModal(internshipId, internshipTitle) {
        const modal = document.getElementById('applicationModal');
        const modalTitle = document.getElementById('modalTitle');
        const internshipIdField = document.getElementById('internshipId');
        
        modalTitle.textContent = 'Apply for: ' + internshipTitle;
        internshipIdField.value = internshipId;
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeApplicationModal() {
        const modal = document.getElementById('applicationModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Close modals when clicking outside
    document.getElementById('ratingModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRatingModal();
        }
    });
    
    document.getElementById('applicationModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeApplicationModal();
        }
    });
</script>
</body>
</html>