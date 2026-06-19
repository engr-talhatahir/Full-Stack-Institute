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
$message = '';
$error = '';

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $cnic = trim($_POST['cnic']);
        $address = trim($_POST['address']);
        
        // Handle profile picture upload
        $profile_pic = $user['profile_pic'];
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../assets/uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $uploaded_file = uploadFile($_FILES['profile_pic'], $upload_dir, array('jpg', 'jpeg', 'png', 'gif'));
            if ($uploaded_file) {
                $profile_pic = $uploaded_file;
            }
        }
        
        // Update password if provided
        $password_query = "";
        $params = array($full_name, $phone, $cnic, $address, $profile_pic, $user_id);
        $types = "sssssi";
        
        if (!empty($_POST['new_password'])) {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            if ($new_password === $confirm_password && strlen($new_password) >= 6) {
                $password_query = ", password = ?";
                $params = array($full_name, $phone, $cnic, $address, $profile_pic, $new_password, $user_id);
                $types = "ssssssi";
            } else {
                $error = "Passwords do not match or are too short";
            }
        }
        
        if (empty($error)) {
            $sql = "UPDATE users SET full_name = ?, phone = ?, cnic = ?, address = ?, profile_pic = ? $password_query WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['profile_pic'] = $profile_pic;
                $message = "Profile updated successfully!";
                // Refresh user data
                $stmt2 = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt2->bind_param("i", $user_id);
                $stmt2->execute();
                $user = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();
            } else {
                $error = "Failed to update profile";
            }
            $stmt->close();
        }
    }
}

$csrf_token = generateCSRFToken();
$pageTitle = "My Profile - ITsimplera";
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
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
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
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.85rem; }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            color: var(--text-white);
            font-size: 0.9rem;
        }
        .form-control:focus { outline: none; border-color: var(--neon-green); }
        .btn-save {
            background: var(--neon-green);
            color: #0a0a0a;
            padding: 12px 30px;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-save:hover { background: #00cc6a; transform: translateY(-2px); }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success { background: rgba(0,255,136,0.2); border: 1px solid var(--success); color: var(--success); }
        .alert-danger { background: rgba(255,68,68,0.2); border: 1px solid var(--danger); color: var(--danger); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-white);
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: 0.3s; z-index: 1000; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-menu-btn { display: block; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .profile-header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<div class="bg-pattern"></div>
<div class="dashboard-container">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header"><h3>ITSimplera.Institute</h3><p style="font-size: 0.8rem;">Student Portal</p></div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="profile.php" ><i class="fas fa-user"></i> My Profile</a>
            <a href="certificate.php"><i class="fas fa-award"></i> My Certificate</a> 
            <a href="courses.php"><i class="fas fa-book"></i> Browse Courses</a>
            <a href="my-courses.php"><i class="fas fa-graduation-cap"></i> My Courses</a>
            <a href="internships.php"><i class="fas fa-briefcase"></i> Internships</a>
            <a href="my-applications.php"><i class="fas fa-file-alt"></i> My Applications</a>
            <a href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
            <div class="welcome"><h2>My Profile</h2><p>Manage your personal information</p></div>
            <img src="<?php echo ASSETS_URL; ?>uploads/profiles/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" class="user-avatar">
        </div>

        <div class="card">
            <?php if($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            
            <div class="profile-header">
                <img src="<?php echo ASSETS_URL; ?>uploads/profiles/<?php echo htmlspecialchars($user['profile_pic']); ?>" class="profile-avatar" id="profileAvatar">
                <div>
                    <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                    <p>Member since: <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email (Cannot be changed)</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label>CNIC</label>
                        <input type="text" name="cnic" class="form-control" value="<?php echo htmlspecialchars($user['cnic']); ?>" placeholder="12345-1234567-1">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Profile Picture</label>
                    <input type="file" name="profile_pic" class="form-control" accept="image/*" onchange="previewImage(this)">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>New Password (leave blank to keep current)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Enter new password">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password">
                    </div>
                </div>
                
                <button type="submit" class="btn-save">Save Changes</button>
            </form>
        </div>
    </div>
</div>

<script>
    function previewImage(input) {
        if(input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profileAvatar').src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    if(mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() { sidebar.classList.toggle('active'); });
    }
</script>
</body>
</html>