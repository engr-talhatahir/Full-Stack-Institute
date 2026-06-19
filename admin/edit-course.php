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

// Get course ID
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($course_id == 0) {
    header('Location: courses.php');
    exit;
}

// Fetch course data
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    header('Location: courses.php');
    exit;
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $duration = trim($_POST['duration']);
        $fee = floatval($_POST['fee']);
        $total_seats = intval($_POST['total_seats']);
        $start_date = trim($_POST['start_date']);
        $end_date = trim($_POST['end_date']);
        $status = $_POST['status'];
        
        // Debug: Print received values
        $debug_info = "Start Date: '$start_date', End Date: '$end_date'";
        
        // Validate dates
        if (empty($start_date) || empty($end_date)) {
            $error = 'Start date and end date are required. ' . $debug_info;
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            $error = 'Start date must be in YYYY-MM-DD format. Received: ' . $start_date;
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            $error = 'End date must be in YYYY-MM-DD format. Received: ' . $end_date;
        } elseif (strtotime($end_date) < strtotime($start_date)) {
            $error = 'End date must be after start date';
        } elseif (empty($title) || empty($description) || empty($duration)) {
            $error = 'Please fill all required fields';
        } elseif ($fee <= 0) {
            $error = 'Fee must be greater than 0';
        } elseif ($total_seats <= 0) {
            $error = 'Total seats must be greater than 0';
        } else {
            // Handle thumbnail upload
            $thumbnail = $course['thumbnail'];
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = '../assets/uploads/thumbnails/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $uploaded_file = uploadFile($_FILES['thumbnail'], $upload_dir, array('jpg', 'jpeg', 'png', 'gif', 'webp'));
                if ($uploaded_file) {
                    // Delete old thumbnail
                    if (!empty($course['thumbnail']) && file_exists($upload_dir . $course['thumbnail'])) {
                        unlink($upload_dir . $course['thumbnail']);
                    }
                    $thumbnail = $uploaded_file;
                } else {
                    $error = "Failed to upload thumbnail. Please upload JPG, PNG or GIF file.";
                }
            }
            
            if (empty($error)) {
                // Direct update without prepared statement for debugging
                $update_sql = "UPDATE courses SET 
                    title = '" . mysqli_real_escape_string($conn, $title) . "', 
                    description = '" . mysqli_real_escape_string($conn, $description) . "', 
                    duration = '" . mysqli_real_escape_string($conn, $duration) . "', 
                    fee = $fee, 
                    total_seats = $total_seats, 
                    start_date = '$start_date', 
                    end_date = '$end_date', 
                    thumbnail = '" . mysqli_real_escape_string($conn, $thumbnail) . "', 
                    status = '$status' 
                    WHERE id = $course_id";
                
                if ($conn->query($update_sql)) {
                    setFlash('success', 'Course updated successfully!');
                    header('Location: courses.php');
                    exit;
                } else {
                    $error = "Failed to update course: " . $conn->error . "<br>SQL: " . $update_sql;
                }
            }
        }
    }
}

$csrf_token = generateCSRFToken();
$pageTitle = "Edit Course - " . $course['title'];

// Fix date formats for display
$start_date_display = date('Y-m-d', strtotime($course['start_date']));
$end_date_display = date('Y-m-d', strtotime($course['end_date']));
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
        
        .form-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .form-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 20px;
            padding: 30px;
        }
        
        .form-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-light);
        }
        
        .form-header i {
            font-size: 2rem;
            color: var(--neon-green);
        }
        
        .form-header h3 {
            font-size: 1.3rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--text-gray);
        }
        
        .form-group label .required {
            color: var(--danger);
            margin-left: 3px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-light);
            border-radius: 10px;
            color: var(--text-white);
            font-size: 0.9rem;
            transition: 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--neon-green);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        .current-thumbnail {
            margin-top: 10px;
            text-align: center;
        }
        
        .current-thumbnail img {
            max-width: 150px;
            max-height: 100px;
            border-radius: 10px;
            border: 2px solid var(--neon-green);
            object-fit: cover;
        }
        
        .thumbnail-preview {
            margin-top: 15px;
            text-align: center;
        }
        
        .thumbnail-preview img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 12px;
            border: 2px solid var(--neon-green);
            object-fit: cover;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--neon-green);
            color: #0a0a0a;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }
        
        .btn-submit:hover {
            background: var(--neon-green-dark);
            transform: translateY(-2px);
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: transparent;
            border: 1px solid var(--border-light);
            border-radius: 10px;
            color: var(--text-gray);
            text-decoration: none;
            margin-bottom: 20px;
            transition: 0.3s;
        }
        
        .btn-back:hover {
            border-color: var(--neon-green);
            color: var(--neon-green);
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
        
        .debug-info {
            background: rgba(255,68,68,0.1);
            border: 1px solid var(--danger);
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 0.8rem;
            word-break: break-all;
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
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .form-card {
                padding: 20px;
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
            <a href="courses.php" class="active"><i class="fas fa-book"></i> Courses</a>
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
                    <h2>Edit Course</h2>
                    <p>Update course information</p>
                </div>
            </div>
        </div>

        <div class="form-container">
            <a href="courses.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Courses
            </a>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <strong>Error:</strong> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="form-card">
                <div class="form-header">
                    <i class="fas fa-edit"></i>
                    <h3>Edit Course: <?php echo htmlspecialchars($course['title']); ?></h3>
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data" id="courseForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label>Course Title <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($course['title']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Description <span class="required">*</span></label>
                        <textarea name="description" class="form-control" required><?php echo htmlspecialchars($course['description']); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Duration <span class="required">*</span></label>
                            <input type="text" name="duration" class="form-control" required value="<?php echo htmlspecialchars($course['duration']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Fee (Rs.) <span class="required">*</span></label>
                            <input type="number" name="fee" class="form-control" required value="<?php echo $course['fee']; ?>" min="0" step="1000">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Total Seats <span class="required">*</span></label>
                            <input type="number" name="total_seats" class="form-control" required value="<?php echo $course['total_seats']; ?>" min="1">
                            <small style="color: var(--text-gray); font-size: 0.7rem;">Currently enrolled: <?php echo $course['enrolled_seats']; ?> seats</small>
                        </div>
                        <div class="form-group">
                            <label>Status <span class="required">*</span></label>
                            <select name="status" class="form-control">
                                <option value="active" <?php echo $course['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $course['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Date <span class="required">*</span></label>
                            <input type="date" name="start_date" class="form-control" required value="<?php echo $start_date_display; ?>">
                        </div>
                        <div class="form-group">
                            <label>End Date <span class="required">*</span></label>
                            <input type="date" name="end_date" class="form-control" required value="<?php echo $end_date_display; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Current Thumbnail</label>
                        <div class="current-thumbnail">
                            <?php if(!empty($course['thumbnail'])): ?>
                                <img src="<?php echo ASSETS_URL; ?>uploads/thumbnails/<?php echo htmlspecialchars($course['thumbnail']); ?>" alt="Current Thumbnail">
                                <p style="font-size: 0.7rem; margin-top: 5px;">Current thumbnail</p>
                            <?php else: ?>
                                <p style="color: var(--text-gray);">No thumbnail uploaded</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Change Thumbnail (Optional)</label>
                        <input type="file" name="thumbnail" class="form-control" accept="image/*" onchange="previewImage(this)">
                        <div class="thumbnail-preview" id="thumbnailPreview"></div>
                        <p style="font-size: 0.7rem; color: var(--text-gray); margin-top: 5px;">Allowed: JPG, PNG, GIF, WEBP (Max 5MB). Leave empty to keep current.</p>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Update Course
                    </button>
                </form>
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
    
    // Image preview for new thumbnail
    function previewImage(input) {
        var preview = document.getElementById('thumbnailPreview');
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '<img src="' + e.target.result + '" alt="New Thumbnail Preview">';
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.innerHTML = '';
        }
    }
    
    // Form validation
    document.getElementById('courseForm').addEventListener('submit', function(e) {
        var startDate = document.querySelector('input[name="start_date"]').value;
        var endDate = document.querySelector('input[name="end_date"]').value;
        
        if (!startDate || !endDate) {
            e.preventDefault();
            alert('Please select both start date and end date');
            return false;
        }
        
        // Validate date format YYYY-MM-DD
        var dateRegex = /^\d{4}-\d{2}-\d{2}$/;
        if (!dateRegex.test(startDate)) {
            e.preventDefault();
            alert('Start date must be in YYYY-MM-DD format. Example: 2026-06-01');
            return false;
        }
        if (!dateRegex.test(endDate)) {
            e.preventDefault();
            alert('End date must be in YYYY-MM-DD format. Example: 2026-09-01');
            return false;
        }
        
        if (new Date(endDate) < new Date(startDate)) {
            e.preventDefault();
            alert('End date must be after start date');
            return false;
        }
        
        var fee = parseFloat(document.querySelector('input[name="fee"]').value);
        if (fee <= 0) {
            e.preventDefault();
            alert('Fee must be greater than 0');
            return false;
        }
        
        var seats = parseInt(document.querySelector('input[name="total_seats"]').value);
        if (seats <= 0) {
            e.preventDefault();
            alert('Total seats must be greater than 0');
            return false;
        }
        
        var enrolledSeats = <?php echo $course['enrolled_seats']; ?>;
        if (seats < enrolledSeats) {
            var confirmMsg = 'Warning: You are reducing total seats below currently enrolled students (' + enrolledSeats + ' enrolled). This may cause issues. Continue anyway?';
            if (!confirm(confirmMsg)) {
                e.preventDefault();
                return false;
            }
        }
    });
</script>
</body>
</html>