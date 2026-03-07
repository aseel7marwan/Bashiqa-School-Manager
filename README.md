<div align="center">

# 🏫 Bashiqa High School Management System

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

A digital platform designed for **Bashiqa High School for Boys** to manage school news, student information, and educational resources.

---

### 🔴 Live Demo

<a href="https://bashiqa.infinityfree.me/" target="_blank">
  <img src="https://img.shields.io/badge/🌐_LIVE_DEMO-Visit_Website-1a7431?style=for-the-badge&logoColor=white" alt="Live Demo" />
</a>

**[https://bashiqa.infinityfree.me/](https://bashiqa.infinityfree.me/)**

</div>

---

## 📖 About The Project

The **Bashiqa High School Management System** is a comprehensive web-based platform built to serve the Bashiqa High School for Boys community. It digitalizes administrative tasks, streamlines daily operations, and provides a centralized hub for managing students, teachers, grades, attendance, and school events.

The system supports **Arabic (RTL)** and **English** interfaces, features a role-based access control system with four distinct roles (Admin, Assistant, Teacher, Student), and is designed to be fully responsive for mobile devices.

---

## ✨ Key Features

| Module                     | Description                                                                         |
| -------------------------- | ----------------------------------------------------------------------------------- |
| 📰 **Dynamic News Feed**   | Public-facing welcome page with school news and announcements                       |
| 🔐 **Secure Admin Panel**  | Role-based dashboard with granular permissions (Admin, Assistant, Teacher, Student) |
| 👨‍🎓 **Student Management**  | Full student records, enrollment, profiles, and ID card generation                  |
| 👨‍🏫 **Teacher Management**  | Teacher profiles, subject assignments, and document tracking                        |
| 📊 **Grades System**       | Term-based and monthly grading with automatic average calculations                  |
| ✅ **Attendance Tracking** | Per-lesson attendance recording for students and teachers                           |
| 📅 **Weekly Schedules**    | Visual timetable management per class and section                                   |
| 📋 **Leave Management**    | Sick, regular, and emergency leave tracking with reports                            |
| 🎉 **School Events**       | Event and holiday calendar management                                               |
| 🖥️ **Classroom Equipment** | Inventory tracking for classroom assets                                             |
| 📈 **Reports & Exports**   | Comprehensive reporting with Excel/PDF export capabilities                          |
| 💾 **Backup System**       | Automated and manual database backup with scheduled cleanup                         |
| 📝 **Activity Logging**    | Full audit trail of all system operations                                           |
| 📱 **Mobile-Responsive**   | Fully responsive design optimized for all screen sizes                              |
| 🌐 **Bilingual Support**   | Arabic (RTL) and English interface with live language switching                     |
| 🌙 **Dark Mode**           | Light and dark theme toggle                                                         |

---

## 🛠️ Tech Stack

- **Backend:** PHP (PDO with prepared statements)
- **Database:** MySQL / MariaDB (utf8mb4)
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Security:** bcrypt password hashing, CSRF protection, session fingerprinting, rate limiting
- **Server:** Apache with `.htaccess` security headers and URL rewriting

---

## 🚀 Installation

1. **Clone the repository**

   ```bash
   git clone https://github.com/aseel7marwan/School-Manager.git
   ```

2. **Set up your environment**
   - Install [XAMPP](https://www.apachefriends.org/) (or any PHP + MySQL stack)
   - Place the project folder in your web server root (e.g., `htdocs/`)

3. **Configure the database**
   - Copy `config/database.php.example` or create `config/database.php`
   - Fill in your database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'school_db');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```

4. **Run the installer**
   - Navigate to `http://localhost/School-Manager/install`
   - The installer will automatically create the database schema and a default admin account

5. **Login**
   - **Username:** `admin`
   - **Password:** `password`
   - ⚠️ _Change the default password immediately after first login_

---

## ⚖️ License & Intellectual Property

> **⚠️ Proprietary / Showcase Only**
> This repository serves primarily as a technical portfolio piece. The architecture, concepts, and custom source code are proprietary. Unauthorized commercial use, modification, or distribution is strictly prohibited.

## 👤 Author & Contact

**Aseel Marwan Kheder**
_Full-Stack Softwareentwickler | Experte für DSGVO-konforme Plattformen & Native Apps_

[![LinkedIn](https://img.shields.io/badge/LinkedIn-0A66C2?style=for-the-badge&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/aseel-marwan-kheder-36b17033b/)
[![GitHub](https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white)](https://github.com/aseel7marwan)

📧 **Email:** [aseel.marwan.kheder@gmail.com](mailto:aseel.marwan.kheder@gmail.com)
