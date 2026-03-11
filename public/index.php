<?php
require_once __DIR__ . '/../inc/config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS · company logo welcome</title>
    <link href="<?= BASE_URL ?>/assets/css/index.css" rel="stylesheet">
     <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
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
        background-color: rgba(0, 35, 102, 0.4); /* Dark royal */
        z-index: -1;
    }
    
        .content {
        position: relative;
        padding: 50px;
        color: white;
        text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        z-index: 1;
    }

        /* main welcome card */
        .welcome-card {
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
            padding: 3rem 2.5rem;
        }

        /* two-column layout */
        .grid-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2.5rem;
            align-items: center;
        }

        /* left side – company logo as MAIN SUBJECT (PNG placeholder) */
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

        /* logo container: the main subject */
        .logo-main {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="overlay"></div>
    <div class="welcome-card">
        <div class="grid-layout">
            <!-- LEFT SIDE: COMPANY LOGO AS MAIN SUBJECT – now PNG placeholder -->
            <div class="logo-hero">
                <div class="logo-main">
                    <img 
                        class="company-logo-png" 
                        src="../uploads/images/armmc-logo.png" 
                        alt="Company logo – main visual"
                        title="Your company logo"
                    >
                    <!-- company name below logo – reinforces main subject -->
                    <div class="logo-caption">
                        <i class="fas fa-circle" style="font-size: 0.4rem; vertical-align: middle; color: #1f6fb0;"></i> 
                        AMANG RODRIGUEZ MEMORIAL MEDICAL CENTER 
                        <i class="fas fa-circle" style="font-size: 0.4rem; vertical-align: middle; color: #1f6fb0;"></i>
                    </div>
                </div>
            </div>

            <!-- RIGHT SIDE: Welcome message and LMS info -->
            <div class="welcome-content">
                <h1 class="welcome-title">
                    welcome to <span>ARMMC LMS</span>
                </h1>
                <p class="welcome-description">
                    Transform your learning experience with our comprehensive Learning Management System. 
               Access courses, track progress, and connect with educators in one seamless platform.
                </p>

                <!-- micro features (relevant to LMS) -->
                <div class="feature-grid">
                    <div class="feature-item">
                        <i class="fas fa-video"></i> <span>Interactive courses</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i> <span>Progress tracking</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-users"></i> <span>Collaborative</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-certificate"></i> <span>Certification</span>
                    </div>
                </div>

                <!-- call to actions -->
                <div class="cta-group" style="margin-top: 0.5rem; margin-left: 190px;">
                    <button class="btn-primary">
                        <a href="../public/login.php" class="auth-btn login-btn" style="color: white; text-decoration: none;">
                            <i class="fas fa-rocket"></i> Get Started
                        </a>
                    </button>
                </div>
                

                <!-- subtle bottom note / additional trust text -->
             
                <div class="bottom-note">
                    <span class="line"></span>
                    <span>ARMMC Learning Management System. All rights reserved 2026.</span><span class="line"></span>
                </div>
                <div class="bottom-note" style="margin-top: 0.5rem; margin-left: 250px;">
                    <span>iMISS</span>
                </div>

               <div class="bottom-note" style="margin-top: 0.5rem; margin-left: 128px;">
             <span onclick="window.location.href='#'" style="cursor: pointer;">Privacy Policy |</span>
            <span onclick="window.location.href='#'" style="cursor: pointer;"> Terms and Condition |</span>
            <span onclick="window.location.href='<?= BASE_URL ?>/public/contact_form.php'" style="cursor: pointer;"> Contact Us</span>
            </div>
            </div>
        </div>
    </div>
</body>
</html>