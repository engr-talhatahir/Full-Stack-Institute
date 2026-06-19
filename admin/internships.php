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

// Handle status toggle (open/closed)
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $internship_id = intval($_GET['id']);
    $new_status = $_GET['toggle'] == 'open' ? 'open' : 'closed';
    
    $stmt = $conn->prepare("UPDATE internships SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $internship_id);
    if ($stmt->execute()) {
        $message = "Internship status updated successfully!";
    } else {
        $error = "Failed to update status";
    }
    $stmt->close();
}

// Handle delete internship
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $internship_id = intval($_GET['delete']);
    
    // Check if internship has applications
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM internship_applications WHERE internship_id = ?");
    $check_stmt->bind_param("i", $internship_id);
    $check_stmt->execute();
    $applications = $check_stmt->get_result()->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($applications > 0) {
        $error = "Cannot delete internship. It has " . $applications . " application(s).";
    } else {
        $stmt = $conn->prepare("DELETE FROM internships WHERE id = ?");
        $stmt->bind_param("i", $internship_id);
        if ($stmt->execute()) {
            $message = "Internship deleted successfully!";
        } else {
            $error = "Failed to delete internship";
        }
        $stmt->close();
    }
}

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query
$sql = "SELECT i.*, 
        (SELECT COUNT(*) FROM internship_applications WHERE internship_id = i.id) as applications_count,
        (SELECT AVG(rating) FROM internship_ratings WHERE internship_id = i.id) as avg_rating,
        (SELECT COUNT(*) FROM internship_ratings WHERE internship_id = i.id) as rating_count
        FROM internships i WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (i.title LIKE '%$search%' OR i.company_name LIKE '%$search%' OR i.description LIKE '%$search%')";
}
if ($status_filter != 'all') {
    $sql .= " AND i.status = '$status_filter'";
}
$sql .= " ORDER BY i.created_at DESC";

$result = $conn->query($sql);

// Get counts for status filter
$total_open = 0;
$total_closed = 0;
$count_result = $conn->query("SELECT status, COUNT(*) as count FROM internships GROUP BY status");
if ($count_result && $count_result->num_rows > 0) {
    while ($row = $count_result->fetch_assoc()) {
        if ($row['status'] == 'open') $total_open = $row['count'];
        if ($row['status'] == 'closed') $total_closed = $row['count'];
    }
}
$total_internships = $total_open + $total_closed;

$pageTitle = "Manage Internships - ITsimplera";
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
        
        .btn-add {
            background: var(--neon-green);
            color: #0a0a0a;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            transition: 0.3s;
        }
        
        .btn-add:hover {
            background: var(--neon-green-dark);
            transform: translateY(-2px);
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
        
        .status-open {
            background: rgba(0,255,136,0.15);
            border: 1px solid rgba(0,255,136,0.3);
            color: var(--success);
        }
        
        .status-closed {
            background: rgba(255,68,68,0.15);
            border: 1px solid rgba(255,68,68,0.3);
            color: var(--danger);
        }
        
        .rating-stars i {
            color: var(--star-color);
            font-size: 0.7rem;
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
        }
        
        .btn-edit {
            background: rgba(0,204,255,0.15);
            border: 1px solid rgba(0,204,255,0.3);
            color: var(--info);
        }
        
        .btn-edit:hover {
            background: rgba(0,204,255,0.3);
            transform: translateY(-2px);
        }
        
        .btn-open {
            background: rgba(0,255,136,0.15);
            border: 1px solid rgba(0,255,136,0.3);
            color: var(--success);
        }
        
        .btn-open:hover {
            background: rgba(0,255,136,0.3);
            transform: translateY(-2px);
        }
        
        .btn-close {
            background: rgba(255,68,68,0.15);
            border: 1px solid rgba(255,68,68,0.3);
            color: var(--danger);
        }
        
        .btn-close:hover {
            background: rgba(255,68,68,0.3);
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background: rgba(255,68,68,0.15);
            border: 1px solid rgba(255,68,68,0.3);
            color: var(--danger);
        }
        
        .btn-delete:hover {
            background: rgba(255,68,68,0.3);
            transform: translateY(-2px);
        }
        
        .btn-applications {
            background: rgba(0,204,255,0.15);
            border: 1px solid rgba(0,204,255,0.3);
            color: var(--info);
        }
        
        .btn-applications:hover {
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
        
        .no-results {
            text-align: center;
            padding: 50px;
            color: var(--text-gray);
        }
        
        .deadline-passed {
            color: var(--danger);
            font-size: 0.7rem;
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
            <h3>ITsimplera</h3>
            <p style="font-size: 0.7rem; color: var(--text-gray);">Admin Panel</p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="students.php"><i class="fas fa-users"></i> Students</a>
            <a href="courses.php"><i class="fas fa-book"></i> Courses</a>
            <a href="course-enrollments.php"><i class="fas fa-graduation-cap"></i> Enrollments</a>
            <a href="internships.php" class="active"><i class="fas fa-briefcase"></i> Internships</a>
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
                    <h2>Manage Internships</h2>
                    <p>Add, edit, open/close or delete internships</p>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card-mini">
                <h4><?php echo $total_internships; ?></h4>
                <p><i class="fas fa-briefcase"></i> Total Internships</p>
            </div>
            <div class="stat-card-mini">
                <h4><?php echo $total_open; ?></h4>
                <p><i class="fas fa-door-open"></i> Open</p>
            </div>
            <div class="stat-card-mini">
                <h4><?php echo $total_closed; ?></h4>
                <p><i class="fas fa-door-closed"></i> Closed</p>
            </div>
        </div>

        <!-- Add Internship Button -->
        <a href="add-internship.php" class="btn-add">
            <i class="fas fa-plus-circle"></i> Add New Internship
        </a>

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
                    <input type="text" name="search" placeholder="Search by title, company or description..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Status</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="open" <?php echo $status_filter == 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                    <a href="internships.php" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>

        <!-- Internships Table -->
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Company</th>
                        <th>Location</th>
                        <th>Stipend</th>
                        <th>Duration</th>
                        <th>Deadline</th>
                        <th>Slots</th>
                        <th>Applied</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($internship = $result->fetch_assoc()): ?>
                            <?php
                            $available_slots = $internship['total_slots'] - $internship['applied_count'];
                            $deadline_passed = strtotime($internship['deadline']) < time();
                            ?>
                            <tr>
                                <td>#<?php echo $internship['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($internship['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($internship['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($internship['location']); ?></td>
                                <td><?php echo htmlspecialchars($internship['stipend']); ?></td>
                                <td><?php echo htmlspecialchars($internship['duration']); ?></td>
                                <td class="<?php echo $deadline_passed ? 'deadline-passed' : ''; ?>">
                                    <?php echo date('M d, Y', strtotime($internship['deadline'])); ?>
                                    <?php if($deadline_passed): ?>
                                        <br><small>(Passed)</small>
                                    <?php endif; ?>
                                 </div>
                                <td><?php echo $internship['total_slots']; ?> / <?php echo $available_slots; ?> left</div>
                                <td><?php echo $internship['applications_count']; ?></td>
                                <td class="rating-stars">
                                    <?php
                                    $avg = round($internship['avg_rating'], 1);
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
                                 </div>
                                <td>
                                    <span class="status-badge status-<?php echo $internship['status']; ?>">
                                        <?php echo ucfirst($internship['status']); ?>
                                    </span>
                                 </div>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit-internship.php?id=<?php echo $internship['id']; ?>" class="btn-icon btn-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="internship-applications.php?internship=<?php echo $internship['id']; ?>" class="btn-icon btn-applications">
                                            <i class="fas fa-users"></i> Apps (<?php echo $internship['applications_count']; ?>)
                                        </a>
                                        <?php if($internship['status'] == 'open'): ?>
                                            <a href="?toggle=closed&id=<?php echo $internship['id']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="btn-icon btn-close" onclick="return confirm('Close this internship? Students won\'t be able to apply.')">
                                                <i class="fas fa-lock"></i> Close
                                            </a>
                                        <?php else: ?>
                                            <a href="?toggle=open&id=<?php echo $internship['id']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="btn-icon btn-open" onclick="return confirm('Open this internship? Students will be able to apply.')">
                                                <i class="fas fa-unlock"></i> Open
                                            </a>
                                        <?php endif; ?>
                                        <a href="?delete=<?php echo $internship['id']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="btn-icon btn-delete" onclick="return confirm('Are you sure you want to delete this internship? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                 </div>
                             </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="no-results">
                                <i class="fas fa-briefcase" style="font-size: 3rem; opacity: 0.5; margin-bottom: 15px; display: block;"></i>
                                No internships found
                                <br>
                                <a href="add-internship.php" class="btn-add" style="margin-top: 15px; display: inline-block;">Add Your First Internship</a>
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