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

// Generate unique certificate code
function generateCertificateCode($student_id, $course_id) {
    return 'ITS-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6)) . '-' . $student_id;
}

// Handle add/edit certificate
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        if (isset($_POST['add_certificate'])) {
            $student_id = intval($_POST['student_id']);
            $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : NULL;
            $total_marks = intval($_POST['total_marks']);
            $obtained_marks = intval($_POST['obtained_marks']);
            $percentage = ($obtained_marks / $total_marks) * 100;
            $grade = $_POST['grade'];
            $status = $_POST['status'];
            $issue_date = $_POST['issue_date'];
            
            // Generate unique certificate code
            $certificate_code = generateCertificateCode($student_id, $course_id);
            
            // Check if student already has a certificate
            $check_stmt = $conn->prepare("SELECT id FROM student_certificates WHERE student_id = ?");
            $check_stmt->bind_param("i", $student_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = "This student already has a certificate!";
            } else {
                $stmt = $conn->prepare("INSERT INTO student_certificates (student_id, course_id, certificate_code, total_marks, obtained_marks, percentage, grade, status, issue_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iisiddsss", $student_id, $course_id, $certificate_code, $total_marks, $obtained_marks, $percentage, $grade, $status, $issue_date);
                
                if ($stmt->execute()) {
                    $message = "Certificate added successfully! Code: " . $certificate_code;
                } else {
                    $error = "Failed to add certificate: " . $stmt->error;
                }
                $stmt->close();
            }
            $check_stmt->close();
        }
        
        elseif (isset($_POST['update_certificate'])) {
            $cert_id = intval($_POST['cert_id']);
            $total_marks = intval($_POST['total_marks']);
            $obtained_marks = intval($_POST['obtained_marks']);
            $percentage = ($obtained_marks / $total_marks) * 100;
            $grade = $_POST['grade'];
            $status = $_POST['status'];
            $issue_date = $_POST['issue_date'];
            
            $stmt = $conn->prepare("UPDATE student_certificates SET total_marks = ?, obtained_marks = ?, percentage = ?, grade = ?, status = ?, issue_date = ? WHERE id = ?");
            $stmt->bind_param("iidsssi", $total_marks, $obtained_marks, $percentage, $grade, $status, $issue_date, $cert_id);
            
            if ($stmt->execute()) {
                $message = "Certificate updated successfully!";
            } else {
                $error = "Failed to update certificate";
            }
            $stmt->close();
        }
        
        elseif (isset($_POST['delete_certificate'])) {
            $cert_id = intval($_POST['cert_id']);
            $stmt = $conn->prepare("DELETE FROM student_certificates WHERE id = ?");
            $stmt->bind_param("i", $cert_id);
            if ($stmt->execute()) {
                $message = "Certificate deleted successfully!";
            } else {
                $error = "Failed to delete certificate";
            }
            $stmt->close();
        }
    }
}

// Get all certificates with student and course info
$sql = "SELECT sc.*, u.full_name, u.email, u.phone, c.title as course_title 
        FROM student_certificates sc 
        JOIN users u ON sc.student_id = u.id 
        LEFT JOIN courses c ON sc.course_id = c.id 
        ORDER BY sc.created_at DESC";
$certificates = $conn->query($sql);

// Get all students for dropdown
$students = $conn->query("SELECT id, full_name, email FROM users WHERE role = 'student' ORDER BY full_name");

// Get all courses for dropdown
$courses = $conn->query("SELECT id, title FROM courses WHERE status = 'active' ORDER BY title");

$csrf_token = generateCSRFToken();
$pageTitle = "Manage Certificates - ITsimplera";
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
            --bg-card: rgba(255,255,255,0.05);
            --neon-green: #00ff88;
            --neon-green-dark: #00cc6a;
            --text-white: #ffffff;
            --text-gray: #a0a0a0;
            --border-light: rgba(255,255,255,0.08);
            --success: #00ff88;
            --danger: #ff4444;
            --warning: #ffaa00;
            --info: #00ccff;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-dark);
            color: var(--text-white);
            overflow-x: hidden;
        }
        .bg-pattern {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: linear-gradient(45deg, #1a1a1a 2%, transparent 2%), linear-gradient(-45deg, #1a1a1a 2%, transparent 2%);
            background-size: 40px 40px; opacity: 0.3; z-index: -1;
        }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-dark); }
        ::-webkit-scrollbar-thumb { background: var(--neon-green); border-radius: 10px; }
        
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px; background: rgba(10,10,10,0.95); border-right: 1px solid var(--border-light);
            position: fixed; height: 100vh; overflow-y: auto; transition: transform 0.3s ease; z-index: 1000;
        }
        .sidebar-header { padding: 25px; border-bottom: 1px solid var(--border-light); text-align: center; }
        .sidebar-header h3 { background: linear-gradient(135deg, var(--neon-green), #00cc6a); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .sidebar-nav { padding: 20px 0; }
        .sidebar-nav a {
            display: flex; align-items: center; padding: 12px 25px; color: var(--text-gray); text-decoration: none; transition: 0.3s; gap: 12px; font-size: 0.95rem;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: rgba(255,255,255,0.05); color: var(--neon-green); border-left: 3px solid var(--neon-green);
        }
        .main-content {
            flex: 1; margin-left: 280px; padding: 20px 25px; width: calc(100% - 280px);
        }
        .top-bar {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px;
            border-bottom: 1px solid var(--border-light); flex-wrap: wrap; gap: 15px;
        }
        .welcome h2 { font-size: 1.3rem; margin-bottom: 5px; }
        .welcome p { color: var(--text-gray); font-size: 0.85rem; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 45px; height: 45px; border-radius: 50%; border: 2px solid var(--neon-green); object-fit: cover; }
        .mobile-menu-btn { display: none; background: none; border: none; font-size: 1.5rem; color: var(--text-white); cursor: pointer; padding: 8px; }
        
        .stats-row { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .stat-card-mini {
            background: linear-gradient(135deg, rgba(0,255,136,0.08), rgba(0,255,136,0.02));
            border: 1px solid rgba(0,255,136,0.2); border-radius: 16px; padding: 18px 25px; flex: 1; min-width: 140px; text-align: center;
        }
        .stat-card-mini h4 { font-size: 2rem; font-weight: 700; color: var(--neon-green); margin-bottom: 8px; }
        .stat-card-mini p { font-size: 0.8rem; color: var(--text-gray); }
        
        .btn-add {
            background: var(--neon-green); color: #0a0a0a; padding: 12px 24px; border-radius: 10px; text-decoration: none;
            font-weight: 600; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px; border: none; cursor: pointer;
        }
        .btn-add:hover { background: var(--neon-green-dark); transform: translateY(-2px); }
        
        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 2000; align-items: center; justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: #1a1a1a; border-radius: 20px; max-width: 500px; width: 90%; padding: 30px;
            border: 1px solid var(--border-light); max-height: 90vh; overflow-y: auto;
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-gray); }
        .close-modal:hover { color: var(--danger); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-gray); }
        .form-control {
            width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light);
            border-radius: 10px; color: var(--text-white); font-size: 0.85rem;
        }
        .form-control:focus { outline: none; border-color: var(--neon-green); }
        .btn-submit {
            width: 100%; padding: 12px; background: var(--neon-green); color: #0a0a0a;
            border: none; border-radius: 10px; font-weight: 600; cursor: pointer;
        }
        
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid var(--border-light); }
        th { color: var(--text-gray); font-weight: 500; background: rgba(255,255,255,0.02); }
        tr:hover { background: rgba(255,255,255,0.02); }
        
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .status-Pending { background: rgba(255,170,0,0.15); color: var(--warning); }
        .status-Certified { background: rgba(0,255,136,0.15); color: var(--success); }
        .status-Rejected { background: rgba(255,68,68,0.15); color: var(--danger); }
        
        .btn-icon { padding: 5px 12px; border-radius: 6px; text-decoration: none; font-size: 0.7rem; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; border: none; }
        .btn-edit { background: rgba(0,204,255,0.15); color: var(--info); }
        .btn-delete { background: rgba(255,68,68,0.15); color: var(--danger); }
        
        .alert { padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 0.85rem; }
        .alert-success { background: rgba(0,255,136,0.1); border: 1px solid var(--success); color: var(--success); }
        .alert-danger { background: rgba(255,68,68,0.1); border: 1px solid var(--danger); color: var(--danger); }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; padding: 15px; }
            .mobile-menu-btn { display: block; }
            .stats-row { flex-direction: column; }
            th, td { padding: 8px 6px; font-size: 0.75rem; }
        }
    </style>
</head>
<body>
<div class="bg-pattern"></div>

<!-- Add/Edit Modal -->
<div class="modal" id="certificateModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add Certificate</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" action="" id="certificateForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="cert_id" id="cert_id">
            <input type="hidden" name="add_certificate" id="add_certificate" value="1">
            
            <div class="form-group">
                <label>Student <span class="required">*</span></label>
                <select name="student_id" id="student_id" class="form-control" required>
                    <option value="">Select Student</option>
                    <?php while($student = $students->fetch_assoc()): ?>
                        <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['full_name']) . " (" . $student['email'] . ")"; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Course (Optional)</label>
                <select name="course_id" id="course_id" class="form-control">
                    <option value="">Select Course</option>
                    <?php 
                    $courses->data_seek(0);
                    while($course = $courses->fetch_assoc()): ?>
                        <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Total Marks <span class="required">*</span></label>
                <input type="number" name="total_marks" id="total_marks" class="form-control" required min="1">
            </div>
            
            <div class="form-group">
                <label>Obtained Marks <span class="required">*</span></label>
                <input type="number" name="obtained_marks" id="obtained_marks" class="form-control" required min="0" onchange="calculateGrade()">
            </div>
            
            <div class="form-group">
                <label>Grade <span class="required">*</span></label>
                <select name="grade" id="grade" class="form-control" required>
                    <option value="A+">A+ (90-100%)</option>
                    <option value="A">A (80-89%)</option>
                    <option value="B+">B+ (75-79%)</option>
                    <option value="B">B (70-74%)</option>
                    <option value="C+">C+ (65-69%)</option>
                    <option value="C">C (60-64%)</option>
                    <option value="D">D (50-59%)</option>
                    <option value="F">F (Below 50%)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Status <span class="required">*</span></label>
                <select name="status" id="status" class="form-control" required>
                    <option value="Pending">Pending</option>
                    <option value="Certified">Certified</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Issue Date <span class="required">*</span></label>
                <input type="date" name="issue_date" id="issue_date" class="form-control" required>
            </div>
            
            <button type="submit" class="btn-submit" id="submitBtn">Add Certificate</button>
        </form>
    </div>
</div>

<div class="dashboard-container">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>ITSimplera.Institute</h3>
            <p style="font-size: 0.7rem;">Admin Panel</p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="students.php"><i class="fas fa-users"></i> Students</a>
            <a href="courses.php"><i class="fas fa-book"></i> Courses</a>
            <a href="course-enrollments.php"><i class="fas fa-graduation-cap"></i> Enrollments</a>
            <a href="internships.php"><i class="fas fa-briefcase"></i> Internships</a>
            <a href="internship-applications.php"><i class="fas fa-file-alt"></i> Applications</a>
            <a href="certificates.php" class="active"><i class="fas fa-award"></i> Certificates</a>
            <a href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
                <div class="welcome">
                    <h2>Manage Certificates</h2>
                    <p>Issue, verify, and manage student certificates</p>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <?php
            $total_certs = $conn->query("SELECT COUNT(*) as count FROM student_certificates")->fetch_assoc()['count'];
            $pending_certs = $conn->query("SELECT COUNT(*) as count FROM student_certificates WHERE status='Pending'")->fetch_assoc()['count'];
            $certified_certs = $conn->query("SELECT COUNT(*) as count FROM student_certificates WHERE status='Certified'")->fetch_assoc()['count'];
            ?>
            <div class="stat-card-mini"><h4><?php echo $total_certs; ?></h4><p>Total Certificates</p></div>
            <div class="stat-card-mini"><h4><?php echo $pending_certs; ?></h4><p>Pending</p></div>
            <div class="stat-card-mini"><h4><?php echo $certified_certs; ?></h4><p>Certified</p></div>
        </div>

        <?php if($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <button class="btn-add" onclick="openAddModal()">
            <i class="fas fa-plus-circle"></i> Issue New Certificate
        </button>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Certificate Code</th>
                        <th>Marks</th>
                        <th>Percentage</th>
                        <th>Grade</th>
                        <th>Status</th>
                        <th>Issue Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($certificates && $certificates->num_rows > 0): ?>
                        <?php while($cert = $certificates->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $cert['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($cert['full_name']); ?></strong><br>
                                    <small style="color: var(--text-gray);"><?php echo htmlspecialchars($cert['email']); ?></small>
                                 </div>
                                <td><?php echo htmlspecialchars($cert['course_title'] ?: 'N/A'); ?> </div>
                                <td>
                                    <code style="background: rgba(0,255,136,0.1); padding: 4px 8px; border-radius: 6px;">
                                        <?php echo htmlspecialchars($cert['certificate_code']); ?>
                                    </code>
                                 </div>
                                <td><?php echo $cert['obtained_marks']; ?> / <?php echo $cert['total_marks']; ?> </div>
                                <td><?php echo round($cert['percentage'], 2); ?>%</div>
                                <td><strong>Grade <?php echo htmlspecialchars($cert['grade']); ?></strong></div>
                                <td><span class="status-badge status-<?php echo $cert['status']; ?>"><?php echo $cert['status']; ?></span></div>
                                <td><?php echo date('M d, Y', strtotime($cert['issue_date'])); ?></div>
                                <td>
                                    <button onclick="editCertificate(<?php echo htmlspecialchars(json_encode($cert)); ?>)" class="btn-icon btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button onclick="deleteCertificate(<?php echo $cert['id']; ?>)" class="btn-icon btn-delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                 </div>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="10" style="text-align: center;">No certificates issued yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    var mobileMenuBtn = document.getElementById('mobileMenuBtn');
    var sidebar = document.getElementById('sidebar');
    if(mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() { sidebar.classList.toggle('active'); });
    }
    
    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Issue New Certificate';
        document.getElementById('certificateForm').reset();
        document.getElementById('cert_id').value = '';
        document.getElementById('add_certificate').value = '1';
        document.getElementById('submitBtn').innerText = 'Add Certificate';
        document.getElementById('certificateModal').classList.add('active');
        
        // Set default issue date to today
        var today = new Date().toISOString().split('T')[0];
        document.getElementById('issue_date').value = today;
    }
    
    function editCertificate(cert) {
        document.getElementById('modalTitle').innerText = 'Edit Certificate';
        document.getElementById('cert_id').value = cert.id;
        document.getElementById('student_id').value = cert.student_id;
        document.getElementById('course_id').value = cert.course_id || '';
        document.getElementById('total_marks').value = cert.total_marks;
        document.getElementById('obtained_marks').value = cert.obtained_marks;
        document.getElementById('grade').value = cert.grade;
        document.getElementById('status').value = cert.status;
        document.getElementById('issue_date').value = cert.issue_date;
        
        // Change form action to update
        var updateInput = document.createElement('input');
        updateInput.type = 'hidden';
        updateInput.name = 'update_certificate';
        updateInput.value = '1';
        document.getElementById('certificateForm').appendChild(updateInput);
        document.getElementById('add_certificate').value = '';
        document.getElementById('submitBtn').innerText = 'Update Certificate';
        
        document.getElementById('certificateModal').classList.add('active');
    }
    
    function deleteCertificate(id) {
        if(confirm('Are you sure you want to delete this certificate?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            var csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?php echo $csrf_token; ?>';
            form.appendChild(csrfInput);
            
            var deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete_certificate';
            deleteInput.value = '1';
            form.appendChild(deleteInput);
            
            var idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'cert_id';
            idInput.value = id;
            form.appendChild(idInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    function closeModal() {
        document.getElementById('certificateModal').classList.remove('active');
        // Remove update_certificate input if exists
        var updateInput = document.querySelector('input[name="update_certificate"]');
        if(updateInput) updateInput.remove();
    }
    
    function calculateGrade() {
        var total = parseInt(document.getElementById('total_marks').value);
        var obtained = parseInt(document.getElementById('obtained_marks').value);
        if(total && obtained) {
            var percentage = (obtained / total) * 100;
            var gradeSelect = document.getElementById('grade');
            if(percentage >= 90) gradeSelect.value = 'A+';
            else if(percentage >= 80) gradeSelect.value = 'A';
            else if(percentage >= 75) gradeSelect.value = 'B+';
            else if(percentage >= 70) gradeSelect.value = 'B';
            else if(percentage >= 65) gradeSelect.value = 'C+';
            else if(percentage >= 60) gradeSelect.value = 'C';
            else if(percentage >= 50) gradeSelect.value = 'D';
            else gradeSelect.value = 'F';
        }
    }
    
    window.onclick = function(event) {
        var modal = document.getElementById('certificateModal');
        if (event.target == modal) closeModal();
    }
    
    // Set default issue date
    var today = new Date().toISOString().split('T')[0];
    if(document.getElementById('issue_date')) document.getElementById('issue_date').value = today;
</script>
</body>
</html>