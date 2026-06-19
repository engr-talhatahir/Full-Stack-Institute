<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($student_id == 0) {
    header('Location: students.php');
    exit;
}

// Fetch student details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    header('Location: students.php');
    exit;
}

// Get enrolled courses with ratings
$enrolled_courses = array();
$sql = "SELECT ce.*, c.title, c.description, c.duration, c.fee, c.start_date, c.end_date, c.thumbnail,
        (SELECT AVG(rating) FROM course_ratings WHERE course_id = c.id) as avg_rating,
        (SELECT COUNT(*) FROM course_ratings WHERE course_id = c.id) as rating_count
        FROM course_enrollments ce 
        JOIN courses c ON ce.course_id = c.id 
        WHERE ce.student_id = $student_id 
        ORDER BY ce.applied_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $enrolled_courses[] = $row;
    }
}

// Get internship applications with ratings
$applications = array();
$sql = "SELECT ia.*, i.title, i.company_name, i.location, i.stipend, i.duration, i.deadline,
        (SELECT AVG(rating) FROM internship_ratings WHERE internship_id = i.id) as avg_rating,
        (SELECT COUNT(*) FROM internship_ratings WHERE internship_id = i.id) as rating_count
        FROM internship_applications ia 
        JOIN internships i ON ia.internship_id = i.id 
        WHERE ia.student_id = $student_id 
        ORDER BY ia.applied_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
}

// Get course ratings given by this student
$given_ratings = array();
$sql = "SELECT cr.*, c.title as course_title 
        FROM course_ratings cr 
        JOIN courses c ON cr.course_id = c.id 
        WHERE cr.user_id = $student_id 
        ORDER BY cr.created_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $given_ratings[] = $row;
    }
}

$pageTitle = "Student Details - " . $student['full_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-dark: #0a0a0a;
            --bg-card: rgba(255,255,255,0.03);
            --neon-green: #00ff88;
            --neon-green-dark: #00cc6a;
            --text-white: #ffffff;
            --text-gray: rgba(255,255,255,0.6);
            --border-light: rgba(255,255,255,0.05);
            --success: #00ff88;
            --danger: #ff4444;
            --warning: #ffaa00;
            --info: #00ccff;
            --star-color: #ffd700;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-dark);
            color: var(--text-white);
            overflow-x: hidden;
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
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-dark); }
        ::-webkit-scrollbar-thumb { background: var(--neon-green); border-radius: 10px; }
        
        /* Dashboard Container */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: rgba(10,10,10,0.95);
            border-right: 1px solid var(--border-light);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 25px;
            border-bottom: 1px solid var(--border-light);
            text-align: center;
        }
        
        .sidebar-header h3 {
            background: linear-gradient(135deg, var(--neon-green), #00cc6a);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-size: 1.3rem;
        }
        
        .sidebar-nav {
            padding: 20px 0;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: var(--text-gray);
            text-decoration: none;
            transition: 0.3s;
            gap: 12px;
            font-size: 0.95rem;
        }
        
        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: var(--bg-card);
            color: var(--neon-green);
            border-left: 3px solid var(--neon-green);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px 25px;
            width: calc(100% - 280px);
        }
        
        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-light);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .welcome h2 {
            font-size: 1.3rem;
            margin-bottom: 5px;
        }
        
        .welcome p {
            color: var(--text-gray);
            font-size: 0.85rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid var(--neon-green);
            object-fit: cover;
        }
        
        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-white);
            cursor: pointer;
            padding: 8px;
        }
        
        /* Back Button */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: transparent;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            color: var(--text-white);
            text-decoration: none;
            margin-bottom: 20px;
            transition: 0.3s;
        }
        
        .btn-back:hover {
            border-color: var(--neon-green);
            color: var(--neon-green);
        }
        
        /* Profile Header */
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 3px solid var(--neon-green);
            object-fit: cover;
        }
        
        .profile-info h3 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .profile-info p {
            color: var(--text-gray);
            margin: 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-active {
            background: rgba(0,255,136,0.2);
            color: var(--success);
        }
        
        .status-suspended {
            background: rgba(255,68,68,0.2);
            color: var(--danger);
        }
        
        /* Cards */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-light);
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .card-header h3 {
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Tables */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        th, td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }
        
        th {
            color: var(--text-gray);
            font-weight: 500;
        }
        
        /* Status Badges for Enrollments */
        .enroll-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .enroll-pending {
            background: rgba(255,170,0,0.2);
            color: var(--warning);
        }
        
        .enroll-approved {
            background: rgba(0,255,136,0.2);
            color: var(--success);
        }
        
        .enroll-rejected {
            background: rgba(255,68,68,0.2);
            color: var(--danger);
        }
        
        /* Rating Stars */
        .rating-stars i {
            color: var(--star-color);
            font-size: 0.75rem;
        }
        
        /* Action Buttons */
        .btn-sm {
            padding: 5px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        /* No Results */
        .no-results {
            text-align: center;
            padding: 40px;
            color: var(--text-gray);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-info p {
                justify-content: center;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            th, td {
                padding: 8px 6px;
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-info {
                align-self: flex-end;
            }
            
            .profile-avatar {
                width: 90px;
                height: 90px;
            }
            
            .profile-info h3 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
<div class="bg-pattern"></div>

<div class="dashboard-container">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>ITSimplera.Institute</h3>
            <p style="font-size: 0.7rem; color: var(--text-gray);">Admin Panel</p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="students.php" class="active"><i class="fas fa-users"></i> Students</a>
            <a href="courses.php"><i class="fas fa-book"></i> Courses</a>
            <a href="course-enrollments.php"><i class="fas fa-graduation-cap"></i> Enrollments</a>
            <a href="internships.php"><i class="fas fa-briefcase"></i> Internships</a>
            <a href="internship-applications.php"><i class="fas fa-file-alt"></i> Applications</a>
            <a href="certificates.php"><i class="fas fa-award"></i> Certificates</a>
            <a href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div>
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="welcome">
                    <h2>Student Details</h2>
                    <p>View complete student information</p>
                </div>
            </div>
        </div>

        <!-- Back Button -->
        <a href="students.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Students
        </a>

        <!-- Profile Header -->
        <div class="profile-header">
            <img src="<?php echo ASSETS_URL; ?>uploads/profiles/<?php echo htmlspecialchars($student['profile_pic']); ?>" class="profile-avatar" alt="Profile Picture">
            <div class="profile-info">
                <h3><?php echo htmlspecialchars($student['full_name']); ?></h3>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></p>
                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($student['phone'] ?: 'Not provided'); ?></p>
                <p><i class="fas fa-id-card"></i> CNIC: <?php echo htmlspecialchars($student['cnic'] ?: 'Not provided'); ?></p>
                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($student['address'] ?: 'Not provided'); ?></p>
                <p><i class="fas fa-calendar-alt"></i> Joined: <?php echo date('F d, Y', strtotime($student['created_at'])); ?></p>
                <p>
                    <i class="fas fa-toggle-on"></i> Status: 
                    <span class="status-badge status-<?php echo $student['status']; ?>">
                        <?php echo ucfirst($student['status']); ?>
                    </span>
                </p>
            </div>
        </div>

        <!-- Enrolled Courses -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-graduation-cap"></i> Enrolled Courses</h3>
                <span class="enroll-status enroll-approved">Total: <?php echo count($enrolled_courses); ?></span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Course Name</th>
                            <th>Duration</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Applied On</th>
                            <th>Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($enrolled_courses) > 0): ?>
                            <?php foreach($enrolled_courses as $course): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                                    <td><?php echo htmlspecialchars($course['duration']); ?></td>
                                    <td>Rs. <?php echo number_format($course['fee']); ?></td>
                                    <td>
                                        <span class="enroll-status enroll-<?php echo $course['status']; ?>">
                                            <?php echo ucfirst($course['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($course['applied_at'])); ?></td>
                                    <td class="rating-stars">
                                        <?php
                                        $avg = round($course['avg_rating'], 1);
                                        $full = floor($avg);
                                        for($i=1;$i<=5;$i++){
                                            if($i<=$full) echo '<i class="fas fa-star"></i>';
                                            else echo '<i class="far fa-star"></i>';
                                        }
                                        ?>
                                        <?php if($avg > 0): ?>
                                            (<?php echo $avg; ?>)
                                        <?php else: ?>
                                            (No ratings)
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-results">No courses enrolled</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Internship Applications -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-briefcase"></i> Internship Applications</h3>
                <span class="enroll-status enroll-approved">Total: <?php echo count($applications); ?></span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Internship</th>
                            <th>Company</th>
                            <th>Location</th>
                            <th>Stipend</th>
                            <th>Status</th>
                            <th>Applied On</th>
                            <th>Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($applications) > 0): ?>
                            <?php foreach($applications as $app): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($app['title']); ?></td>
                                    <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['location']); ?></td>
                                    <td><?php echo htmlspecialchars($app['stipend']); ?></td>
                                    <td>
                                        <span class="enroll-status enroll-<?php echo $app['status']; ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                    <td class="rating-stars">
                                        <?php
                                        $avg = round($app['avg_rating'], 1);
                                        $full = floor($avg);
                                        for($i=1;$i<=5;$i++){
                                            if($i<=$full) echo '<i class="fas fa-star"></i>';
                                            else echo '<i class="far fa-star"></i>';
                                        }
                                        ?>
                                        <?php if($avg > 0): ?>
                                            (<?php echo $avg; ?>)
                                        <?php else: ?>
                                            (No ratings)
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="no-results">No internship applications</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Ratings Given by Student -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-star"></i> Ratings Given by Student</h3>
                <span class="enroll-status enroll-approved">Total: <?php echo count($given_ratings); ?></span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Rating</th>
                            <th>Review</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($given_ratings) > 0): ?>
                            <?php foreach($given_ratings as $rating): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rating['course_title']); ?></td>
                                    <td class="rating-stars">
                                        <?php
                                        for($i=1;$i<=5;$i++){
                                            if($i<=$rating['rating']) echo '<i class="fas fa-star"></i>';
                                            else echo '<i class="far fa-star"></i>';
                                        }
                                        ?>
                                        (<?php echo $rating['rating']; ?>/5)
                                    </td>
                                    <td><?php echo htmlspecialchars($rating['review'] ?: 'No review'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($rating['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="no-results">No ratings given by this student</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-cog"></i> Quick Actions</h3>
            </div>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <?php if($student['status'] == 'active'): ?>
                    <a href="students.php?action=suspend&id=<?php echo $student['id']; ?>" class="btn-back" style="background: rgba(255,68,68,0.2); border-color: var(--danger); color: var(--danger);" onclick="return confirm('Suspend this student?')">
                        <i class="fas fa-ban"></i> Suspend Student
                    </a>
                <?php else: ?>
                    <a href="students.php?action=activate&id=<?php echo $student['id']; ?>" class="btn-back" style="background: rgba(0,255,136,0.2); border-color: var(--success); color: var(--success);" onclick="return confirm('Activate this student?')">
                        <i class="fas fa-check-circle"></i> Activate Student
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    
    if(mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const isMobile = window.innerWidth <= 768;
        if (isMobile && sidebar && sidebar.classList.contains('active')) {
            const isClickInside = sidebar.contains(event.target) || mobileMenuBtn.contains(event.target);
            if (!isClickInside) {
                sidebar.classList.remove('active');
            }
        }
    });
    
    // Close sidebar on window resize if desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && sidebar) {
            sidebar.classList.remove('active');
        }
    });
</script>
</body>
</html>