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

$message = '';
$error = '';

// Handle approve/reject enrollment
if (isset($_GET['action']) && isset($_GET['id'])) {
    $enrollment_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action == 'approve') {
        $status = 'approved';
    } elseif ($action == 'reject') {
        $status = 'rejected';
    } else {
        $error = 'Invalid action';
    }
    
    if (empty($error)) {
        // Get course_id for this enrollment
        $get_stmt = $conn->prepare("SELECT course_id FROM course_enrollments WHERE id = ?");
        $get_stmt->bind_param("i", $enrollment_id);
        $get_stmt->execute();
        $enrollment_data = $get_stmt->get_result()->fetch_assoc();
        $get_stmt->close();
        
        if ($enrollment_data) {
            $course_id = $enrollment_data['course_id'];
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Update enrollment status
                $update_stmt = $conn->prepare("UPDATE course_enrollments SET status = ? WHERE id = ?");
                $update_stmt->bind_param("si", $status, $enrollment_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                // If approving, update enrolled_seats in courses table
                if ($status == 'approved') {
                    $seat_stmt = $conn->prepare("UPDATE courses SET enrolled_seats = enrolled_seats + 1 WHERE id = ?");
                    $seat_stmt->bind_param("i", $course_id);
                    $seat_stmt->execute();
                    $seat_stmt->close();
                }
                
                $conn->commit();
                $message = "Enrollment " . ($status == 'approved' ? "approved" : "rejected") . " successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to update enrollment";
            }
        } else {
            $error = "Enrollment not found";
        }
    }
}

// Get filter parameters - preserve after action
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$course_filter = isset($_GET['course']) ? intval($_GET['course']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query - show all statuses when 'all' is selected, otherwise filter
$sql = "SELECT ce.*, u.full_name, u.email, u.phone, c.title as course_title, c.fee, c.duration,
        (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id AND status = 'approved') as enrolled_count
        FROM course_enrollments ce 
        JOIN users u ON ce.student_id = u.id 
        JOIN courses c ON ce.course_id = c.id 
        WHERE 1=1";

if ($status_filter != 'all') {
    $sql .= " AND ce.status = '$status_filter'";
}
if ($course_filter > 0) {
    $sql .= " AND ce.course_id = $course_filter";
}
if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE '%$search%' OR u.email LIKE '%$search%' OR c.title LIKE '%$search%')";
}
$sql .= " ORDER BY ce.applied_at DESC";

$result = $conn->query($sql);

// Get counts for status filter
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;
$count_result = $conn->query("SELECT status, COUNT(*) as count FROM course_enrollments GROUP BY status");
if ($count_result && $count_result->num_rows > 0) {
    while ($row = $count_result->fetch_assoc()) {
        if ($row['status'] == 'pending') $pending_count = $row['count'];
        if ($row['status'] == 'approved') $approved_count = $row['count'];
        if ($row['status'] == 'rejected') $rejected_count = $row['count'];
    }
}
$total_enrollments = $pending_count + $approved_count + $rejected_count;

// Get courses for filter dropdown
$courses = array();
$course_result = $conn->query("SELECT id, title FROM courses ORDER BY title");
if ($course_result && $course_result->num_rows > 0) {
    while ($row = $course_result->fetch_assoc()) {
        $courses[] = $row;
    }
}

$pageTitle = "Course Enrollments - ITsimplera";
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
            --bg-card: rgba(255,255,255,0.05);
            --neon-green: #00ff88;
            --neon-green-dark: #00cc6a;
            --text-white: #ffffff;
            --text-gray: #a0a0a0;
            --border-light: rgba(255,255,255,0.08);
            --success: #00ff88;
            --danger: #ff4444;
            --warning: #ffaa00;
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
            background: rgba(255,255,255,0.05);
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
        
        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .stat-card-mini {
            background: linear-gradient(135deg, rgba(0,255,136,0.08), rgba(0,255,136,0.02));
            border: 1px solid rgba(0,255,136,0.2);
            border-radius: 16px;
            padding: 18px 25px;
            flex: 1;
            min-width: 140px;
            text-align: center;
            transition: 0.3s;
        }
        
        .stat-card-mini:hover {
            transform: translateY(-3px);
            border-color: var(--neon-green);
        }
        
        .stat-card-mini h4 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--neon-green);
            margin-bottom: 8px;
        }
        
        .stat-card-mini p {
            font-size: 0.8rem;
            color: var(--text-gray);
        }
        
        .filter-section {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 160px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.75rem;
            color: var(--text-gray);
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-light);
            border-radius: 10px;
            color: var(--text-white);
            font-size: 0.85rem;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--neon-green);
        }
        
        .btn-filter {
            padding: 12px 24px;
            background: var(--neon-green);
            color: #0a0a0a;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }
        
        .btn-filter:hover {
            background: var(--neon-green-dark);
        }
        
        .btn-reset {
            padding: 12px 24px;
            background: transparent;
            border: 1px solid var(--border-light);
            color: var(--text-gray);
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: 0.3s;
        }
        
        .btn-reset:hover {
            border-color: var(--neon-green);
            color: var(--neon-green);
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
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }
        
        th {
            color: var(--text-gray);
            font-weight: 500;
            background: rgba(255,255,255,0.02);
        }
        
        tr:hover {
            background: rgba(255,255,255,0.02);
        }
        
        .status-badge {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending {
            background: rgba(255,170,0,0.15);
            border: 1px solid rgba(255,170,0,0.3);
            color: var(--warning);
        }
        
        .status-approved {
            background: rgba(0,255,136,0.15);
            border: 1px solid rgba(0,255,136,0.3);
            color: var(--success);
        }
        
        .status-rejected {
            background: rgba(255,68,68,0.15);
            border: 1px solid rgba(255,68,68,0.3);
            color: var(--danger);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-icon {
            padding: 6px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
            font-weight: 500;
            cursor: pointer;
            border: none;
        }
        
        .btn-approve {
            background: rgba(0,255,136,0.15);
            border: 1px solid rgba(0,255,136,0.3);
            color: var(--success);
        }
        
        .btn-approve:hover {
            background: rgba(0,255,136,0.3);
            transform: translateY(-2px);
        }
        
        .btn-reject {
            background: rgba(255,68,68,0.15);
            border: 1px solid rgba(255,68,68,0.3);
            color: var(--danger);
        }
        
        .btn-reject:hover {
            background: rgba(255,68,68,0.3);
            transform: translateY(-2px);
        }
        
        .btn-view {
            background: rgba(0,204,255,0.15);
            border: 1px solid rgba(0,204,255,0.3);
            color: var(--info);
        }
        
        .btn-view:hover {
            background: rgba(0,204,255,0.3);
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }
        
        .alert-success {
            background: rgba(0,255,136,0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }
        
        .alert-danger {
            background: rgba(255,68,68,0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        
        .info-message {
            background: rgba(0,204,255,0.1);
            border: 1px solid var(--info);
            border-radius: 10px;
            padding: 12px 18px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: var(--info);
        }
        
        .no-results {
            text-align: center;
            padding: 50px;
            color: var(--text-gray);
        }
        
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
            .filter-form {
                flex-direction: column;
            }
            .filter-group {
                width: 100%;
            }
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            .btn-icon {
                justify-content: center;
            }
            th, td {
                padding: 10px 8px;
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-row {
                flex-direction: column;
            }
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            .user-info {
                align-self: flex-end;
            }
        }
    </style>
</head>
<body>
<div class="bg-pattern"></div>

<div class="dashboard-container">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>ITSimplera.Institute</h3>
            <p style="font-size: 0.7rem; color: var(--text-gray);">Admin Panel</p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="students.php"><i class="fas fa-users"></i> Students</a>
            <a href="courses.php"><i class="fas fa-book"></i> Courses</a>
            <a href="course-enrollments.php" class="active"><i class="fas fa-graduation-cap"></i> Enrollments</a>
            <a href="internships.php"><i class="fas fa-briefcase"></i> Internships</a>
            <a href="internship-applications.php"><i class="fas fa-file-alt"></i> Applications</a>
            <a href="certificates.php"><i class="fas fa-award"></i> Certificates</a>
            <a href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="welcome">
                    <h2>Course Enrollments</h2>
                    <p>Approve or reject student enrollment requests</p>
                </div>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-card-mini">
                <h4><?php echo $total_enrollments; ?></h4>
                <p><i class="fas fa-users"></i> Total</p>
            </div>
            <div class="stat-card-mini">
                <h4><?php echo $pending_count; ?></h4>
                <p><i class="fas fa-clock"></i> Pending</p>
            </div>
            <div class="stat-card-mini">
                <h4><?php echo $approved_count; ?></h4>
                <p><i class="fas fa-check-circle"></i> Approved</p>
            </div>
            <div class="stat-card-mini">
                <h4><?php echo $rejected_count; ?></h4>
                <p><i class="fas fa-times-circle"></i> Rejected</p>
            </div>
        </div>

        <?php if($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($status_filter == 'pending' && $pending_count == 0 && $total_enrollments > 0): ?>
            <div class="info-message">
                <i class="fas fa-info-circle"></i> No pending enrollments. 
                <a href="?status=approved" style="color: var(--info);">View Approved</a> or 
                <a href="?status=all" style="color: var(--info);">View All</a>
            </div>
        <?php endif; ?>

        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" placeholder="Student name, email or course..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Status</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-book"></i> Course</label>
                    <select name="course">
                        <option value="0">All Courses</option>
                        <?php foreach($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                    <a href="course-enrollments.php" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Fee</th>
                        <th>Duration</th>
                        <th>Applied On</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($enrollment = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $enrollment['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($enrollment['full_name']); ?></strong><br>
                                    <small style="color: var(--text-gray);"><?php echo htmlspecialchars($enrollment['email']); ?></small>
                                 </div>
                                <td>
                                    <strong><?php echo htmlspecialchars($enrollment['course_title']); ?></strong>
                                 </div>
                                <td>Rs. <?php echo number_format($enrollment['fee']); ?></td>
                                <td><?php echo htmlspecialchars($enrollment['duration']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($enrollment['applied_at'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $enrollment['status']; ?>">
                                        <?php echo ucfirst($enrollment['status']); ?>
                                    </span>
                                 </div>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view-student.php?id=<?php echo $enrollment['student_id']; ?>" class="btn-icon btn-view">
                                            <i class="fas fa-user"></i> View Student
                                        </a>
                                        <?php if($enrollment['status'] == 'pending'): ?>
                                            <a href="?action=approve&id=<?php echo $enrollment['id']; ?>&status=<?php echo $status_filter; ?>&course=<?php echo $course_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-icon btn-approve" onclick="return confirm('Approve this enrollment?')">
                                                <i class="fas fa-check"></i> Approve
                                            </a>
                                            <a href="?action=reject&id=<?php echo $enrollment['id']; ?>&status=<?php echo $status_filter; ?>&course=<?php echo $course_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-icon btn-reject" onclick="return confirm('Reject this enrollment?')">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                 </div>
                             </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-results">
                                <i class="fas fa-graduation-cap" style="font-size: 3rem; opacity: 0.5; margin-bottom: 15px; display: block;"></i>
                                <?php if($status_filter == 'pending'): ?>
                                    No pending enrollment requests
                                <?php elseif($status_filter == 'approved'): ?>
                                    No approved enrollments
                                <?php elseif($status_filter == 'rejected'): ?>
                                    No rejected enrollments
                                <?php else: ?>
                                    No enrollment requests found
                                <?php endif; ?>
                             </div>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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