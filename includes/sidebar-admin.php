<?php
// Admin Sidebar
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h3>Admin Panel</h3>
    </div>
    <nav class="sidebar-nav">
        <a href="/admin/dashboard.php" <?php echo $current_page == 'dashboard.php' ? 'class="active"' : ''; ?>>
            📊 Dashboard
        </a>
        <a href="/admin/students.php" <?php echo $current_page == 'students.php' ? 'class="active"' : ''; ?>>
            👥 Students
        </a>
        <a href="/admin/courses.php" <?php echo ($current_page == 'courses.php' || $current_page == 'add-course.php' || $current_page == 'edit-course.php') ? 'class="active"' : ''; ?>>
            📚 Courses
        </a>
        <a href="/admin/course-enrollments.php" <?php echo $current_page == 'course-enrollments.php' ? 'class="active"' : ''; ?>>
            📝 Enrollments
        </a>
        <a href="/admin/internships.php" <?php echo ($current_page == 'internships.php' || $current_page == 'add-internship.php' || $current_page == 'edit-internship.php') ? 'class="active"' : ''; ?>>
            💼 Internships
        </a>
        <a href="/admin/internship-applications.php" <?php echo $current_page == 'internship-applications.php' ? 'class="active"' : ''; ?>>
            📄 Applications
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