<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

if ($_SESSION['role'] != 'admin') {
    header('Location: ' . BASE_URL . 'student/dashboard.php');
    exit;
}

// Get statistics
// Total students
$total_students = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
if ($result) {
    $total_students = $result->fetch_assoc()['count'];
}

// Total active students
$active_students = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND status = 'active'");
if ($result) {
    $active_students = $result->fetch_assoc()['count'];
}

// Total courses
$total_courses = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM courses");
if ($result) {
    $total_courses = $result->fetch_assoc()['count'];
}

// Total active courses
$active_courses = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM courses WHERE status = 'active'");
if ($result) {
    $active_courses = $result->fetch_assoc()['count'];
}

// Total internships
$total_internships = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM internships");
if ($result) {
    $total_internships = $result->fetch_assoc()['count'];
}

// Total open internships
$open_internships = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM internships WHERE status = 'open'");
if ($result) {
    $open_internships = $result->fetch_assoc()['count'];
}

// Pending enrollments
$pending_enrollments = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM course_enrollments WHERE status = 'pending'");
if ($result) {
    $pending_enrollments = $result->fetch_assoc()['count'];
}

// Pending internship applications
$pending_applications = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM internship_applications WHERE status = 'pending'");
if ($result) {
    $pending_applications = $result->fetch_assoc()['count'];
}

// Total certificates
$total_certificates = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM student_certificates");
if ($result) {
    $total_certificates = $result->fetch_assoc()['count'];
}

// Pending certificates
$pending_certificates = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM student_certificates WHERE status = 'Pending'");
if ($result) {
    $pending_certificates = $result->fetch_assoc()['count'];
}

// Total ratings
$total_ratings = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM course_ratings");
if ($result) {
    $total_ratings = $result->fetch_assoc()['count'];
}
$result2 = $conn->query("SELECT COUNT(*) as count FROM internship_ratings");
if ($result2) {
    $total_ratings += $result2->fetch_assoc()['count'];
}

// Recent enrollments
$recent_enrollments = array();
$sql = "SELECT ce.*, u.full_name, u.email, c.title as course_title 
        FROM course_enrollments ce 
        JOIN users u ON ce.student_id = u.id 
        JOIN courses c ON ce.course_id = c.id 
        ORDER BY ce.applied_at DESC LIMIT 8";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_enrollments[] = $row;
    }
}

// Recent internship applications
$recent_applications = array();
$sql = "SELECT ia.*, u.full_name, u.email, i.title as internship_title 
        FROM internship_applications ia 
        JOIN users u ON ia.student_id = u.id 
        JOIN internships i ON ia.internship_id = i.id 
        ORDER BY ia.applied_at DESC LIMIT 8";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_applications[] = $row;
    }
}

// Top rated courses
$top_courses = array();
$sql = "SELECT c.id, c.title, AVG(cr.rating) as avg_rating, COUNT(cr.id) as rating_count 
        FROM courses c 
        LEFT JOIN course_ratings cr ON c.id = cr.course_id 
        GROUP BY c.id 
        HAVING avg_rating IS NOT NULL 
        ORDER BY avg_rating DESC LIMIT 5";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $top_courses[] = $row;
    }
}

$pageTitle = "Admin Dashboard - ITsimplera";
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
            --warning: #ffaa00;
            --success: #00ff88;
            --danger: #ff4444;
            --info: #00ccff;
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
        
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-dark); }
        ::-webkit-scrollbar-thumb { background: var(--neon-green); border-radius: 10px; }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
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
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px 25px;
            width: calc(100% - 280px);
        }
        
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
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-white);
            cursor: pointer;
            padding: 8px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 15px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--neon-green);
        }
        
        .stat-info h3 {
            font-size: 1.4rem;
            color: var(--neon-green);
            margin-bottom: 5px;
        }
        
        .stat-info p {
            font-size: 0.7rem;
            color: var(--text-gray);
        }
        
        .stat-icon {
            font-size: 1.5rem;
            opacity: 0.5;
        }
        
        .row-2col {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }
        
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
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-light);
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .card-header h3 {
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-view {
            color: var(--neon-green);
            text-decoration: none;
            font-size: 0.8rem;
        }
        
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
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }
        
        th {
            color: var(--text-gray);
            font-weight: 500;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending {
            background: rgba(255,170,0,0.2);
            color: var(--warning);
        }
        
        .status-approved,
        .status-active {
            background: rgba(0,255,136,0.2);
            color: var(--success);
        }
        
        .status-rejected,
        .status-suspended {
            background: rgba(255,68,68,0.2);
            color: var(--danger);
        }
        
        .rating-stars i {
            color: #ffd700;
            font-size: 0.75rem;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .row-2col {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
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
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .stat-card {
                padding: 12px;
            }
            .stat-info h3 {
                font-size: 1.2rem;
            }
            .card {
                padding: 15px;
            }
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            .user-info {
                align-self: flex-end;
            }
            th, td {
                padding: 8px 6px;
                font-size: 0.75rem;
            }
        }
        
        .text-center {
            text-align: center;
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
            <a href="dashboard.php" class="active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="students.php">
                <i class="fas fa-users"></i> Students
            </a>
            <a href="courses.php">
                <i class="fas fa-book"></i> Courses
            </a>
            <a href="course-enrollments.php">
                <i class="fas fa-graduation-cap"></i> Enrollments
            </a>
            <a href="internships.php">
                <i class="fas fa-briefcase"></i> Internships
            </a>
            <a href="internship-applications.php">
                <i class="fas fa-file-alt"></i> Applications
            </a>
            <a href="certificates.php">
                <i class="fas fa-award"></i> Certificates
            </a>
            <a href="<?php echo BASE_URL; ?>logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
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
                    <h2>Admin Dashboard</h2>
                    <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $total_students; ?></h3>
                    <p>Total Students</p>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $active_students; ?></h3>
                    <p>Active Students</p>
                </div>
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $total_courses; ?></h3>
                    <p>Total Courses</p>
                </div>
                <div class="stat-icon"><i class="fas fa-book"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $active_courses; ?></h3>
                    <p>Active Courses</p>
                </div>
                <div class="stat-icon"><i class="fas fa-play-circle"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $total_internships; ?></h3>
                    <p>Total Internships</p>
                </div>
                <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $open_internships; ?></h3>
                    <p>Open Internships</p>
                </div>
                <div class="stat-icon"><i class="fas fa-door-open"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $pending_enrollments; ?></h3>
                    <p>Pending Enrollments</p>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $pending_applications; ?></h3>
                    <p>Pending Applications</p>
                </div>
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $total_certificates; ?></h3>
                    <p>Total Certificates</p>
                </div>
                <div class="stat-icon"><i class="fas fa-award"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $pending_certificates; ?></h3>
                    <p>Pending Certificates</p>
                </div>
                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            </div>
        </div>

        <!-- Row with 2 columns -->
        <div class="row-2col">
            <!-- Recent Enrollments -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-graduation-cap"></i> Recent Enrollments</h3>
                    <a href="course-enrollments.php" class="btn-view">View All →</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Student</th><th>Course</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if(count($recent_enrollments) > 0): ?>
                                <?php foreach($recent_enrollments as $enrollment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($enrollment['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($enrollment['course_title']); ?></td>
                                        <td><span class="status-badge status-<?php echo $enrollment['status']; ?>"><?php echo ucfirst($enrollment['status']); ?></span></td>
                                        <td><?php echo date('M d', strtotime($enrollment['applied_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center">No enrollments yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Internship Applications -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-file-alt"></i> Recent Applications</h3>
                    <a href="internship-applications.php" class="btn-view">View All →</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Student</th><th>Internship</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if(count($recent_applications) > 0): ?>
                                <?php foreach($recent_applications as $application): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($application['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($application['internship_title']); ?></td>
                                        <td><span class="status-badge status-<?php echo $application['status']; ?>"><?php echo ucfirst($application['status']); ?></span></td>
                                        <td><?php echo date('M d', strtotime($application['applied_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <td><td colspan="4" class="text-center">No applications yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Rated Courses -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-star"></i> Top Rated Courses</h3>
                <a href="courses.php" class="btn-view">View All →</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Course Name</th><th>Rating</th><th>Reviews</th></tr>
                    </thead>
                    <tbody>
                        <?php if(count($top_courses) > 0): ?>
                            <?php foreach($top_courses as $course): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                                    <td class="rating-stars">
                                        <?php
                                        $full = floor($course['avg_rating']);
                                        for($i=1;$i<=5;$i++){
                                            if($i<=$full) echo '<i class="fas fa-star"></i>';
                                            else echo '<i class="far fa-star"></i>';
                                        }
                                        ?> <?php echo number_format($course['avg_rating'], 1); ?>
                                    </td>
                                    <td><?php echo $course['rating_count']; ?> reviews</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center">No ratings yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- System Info -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> System Overview</h3>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <tbody>
                        <tr><td style="width: 50%;">Total Ratings Submitted</td><td><?php echo $total_ratings; ?></td></tr>
                        <tr><td>Total Certificates Issued</td><td><?php echo $total_certificates; ?></td></tr>
                        <tr><td>Platform Status</td><td><span class="status-badge status-active">Active</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    var mobileMenuBtn = document.getElementById('mobileMenuBtn');
    var sidebar = document.getElementById('sidebar');
    
    if(mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    document.addEventListener('click', function(event) {
        var isMobile = window.innerWidth <= 768;
        if (isMobile && sidebar && sidebar.classList.contains('active')) {
            var isClickInside = sidebar.contains(event.target) || mobileMenuBtn.contains(event.target);
            if (!isClickInside) {
                sidebar.classList.remove('active');
            }
        }
    });
    
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && sidebar) {
            sidebar.classList.remove('active');
        }
    });
</script>
</body>
</html>