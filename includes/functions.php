<?php
function setFlash($key, $message) {
    $_SESSION['flash'][$key] = $message;
}

function getFlash($key) {
    if (isset($_SESSION['flash'][$key])) {
        $message = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }
    return '';
}

function displayFlashMessages() {
    $success = getFlash('success');
    $error = getFlash('error');
    $warning = getFlash('warning');
    
    if ($success != '') {
        echo '<div class="alert alert-success">' . sanitize($success) . '</div>';
    }
    if ($error != '') {
        echo '<div class="alert alert-error">' . sanitize($error) . '</div>';
    }
    if ($warning != '') {
        echo '<div class="alert alert-warning">' . sanitize($warning) . '</div>';
    }
}

function sanitize($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

function uploadFile($file, $targetDir, $allowedTypes = array('jpg', 'jpeg', 'png', 'gif', 'pdf')) {
    if ($file['error'] != UPLOAD_ERR_OK) {
        return false;
    }
    
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedTypes)) {
        return false;
    }
    
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return false;
    }
    
    $fileName = uniqid() . '.' . $fileExt;
    $targetPath = $targetDir . '/' . $fileName;
    
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $fileName;
    }
    
    return false;
}

function getRoleBadge($role) {
    if ($role == 'admin') {
        return '<span class="badge badge-danger">Admin</span>';
    } else {
        return '<span class="badge badge-primary">Student</span>';
    }
}

function getStatusBadge($status) {
    if ($status == 'active' || $status == 'approved' || $status == 'open' || $status == 'selected') {
        return '<span class="badge badge-success">' . ucfirst($status) . '</span>';
    } elseif ($status == 'inactive' || $status == 'closed') {
        return '<span class="badge badge-warning">' . ucfirst($status) . '</span>';
    } elseif ($status == 'suspended' || $status == 'rejected') {
        return '<span class="badge badge-danger">' . ucfirst($status) . '</span>';
    } elseif ($status == 'pending') {
        return '<span class="badge badge-warning">Pending</span>';
    } elseif ($status == 'shortlisted') {
        return '<span class="badge badge-info">Shortlisted</span>';
    } else {
        return '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
    }
}

// CSRF Functions - PHP 5.6 Compatible
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        // Try multiple methods for different PHP versions
        if (function_exists('random_bytes')) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        } else {
            // Fallback for older PHP versions
            $_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true)) . md5(uniqid(mt_rand(), true));
        }
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] == $token;
}

// ============================================
// RATING FUNCTIONS
// ============================================

function getCourseRating($course_id, $conn) {
    $avg_rating = 0;
    $total_ratings = 0;
    
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM course_ratings WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $avg_rating = round($row['avg_rating'], 1);
        $total_ratings = $row['total'];
    }
    $stmt->close();
    
    return array('avg' => $avg_rating, 'total' => $total_ratings);
}

function getUserCourseRating($course_id, $user_id, $conn) {
    $user_rating = 0;
    $stmt = $conn->prepare("SELECT rating FROM course_ratings WHERE course_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $course_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $user_rating = $row['rating'];
    }
    $stmt->close();
    return $user_rating;
}

function getInternshipRating($internship_id, $conn) {
    $avg_rating = 0;
    $total_ratings = 0;
    
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM internship_ratings WHERE internship_id = ?");
    $stmt->bind_param("i", $internship_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $avg_rating = round($row['avg_rating'], 1);
        $total_ratings = $row['total'];
    }
    $stmt->close();
    
    return array('avg' => $avg_rating, 'total' => $total_ratings);
}

function getUserInternshipRating($internship_id, $user_id, $conn) {
    $user_rating = 0;
    $stmt = $conn->prepare("SELECT rating FROM internship_ratings WHERE internship_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $internship_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $user_rating = $row['rating'];
    }
    $stmt->close();
    return $user_rating;
}

function displayStars($rating) {
    $stars = '';
    $full_stars = floor($rating);
    $half_star = ($rating - $full_stars) >= 0.5 ? true : false;
    
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full_stars) {
            $stars .= '<i class="fas fa-star"></i>';
        } elseif ($half_star && $i == $full_stars + 1) {
            $stars .= '<i class="fas fa-star-half-alt"></i>';
        } else {
            $stars .= '<i class="far fa-star"></i>';
        }
    }
    
    return $stars;
}

function displayInteractiveStars($name, $value = 0) {
    $stars = '<div class="star-rating-input" data-rating-name="' . $name . '">';
    for ($i = 5; $i >= 1; $i--) {
        $checked = ($value == $i) ? 'checked' : '';
        $stars .= '<input type="radio" id="star' . $i . '_' . $name . '" name="' . $name . '" value="' . $i . '" ' . $checked . ' />';
        $stars .= '<label for="star' . $i . '_' . $name . '">★</label>';
    }
    $stars .= '</div>';
    return $stars;
}
?>