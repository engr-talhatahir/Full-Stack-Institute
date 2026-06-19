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

// Handle status change (activate/suspend)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $student_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action == 'activate') {
        $status = 'active';
    } elseif ($action == 'suspend') {
        $status = 'suspended';
    } else {
        $error = 'Invalid action';
    }
    
    if (empty($error)) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'student'");
        $stmt->bind_param("si", $status, $student_id);
        if ($stmt->execute()) {
            $message = "Student status updated successfully!";
        } else {
            $error = "Failed to update status";
        }
        $stmt->close();
    }
}

// Handle delete student
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $student_id = intval($_GET['delete']);
    
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM course_enrollments WHERE student_id = ?");
    $check_stmt->bind_param("i", $student_id);
    $check_stmt->execute();
    $enrollments = $check_stmt->get_result()->fetch_assoc()['count'];
    $check_stmt->close();
    
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM internship_applications WHERE student_id = ?");
    $check_stmt->bind_param("i", $student_id);
    $check_stmt->execute();
    $applications = $check_stmt->get_result()->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($enrollments > 0 || $applications > 0) {
        $error = "Cannot delete student. They have " . $enrollments . " enrollment(s) and " . $applications . " application(s).";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
        $stmt->bind_param("i", $student_id);
        if ($stmt->execute()) {
            $message = "Student deleted successfully!";
        } else {
            $error = "Failed to delete student";
        }
        $stmt->close();
    }
}

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query
$sql = "SELECT * FROM users WHERE role = 'student'";
if (!empty($search)) {
    $sql .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}
if ($status_filter != 'all') {
    $sql .= " AND status = '$status_filter'";
}
$sql .= " ORDER BY created_at DESC";

$result = $conn->query($sql);

// Get counts for status filter
$total_active = 0;
$total_suspended = 0;
$count_result = $conn->query("SELECT status, COUNT(*) as count FROM users WHERE role = 'student' GROUP BY status");
if ($count_result && $count_result->num_rows > 0) {
    while ($row = $count_result->fetch_assoc()) {
        if ($row['status'] == 'active') $total_active = $row['count'];
        if ($row['status'] == 'suspended') $total_suspended = $row['count'];
    }
}
$total_students = $total_active + $total_suspended;

$pageTitle = "Manage Students - ITsimplera";
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
            --text-white: #d3d3d3;
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
        
        /* Stats Cards - FIXED with better visibility */
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
            background: linear-gradient(135deg, rgba(0,255,136,0.12), rgba(0,255,136,0.04));
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
            letter-spacing: 0.5px;
        }
        
        /* Filter Section */
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
            min-width: 180px;
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
            font-size: 0.85rem;
            transition: 0.3s;
        }
        
        .btn-filter:hover {
            background: var(--neon-green-dark);
            transform: translateY(-2px);
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
            font-size: 0.85rem;
            transition: 0.3s;
        }
        
        .btn-reset:hover {
            border-color: var(--neon-green);
            color: var(--neon-green);
        }
        
        /* Table */
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
        
        /* Status Badges */
        .status-badge {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-active {
            background: rgba(0,255,136,0.15);
            border: 1px solid rgba(0,255,136,0.3);
            color: var(--success);
        }
        
        .status-suspended {
            background: rgba(255,68,68,0.15);
            border: 1px solid rgba(255,68,68,0.3);
            color: var(--danger);
        }
        
        /* Action Buttons - FIXED hover colors */
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
        }
        
        .btn-view {
            background: rgba(0,204,255,0.15);
            border: 1px solid rgba(0,204,255,0.3);
            color: var(--info);
        }
        
        .btn-view:hover {
            background: rgba(0,204,255,0.3);
            transform: translateY(-2px);
            color: #d3d3d3;
        }
        
        .btn-activate {
            background: rgba(0,255,136,0.15);
            border: 1px solid rgba(0,255,136,0.3);
            color: var(--success);
        }
        
        .btn-activate:hover {
            background: rgba(0,255,136,0.3);
            transform: translateY(-2px);
            color: #d3d3d3;
        }
        
        .btn-suspend {
            background: rgba(255,68,68,0.15);
            border: 1px solid rgba(255,68,68,0.3);
            color: var(--danger);
        }
        
        .btn-suspend:hover {
            background: rgba(255,68,68,0.3);
            transform: translateY(-2px);
            color: #d3d3d3;
        }
        
        .btn-delete {
            background: rgba(255,68,68,0.15);
            border: 1px solid rgba(255,68,68,0.3);
            color: var(--danger);
        }
        
        .btn-delete:hover {
            background: rgba(255,68,68,0.3);
            transform: translateY(-2px);
            color: #d3d3d3;
        }
        
        /* Alert Messages */
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
        
        .student-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            vertical-align: middle;
            border: 1px solid var(--border-light);
        }
        
        .no-results {
            text-align: center;
            padding: 50px;
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
            .stat-card-mini h4 {
                font-size: 1.5rem;
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
                    <h2>Manage Students</h2>
                    <p>View, activate, suspend or delete students</p>
                </div>
            </div>
        </div>

        <!-- Stats Cards - Fixed with better visibility -->
        <div class="stats-row">
            <div class="stat-card-mini">
                <h4><?php echo $total_students; ?></h4>
                <p><i class="fas fa-users"></i> Total Students</p>
            </div>
            <div class="stat-card-mini">
                <h4><?php echo $total_active; ?></h4>
                <p><i class="fas fa-check-circle"></i> Active</p>
            </div>
            <div class="stat-card-mini">
                <h4><?php echo $total_suspended; ?></h4>
                <p><i class="fas fa-ban"></i> Suspended</p>
            </div>
        </div>

        <?php if($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" placeholder="Name, email or phone..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Status</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                    <a href="students.php" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>

        <!-- Students Table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($student = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $student['id']; ?></td>
                                <td>
                                    <img src="<?php echo ASSETS_URL; ?>uploads/profiles/<?php echo htmlspecialchars($student['profile_pic']); ?>" class="student-avatar" alt="Avatar">
                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['phone'] ?: '-'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $student['status']; ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view-student.php?id=<?php echo $student['id']; ?>" class="btn-icon btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if($student['status'] == 'active'): ?>
                                            <a href="?action=suspend&id=<?php echo $student['id']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="btn-icon btn-suspend" onclick="return confirm('Are you sure you want to suspend this student?')">
                                                <i class="fas fa-ban"></i> Suspend
                                            </a>
                                        <?php else: ?>
                                            <a href="?action=activate&id=<?php echo $student['id']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="btn-icon btn-activate" onclick="return confirm('Are you sure you want to activate this student?')">
                                                <i class="fas fa-check-circle"></i> Activate
                                            </a>
                                        <?php endif; ?>
                                        <a href="?delete=<?php echo $student['id']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="btn-icon btn-delete" onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-results">
                                <i class="fas fa-users" style="font-size: 3rem; opacity: 0.5; margin-bottom: 15px; display: block;"></i>
                                No students found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    
    if(mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    document.addEventListener('click', function(event) {
        const isMobile = window.innerWidth <= 768;
        if (isMobile && sidebar && sidebar.classList.contains('active')) {
            const isClickInside = sidebar.contains(event.target) || mobileMenuBtn.contains(event.target);
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