<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header('Location: ' . BASE_URL . 'admin/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'student/dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $phone = trim($_POST['phone']);
        $cnic = trim($_POST['cnic']);
        $address = trim($_POST['address']);
        
        if (empty($full_name) || empty($email) || empty($password)) {
            $error = 'Please fill all required fields';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif (!empty($phone) && !preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
            $error = 'Please enter a valid phone number';
        } elseif (!empty($cnic) && !preg_match('/^[0-9]{5}-[0-9]{7}-[0-9]$/', $cnic)) {
            $error = 'Please enter valid CNIC (format: 12345-1234567-1)';
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error = 'Email already registered';
            } else {
                $profile_pic = 'default-avatar.png';
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
                    $upload_dir = 'assets/uploads/profiles/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $uploaded_file = uploadFile($_FILES['profile_pic'], $upload_dir, array('jpg', 'jpeg', 'png', 'gif'));
                    if ($uploaded_file) {
                        $profile_pic = $uploaded_file;
                    }
                }
                
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, cnic, address, profile_pic, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'student', 'active')");
                $stmt->bind_param("sssssss", $full_name, $email, $password, $phone, $cnic, $address, $profile_pic);
                
                if ($stmt->execute()) {
                    setFlash('success', 'Registration successful! Please login.');
                    header('Location: ' . BASE_URL . 'login.php');
                    exit;
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
            $stmt->close();
        }
    }
}

$csrf_token = generateCSRFToken();
$pageTitle = "Register - ITsimplera.Institute";
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
            --danger: #ff4444;
            --warning: #ffaa00;
            --success: #00ff88;
            --border-radius-sm: 8px;
            --border-radius-md: 16px;
            --border-radius-lg: 24px;
            --border-radius-xl: 30px;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-dark);
            color: var(--text-white);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated Background */
        .bg-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: linear-gradient(45deg, #1a1a1a 2%, transparent 2%), linear-gradient(-45deg, #1a1a1a 2%, transparent 2%);
            background-size: 40px 40px;
            opacity: 0.3;
            z-index: -2;
        }
        
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 30% 40%, rgba(0,255,136,0.03) 0%, transparent 50%),
                        radial-gradient(circle at 70% 60%, rgba(0,255,136,0.02) 0%, transparent 50%);
            z-index: -1;
            animation: pulseBg 8s ease-in-out infinite;
        }
        
        @keyframes pulseBg {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }
        
        /* Floating Particles */
        .particle {
            position: fixed;
            background: var(--neon-green);
            border-radius: 50%;
            opacity: 0.1;
            pointer-events: none;
            z-index: -1;
            animation: floatParticle 15s ease-in-out infinite;
        }
        
        @keyframes floatParticle {
            0%, 100% { transform: translateY(0) translateX(0); }
            25% { transform: translateY(-100px) translateX(50px); }
            50% { transform: translateY(-50px) translateX(100px); }
            75% { transform: translateY(50px) translateX(50px); }
        }
        
        /* Blur Shapes */
        .shape {
            position: fixed;
            z-index: -1;
            opacity: 0.05;
        }
        
        .shape-1 {
            top: 10%;
            left: 5%;
            width: 300px;
            height: 300px;
            background: var(--neon-green);
            border-radius: 50%;
            filter: blur(60px);
            animation: floatShape 20s ease-in-out infinite;
        }
        
        .shape-2 {
            bottom: 10%;
            right: 5%;
            width: 400px;
            height: 400px;
            background: var(--neon-green);
            border-radius: 50%;
            filter: blur(80px);
            animation: floatShape 25s ease-in-out infinite reverse;
        }
        
        @keyframes floatShape {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(50px, 50px); }
        }
        
        .register-container { 
            width: 100%; 
            max-width: 580px; 
            margin: 0 auto;
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .register-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-light);
            padding: 40px;
            transition: all 0.3s ease;
        }
        
        .register-card:hover {
            border-color: rgba(0,255,136,0.3);
            transform: translateY(-5px);
        }
        
        .register-header { text-align: center; margin-bottom: 30px; }
        
        .logo {
            font-size: 2rem;
            font-weight: 800;
            text-decoration: none;
            color: var(--text-white);
            display: inline-block;
            margin-bottom: 15px;
            transition: 0.3s;
        }
        
        .logo:hover {
            transform: scale(1.02);
        }
        
        .logo span { 
            color: var(--neon-green);
            position: relative;
        }
        
        .logo span::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--neon-green);
            transform: scaleX(0);
            transition: transform 0.3s;
        }
        
        .logo:hover span::after {
            transform: scaleX(1);
        }
        
        .institute-name {
            font-size: 0.8rem;
            color: var(--text-gray);
            letter-spacing: 2px;
            margin-top: 5px;
        }
        
        .register-header h1 { 
            font-size: 1.8rem; 
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--text-white), var(--neon-green));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .register-header p { color: var(--text-gray); font-size: 0.9rem; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.85rem; }
        .form-group label .required { color: var(--neon-green); margin-left: 3px; }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-medium);
            border-radius: var(--border-radius-sm);
            font-size: 0.9rem;
            color: var(--text-white);
            transition: all 0.3s;
        }
        
        .form-control:focus { 
            outline: none; 
            border-color: var(--neon-green);
            box-shadow: 0 0 10px rgba(0,255,136,0.2);
        }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        
        .password-strength { margin-top: 8px; font-size: 0.7rem; }
        .strength-weak { color: #ff4444; }
        .strength-fair { color: #ffaa00; }
        .strength-good { color: #00ff88; }
        .strength-strong { color: #00ccff; }
        
        .image-preview {
            margin-top: 10px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--neon-green);
            background: rgba(255,255,255,0.05);
        }
        .image-preview img { width: 100%; height: 100%; object-fit: cover; }
        
        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--neon-green), var(--neon-green-dark));
            color: #0a0a0a;
            border: none;
            border-radius: var(--border-radius-xl);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .btn-register::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn-register:hover::before {
            left: 100%;
        }
        
        .btn-register:hover { 
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,255,136,0.3);
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border-light);
            font-size: 0.85rem;
            color: var(--text-gray);
        }
        
        .login-link a { 
            color: var(--neon-green); 
            text-decoration: none; 
            font-weight: 600;
            transition: 0.3s;
        }
        
        .login-link a:hover {
            text-decoration: underline;
            text-shadow: 0 0 5px rgba(0,255,136,0.5);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 20px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .alert-danger { background: rgba(255,68,68,0.1); border: 1px solid var(--danger); color: var(--danger); }
        
        @media (max-width: 600px) { 
            .register-card { padding: 25px; } 
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .logo { font-size: 1.5rem; }
            .register-header h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<!-- Animated Background Elements -->
<div class="bg-pattern"></div>
<div class="animated-bg"></div>
<div class="shape shape-1"></div>
<div class="shape shape-2"></div>

<!-- Floating Particles -->
<div class="particle" style="width: 4px; height: 4px; top: 20%; left: 10%; animation-duration: 12s;"></div>
<div class="particle" style="width: 6px; height: 6px; top: 60%; left: 85%; animation-duration: 15s;"></div>
<div class="particle" style="width: 3px; height: 3px; top: 70%; left: 20%; animation-duration: 18s;"></div>
<div class="particle" style="width: 5px; height: 5px; top: 30%; left: 75%; animation-duration: 10s;"></div>
<div class="particle" style="width: 7px; height: 7px; top: 80%; left: 50%; animation-duration: 20s;"></div>

<div class="register-container">
    <div class="register-card">
        <div class="register-header">
            <a href="<?php echo BASE_URL; ?>index.php" class="logo">IT<span>Simplera.Institute</span></a>
            <h1>Create Account</h1>
            <p>Join ITsimplera and start your learning journey</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <label for="full_name">Full Name <span class="required">*</span></label>
                <input type="text" class="form-control" id="full_name" name="full_name" required placeholder="Enter your full name">
            </div>
            
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="Enter your email">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="Min 6 characters">
                    <div class="password-strength" id="passwordStrength"></div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Confirm password">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="e.g., 03001234567">
                </div>
                <div class="form-group">
                    <label for="cnic">CNIC</label>
                    <input type="text" class="form-control" id="cnic" name="cnic" placeholder="12345-1234567-1">
                </div>
            </div>
            
            <div class="form-group">
                <label for="address">Address</label>
                <textarea class="form-control" id="address" name="address" rows="2" placeholder="Your address"></textarea>
            </div>
            
            <div class="form-group">
                <label for="profile_pic">Profile Picture</label>
                <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*" onchange="previewImage(this)">
                <div id="imagePreview" class="image-preview" style="display: none;"></div>
            </div>
            
            <button type="submit" class="btn-register">Create Account <i class="fas fa-arrow-right"></i></button>
            
            <div class="login-link">
                Already have an account? <a href="<?php echo BASE_URL; ?>login.php">Login here</a>
            </div>
        </form>
    </div>
</div>

<script>
    var passwordInput = document.getElementById('password');
    var strengthDiv = document.getElementById('passwordStrength');
    
    if(passwordInput){
        passwordInput.addEventListener('input', function() {
            var password = this.value;
            var strength = 0;
            var strengthText = '', strengthClass = '';
            
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
                strengthClass = 'strength-weak'; 
            } else if(strength <= 4) { 
                strengthText = 'Fair password'; 
                strengthClass = 'strength-fair'; 
            } else if(strength <= 5) { 
                strengthText = 'Good password'; 
                strengthClass = 'strength-good'; 
            } else { 
                strengthText = 'Strong password'; 
                strengthClass = 'strength-strong'; 
            }
            
            strengthDiv.innerHTML = password.length === 0 ? '' : '<span class="' + strengthClass + '"><i class="fas fa-shield-alt"></i> ' + strengthText + '</span>';
        });
    }
    
    var cnicInput = document.getElementById('cnic');
    if(cnicInput){
        cnicInput.addEventListener('input', function(e) {
            var value = this.value.replace(/\D/g, '');
            if(value.length > 5) value = value.substring(0,5) + '-' + value.substring(5);
            if(value.length > 13) value = value.substring(0,13) + '-' + value.substring(13,14);
            if(value.length > 15) value = value.substring(0,15);
            this.value = value;
        });
    }
    
    function previewImage(input) {
        var preview = document.getElementById('imagePreview');
        if(input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) { 
                preview.style.display = 'block'; 
                preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">'; 
            };
            reader.readAsDataURL(input.files[0]);
        } else { 
            preview.style.display = 'none'; 
            preview.innerHTML = ''; 
        }
    }
    
    var registerForm = document.getElementById('registerForm');
    if(registerForm) {
        registerForm.addEventListener('submit', function(e) {
            var password = document.getElementById('password').value;
            var confirm = document.getElementById('confirm_password').value;
            var email = document.getElementById('email').value;
            
            if(password !== confirm) { 
                e.preventDefault(); 
                alert('Passwords do not match!'); 
                return false; 
            }
            if(password.length < 6) { 
                e.preventDefault(); 
                alert('Password must be at least 6 characters!'); 
                return false; 
            }
            
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if(!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address!');
                return false;
            }
        });
    }
    
    // Animate particles
    var particles = document.querySelectorAll('.particle');
    for(var i = 0; i < particles.length; i++) {
        var duration = 15 + (i * 2);
        particles[i].style.animationDuration = duration + 's';
        particles[i].style.left = Math.random() * 100 + '%';
        particles[i].style.top = Math.random() * 100 + '%';
    }
</script>
</body>
</html>