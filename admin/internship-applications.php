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

// Handle status update (shortlist, select, reject)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $application_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action == 'shortlist') {
        $status = 'shortlisted';
    } elseif ($action == 'select') {
        $status = 'selected';
    } elseif ($action == 'reject') {
        $status = 'rejected';
    } else {
        $error = 'Invalid action';
    }
    
    if (empty($error)) {
        $stmt = $conn->prepare("UPDATE internship_applications SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $application_id);
        if ($stmt->execute()) {
            $message = "Application " . $status . " successfully!";
        } else {
            $error = "Failed to update status";
        }
        $stmt->close();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$internship_filter = isset($_GET['internship']) ? intval($_GET['internship']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT ia.*, u.full_name, u.email, u.phone, u.profile_pic, i.title as internship_title, i.company_name, i.location, i.deadline 
        FROM internship_applications ia 
        JOIN users u ON ia.student_id = u.id 
        JOIN internships i ON ia.internship_id = i.id 
        WHERE 1=1";

if ($status_filter != 'all') {
    $sql .= " AND ia.status = '$status_filter'";
}
if ($internship_filter > 0) {
    $sql .= " AND ia.internship_id = $internship_filter";
}
if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE '%$search%' OR u.email LIKE '%$search%' OR i.title LIKE '%$search%')";
}
$sql .= " ORDER BY ia.applied_at DESC";

$result = $conn->query($sql);

// Get counts for status filter
$pending_count = 0;
$shortlisted_count = 0;
$selected_count = 0;
$rejected_count = 0;
$count_result = $conn->query("SELECT status, COUNT(*) as count FROM internship_applications GROUP BY status");
if ($count_result && $count_result->num_rows > 0) {
    while ($row = $count_result->fetch_assoc()) {
        if ($row['status'] == 'pending') $pending_count = $row['count'];
        if ($row['status'] == 'shortlisted') $shortlisted_count = $row['count'];
        if ($row['status'] == 'selected') $selected_count = $row['count'];
        if ($row['status'] == 'rejected') $rejected_count = $row['count'];
    }
}
$total_applications = $pending_count + $shortlisted_count + $selected_count + $rejected_count;

// Get internships for filter dropdown
$internships = array();
$internship_result = $conn->query("SELECT id, title FROM internships ORDER BY title");
if ($internship_result && $internship_result->num_rows > 0) {
    while ($row = $internship_result->fetch_assoc()) {
        $internships[] = $row;
    }
}

// Get current internship name if filtered
$current_internship_name = '';
if ($internship_filter > 0) {
    $name_stmt = $conn->prepare("SELECT title FROM internships WHERE id = ?");
    $name_stmt->bind_param("i", $internship_filter);
    $name_stmt->execute();
    $name_result = $name_stmt->get_result();
    if ($name_row = $name_result->fetch_assoc()) {
        $current_internship_name = $name_row['title'];
    }
    $name_stmt->close();
}

$pageTitle = "Internship Applications - ITsimplera";
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
        
        .status-shortlisted {
            background: rgba(0,204,255,0.15);
            border: 1px solid rgba(0,204,255,0.3);
            color: var(--info);
        }
        
        .status-selected {
            background: rgba(0,255,136,0.15);
            border: 1px solid rgba(0,255,136,0.3);
            color: var(--success);
        }
        
        .status-rejected {
            background: rgba(255,68,68,0.15);
            border: 1px solid rgba(255,68,68,0.3);
            color: var(--danger);
        }
        
        .student-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            vertical-align: middle;
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
        
        .btn-shortlist {
            background: rgba(0,204,255,0.15);
            border: 1px solid rgba(0,204,255,0.3);
            color: var(--info);
        }
        
        .btn-shortlist:hover {
            background: rgba(0,204,255,0.3);
            transform: translateY(-2px);
        }
        
        .btn-select {
            background: rgba(0,255,136,0.15);
            border: 1px solid rgba(0,255,136,0.3);
            color: var(--success);
        }
        
        .btn-select:hover {
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
        
        .btn-download {
            background: rgba(0,204,255,0.15);
            border: 1px solid rgba(0,204,255,0.3);
            color: var(--info);
            text-decoration: none;
        }
        
        .btn-download:hover {
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
        
        .cover-letter-preview {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>ITSimplera.Institute</h3>
            <p style="font-size: 0.7rem; color: var(--text-gray);">Admin Panel</p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="students.php"><i class="fas fa-users"></i> Students</a>
            <a href="courses.php"><i class="fas fa-book"></i> Courses</a>
            <a href="course-enrollments.php"><i class="fas fa-graduation-cap"></i> Enrollments</a>
            <a href="internships.php"><i class="fas fa-briefcase"></i> Internships</a>
            <a href="internship-applications.php" class="active"><i class="fas fa-file-alt"></i> Applications</a>
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
                    <h2>Internship Applications</h2>
                    <p>Manage student internship applications</p>
                    <?php if($current_internship_name): ?>
                        <p style="font-size: 0.8rem; color: var(--neon-green);">
                            <i class="fas fa-briefcase"></i> Currently viewing: <?php echo htmlspecialchars($current_internship_name); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card-mini">
                <h4><?php echo $total_applications; ?></h4>
                <p><i class="fas fa-file-alt"></i> Total</p>
            </div>
            <div class="stat-card-mini">
                <h4><?php echo $pending_count; ?></h4>
                <p><i class="fas fa-clock"></i> Pending</p>
            </div>
            <div class="stat-card-mini">
                <h4><?php echo $shortlisted_count; ?></h4>
                <p><i class="fas fa-star"></i> Shortlisted</p>
            </div>
            <div class="stat-card-mini">
                <h4><?php echo $selected_count; ?></h4>
                <p><i class="fas fa-trophy"></i> Selected</p>
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
        
        <?php if($status_filter == 'pending' && $pending_count == 0 && $total_applications > 0): ?>
            <div class="info-message">
                <i class="fas fa-info-circle"></i> No pending applications. 
                <a href="?status=shortlisted" style="color: var(--info);">View Shortlisted</a> or 
                <a href="?status=all" style="color: var(--info);">View All</a>
            </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" placeholder="Student name, email or internship..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Status</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="shortlisted" <?php echo $status_filter == 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                        <option value="selected" <?php echo $status_filter == 'selected' ? 'selected' : ''; ?>>Selected</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-briefcase"></i> Internship</label>
                    <select name="internship">
                        <option value="0">All Internships</option>
                        <?php foreach($internships as $internship): ?>
                            <option value="<?php echo $internship['id']; ?>" <?php echo $internship_filter == $internship['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($internship['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                    <a href="internship-applications.php" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>

        <!-- Applications Table -->
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Internship</th>
                        <th>Company</th>
                        <th>Cover Letter</th>
                        <th>Resume</th>
                        <th>Applied On</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($application = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $application['id']; ?></td>
                                <td>
                                    <img src="<?php echo ASSETS_URL; ?>uploads/profiles/<?php echo htmlspecialchars($application['profile_pic']); ?>" class="student-avatar" alt="Avatar">
                                    <strong><?php echo htmlspecialchars($application['full_name']); ?></strong><br>
                                    <small style="color: var(--text-gray);"><?php echo htmlspecialchars($application['email']); ?></small>
                                 </div>
                                <td>
                                    <strong><?php echo htmlspecialchars($application['internship_title']); ?></strong><br>
                                    <small style="color: var(--text-gray);"><?php echo htmlspecialchars($application['company_name']); ?></small>
                                 </div>
                                <td><?php echo htmlspecialchars($application['company_name']); ?></div>
                                <td class="cover-letter-preview">
                                    <?php if(!empty($application['cover_letter'])): ?>
                                        <?php echo substr(htmlspecialchars($application['cover_letter']), 0, 50); ?>...
                                    <?php else: ?>
                                        <em style="color: var(--text-gray);">No cover letter</em>
                                    <?php endif; ?>
                                 </div>
                                <td>
                                    <?php if(!empty($application['resume_path'])): ?>
                                        <a href="<?php echo ASSETS_URL; ?>uploads/resumes/<?php echo htmlspecialchars($application['resume_path']); ?>" class="btn-icon btn-download" target="_blank" style="text-decoration: none;">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--text-gray);">No resume</span>
                                    <?php endif; ?>
                                 </div>
                                <td><?php echo date('M d, Y', strtotime($application['applied_at'])); ?> </div>
                                <td>
                                    <span class="status-badge status-<?php echo $application['status']; ?>">
                                        <?php echo ucfirst($application['status']); ?>
                                    </span>
                                 </div>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view-student.php?id=<?php echo $application['student_id']; ?>" class="btn-icon btn-view">
                                            <i class="fas fa-user"></i> View Student
                                        </a>
                                        <?php if($application['status'] == 'pending'): ?>
                                            <a href="?action=shortlist&id=<?php echo $application['id']; ?>&status=<?php echo $status_filter; ?>&internship=<?php echo $internship_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-icon btn-shortlist" onclick="return confirm('Shortlist this application?')">
                                                <i class="fas fa-star"></i> Shortlist
                                            </a>
                                            <a href="?action=reject&id=<?php echo $application['id']; ?>&status=<?php echo $status_filter; ?>&internship=<?php echo $internship_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-icon btn-reject" onclick="return confirm('Reject this application?')">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                        <?php elseif($application['status'] == 'shortlisted'): ?>
                                            <a href="?action=select&id=<?php echo $application['id']; ?>&status=<?php echo $status_filter; ?>&internship=<?php echo $internship_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-icon btn-select" onclick="return confirm('Select this candidate?')">
                                                <i class="fas fa-check-circle"></i> Select
                                            </a>
                                            <a href="?action=reject&id=<?php echo $application['id']; ?>&status=<?php echo $status_filter; ?>&internship=<?php echo $internship_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-icon btn-reject" onclick="return confirm('Reject this application?')">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                        <?php elseif($application['status'] == 'selected'): ?>
                                            <span class="status-badge status-selected">
                                                <i class="fas fa-check"></i> Selected
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                 </div>
                             </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-results">
                                <i class="fas fa-file-alt" style="font-size: 3rem; opacity: 0.5; margin-bottom: 15px; display: block;"></i>
                                <?php if($status_filter == 'pending'): ?>
                                    No pending applications
                                <?php elseif($status_filter == 'shortlisted'): ?>
                                    No shortlisted applications
                                <?php elseif($status_filter == 'selected'): ?>
                                    No selected applications
                                <?php elseif($status_filter == 'rejected'): ?>
                                    No rejected applications
                                <?php else: ?>
                                    No internship applications found
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