<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$certificate_data = null;
$error = '';
$searched = false;

// Agar student ne apna code daal kar search kiya
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_code'])) {
    $search_code = trim($_POST['certificate_code']);
    
    if (empty($search_code)) {
        $error = "Please enter your unique certificate code.";
    } else {
        $searched = true;
        // Query check karegi ke yeh code isi student ka hai aur admin se 'Certified' ho chuka hai
        $stmt = $conn->prepare("SELECT * FROM student_certificates WHERE student_id = ? AND certificate_code = ? AND status = 'Certified'");
        $stmt->bind_param("is", $user_id, $search_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $certificate_data = $result->fetch_assoc();
        } else {
            $error = "Invalid Certificate Code or verification is pending from Admin.";
        }
        $stmt->close();
    }
}

$pageTitle = "My Certificate - ITsimplera";
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
            --text-white: #ffffff;
            --text-gray: rgba(255,255,255,0.6);
            --border-light: rgba(255,255,255,0.05);
            --success: #00ff88;
            --danger: #ff4444;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-dark);
            color: var(--text-white);
        }
        .bg-pattern {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: linear-gradient(45deg, #1a1a1a 2%, transparent 2%), linear-gradient(-45deg, #1a1a1a 2%, transparent 2%);
            background-size: 40px 40px; opacity: 0.3; z-index: -1;
        }
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px; background: rgba(10,10,10,0.95); border-right: 1px solid var(--border-light);
            position: fixed; height: 100vh; overflow-y: auto;
        }
        .sidebar-header { padding: 25px; border-bottom: 1px solid var(--border-light); text-align: center; }
        .sidebar-header h3 { background: linear-gradient(135deg, var(--neon-green), #00cc6a); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .sidebar-nav { padding: 20px 0; }
        .sidebar-nav a {
            display: flex; align-items: center; padding: 12px 25px; color: var(--text-gray); text-decoration: none; transition: 0.3s; gap: 12px;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: var(--bg-card); color: var(--neon-green); border-left: 3px solid var(--neon-green);
        }
        .main-content { flex: 1; margin-left: 280px; padding: 30px; }
        .top-bar {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid var(--border-light);
        }
        .welcome h2 { font-size: 1.5rem; margin-bottom: 5px; }
        .card {
            background: var(--bg-card); border: 1px solid var(--border-light); border-radius: 16px; padding: 30px; max-width: 800px; margin: 0 auto;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.85rem; }
        .form-control {
            width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 8px; color: var(--text-white); font-size: 0.9rem;
        }
        .form-control:focus { outline: none; border-color: var(--neon-green); }
        .btn-action {
            background: var(--neon-green); color: #0a0a0a; padding: 12px 30px; border: none; border-radius: 30px; font-weight: 600; cursor: pointer; transition: 0.3s;
        }
        .btn-action:hover { background: #00cc6a; transform: translateY(-2px); }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
        .alert-danger { background: rgba(255,68,68,0.2); border: 1px solid var(--danger); color: var(--danger); }
        
        /* Certificate Certificate Display Design */
        .certificate-badge {
            border: 2px dashed var(--neon-green); border-radius: 12px; padding: 30px; text-align: center; margin-top: 30px; background: rgba(0, 255, 136, 0.02);
        }
        .certificate-badge i { font-size: 3.5rem; color: var(--neon-green); margin-bottom: 15px; }
        .certificate-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; text-align: left; }
        .grid-item { background: rgba(255,255,255,0.02); padding: 15px; border-radius: 8px; border: 1px solid var(--border-light); }
        .grid-item span { display: block; font-size: 0.8rem; color: var(--text-gray); margin-bottom: 5px; }
        .grid-item h4 { font-size: 1.2rem; color: var(--text-white); }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: 0.3s; z-index: 1000; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .certificate-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="bg-pattern"></div>
<div class="dashboard-container">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header"><h3>ITSimplera.Institute</h3><p style="font-size: 0.8rem;">Student Portal</p></div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
            <a href="certificate.php" class="active"><i class="fas fa-award"></i> My Certificate</a>
            <a href="courses.php"><i class="fas fa-book"></i> Browse Courses</a>
            <a href="my-courses.php"><i class="fas fa-graduation-cap"></i> My Courses</a>
            <a href="internships.php"><i class="fas fa-briefcase"></i> Internships</a>
            <a href="my-applications.php"><i class="fas fa-file-alt"></i> My Applications</a>
            <a href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="welcome"><h2>Certificate Verification</h2><p>Verify your certification, marks, and grades</p></div>
        </div>

        <div class="card">
            <?php if($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Enter Khas Certificate Code</label>
                    <input type="text" name="certificate_code" class="form-control" placeholder="e.g., ITS-2026-XYZ89" value="<?php echo isset($_POST['certificate_code']) ? htmlspecialchars($_POST['certificate_code']) : ''; ?>" required>
                </div>
                <button type="submit" name="search_code" class="btn-action">Verify & View</button>
            </form>

            <?php if ($searched && $certificate_data): ?>
                <div class="certificate-badge">
                    <i class="fas fa-ribbon"></i>
                    <h3 style="color: var(--neon-green); font-size: 1.6rem; margin-bottom: 5px;">STUDENT CERTIFIED</h3>
                    <p style="color: var(--text-gray);">This certificate is officially verified by ITsimplera Admin Panel.</p>
                    
                    <div class="certificate-grid">
                        <div class="grid-item">
                            <span>Certificate Code</span>
                            <h4><?php echo htmlspecialchars($certificate_data['certificate_code']); ?></h4>
                        </div>
                        <div class="grid-item">
                            <span>Obtained Marks / Total</span>
                            <h4><?php echo htmlspecialchars($certificate_data['obtained_marks']) . " / " . htmlspecialchars($certificate_data['total_marks']); ?></h4>
                        </div>
                        <div class="grid-item">
                            <span>Percentage</span>
                            <h4><?php echo htmlspecialchars($certificate_data['percentage']); ?>%</h4>
                        </div>
                        <div class="grid-item">
                            <span>Final Grade</span>
                            <h4>Grade <?php echo htmlspecialchars($certificate_data['grade']); ?></h4>
                        </div>
                    </div>
                    <p style="font-size: 0.8rem; color: var(--text-gray); margin-top: 20px;">Issued Date: <?php echo date('M d, Y', strtotime($certificate_data['issue_date'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>