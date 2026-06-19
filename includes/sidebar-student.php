<?php
// Student Sidebar
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h3>Student Portal</h3>
    </div>
    <nav class="sidebar-nav">
        <a href="/student/dashboard.php" <?php echo $current_page == 'dashboard.php' ? 'class="active"' : ''; ?>>
            📊 Dashboard
        </a>
        <a href="/student/profile.php" <?php echo $current_page == 'profile.php' ? 'class="active"' : ''; ?>>
            👤 My Profile
        </a>
        <a href="/student/courses.php" <?php echo $current_page == 'courses.php' ? 'class="active"' : ''; ?>>
            📚 Browse Courses
        </a>
        <a href="/student/my-courses.php" <?php echo $current_page == 'my-courses.php' ? 'class="active"' : ''; ?>>
            🎓 My Courses
        </a>
        <a href="/student/internships.php" <?php echo $current_page == 'internships.php' ? 'class="active"' : ''; ?>>
            💼 Internships
        </a>
        <a href="/student/my-applications.php" <?php echo $current_page == 'my-applications.php' ? 'class="active"' : ''; ?>>
            📝 My Applications
        </a>
        <a href="/logout.php">
            🚪 Logout
        </a>
    </nav>
</div>
<div class="main-content">
    <div class="top-header">
        <h2>Welcome, <?php echo sanitize($_SESSION['full_name']); ?></h2>
        <div class="user-menu">
            <?php 
            $profile_pic = isset($_SESSION['profile_pic']) ? $_SESSION['profile_pic'] : 'default.png';
            ?>
            <img src="/assets/uploads/profiles/<?php echo sanitize($profile_pic); ?>" class="user-avatar" alt="Avatar">
        </div>
    </div>
    <div class="content-wrapper">
        <?php displayFlashMessages(); ?>