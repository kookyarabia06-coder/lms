<?php

require_once __DIR__ . '/../inc/config.php';


$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.description, c.thumbnail, c.file_pdf, c.file_video,
           e.status AS enroll_status, e.progress
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
    WHERE c.is_active = 1
    ORDER BY c.id DESC
");

$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate counters
$counter = ['total_courses' => 0, 'not_enrolled' => 0];
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM courses WHERE is_active = 1");
$counter['total_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$avail = ['total_users' => 0];
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM users");
$avail['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
//added tweak for welcome page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to EduLearn - Learning Management System</title>
    <link href="<?= BASE_URL ?>/assets/css/index.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>


        <div class="welcome-container"   >
            <div class="auth-buttons">
                <a href="<?= BASE_URL ?>/public/login.php" class="auth-btn login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                        Log-In
                </a>
                <a href="<?= BASE_URL ?>/public/register.php" class="auth-btn register-btn">
                    <i class="fas fa-user-plus"></i>
                        Create Account
                </a>
            </div>
        </div>
   
        <!-- Hero Section -->
        <div class="welcome-hero">
            <h1>Welcome to Learning Management System</h1>
            <p>Transform your learning experience with our comprehensive Learning Management System. 
               Access courses, track progress, and connect with educators in one seamless platform.</p>
              
        
        <!-- Features Section -->
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3>Interactive Courses</h3>
                <p>Engage with multimedia content, quizzes, and interactive assignments designed to enhance your learning experience.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Progress Tracking</h3>
                <p>Monitor your learning journey with detailed analytics and progress reports to stay on track with your goals.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3>Self-Paced Learning</h3>
                <p>Courses are designed to be completed at your own pace, allowing you to learn whenever and wherever it's convenient for you.</p>
            </div>
        </div>
        
        <!-- Stats Section -->
        <div class="welcome-stats">
            <div class="stat-item">
                <div class="stat-number"><?= $counter['total_courses'] ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $avail['total_users'] ?></div>
                <div class="stat-label">Active Learners</div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="welcome-footer">
            <p>&copy; <?= date('Y') ?> EduLearn Learning Management System. All rights reserved.</p>
            <div class="footer-links" style="margin-top: 10px;">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Contact Us</a>
            </div>
        </footer>
    </div>

    <script>
        // Simple animations on scroll
        document.addEventListener('DOMContentLoaded', function() {
            const featureCards = document.querySelectorAll('.feature-card');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });
            
            featureCards.forEach(card => {
                observer.observe(card);
            });
        });
    </script>
</body>
</html>