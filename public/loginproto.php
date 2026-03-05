<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMMC LMS · Login</title>
    <!-- Font Awesome 6 (free) for subtle icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* === GLOBAL STYLES (identical to welcome page) === */
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

        /* main card - EXACT same dimensions as welcome page */
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
            padding: 3rem 2.5rem; /* EXACT same padding */
        }

        /* two-column layout - EXACT same grid */
        .grid-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2.5rem; /* EXACT same gap */
            align-items: center;
        }

        /* left side – company logo (identical) */
        .logo-hero {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border-radius: 2.5rem;
            padding: 3rem 2rem; /* EXACT same padding */
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

        /* === LOGIN-SPECIFIC STYLES - sized to match welcome content exactly === */
        .login-container {
            padding: 1rem 0.5rem; /* EXACT same padding as welcome-content */
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .login-badge {
            display: inline-block;
            background: #1d4e75;
            color: white;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.4rem 1.2rem;
            border-radius: 40px;
            letter-spacing: 0.3px;
            margin-bottom: 1.8rem; /* Adjusted to match visual weight */
            border: 1px solid rgba(255,255,255,0.3);
            box-shadow: 0 4px 10px rgba(0,60,110,0.2);
            align-self: flex-start;
        }

        .login-header {
            font-size: clamp(2.2rem, 5vw, 3.4rem); /* EXACT same as welcome-title */
            font-weight: 700;
            line-height: 1.2;
            color: #0c2e45;
            margin-bottom: 0.5rem;
        }

        .login-header span {
            background: linear-gradient(135deg, #1f6392, #0a3b58);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            border-bottom: 4px solid #6ab0f5; /* Same as welcome page accent */
            display: inline-block;
            padding-bottom: 2px;
        }

        .login-subtitle {
            font-size: 1.1rem; /* Slightly smaller than description for hierarchy */
            color: #2b4e6b;
            margin-bottom: 1.8rem;
            line-height: 1.5;
            font-weight: 400;
            max-width: 500px;
        }

        /* login form - sized to fit perfectly in the space */
        .login-form-wrapper {
            width: 100%;
            max-width: 420px; /* Optimal form width */
            margin: 0.5rem 0 1rem;
        }

        .login-form-group {
            margin-bottom: 1.4rem;
            position: relative;
        }

        .login-form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #144a6f;
            margin-bottom: 0.4rem;
            letter-spacing: 0.3px;
        }

        .login-input-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .login-input-icon {
            position: absolute;
            left: 1.2rem;
            color: #1f6fb0;
            font-size: 1rem;
            opacity: 0.7;
            z-index: 1;
        }

        .login-form-input {
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

        .login-form-input:focus {
            outline: none;
            border-color: #1f6fb0;
            background: white;
            box-shadow: 0 0 0 4px rgba(31, 111, 176, 0.1);
        }

        .login-form-input::placeholder {
            color: #6f9ac0;
            font-size: 0.9rem;
        }

        .login-password-toggle {
            position: absolute;
            right: 1.2rem;
            background: none;
            border: none;
            color: #1f6fb0;
            cursor: pointer;
            font-size: 1rem;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .login-password-toggle:hover {
            opacity: 1;
        }

        .login-options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1.2rem 0 1.5rem;
        }

        .login-remember {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #144a6f;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .login-remember input[type="checkbox"] {
            width: 1rem;
            height: 1rem;
            accent-color: #1f6fb0;
            cursor: pointer;
        }

        .login-forgot-link {
            color: #1f6fb0;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        .login-forgot-link:hover {
            color: #0f4a78;
            text-decoration: underline;
        }

        .login-btn-primary {
            background: #1f6fb0;
            border: none;
            padding: 0.9rem 2rem; /* EXACT same as welcome page button */
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            cursor: pointer;
            transition: 0.15s;
            box-shadow: 0 12px 18px -10px #1f6fb0;
            border: 1px solid rgba(255,255,255,0.3);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            width: 100%;
            margin: 0.5rem 0 1.2rem;
        }

        .login-btn-primary i {
            font-size: 1.2rem;
        }

        .login-btn-primary:hover {
            background: #0f558b;
            transform: translateY(-3px);
            box-shadow: 0 20px 22px -12px #1f6fb0;
        }

        .login-signup-prompt {
            text-align: center;
            color: #2b4e6b;
            font-size: 0.95rem;
            margin-bottom: 1.2rem;
        }

        .login-signup-link {
            color: #1f6fb0;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }

        .login-signup-link:hover {
            color: #0f4a78;
            text-decoration: underline;
        }

        .login-divider {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            color: #7fa3c2;
            font-size: 0.8rem;
            margin: 1rem 0;
        }

        .login-divider-line {
            height: 1px;
            background: linear-gradient(90deg, transparent, #9bbcdd, transparent);
            flex: 1;
        }

        .login-social-icons {
            display: flex;
            justify-content: center;
            gap: 1.2rem;
            margin: 1rem 0 0.8rem;
        }

        .login-social-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(31, 111, 176, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1f6fb0;
            font-size: 1.2rem;
            transition: all 0.2s;
            text-decoration: none;
        }

        .login-social-icon:hover {
            background: white;
            border-color: #1f6fb0;
            transform: translateY(-3px);
            box-shadow: 0 8px 12px -8px #1f6fb0;
        }

        .login-back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #567e9f;
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 0.8rem;
            transition: color 0.2s;
            align-self: flex-start;
        }

        .login-back-link i {
            font-size: 0.9rem;
        }

        .login-back-link:hover {
            color: #1f6fb0;
        }

        /* bottom note - EXACT same as welcome page */
        .login-bottom-note {
            margin-top: 1.8rem;
            font-size: 0.9rem;
            color: #567e9f;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .login-bottom-note .line {
            height: 1px;
            background: linear-gradient(90deg, transparent, #9bbcdd, transparent);
            flex: 1;
        }

        .login-imiss {
            margin-top: 0.5rem;
            text-align: right;
            color: #567e9f;
            font-size: 0.9rem;
        }

        /* responsiveness - matches welcome page exactly */
        @media (max-width: 880px) {
            .grid-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .logo-hero {
                order: 1;
            }
            .login-container {
                order: 2;
                text-align: center;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .login-badge {
                align-self: center;
            }
            .login-form-wrapper {
                margin: 0 auto;
            }
            .login-options-row {
                justify-content: space-around;
            }
            .login-back-link {
                align-self: center;
            }
            .login-subtitle {
                margin-left: auto;
                margin-right: auto;
            }
        }

        @media (max-width: 500px) {
            .login-card {
                padding: 1.8rem 1.2rem; /* EXACT same as welcome page mobile */
            }
            .logo-caption {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="overlay"></div>
    <div class="login-card">
        <div class="grid-layout">
            <!-- LEFT SIDE: COMPANY LOGO (identical to welcome page) -->
            <div class="logo-hero">
                <div class="logo-main">
                    <img 
                        class="company-logo-png" 
                        src="../uploads/images/armmc-logo.png" 
                        alt="ARMMC Logo"
                        title="Amang Rodriguez Memorial Medical Center"
                    >
                    <div class="logo-caption">
                        <i class="fas fa-circle" style="font-size: 0.4rem; vertical-align: middle; color: #1f6fb0;"></i> 
                        AMANG RODRIGUEZ MEMORIAL MEDICAL CENTER 
                        <i class="fas fa-circle" style="font-size: 0.4rem; vertical-align: middle; color: #1f6fb0;"></i>
                    </div>
                </div>
            </div>

            <!-- RIGHT SIDE: LOGIN FORM - sized exactly like welcome content -->
            <div class="login-container">  
                <h1 class="login-header">
                    <p>access your 
                    <p><span>account</span>
                </h1>
                <p class="login-subtitle">
                    Please enter your credentials to continue
                </p>

                <!-- Login Form -->
                <div class="login-form-wrapper">
                    <form action="../public/authenticate.php" method="POST" id="loginForm">
                        <!-- Username/Email field -->
                        <div class="login-form-group">
                            <label for="username" class="login-form-label">Username or Email</label>
                            <div class="login-input-container">
                                <i class="fas fa-user login-input-icon"></i>
                                <input 
                                    type="text" 
                                    id="username" 
                                    name="username" 
                                    class="login-form-input" 
                                    placeholder="Enter your username"
                                    required
                                >
                            </div>
                        </div>

                        <!-- Password field -->
                        <div class="login-form-group">
                            <label for="password" class="login-form-label">Password</label>
                            <div class="login-input-container">
                                <i class="fas fa-lock login-input-icon"></i>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="login-form-input" 
                                    placeholder="Enter your password"
                                    required
                                >
                                <button type="button" class="login-password-toggle" onclick="togglePasswordVisibility()">
                                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember me & Forgot password -->
                        <div class="login-options-row">
                            <label class="login-remember">
                                <input type="checkbox" name="remember"> Remember me
                            </label>
                            <a href="../public/forgot-password.php" class="login-forgot-link">Forgot password?</a>
                        </div>

                        <!-- Login button (same size as Get Started button) -->
                        <button type="submit" class="login-btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Login to LMS
                        </button>

                        <!-- Sign up link -->
                        <div class="login-signup-prompt">
                            Don't have an account? 
                            <a href="../public/protoregister.php" class="login-signup-link">Sign up here</a>
                        </div>
                    </form>
                </div>

                <!-- Bottom note (EXACT match to welcome page) -->
                <div class="login-bottom-note">
                    <span class="line"></span>
                    <span>ARMMC Learning Management System. All rights reserved 2026.</span>
                    <span class="line"></span>
                </div>
                <div class="login-bottom-note" style="margin-top: 0.5rem; margin-left: 250px;">
                    iMISS
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for password visibility toggle -->
    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
            }
        });
    </script>
</body>
</html>