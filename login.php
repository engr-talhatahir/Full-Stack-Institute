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
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            $error = 'Please fill all fields';
        } else {
            $stmt = $conn->prepare("SELECT id, full_name, email, password, role, status, profile_pic FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                if ($password == $user['password']) {
                    if ($user['status'] == 'suspended') {
                        $error = 'Your account has been suspended. Please contact admin.';
                    } else {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['profile_pic'] = $user['profile_pic'];
                        
                        setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
                        
                        if ($user['role'] == 'admin') {
                            header('Location: ' . BASE_URL . 'admin/dashboard.php');
                        } else {
                            header('Location: ' . BASE_URL . 'student/dashboard.php');
                        }
                        exit;
                    }
                } else {
                    $error = 'Invalid email or password';
                }
            } else {
                $error = 'Invalid email or password';
            }
            $stmt->close();
        }
    }
}

$csrf_token = generateCSRFToken();
$pageTitle = "Login - ITsimplera.Institute";
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
            --border-radius-sm: 8px;
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
        
        .login-container { 
            width: 100%; 
            max-width: 450px; 
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
        
        .login-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-light);
            padding: 40px;
            transition: all 0.3s ease;
            animation: glowPulse 3s ease-in-out infinite;
        }
        
        @keyframes glowPulse {
            0%, 100% { box-shadow: 0 0 0px rgba(0,255,136,0); }
            50% { box-shadow: 0 0 20px rgba(0,255,136,0.1); }
        }
        
        .login-card:hover {
            border-color: rgba(0,255,136,0.3);
            transform: translateY(-5px);
        }
        
        .login-header { text-align: center; margin-bottom: 30px; }
        
        .logo {
            font-size: 2rem;
            font-weight: 800;
            text-decoration: none;
            color: var(--text-white);
            display: inline-block;
            margin-bottom: 15px;
            transition: 0.3s;
            position: relative;
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
        
        .login-header h1 { 
            font-size: 1.8rem; 
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--text-white), var(--neon-green));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .login-header p { color: var(--text-gray); font-size: 0.9rem; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.85rem; }
        
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
        
        .password-input { position: relative; }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-gray);
            transition: 0.3s;
        }
        
        .toggle-password:hover {
            color: var(--neon-green);
        }
        
        .btn-login {
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
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:hover { 
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,255,136,0.3);
        }
        
        .register-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border-light);
            font-size: 0.85rem;
            color: var(--text-gray);
        }
        
        .register-link a { 
            color: var(--neon-green); 
            text-decoration: none; 
            font-weight: 600;
            transition: 0.3s;
        }
        
        .register-link a:hover {
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
        
        /* Floating shapes */
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
        
        @media (max-width: 500px) { 
            .login-card { padding: 25px; } 
            .login-header h1 { font-size: 1.5rem; }
            .logo { font-size: 1.5rem; }
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

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <a href="<?php echo BASE_URL; ?>index.php" class="logo">IT<span>Simplera.Institute</span></a>
            <h1>Welcome Back</h1>
            <p>Login to continue your learning journey</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php displayFlashMessages(); ?>
        
        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="Enter your email">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-input">
                    <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
                    <span class="toggle-password" onclick="togglePassword()"><i class="fas fa-eye"></i></span>
                </div>
            </div>
            
            <button type="submit" class="btn-login">Login <i class="fas fa-arrow-right"></i></button>
            
            <div class="register-link">
                Don't have an account? <a href="<?php echo BASE_URL; ?>register.php">Create Account</a>
            </div>
        </form>
    </div>
</div>

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.querySelector('.toggle-password i');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }
    
    // Form validation with animation
    const loginForm = document.getElementById('loginForm');
    if(loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if(!email || !password) {
                e.preventDefault();
                alert('Please fill all fields');
                return false;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if(!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
        });
    }
    
    // Add floating animation to particles
    const particles = document.querySelectorAll('.particle');
    particles.forEach(function(particle, index) {
        const duration = 15 + (index * 2);
        particle.style.animationDuration = duration + 's';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.top = Math.random() * 100 + '%';
    });
</script>
</body>
</html>