<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/config.php';

$pageTitle = "ITsimplera - Future of IT Education";

// Fetch featured courses with ratings
$featured_courses = array();
$sql = "SELECT * FROM courses WHERE status = 'active' ORDER BY created_at DESC LIMIT 3";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $rating_data = getCourseRating($row['id'], $conn);
        $row['avg_rating'] = $rating_data['avg'];
        $row['total_ratings'] = $rating_data['total'];
        $featured_courses[] = $row;
    }
}

// Fetch open internships with ratings
$open_internships = array();
$sql = "SELECT * FROM internships WHERE status = 'open' ORDER BY created_at DESC LIMIT 3";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $rating_data = getInternshipRating($row['id'], $conn);
        $row['avg_rating'] = $rating_data['avg'];
        $row['total_ratings'] = $rating_data['total'];
        $open_internships[] = $row;
    }
}

// Get stats
$total_courses = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM courses WHERE status = 'active'");
if ($result) {
    $row = $result->fetch_assoc();
    $total_courses = $row['count'];
}

$total_students = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND status = 'active'");
if ($result) {
    $row = $result->fetch_assoc();
    $total_students = $row['count'];
}

$total_internships = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM internships WHERE status = 'open'");
if ($result) {
    $row = $result->fetch_assoc();
    $total_internships = $row['count'];
}

$csrf_token = generateCSRFToken();
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
            --bg-card: rgba(255,255,255,0.03);
            --bg-card-hover: rgba(255,255,255,0.08);
            --neon-green: #00ff88;
            --neon-green-dark: #00cc6a;
            --neon-green-light: rgba(0,255,136,0.1);
            --text-white: #ffffff;
            --text-gray: rgba(255,255,255,0.6);
            --border-light: rgba(255,255,255,0.05);
            --border-medium: rgba(255,255,255,0.1);
            --star-color: #ffd700;
            --star-empty: #ddd;
            --danger: #ff4444;
            
            --border-radius-sm: 8px;
            --border-radius-md: 16px;
            --border-radius-lg: 24px;
            --border-radius-xl: 30px;
            --transition-normal: 0.3s;
            --transition-slow: 0.6s;
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
            pointer-events: none;
        }
        
        /* Animated Gradient Background */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 50%, rgba(0,255,136,0.03) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(0,255,136,0.02) 0%, transparent 50%);
            z-index: -2;
            animation: pulse 8s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }
        
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-dark); }
        ::-webkit-scrollbar-thumb { background: var(--neon-green); border-radius: 10px; }
        
        /* Navbar with animation */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 16px 5%;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
            background: rgba(10,10,10,0.85);
            border-bottom: 1px solid var(--border-light);
            transition: all 0.3s ease;
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .navbar.scrolled {
            background: rgba(10,10,10,0.95);
            padding: 12px 5%;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .logo {
            font-size: clamp(1.3rem, 5vw, 1.8rem);
            font-weight: 800;
            text-decoration: none;
            color: var(--text-white);
            transition: 0.3s;
        }
        
        .logo:hover {
            transform: scale(1.02);
        }
        
        .logo span { color: var(--neon-green); }
        
        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--text-white);
            opacity: 0.7;
            font-weight: 500;
            font-size: 0.95rem;
            transition: 0.3s;
            position: relative;
        }
        
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--neon-green);
            transition: width 0.3s;
        }
        
        .nav-links a:hover::after {
            width: 100%;
        }
        
        .nav-links a:hover { opacity: 1; color: var(--neon-green); }
        
        .btn-outline {
            padding: 8px 22px;
            border: 1px solid var(--border-medium);
            border-radius: var(--border-radius-xl);
            background: transparent;
            color: var(--text-white);
            transition: 0.3s;
        }
        
        .btn-outline:hover {
            border-color: var(--neon-green);
            background: var(--neon-green-light);
            color: var(--neon-green);
            transform: translateY(-2px);
        }
        
        .btn-outline:hover::after {
            display: none;
        }
        
        .btn-filled {
            padding: 8px 26px;
            background: var(--neon-green);
            color: #0a0a0a;
            border-radius: var(--border-radius-xl);
            font-weight: 600;
            transition: 0.3s;
        }
        
        .btn-filled:hover {
            background: var(--neon-green-dark);
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(0,255,136,0.3);
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-white);
            transition: 0.3s;
        }
        
        .mobile-menu-btn:hover {
            color: var(--neon-green);
        }
        
        .btn-primary {
            padding: 12px 28px;
            background: var(--neon-green);
            color: #0a0a0a;
            border-radius: var(--border-radius-xl);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            transform: translateX(5px);
            background: var(--neon-green-dark);
            box-shadow: 0 0 25px rgba(0,255,136,0.4);
        }
        
        .btn-secondary {
            padding: 12px 28px;
            border: 1px solid var(--border-medium);
            border-radius: var(--border-radius-xl);
            text-decoration: none;
            color: var(--text-white);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: transparent;
            transition: 0.3s;
        }
        
        .btn-secondary:hover {
            border-color: var(--neon-green);
            transform: translateX(5px);
            color: var(--neon-green);
        }
        
        /* Hero Section with Animation */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 100px 5% 60px;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(0,255,136,0.03)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            opacity: 0.2;
            animation: wave 10s ease-in-out infinite;
        }
        
        @keyframes wave {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(-20px); }
        }
        
        .hero-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 50px;
            align-items: center;
            width: 100%;
            position: relative;
            z-index: 1;
        }
        
        .badge {
            display: inline-block;
            background: var(--neon-green-light);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            color: var(--neon-green);
            margin-bottom: 30px;
            border: 1px solid rgba(0,255,136,0.2);
            animation: fadeInUp 0.6s ease;
        }
        
        .hero h1 {
            font-size: clamp(2rem, 6vw, 4rem);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 20px;
            animation: fadeInUp 0.6s ease 0.1s backwards;
        }
        
        .hero h1 span {
            color: var(--neon-green);
            border-bottom: 3px solid var(--neon-green);
            display: inline-block;
            animation: glow 2s ease-in-out infinite;
        }
        
        @keyframes glow {
            0%, 100% { text-shadow: 0 0 5px rgba(0,255,136,0.5); }
            50% { text-shadow: 0 0 20px rgba(0,255,136,0.8); }
        }
        
        .hero p {
            font-size: clamp(0.9rem, 3vw, 1.1rem);
            opacity: 0.7;
            margin-bottom: 35px;
            animation: fadeInUp 0.6s ease 0.2s backwards;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-bottom: 50px;
            flex-wrap: wrap;
            animation: fadeInUp 0.6s ease 0.3s backwards;
        }
        
        .stats {
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
            animation: fadeInUp 0.6s ease 0.4s backwards;
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
        
        .stat-item {
            transition: 0.3s;
        }
        
        .stat-item:hover {
            transform: translateY(-5px);
        }
        
        .stat-item h2 {
            font-size: clamp(1.3rem, 4vw, 2rem);
            color: var(--neon-green);
            margin-bottom: 5px;
            transition: 0.3s;
        }
        
        .stat-item:hover h2 {
            text-shadow: 0 0 10px rgba(0,255,136,0.5);
        }
        
        .stat-item p { font-size: 0.85rem; opacity: 0.6; }
        
        .hero-right { position: relative; }
        
        .feature-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            padding: 30px;
            border: 1px solid var(--border-light);
            text-align: center;
            transition: 0.3s;
            animation: fadeInRight 0.6s ease;
        }
        
        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--neon-green);
            box-shadow: 0 10px 40px rgba(0,255,136,0.1);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            animation: bounce 2s ease-in-out infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .rating {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-light);
        }
        
        .rating i {
            color: var(--star-color);
            margin: 0 2px;
            animation: starPulse 1.5s ease-in-out infinite;
        }
        
        @keyframes starPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .floating-badge {
            position: absolute;
            background: var(--neon-green-light);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(0,255,136,0.2);
            animation: float 3s ease-in-out infinite;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0); }
            50% { transform: translateY(-15px) translateX(5px); }
        }
        
        .badge-1 {
            top: -20px;
            right: -20px;
            animation-delay: 0s;
        }
        
        .badge-2 {
            bottom: 30px;
            left: -30px;
            animation-delay: 1s;
        }
        
        /* Section with fade-in on scroll */
        .section {
            padding: 80px 5%;
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }
        
        .section.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .section-title {
            text-align: center;
            font-size: clamp(1.8rem, 5vw, 2.5rem);
            font-weight: 700;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--text-white), var(--neon-green));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .section-subtitle {
            text-align: center;
            opacity: 0.6;
            margin-bottom: 50px;
            font-size: clamp(0.9rem, 3vw, 1rem);
        }
        
        .courses-grid, .internships-grid, .features-grid {
            display: grid;
            gap: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .courses-grid { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .internships-grid { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .features-grid { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
        
        .course-card, .internship-card, .feature {
            background: var(--bg-card);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-light);
            transition: all var(--transition-normal);
            opacity: 0;
            transform: translateY(30px);
            animation: cardFadeIn 0.6s ease forwards;
        }
        
        @keyframes cardFadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .course-card:nth-child(1) { animation-delay: 0.1s; }
        .course-card:nth-child(2) { animation-delay: 0.2s; }
        .course-card:nth-child(3) { animation-delay: 0.3s; }
        .internship-card:nth-child(1) { animation-delay: 0.1s; }
        .internship-card:nth-child(2) { animation-delay: 0.2s; }
        .internship-card:nth-child(3) { animation-delay: 0.3s; }
        
        .course-card:hover, .internship-card:hover, .feature:hover {
            transform: translateY(-10px);
            border-color: rgba(0,255,136,0.3);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        
        .course-image {
            height: 180px;
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            overflow: hidden;
            position: relative;
        }
        
        .course-image::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }
        
        .course-card:hover .course-image::after {
            left: 100%;
        }
        
        .course-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .course-card:hover .course-image img { transform: scale(1.05); }
        
        .course-content, .internship-card { padding: 24px; }
        .feature { text-align: center; padding: 35px 20px; }
        
        .course-title, .internship-title { font-size: 1.2rem; margin-bottom: 10px; }
        .course-desc, .internship-card p { opacity: 0.6; font-size: 0.85rem; margin-bottom: 15px; line-height: 1.5; }
        
        .rating-display {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
            flex-wrap: wrap;
        }
        .rating-stars { display: inline-flex; gap: 3px; }
        .rating-stars i { font-size: 0.85rem; transition: 0.2s; }
        .rating-stars i:hover { transform: scale(1.1); }
        .rating-value { font-weight: 600; color: var(--star-color); font-size: 0.85rem; }
        .rating-count { font-size: 0.75rem; opacity: 0.6; }
        
        .course-meta {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-top: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
            margin: 15px 0;
            font-size: 0.85rem;
            flex-wrap: wrap;
        }
        .price { color: var(--neon-green); font-weight: 700; }
        
        .company { color: var(--neon-green); font-size: 0.85rem; margin-bottom: 10px; }
        .details {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            font-size: 0.8rem;
            opacity: 0.6;
            flex-wrap: wrap;
        }
        
        .feature-icon { font-size: 2.2rem; margin-bottom: 15px; transition: 0.3s; }
        .feature:hover .feature-icon { transform: scale(1.1); }
        .feature h3 { font-size: 1.1rem; margin-bottom: 10px; }
        .feature p { font-size: 0.85rem; opacity: 0.6; }
        
        /* CTA Section with Animation */
        .cta {
            background: linear-gradient(135deg, var(--neon-green) 0%, var(--neon-green-dark) 100%);
            margin: 0 5% 60px;
            border-radius: var(--border-radius-lg);
            padding: 60px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .cta::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .cta h2 {
            font-size: clamp(1.5rem, 5vw, 2.2rem);
            color: #0a0a0a;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        
        .cta p {
            color: #0a0a0a;
            opacity: 0.8;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }
        
        .btn-dark {
            background: #0a0a0a;
            color: var(--text-white);
            padding: 12px 32px;
            border-radius: var(--border-radius-xl);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
            position: relative;
            z-index: 1;
        }
        
        .btn-dark:hover {
            transform: translateX(5px);
            background: #1a1a1a;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }
        
        .alert {
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 12px 20px;
            border-radius: var(--border-radius-sm);
            z-index: 1001;
            animation: slideInRight 0.3s ease;
        }
        .alert-success { background: rgba(0,255,136,0.2); border: 1px solid var(--neon-green); color: var(--neon-green); }
        .alert-error { background: rgba(255,68,68,0.2); border: 1px solid var(--danger); color: var(--danger); }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .footer {
            padding: 60px 5% 30px;
            border-top: 1px solid var(--border-light);
        }
        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        .footer-section h4 { margin-bottom: 20px; color: var(--neon-green); }
        .footer-section p, .footer-section a {
            opacity: 0.6;
            text-decoration: none;
            color: var(--text-white);
            line-height: 2;
            font-size: 0.85rem;
            transition: 0.3s;
        }
        .footer-section a:hover { opacity: 1; color: var(--neon-green); padding-left: 5px; }
        .footer-bottom { text-align: center; padding-top: 30px; border-top: 1px solid var(--border-light); opacity: 0.5; font-size: 0.8rem; }
        
        @media (max-width: 1024px) {
            .hero-content { grid-template-columns: 1fr; gap: 60px; }
            .hero h1, .hero p { text-align: center; }
            .btn-group, .stats { justify-content: center; }
            .badge { display: table; margin-left: auto; margin-right: auto; }
            .badge-1, .badge-2 { display: none; }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
                position: absolute;
                top: 60px;
                left: 0;
                right: 0;
                background: rgba(10,10,10,0.95);
                flex-direction: column;
                padding: 20px;
                gap: 15px;
            }
            .nav-links.show { display: flex; }
            .mobile-menu-btn { display: block; }
            .section { padding: 60px 5%; }
        }
        
        @media (max-width: 480px) {
            .btn-group { flex-direction: column; width: 100%; }
            .btn-primary, .btn-secondary { width: 100%; justify-content: center; }
            .stats { flex-direction: column; align-items: center; text-align: center; gap: 15px; }
            .course-meta, .details { flex-direction: column; align-items: center; text-align: center; }
            .footer-content { grid-template-columns: 1fr; text-align: center; }
        }
        
        .mt-2 { margin-top: 10px; }
    </style>
</head>
<body>

<div class="bg-pattern"></div>
<div class="animated-bg"></div>

<nav class="navbar" id="navbar">
    <a href="<?php echo BASE_URL; ?>index.php" class="logo">IT<span>Simplera.Institute</span></a>
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="fas fa-bars"></i>
    </button>
    <div class="nav-links" id="navLinks">
        <a href="#home">Home</a>
        <a href="#courses">Courses</a>
        <a href="#internships">Internships</a>
        <a href="#about">About</a>
        <?php if(isset($_SESSION['user_id'])): ?>
            <?php if($_SESSION['role'] == 'admin'): ?>
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn-filled">Dashboard</a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="btn-filled">Dashboard</a>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>logout.php" class="btn-outline">Logout</a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>login.php" class="btn-outline">Login</a>
            <a href="<?php echo BASE_URL; ?>register.php" class="btn-filled">Register</a>
        <?php endif; ?>
    </div>
</nav>

<?php displayFlashMessages(); ?>

<section class="hero" id="home">
    <div class="hero-content">
        <div>
            <div class="badge">✨ Trusted by 5000+ Students</div>
            <h1>Master Modern <span>IT Skills</span> with Industry Experts</h1>
            <p>Join ITsimplera and transform your career with hands-on training, real-world projects, and guaranteed internship opportunities.</p>
            <div class="btn-group">
                <a href="<?php echo BASE_URL; ?>register.php" class="btn-primary">Get Started Free <i class="fas fa-arrow-right"></i></a>
                <a href="#courses" class="btn-secondary"><i class="fas fa-play"></i> Explore Courses</a>
            </div>
            <div class="stats">
                <div class="stat-item"><h2 class="counter" data-target="<?php echo $total_courses; ?>">0</h2><p>Expert Courses</p></div>
                <div class="stat-item"><h2 class="counter" data-target="<?php echo $total_students; ?>">0</h2><p>Active Students</p></div>
                <div class="stat-item"><h2 class="counter" data-target="<?php echo $total_internships; ?>">0</h2><p>Open Internships</p></div>
                <div class="stat-item"><h2 class="counter" data-target="98">0</h2><p>Placement Rate</p></div>
            </div>
        </div>
        <div class="hero-right">
            <div class="feature-card">
                <div class="feature-icon">🚀</div>
                <h3>Learn. Grow. Succeed.</h3>
                <p>Join 5000+ successful graduates</p>
                <div class="rating">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    <p class="mt-2">Rated 4.9/5 by students</p>
                </div>
            </div>
            <div class="floating-badge badge-1"><i class="fas fa-certificate"></i><span>Certified Courses</span></div>
            <div class="floating-badge badge-2"><i class="fas fa-briefcase"></i><span>100% Job Assistance</span></div>
        </div>
    </div>
</section>

<section class="section" id="courses">
    <h2 class="section-title">Featured Courses</h2>
    <p class="section-subtitle">Industry-relevant programs designed for your success</p>
    <div class="courses-grid">
        <?php if(count($featured_courses) > 0): ?>
            <?php foreach($featured_courses as $course): ?>
                <div class="course-card">
                    <div class="course-image">
                        <?php if(!empty($course['thumbnail'])): ?>
                            <img src="<?php echo ASSETS_URL; ?>uploads/thumbnails/<?php echo htmlspecialchars($course['thumbnail']); ?>">
                        <?php else: ?>
                            📚
                        <?php endif; ?>
                    </div>
                    <div class="course-content">
                        <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                        <p class="course-desc"><?php echo substr(htmlspecialchars($course['description']), 0, 100); ?>...</p>
                        <div class="rating-display">
                            <div class="rating-stars">
                                <?php echo displayStars($course['avg_rating']); ?>
                            </div>
                            <span class="rating-value"><?php echo $course['avg_rating']; ?></span>
                            <span class="rating-count">(<?php echo $course['total_ratings']; ?> reviews)</span>
                        </div>
                        <div class="course-meta">
                            <span><i class="far fa-clock"></i> <?php echo htmlspecialchars($course['duration']); ?></span>
                            <span><i class="fas fa-users"></i> <?php echo $course['total_seats'] - $course['enrolled_seats']; ?> seats</span>
                        </div>
                        <div class="course-meta">
                            <span class="price">💰 Rs. <?php echo number_format($course['fee']); ?></span>
                            <span><i class="far fa-calendar-alt"></i> <?php echo date('M d', strtotime($course['start_date'])); ?></span>
                        </div>
                        <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'student'): ?>
                            <a href="<?php echo BASE_URL; ?>student/courses.php" class="btn-primary" style="width:100%; justify-content:center; margin-top:15px;">Enroll Now →</a>
                        <?php elseif(isset($_SESSION['user_id']) && $_SESSION['role'] == 'admin'): ?>
                            <a href="<?php echo BASE_URL; ?>admin/courses.php" class="btn-primary" style="width:100%; justify-content:center; margin-top:15px;">Manage →</a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>register.php" class="btn-primary" style="width:100%; justify-content:center; margin-top:15px;">Login to Enroll →</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center; padding:60px; background:rgba(255,255,255,0.03); border-radius:24px; grid-column:1/-1;">
                <p>No courses available yet. Check back soon!</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section" id="internships" style="background: rgba(0,255,136,0.02);">
    <h2 class="section-title">Open Internships</h2>
    <p class="section-subtitle">Gain real-world experience with top companies</p>
    <div class="internships-grid">
        <?php if(count($open_internships) > 0): ?>
            <?php foreach($open_internships as $internship): ?>
                <div class="internship-card">
                    <div class="company"><i class="fas fa-building"></i> <?php echo htmlspecialchars($internship['company_name']); ?></div>
                    <h3 class="internship-title"><?php echo htmlspecialchars($internship['title']); ?></h3>
                    <p><?php echo substr(htmlspecialchars($internship['description']), 0, 100); ?>...</p>
                    <div class="rating-display">
                        <div class="rating-stars">
                            <?php echo displayStars($internship['avg_rating']); ?>
                        </div>
                        <span class="rating-value"><?php echo $internship['avg_rating']; ?></span>
                        <span class="rating-count">(<?php echo $internship['total_ratings']; ?> reviews)</span>
                    </div>
                    <div class="details">
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($internship['location']); ?></span>
                        <span><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($internship['stipend']); ?></span>
                    </div>
                    <div class="details">
                        <span><i class="far fa-clock"></i> <?php echo htmlspecialchars($internship['duration']); ?></span>
                        <span><i class="far fa-calendar-alt"></i> <?php echo date('M d', strtotime($internship['deadline'])); ?></span>
                    </div>
                    <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'student'): ?>
                        <a href="<?php echo BASE_URL; ?>student/internships.php" class="btn-primary" style="width:100%; justify-content:center; margin-top:20px;">Apply Now →</a>
                    <?php elseif(isset($_SESSION['user_id']) && $_SESSION['role'] == 'admin'): ?>
                        <a href="<?php echo BASE_URL; ?>admin/internships.php" class="btn-primary" style="width:100%; justify-content:center; margin-top:20px;">Manage →</a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>register.php" class="btn-primary" style="width:100%; justify-content:center; margin-top:20px;">Login to Apply →</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center; padding:60px; background:rgba(255,255,255,0.03); border-radius:24px; grid-column:1/-1;">
                <p>No internships available at the moment.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section" id="about">
    <h2 class="section-title">Why Choose ITsimplera?</h2>
    <p class="section-subtitle">We're committed to your success</p>
    <div class="features-grid">
        <div class="feature"><div class="feature-icon">🎯</div><h3>Industry-Ready Skills</h3><p>Learn what employers actually need</p></div>
        <div class="feature"><div class="feature-icon">👨‍🏫</div><h3>Expert Instructors</h3><p>10+ years industry experience</p></div>
        <div class="feature"><div class="feature-icon">💼</div><h3>Guaranteed Internship</h3><p>Real experience before graduation</p></div>
        <div class="feature"><div class="feature-icon">🎓</div><h3>Lifetime Access</h3><p>Learn at your own pace</p></div>
    </div>
</section>

<section class="cta">
    <h2>Ready to Start Your Journey?</h2>
    <p>Join 5000+ students who transformed their careers</p>
    <a href="<?php echo BASE_URL; ?>register.php" class="btn-dark">Get Started Today <i class="fas fa-arrow-right"></i></a>
</section>

<footer class="footer">
    <div class="footer-content">
        <div class="footer-section">
            <h4>ITsimplera</h4>
            <p>Empowering IT education with industry-leading courses and guaranteed internships.</p>
        </div>
        <div class="footer-section">
            <h4>Quick Links</h4>
            <p><a href="#home">Home</a></p>
            <p><a href="#courses">Courses</a></p>
            <p><a href="#internships">Internships</a></p>
            <p><a href="#about">About</a></p>
        </div>
        <div class="footer-section">
            <h4>Contact</h4>
            <p><i class="fas fa-phone"></i> <a href="tel:+923079006441">+92 3079006441</a></p>
            <p><i class="fas fa-map-marker-alt"></i> Mardan, Pakistan</p>
        </div>
        <div class="footer-section">
            <h4>Follow Us</h4>
            <p><a href="https://web.facebook.com/ITSimpleraInstitute/" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook"></i> Facebook</a></p>
            <p><a href="https://www.linkedin.com/company/itsimplera-institute/" target="_blank" rel="noopener noreferrer"><i class="fab fa-linkedin"></i> LinkedIn</a></p>
            <p><a href="https://www.instagram.com/itsimplera.institute?igsh=a3lsYTh4YzVwenpi" target="_blank" rel="noopener noreferrer"><i class="fab fa-instagram"></i> Instagram</a></p>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> ITsimplera. All rights reserved. | Designed with <i class="fas fa-heart" style="color: #ff6b6b;"></i> for your success</p>
    </div>
</footer>

<script>
    var mobileMenuBtn = document.getElementById('mobileMenuBtn');
    var navLinks = document.getElementById('navLinks');
    
    if(mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            navLinks.classList.toggle('show');
        });
    }
    
    document.querySelectorAll('.nav-links a').forEach(function(link) {
        link.addEventListener('click', function() {
            if(window.innerWidth <= 768) {
                navLinks.classList.remove('show');
            }
        });
    });
    
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        var navbar = document.getElementById('navbar');
        if(window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    
    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            var targetId = this.getAttribute('href');
            if(targetId === '#') return;
            var target = document.querySelector(targetId);
            if(target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
    
    // Counter Animation
    function animateCounter(element, target) {
        var current = 0;
        var increment = target / 50;
        var timer = setInterval(function() {
            current += increment;
            if(current >= target) {
                element.innerText = target;
                clearInterval(timer);
            } else {
                element.innerText = Math.floor(current);
            }
        }, 30);
    }
    
    // Intersection Observer for counters
    var observerOptions = {
        threshold: 0.5,
        rootMargin: '0px'
    };
    
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if(entry.isIntersecting) {
                var counter = entry.target;
                var target = parseInt(counter.getAttribute('data-target'));
                animateCounter(counter, target);
                observer.unobserve(counter);
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.counter').forEach(function(counter) {
        observer.observe(counter);
    });
    
    // Section fade-in on scroll
    var sectionObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if(entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.2 });
    
    document.querySelectorAll('.section').forEach(function(section) {
        sectionObserver.observe(section);
    });
    
    // Auto hide alerts
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            setTimeout(function() {
                alert.style.opacity = '0';
                setTimeout(function() {
                    if(alert.parentNode) alert.remove();
                }, 300);
            }, 5000);
        });
    }, 100);
</script>
</body>
</html>