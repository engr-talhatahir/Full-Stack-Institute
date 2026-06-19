-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 23, 2026 at 09:08 AM
-- Server version: 5.7.19
-- PHP Version: 5.6.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `itsimplera_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
CREATE TABLE IF NOT EXISTS `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text,
  `duration` varchar(50) DEFAULT NULL,
  `fee` decimal(10,2) DEFAULT NULL,
  `total_seats` int(11) DEFAULT NULL,
  `enrolled_seats` int(11) DEFAULT '0',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `duration`, `fee`, `total_seats`, `enrolled_seats`, `start_date`, `end_date`, `thumbnail`, `status`, `created_by`, `created_at`) VALUES
(6, 'Digital Marketing', 'SEO, Social Media, Google Ads', '2.5 months', '20000.00', 25, 0, '2026-07-01', '2026-09-15', NULL, 'active', 1, '2026-05-23 09:06:53'),
(5, 'Graphic Design', 'Photoshop, Illustrator, CorelDraw', '2 months', '15000.00', 20, 0, '2026-06-15', '2026-08-15', NULL, 'active', 1, '2026-05-23 09:06:53'),
(4, 'Web Development', 'Learn HTML, CSS, JavaScript, PHP', '3 months', '25000.00', 30, 0, '2026-06-01', '2026-08-31', NULL, 'active', 1, '2026-05-23 09:06:53');

-- --------------------------------------------------------

--
-- Table structure for table `course_enrollments`
--

DROP TABLE IF EXISTS `course_enrollments`;
CREATE TABLE IF NOT EXISTS `course_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`student_id`,`course_id`),
  KEY `course_id` (`course_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `internships`
--

DROP TABLE IF EXISTS `internships`;
CREATE TABLE IF NOT EXISTS `internships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `description` text,
  `location` varchar(100) DEFAULT NULL,
  `stipend` decimal(10,2) DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `total_slots` int(11) DEFAULT NULL,
  `applied_count` int(11) DEFAULT '0',
  `status` enum('open','closed') DEFAULT 'open',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `internships`
--

INSERT INTO `internships` (`id`, `title`, `company_name`, `description`, `location`, `stipend`, `duration`, `deadline`, `total_slots`, `applied_count`, `status`, `created_by`, `created_at`) VALUES
(5, 'Frontend Intern', 'Web Masters', 'React.js intern needed', 'Karachi', '10000.00', '2 months', '2026-07-15', 3, 0, 'open', 1, '2026-05-23 09:06:54'),
(4, 'PHP Developer Intern', 'Tech Solutions', 'Looking for PHP intern', 'Lahore', '15000.00', '3 months', '2026-07-30', 5, 0, 'open', 1, '2026-05-23 09:06:54');

-- --------------------------------------------------------

--
-- Table structure for table `internship_applications`
--

DROP TABLE IF EXISTS `internship_applications`;
CREATE TABLE IF NOT EXISTS `internship_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) DEFAULT NULL,
  `internship_id` int(11) DEFAULT NULL,
  `cover_letter` text,
  `resume_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','shortlisted','rejected','selected') DEFAULT 'pending',
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_application` (`student_id`,`internship_id`),
  KEY `internship_id` (`internship_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `cnic` varchar(20) DEFAULT NULL,
  `address` text,
  `profile_pic` varchar(255) DEFAULT 'default-avatar.png',
  `role` enum('student','admin') DEFAULT 'student',
  `status` enum('active','suspended') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `cnic` (`cnic`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `cnic`, `address`, `profile_pic`, `role`, `status`, `created_at`) VALUES
(3, 'Admin User', 'admin@institute.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, NULL, 'default-avatar.png', 'admin', 'active', '2026-05-23 09:06:53'),
(4, 'John Doe', 'student@institute.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1234567890', '12345-6789012-3', '123 Main St', 'default-avatar.png', 'student', 'active', '2026-05-23 09:06:53');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
