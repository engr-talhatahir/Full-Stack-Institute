<div align="center">
  <h1>🎓 ITsimplera - Future of IT Education</h1>
  <p>
    <strong>A comprehensive Educational Institute Management System built with PHP.</strong>
  </p>

  <!-- Badges -->
  <p>
    <img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP" />
    <img src="https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" />
    <img src="https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white" alt="HTML5" />
    <img src="https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white" alt="CSS3" />
    <img src="https://img.shields.io/badge/JavaScript-323330?style=for-the-badge&logo=javascript&logoColor=F7DF1E" alt="JavaScript" />
  </p>
</div>

---

## 🌟 About The Project

**ITsimplera** is a dynamic web application designed to manage the core operations of an IT educational institute. It provides a centralized platform for students to explore courses, apply for internships, and for administrators to manage the institute's offerings efficiently. 

With a user-friendly interface and robust backend architecture, ITsimplera aims to simplify the educational journey for aspiring IT professionals.

## ✨ Key Features

### 👨‍🎓 For Students:
- **Course Catalog:** Browse through a variety of featured IT courses.
- **Internship Opportunities:** View and apply for open internships.
- **Ratings & Reviews:** Check course and internship ratings to make informed decisions.
- **Student Dashboard:** Dedicated portal for enrolled students to manage their learning.
- **User Authentication:** Secure login and registration system.

### 👨‍💼 For Administrators:
- **Dashboard:** Overview of total students, active courses, and internships.
- **Course Management:** Add, edit, or remove courses.
- **Internship Management:** Post new internship openings and manage applications.
- **User Management:** Monitor and manage student accounts.

## 🛠️ Technologies Used

- **Frontend:** HTML5, CSS3, JavaScript
- **Backend:** PHP (Core)
- **Database:** MySQL
- **Architecture:** Custom MVC-like structure

## 📂 Project Structure

```text
Institute_project/
├── admin/          # Administrator dashboard and management scripts
├── assets/         # Static assets (CSS, JS, Images)
├── backend/        # Core backend logic and API endpoints
├── config/         # Configuration files (Database schema: itsimplera_db.sql)
├── includes/       # Reusable PHP components (db.php, functions.php, config.php)
├── student/        # Student portal and dashboard
├── index.php       # Landing page (Course & Internship listings)
├── login.php       # User login page
├── register.php    # User registration page
└── logout.php      # Session termination script
```

## 🚀 Getting Started

To get a local copy up and running, follow these simple steps.

### Prerequisites

- A local server environment like **XAMPP**, **WAMP**, or **MAMP**.
- PHP (version 7.4 or higher recommended).
- MySQL database.

### Installation

1. **Clone the repository or download the project files.**
   Move the project folder (`Institute_project`) into your local server's document root directory (e.g., `htdocs` for XAMPP or `www` for WAMP).

2. **Database Setup:**
   - Open phpMyAdmin or your preferred database manager.
   - Create a new database (e.g., `itsimplera_db`).
   - Import the provided SQL file located at `config/itsimplera_db.sql`.

3. **Configure Database Connection:**
   - Navigate to `includes/db.php`.
   - Update the database credentials (hostname, username, password, database name) to match your local setup.

4. **Run the Application:**
   - Open your web browser and go to `http://localhost/Institute_project/`.

## 🤝 Contributing

Contributions are what make the open-source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 🛡️ License

Distributed under the MIT License.

---
<div align="center">
  <i>Developed for IT Educational Institute Management.</i>
</div>
