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
        
        echo json_encode([
            'success' => true, 
            'message' => 'Rating submitted successfully!',
            'avg_rating' => $avg_rating,
            'total_ratings' => $total_ratings
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
$sql = "SELECT ia.*, i.title, i.company_name, i.location, i.stipend, i.duration, i.deadline,
        DATEDIFF(i.deadline, CURDATE()) as days_left,
        (SELECT COUNT(*) FROM internship_ratings WHERE internship_id = i.id AND user_id = $user_id) as user_rated
        FROM internship_applications ia 
        JOIN internships i ON ia.internship_id = i.id 
        WHERE ia.student_id = $user_id";

if ($status_filter != 'all') {
    $sql .= " AND ia.status = '$status_filter'";
}

$sql .= " ORDER BY ia.applied_at DESC";

$result = $conn->query($sql);

// Get counts for tabs
$pending_count = 0;
$shortlisted_count = 0;
$selected_count = 0;
$rejected_count = 0;

$count_sql = "SELECT status, COUNT(*) as count FROM internship_applications WHERE student_id = $user_id GROUP BY status";
$count_result = $conn->query($count_sql);
if ($count_result && $count_result->num_rows > 0) {
    while ($row = $count_result->fetch_assoc()) {
        if ($row['status'] == 'pending') $pending_count = $row['count'];
        if ($row['status'] == 'shortlisted') $shortlisted_count = $row['count'];
        if ($row['status'] == 'selected') $selected_count = $row['count'];
        if ($row['status'] == 'rejected') $rejected_count = $row['count'];
    }
}

$pageTitle = "My Applications - ITsimplera";
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
        .applications-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .application-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            overflow: hidden;
            transition: 0.3s;
        }
        .application-card:hover {
            transform: translateX(5px);
            border-color: var(--neon-green);
        }
        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px 25px;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid var(--border-light);
            flex-wrap: wrap;
            gap: 15px;
        }
        .company-info .company {
            color: var(--neon-green);
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        .company-info h3 {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }
        .status-badge {
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-pending { background: rgba(255,170,0,0.2); color: var(--warning); }
        .status-shortlisted { background: rgba(0,204,255,0.2); color: var(--info); }
        .status-selected { background: rgba(0,255,136,0.2); color: var(--success); }
        .status-rejected { background: rgba(255,68,68,0.2); color: var(--danger); }
        .application-body {
            padding: 20px 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            color: var(--text-gray);
        }
        .info-item i {
            width: 20px;
            color: var(--neon-green);
        }
        .cover-letter {
            margin-top: 15px;
            padding: 15px;
            background: rgba(255,255,255,0.02);
            border-radius: 12px;
            border-left: 3px solid var(--neon-green);
        }
        .cover-letter p {
            font-size: 0.85rem;
            line-height: 1.5;
            color: var(--text-gray);
        }
        .resume-link {
            margin-top: 15px;
        }
        .btn-download {
            padding: 8px 20px;
            background: rgba(0,255,136,0.1);
            border: 1px solid var(--neon-green);
            border-radius: 30px;
            color: var(--neon-green);
            text-decoration: none;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
        }
        .btn-download:hover {
            background: var(--neon-green);
            color: #0a0a0a;
        }
        .application-footer {
            padding: 15px 25px;
            border-top: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.75rem;
            color: var(--text-gray);
        }
        .rated-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            background: rgba(0,255,136,0.2);
            border-radius: 20px;
            font-size: 0.75rem;
            color: var(--success);
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
            .tabs { justify-content: center; }
            .application-header { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="bg-pattern"></div>

<!-- Rating Modal -->
<div class="rating-modal" id="ratingModal">
    <div class="rating-modal-content">
        <div class="rating-modal-header">
            <h3 id="modalTitle">Rate this Internship</h3>
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
        <button onclick="submitRating()" class="btn-download" style="background: var(--neon-green); color: #0a0a0a; width: 100%; justify-content: center;">Submit Rating</button>
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
            <a href="internships.php"><i class="fas fa-briefcase"></i> Internships</a>
            <a href="my-applications.php" class="active"><i class="fas fa-file-alt"></i> My Applications</a>
            <a href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
            <div class="welcome"><h2>My Applications</h2><p>Track your internship application status</p></div>
            <img src="<?php echo ASSETS_URL; ?>uploads/profiles/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" class="user-avatar">
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <a href="?status=all" class="tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                All <span class="tab-count"><?php echo $pending_count + $shortlisted_count + $selected_count + $rejected_count; ?></span>
            </a>
            <a href="?status=pending" class="tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                Pending <span class="tab-count"><?php echo $pending_count; ?></span>
            </a>
            <a href="?status=shortlisted" class="tab <?php echo $status_filter == 'shortlisted' ? 'active' : ''; ?>">
                Shortlisted <span class="tab-count"><?php echo $shortlisted_count; ?></span>
            </a>
            <a href="?status=selected" class="tab <?php echo $status_filter == 'selected' ? 'active' : ''; ?>">
                Selected <span class="tab-count"><?php echo $selected_count; ?></span>
            </a>
            <a href="?status=rejected" class="tab <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">
                Rejected <span class="tab-count"><?php echo $rejected_count; ?></span>
            </a>
        </div>

        <!-- Applications List -->
        <div class="applications-list">
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($app = $result->fetch_assoc()): ?>
                    <div class="application-card" data-internship-id="<?php echo $app['internship_id']; ?>">
                        <div class="application-header">
                            <div class="company-info">
                                <div class="company"><i class="fas fa-building"></i> <?php echo htmlspecialchars($app['company_name']); ?></div>
                                <h3><?php echo htmlspecialchars($app['title']); ?></h3>
                                <div class="location" style="font-size: 0.8rem; color: var(--text-gray); margin-top: 5px;">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($app['location']); ?>
                                </div>
                            </div>
                            <div>
                                <span class="status-badge status-<?php echo $app['status']; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="application-body">
                            <div class="info-item">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Stipend: <?php echo htmlspecialchars($app['stipend']); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="far fa-clock"></i>
                                <span>Duration: <?php echo htmlspecialchars($app['duration']); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="far fa-calendar-alt"></i>
                                <span>Applied: <?php echo date('M d, Y', strtotime($app['applied_at'])); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="far fa-calendar-check"></i>
                                <span>Deadline: <?php echo date('M d, Y', strtotime($app['deadline'])); ?></span>
                            </div>
                        </div>
                        
                        <?php if(!empty($app['cover_letter'])): ?>
                        <div class="cover-letter">
                            <p><strong><i class="fas fa-envelope"></i> Cover Letter:</strong></p>
                            <p><?php echo nl2br(htmlspecialchars($app['cover_letter'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="resume-link">
                            <?php if(!empty($app['resume_path'])): ?>
                                <a href="<?php echo ASSETS_URL; ?>uploads/resumes/<?php echo htmlspecialchars($app['resume_path']); ?>" class="btn-download" target="_blank">
                                    <i class="fas fa-download"></i> Download Resume
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="application-footer">
                            <div>
                                <i class="fas fa-id-card"></i> Application ID: #<?php echo $app['id']; ?>
                            </div>
                            <div>
                                <?php if($app['status'] == 'selected'): ?>
                                    <?php if($app['user_rated'] > 0): ?>
                                        <span class="rated-badge">
                                            <i class="fas fa-star"></i> You rated this internship
                                        </span>
                                    <?php else: ?>
                                        <button onclick="openRatingModal(<?php echo $app['internship_id']; ?>, '<?php echo htmlspecialchars($app['title']); ?>')" class="btn-download" style="cursor: pointer;">
                                            <i class="fas fa-star"></i> Rate this Internship
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if($app['status'] == 'shortlisted'): ?>
                                    <span class="status-badge status-shortlisted">
                                        <i class="fas fa-envelope"></i> Check your email for next steps
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-file-alt" style="font-size: 3rem; opacity: 0.5; margin-bottom: 15px; display: block;"></i>
                    <h3>No applications found</h3>
                    <p style="color: var(--text-gray); margin-bottom: 20px;">You haven't applied for any internships yet.</p>
                    <a href="internships.php" class="btn-download" style="display: inline-block;">Browse Internships</a>
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
                // Update the UI - replace rate button with rated badge
                const applicationCard = document.querySelector(`.application-card[data-internship-id="${currentInternshipId}"]`);
                if (applicationCard) {
                    const rateBtn = applicationCard.querySelector('.btn-download');
                    if (rateBtn && rateBtn.innerHTML.includes('Rate this Internship')) {
                        const ratedBadge = document.createElement('span');
                        ratedBadge.className = 'rated-badge';
                        ratedBadge.innerHTML = '<i class="fas fa-star"></i> You rated this internship';
                        rateBtn.parentNode.innerHTML = '';
                        rateBtn.parentNode.appendChild(ratedBadge);
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
    
    // Close modal when clicking outside
    document.getElementById('ratingModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRatingModal();
        }
    });
</script>
</body>
</html>