<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/config.php';

// Check if user is logged in and is student
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

if ($_SESSION['role'] != 'student') {
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
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
                    // Delete old profile picture if not default
                    if ($profile_pic != 'default-avatar.png' && file_exists($upload_dir . $profile_pic)) {
                        unlink($upload_dir . $profile_pic);
                    }
                    $profile_pic = $uploaded_file;
                }
            }
            
            $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, cnic = ?, address = ?, profile_pic = ? WHERE id = ?");
            $update_stmt->bind_param("sssssi", $full_name, $phone, $cnic, $address, $profile_pic, $user_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['profile_pic'] = $profile_pic;
                $message = "Profile updated successfully!";
                // Refresh user data
                $refresh_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $refresh_stmt->bind_param("i", $user_id);
                $refresh_stmt->execute();
                $user = $refresh_stmt->get_result()->fetch_assoc();
                $refresh_stmt->close();
            } else {
                $error = "Failed to update profile";
            }
            $update_stmt->close();
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        if (!verifyCSRFToken($_POST['csrf_token'])) {
            $error = 'Invalid request';
        } else {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Verify current password (plain text as per your requirement)
            if ($current_password != $user['password']) {
                $error = "Current password is incorrect";
            } elseif (strlen($new_password) < 6) {
                $error = "New password must be at least 6 characters";
            } elseif ($new_password != $confirm_password) {
                $error = "New passwords do not match";
            } else {
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_password, $user_id);
                
                if ($update_stmt->execute()) {
                    $message = "Password changed successfully!";
                } else {
                    $error = "Failed to change password";
                }
                $update_stmt->close();
            }
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
            --neon-green-dark: #00cc6a;
            --text-white: #ffffff;
            --text-gray: rgba(255,255,255,0.6);
            --border-light: rgba(255,255,255,0.05);
            --border-medium: rgba(255,255,255,0.1);
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
            object-fit: cover;
        }
        /* Profile Container */
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .profile-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 25px;
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-light);
        }
        .card-header i {
            font-size: 1.5rem;
            color: var(--neon-green);
        }
        .card-header h3 {
            font-size: 1.2rem;
        }
        .profile-avatar-section {
            text-align: center;
            margin-bottom: 25px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 3px solid var(--neon-green);
            object-fit: cover;
            margin-bottom: 15px;
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
        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-medium);
            border-radius: 8px;
            color: var(--text-white);
            font-size: 0.9rem;
            transition: 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--neon-green);
        }
        .form-control:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-save {
            width: 100%;
            padding: 12px;
            background: var(--neon-green);
            color: #0a0a0a;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }
        .btn-save:hover {
            background: var(--neon-green-dark);
            transform: translateY(-2px);
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: rgba(0,255,136,0.2);
            border: 1px solid var(--success);
            color: var(--success);
        }
        .alert-danger {
            background: rgba(255,68,68,0.2);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        .info-text {
            font-size: 0.75rem;
            color: var(--text-gray);
            margin-top: 5px;
        }
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-white);
            cursor: pointer;
        }
        @media (max-width: 900px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: 0.3s; z-index: 1000; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-menu-btn { display: block; }
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
            <a href="profile.php" class="active"><i class="fas fa-user"></i> My Profile</a>
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
            <img src="<?php echo ASSETS_URL; ?>uploads/profiles/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" class="user-avatar" alt="Profile">
        </div>

        <?php if($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="profile-container">
            <!-- Update Profile Form -->
            <div class="profile-card">
                <div class="card-header">
                    <i class="fas fa-user-edit"></i>
                    <h3>Edit Profile Information</h3>
                </div>
                
                <div class="profile-avatar-section">
                    <img src="<?php echo ASSETS_URL; ?>uploads/profiles/<?php echo htmlspecialchars($user['profile_pic']); ?>" class="profile-avatar" id="profileAvatar" alt="Profile Picture">
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <div class="info-text">Email cannot be changed</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" placeholder="e.g., 03001234567">
                    </div>
                    
                    <div class="form-group">
                        <label>CNIC</label>
                        <input type="text" name="cnic" class="form-control" id="cnicInput" value="<?php echo htmlspecialchars($user['cnic']); ?>" placeholder="12345-1234567-1">
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Your address"><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <input type="file" name="profile_pic" class="form-control" accept="image/*" onchange="previewImage(this)">
                        <div class="info-text">Allowed: JPG, PNG, GIF (Max 5MB)</div>
                    </div>
                    
                    <button type="submit" class="btn-save">Save Changes</button>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="profile-card">
                <div class="card-header">
                    <i class="fas fa-lock"></i>
                    <h3>Change Password</h3>
                </div>
                
                <form method="POST" action="" onsubmit="return validatePasswordForm()">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control" id="currentPassword" required placeholder="Enter current password">
                    </div>
                    
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" id="newPassword" required placeholder="Min 6 characters">
                        <div class="info-text" id="passwordStrength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" id="confirmPassword" required placeholder="Confirm new password">
                    </div>
                    
                    <button type="submit" class="btn-save">Change Password</button>
                </form>
                
                <div class="info-text" style="margin-top: 15px; text-align: center;">
                    <i class="fas fa-shield-alt"></i> Make sure your password is strong and secure
                </div>
            </div>
        </div>
        
        <!-- Member Since Info -->
        <div class="profile-card" style="margin-top: 20px; text-align: center;">
            <p><i class="fas fa-calendar-alt"></i> Member since: <?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
            <p><i class="fas fa-id-badge"></i> Account Status: 
                <span style="color: <?php echo $user['status'] == 'active' ? 'var(--success)' : 'var(--danger)'; ?>">
                    <?php echo ucfirst($user['status']); ?>
                </span>
            </p>
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
    
    // Image preview
    function previewImage(input) {
        const preview = document.getElementById('profileAvatar');
        if(input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // CNIC formatting
    const cnicInput = document.getElementById('cnicInput');
    if(cnicInput) {
        cnicInput.addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            if(value.length > 5) {
                value = value.substring(0,5) + '-' + value.substring(5);
            }
            if(value.length > 13) {
                value = value.substring(0,13) + '-' + value.substring(13,14);
            }
            if(value.length > 15) {
                value = value.substring(0,15);
            }
            this.value = value;
        });
    }
    
    // Password strength checker
    const newPassword = document.getElementById('newPassword');
    const strengthDiv = document.getElementById('passwordStrength');
    
    if(newPassword) {
        newPassword.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let strengthText = '';
            let strengthColor = '';
            
            if(password.length >= 6) strength++;
            if(password.length >= 8) strength++;
            if(password.match(/[a-z]/)) strength++;
            if(password.match(/[A-Z]/)) strength++;
            if(password.match(/[0-9]/)) strength++;
            if(password.match(/[$@#&!]/)) strength++;
            
            if(password.length === 0) {
                strengthText = '';
            } else if(strength <= 2) {
                strengthText = 'Weak password';
                strengthColor = '#ff4444';
            } else if(strength <= 4) {
                strengthText = 'Fair password';
                strengthColor = '#ffaa00';
            } else {
                strengthText = 'Strong password';
                strengthColor = '#00ff88';
            }
            
            if(password.length === 0) {
                strengthDiv.innerHTML = '';
            } else {
                strengthDiv.innerHTML = '<span style="color: ' + strengthColor + ';"><i class="fas fa-shield-alt"></i> ' + strengthText + '</span>';
            }
        });
    }
    
    // Validate password form
    function validatePasswordForm() {
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if(!currentPassword || !newPassword || !confirmPassword) {
            alert('Please fill all password fields');
            return false;
        }
        
        if(newPassword.length < 6) {
            alert('New password must be at least 6 characters');
            return false;
        }
        
        if(newPassword !== confirmPassword) {
            alert('New passwords do not match');
            return false;
        }
        
        return true;
    }
</script>
</body>
</html>