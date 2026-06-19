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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $title = trim($_POST['title']);
        $company_name = trim($_POST['company_name']);
        $description = trim($_POST['description']);
        $location = trim($_POST['location']);
        $stipend = trim($_POST['stipend']);
        $duration = trim($_POST['duration']);
        $deadline = $_POST['deadline'];
        $total_slots = intval($_POST['total_slots']);
        
        // Validation
        if (empty($title) || empty($company_name) || empty($description) || empty($location) || empty($stipend) || empty($duration) || empty($deadline) || empty($total_slots)) {
            $error = 'Please fill all required fields';
        } elseif ($total_slots <= 0) {
            $error = 'Total slots must be greater than 0';
        } elseif (strtotime($deadline) < time()) {
            $error = 'Deadline must be a future date';
        } else {
            $admin_id = $_SESSION['user_id'];
            $status = 'open';
            $applied_count = 0;
            
            $stmt = $conn->prepare("INSERT INTO internships (title, company_name, description, location, stipend, duration, deadline, total_slots, applied_count, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssiisi", $title, $company_name, $description, $location, $stipend, $duration, $deadline, $total_slots, $applied_count, $status, $admin_id);
            
            if ($stmt->execute()) {
                setFlash('success', 'Internship added successfully!');
                header('Location: internships.php');
                exit;
            } else {
                $error = "Failed to add internship. Please try again.";
            }
            $stmt->close();
        }
    }
}

$csrf_token = generateCSRFToken();
$pageTitle = "Add New Internship - ITsimplera";
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
        
        .info-text {
            font-size: 0.7rem;
            color: var(--text-gray);
            margin-top: 5px;
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
                    <h2>Add New Internship</h2>
                    <p>Create a new internship opportunity for students</p>
                </div>
            </div>
        </div>

        <div class="form-container">
            <a href="internships.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Internships
            </a>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="form-card">
                <div class="form-header">
                    <i class="fas fa-plus-circle"></i>
                    <h3>Internship Information</h3>
                </div>
                
                <form method="POST" action="" id="internshipForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label>Internship Title <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g., Web Development Intern">
                    </div>
                    
                    <div class="form-group">
                        <label>Company Name <span class="required">*</span></label>
                        <input type="text" name="company_name" class="form-control" required placeholder="e.g., Tech Solutions Pvt Ltd">
                    </div>
                    
                    <div class="form-group">
                        <label>Description <span class="required">*</span></label>
                        <textarea name="description" class="form-control" required placeholder="Detailed description of the internship, responsibilities, requirements..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Location <span class="required">*</span></label>
                            <input type="text" name="location" class="form-control" required placeholder="e.g., Lahore, Karachi, Remote">
                        </div>
                        <div class="form-group">
                            <label>Stipend <span class="required">*</span></label>
                            <input type="text" name="stipend" class="form-control" required placeholder="e.g., Rs. 15,000/month, Unpaid">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Duration <span class="required">*</span></label>
                            <input type="text" name="duration" class="form-control" required placeholder="e.g., 3 months, 6 weeks">
                        </div>
                        <div class="form-group">
                            <label>Total Slots <span class="required">*</span></label>
                            <input type="number" name="total_slots" class="form-control" required placeholder="e.g., 5" min="1">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Application Deadline <span class="required">*</span></label>
                        <input type="date" name="deadline" class="form-control" required>
                        <div class="info-text">Applications will close after this date</div>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Create Internship
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
    
    // Set minimum date for deadline (tomorrow)
    var today = new Date();
    var tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    var minDate = tomorrow.toISOString().split('T')[0];
    document.querySelector('input[name="deadline"]').min = minDate;
    
    // Form validation
    document.getElementById('internshipForm').addEventListener('submit', function(e) {
        var deadline = document.querySelector('input[name="deadline"]').value;
        var today = new Date();
        var deadlineDate = new Date(deadline);
        
        if (deadlineDate < today) {
            e.preventDefault();
            alert('Deadline must be a future date');
            return false;
        }
        
        var slots = parseInt(document.querySelector('input[name="total_slots"]').value);
        if (slots <= 0) {
            e.preventDefault();
            alert('Total slots must be greater than 0');
            return false;
        }
    });
</script>
</body>
</html>