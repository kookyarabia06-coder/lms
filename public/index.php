<?php
require_once __DIR__ . '/../inc/config.php';
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
    <div class="welcome-container">
        <!-- Hero Section -->
        <div class="welcome-hero">
            <h1>Welcome to Learning Management System</h1>
            <p>Transform your learning experience with our comprehensive Learning Management System. 
               Access courses, track progress, and connect with educators in one seamless platform.</p>
        </div>
        
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
                    <i class="fas fa-users"></i>
                </div>
                <h3>Community Learning</h3>
                <p>Connect with peers and instructors through discussion forums, group projects, and collaborative tools.</p>
            </div>
        </div>
        
        <!-- Stats Section -->
        <div class="welcome-stats">
            <div class="stat-item">
                <div class="stat-number">500+</div>
                <div class="stat-label">Courses Available</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">10K+</div>
                <div class="stat-label">Active Learners</div>
            </div>
        </div>
        
        <!-- Authentication Section -->
        <div class="auth-section">
            <h2 style="color: white; margin-bottom: 30px; font-size: 32px;">Get Started Today</h2>
            <p style="color: rgba(255, 255, 255, 0.9); max-width: 600px; margin: 0 auto 40px; font-size: 18px;">
                Join thousands of learners who are already advancing their skills with EduLearn LMS.
            </p>
            <div class="auth-buttons">
                <a href="<?= BASE_URL ?>/public/login.php" class="auth-btn login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </a>
                <a href="<?= BASE_URL ?>/public/register.php" class="auth-btn register-btn">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </a>
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