<?php
require_once __DIR__ . '/../inc/config.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    
    // Validate inputs
    $errors = [];
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if (empty($subject)) $errors[] = 'Subject is required';
    if (empty($message)) $errors[] = 'Message is required';
    
    if (empty($errors)) {
        try {
            // Insert into database
            $stmt = $pdo->prepare("
                INSERT INTO contact_messages (name, email, subject, message, created_at, is_read) 
                VALUES (?, ?, ?, ?, NOW(), 0)
            ");
            $stmt->execute([$name, $email, $subject, $message]);
            
            // Set success message
            $success_message = 'Your message has been sent successfully! We will get back to you soon.';
            
        } catch (PDOException $e) {
            $error_message = 'Failed to send message. Please try again later.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - ARMMC Learning Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    
    
    <style>
        /* EXACT CSS from the login page - matching exactly */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-image: url('../uploads/images/armmc-bg.png');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 35, 102, 0.4);
            z-index: -1;
        }

        /* main card - EXACT size as login page (1280px) */
        .login-card {
            max-width: 1280px;
            width: 100%;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 3.5rem;
            box-shadow: 
                0 30px 60px -20px rgba(0,40,80,0.25),
                0 8px 20px -8px rgba(0,32,64,0.1),
                inset 0 1px 1px rgba(255,255,255,0.6);
            border: 1px solid rgba(255,255,255,0.6);
            padding: 1rem 2.5rem;
        }

        /* two-column layout - EXACT same grid */
        .grid-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2.5rem;
            align-items: center;
        }

        /* left side – company logo (exactly as login page) */
        .logo-hero {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border-radius: 2.5rem;
            padding: 3rem 2rem;
            box-shadow: 0 20px 30px -10px rgba(0,20,40,0.15);
            border: 1px solid rgba(255,255,255,0.8);
            transition: transform 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo-hero:hover {
            transform: scale(1.01);
            background: rgba(255,255,255,0.65);
        }

        .logo-main {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .company-logo-png {
            max-width: 340px;
            width: 100%;
            height: auto;
            aspect-ratio: 1 / 1;
            object-fit: contain;
            filter: drop-shadow(0 12px 18px rgba(0,50,90,0.25));
            background: transparent;
            border-radius: 32px;
            transition: filter 0.2s;
        }

        .logo-caption {
            margin-top: 2rem;
            font-weight: 400;
            font-size: 1.1rem;
            letter-spacing: 2px;
            color: #1c3f5c;
            opacity: 0.8;
            text-transform: uppercase;
            border-bottom: 2px solid #a3c6e9;
            padding-bottom: 0.75rem;
            display: inline-block;
        }

        /* === CONTACT FORM STYLES - expanded to match login card perfectly === */
        .contact-container {
            padding: 1rem 0.5rem;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .contact-header {
            font-size: clamp(2.2rem, 5vw, 3.5rem);
            font-weight: 700;
            line-height: 1.2;
            color: #0c2e45;
            margin-bottom: 0.5rem;
        }

        .contact-header span {
            background: linear-gradient(135deg, #1f6392, #0a3b58);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            border-bottom: 4px solid #6ab0f5;
            display: inline-block;
            padding-bottom: 2px;
        }

        .contact-subtitle {
            font-size: 1.1rem;
            color: #2b4e6b;
            margin-bottom: 1.8rem;
            line-height: 1.5;
            font-weight: 400;
            max-width: 480px;
        }

        /* contact form - expanded to fill space */
        .contact-form-wrapper {
            width: 100%;
            max-width: 440px;
            margin: 0.5rem 0 1rem;
        }

        .contact-form-group {
            margin-bottom: 1.4rem;
            position: relative;
        }

        .contact-form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #144a6f;
            margin-bottom: 0.4rem;
            letter-spacing: 0.3px;
        }

        .contact-input-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .contact-input-icon {
            position: absolute;
            left: 1.2rem;
            color: #1f6fb0;
            font-size: 1rem;
            opacity: 0.7;
            z-index: 1;
        }

        .contact-form-input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            border: 1.5px solid rgba(31, 111, 176, 0.2);
            border-radius: 50px;
            font-size: 0.95rem;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(2px);
            transition: all 0.2s;
            color: #0c2e45;
        }

        .contact-form-input:focus {
            outline: none;
            border-color: #1f6fb0;
            background: white;
            box-shadow: 0 0 0 4px rgba(31, 111, 176, 0.1);
        }

        .contact-form-input::placeholder {
            color: #6f9ac0;
            font-size: 0.9rem;
        }

        textarea.contact-form-input {
            min-height: 120px;
            resize: vertical;
            border-radius: 30px;
        }

        .contact-btn-primary {
            background: #1f6fb0;
            border: none;
            padding: 0.9rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            cursor: pointer;
            transition: 0.15s;
            box-shadow: 0 12px 18px -12px #1f6fb0;
            border: 1px solid rgba(255,255,255,0.3);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            width: 100%;
            margin: 0.5rem 0 1rem;
        }

        .contact-btn-primary i {
            font-size: 1.2rem;
        }

        .contact-btn-primary:hover:not(:disabled) {
            background: #0f558b;
            transform: translateY(-3px);
            box-shadow: 0 20px 22px -14px #1f6fb0;
        }

        .contact-btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            background: #6f9ac0;
            box-shadow: none;
        }

        /* Alert & Notification Styles */
        .alert {
            background: rgba(31, 111, 176, 0.1);
            border: 1px solid rgba(31, 111, 176, 0.3);
            border-radius: 50px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            font-size: 0.95rem;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #28a745;
        }

        .alert-success i {
            color: #28a745;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }

        .alert-error i {
            color: #dc3545;
        }

        .alert i {
            font-size: 1.2rem;
            margin-top: 0.2rem;
        }

        .alert-content {
            flex: 1;
        }

        /* back link */
        .contact-back-link {
            margin-top: 1.8rem;
            text-align: center;
        }

        .contact-back-link a {
            color: #1f6fb0;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            transition: color 0.2s;
            padding: 0.5rem 1rem;
            border-radius: 50px;
        }

        .contact-back-link a:hover {
            color: #0f4a78;
            background: rgba(31, 111, 176, 0.1);
        }

        .contact-back-link i {
            font-size: 0.9rem;
        }

        /* bottom note */
        .contact-bottom-note {
            margin-top: 1.8rem;
            font-size: 0.9rem;
            color: #567e9f;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .contact-bottom-note .line {
            height: 1px;
            background: linear-gradient(90deg, transparent, #9bbcdd, transparent);
            flex: 1;
        }

        /* responsiveness */
        @media (max-width: 880px) {
            .grid-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .logo-hero {
                order: 1;
            }
            .contact-container {
                order: 2;
                text-align: center;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .contact-form-wrapper {
                margin: 0 auto;
            }
            .contact-subtitle {
                margin-left: auto;
                margin-right: auto;
            }
        }

        @media (max-width: 500px) {
            .login-card {
                padding: 1.8rem 1.2rem;
            }
            .logo-caption {
                font-size: 0.9rem;
            }
        }

        /* Animations */
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .fa-spinner {
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body>
    <div class="overlay"></div>
    
    <div class="login-card">
        <div class="grid-layout">
           <!-- Left side - Logo (same as login page) -->
            <div class="logo-hero">
                <div class="logo-main">
                    <img src="../uploads/images/armmc-logo.png" alt="ARMMC Logo" class="company-logo-png">
                    <div class="logo-caption">
                        <i class="fas fa-circle" style="font-size: 0.4rem; vertical-align: middle; color: #1f6fb0;"></i> 
                        AMANG RODRIGUEZ MEMORIAL MEDICAL CENTER 
                        <i class="fas fa-circle" style="font-size: 0.4rem; vertical-align: middle; color: #1f6fb0;"></i>
                    </div>
                </div>
            </div>

            <!-- Right side - Contact Form (expanded to match login card) -->
            <div class="contact-container">
                <div class="contact-header">
                    <span>Contact Us</span>
                </div>
                <div class="contact-subtitle">
                    We'd love to hear from you. Send us a message and we'll respond as soon as possible.
                </div>
                
                <!-- Success Message -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success" id="successAlert">
                        <i class="fas fa-check-circle"></i>
                        <div class="alert-content"><?= htmlspecialchars($success_message) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error" id="errorAlert">
                        <i class="fas fa-exclamation-circle"></i>
                        <div class="alert-content"><?= $error_message ?></div>
                    </div>
                <?php endif; ?>

                <div class="contact-form-wrapper">
                    <form method="POST" action="" id="contactForm">
                        <div class="contact-form-group">
                            <label class="contact-form-label" for="name">
                                <i class="fas fa-user" style="margin-right: 0.3rem;"></i> Your Name
                            </label>
                            <div class="contact-input-container">
                                <i class="fas fa-user contact-input-icon"></i>
                                <input type="text" id="name" name="name" class="contact-form-input" 
                                       placeholder="Enter your full name" required
                                       value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                            </div>
                        </div>

                        <div class="contact-form-group">
                            <label class="contact-form-label" for="email">
                                <i class="fas fa-envelope" style="margin-right: 0.3rem;"></i> Email Address
                            </label>
                            <div class="contact-input-container">
                                <i class="fas fa-envelope contact-input-icon"></i>
                                <input type="email" id="email" name="email" class="contact-form-input" 
                                       placeholder="Enter your email address" required
                                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                            </div>
                        </div>

                        <div class="contact-form-group">
                            <label class="contact-form-label" for="subject">
                                <i class="fas fa-tag" style="margin-right: 0.3rem;"></i> Subject
                            </label>
                            <div class="contact-input-container">
                                <i class="fas fa-tag contact-input-icon"></i>
                                <input type="text" id="subject" name="subject" class="contact-form-input" 
                                       placeholder="What is this about?" required
                                       value="<?= isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : '' ?>">
                            </div>
                        </div>

                        <div class="contact-form-group">
                            <label class="contact-form-label" for="message">
                                <i class="fas fa-comment" style="margin-right: 0.3rem;"></i> Message
                            </label>
                            <div class="contact-input-container">
                                <i class="fas fa-comment contact-input-icon" style="top: 1rem;"></i>
                                <textarea id="message" name="message" class="contact-form-input" 
                                          placeholder="Type your message here..." required><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
                            </div>
                        </div>

                        <button type="submit" class="contact-btn-primary" id="submitBtn">
                            <i class="fas fa-paper-plane"></i>
                            Send Message
                        </button>
                    </form>
                </div>

                <div class="contact-back-link">
                    <a href="<?= BASE_URL ?>/public/index.php"><i class="fas fa-arrow-left"></i> Back to Home</a>
                </div>
                
                <div class="contact-bottom-note">
                    <span class="line"></span>
                    <span><i class="far fa-clock"></i> We typically reply within 24 hours</span>
                    <span class="line"></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide success message after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                setTimeout(function() {
                    successAlert.style.transition = 'opacity 0.5s';
                    successAlert.style.opacity = '0';
                    setTimeout(function() {
                        if (successAlert && successAlert.parentElement) {
                            successAlert.remove();
                        }
                    }, 500);
                }, 5000);
            }

            // Auto-hide error message after 5 seconds
            const errorAlert = document.getElementById('errorAlert');
            if (errorAlert) {
                setTimeout(function() {
                    errorAlert.style.transition = 'opacity 0.5s';
                    errorAlert.style.opacity = '0';
                    setTimeout(function() {
                        if (errorAlert && errorAlert.parentElement) {
                            errorAlert.remove();
                        }
                    }, 500);
                }, 5000);
            }

            // Prevent double submission
            const form = document.getElementById('contactForm');
            const submitBtn = document.getElementById('submitBtn');
            
            if (form) {
                form.addEventListener('submit', function() {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner"></i> Sending...';
                });
            }
        });
    </script>
</body>
</html>