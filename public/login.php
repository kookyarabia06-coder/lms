<?php
require_once __DIR__ . '/../inc/config.php';

// Redirect if already logged in 
if(isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

$err = '';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $u = $stmt->fetch();

    if($u && password_verify($password, $u['password'])) {
        $_SESSION['user'] = [
            'id' => $u['id'],
            'username' => $u['username'],
            'fname' => $u['fname'],
            'lname' => $u['lname'],
            'email' => $u['email'],
            'role' => $u['role']
        ];
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    } else {
        $err = 'Invalid credentials';
    }
}
?>
<!doctype html>
<html lang="en" class="h-full">
 <head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="/_sdk/element_sdk.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <style>
    body {
      box-sizing: border-box;
    }
    * {
      font-family: 'DM Sans', sans-serif;
    }
  </style>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            navy: '#1A3263',
            slate: '#547792',
            amber: '#FAB95B',
            cream: '#E8E2DB'
          }
        }
      }
    }
  </script>
  <style>@view-transition { navigation: auto; }</style>
  <script src="/_sdk/data_sdk.js" type="text/javascript"></script>
 </head>
 <body class="h-full bg-cream text-navy">
  <div class="h-full w-full overflow-auto flex flex-col"><!-- Header -->
   <header class="bg-navy">
    <div class="max-w-6xl mx-auto px-6 py-5 flex justify-between items-center">
     <div class="flex items-center gap-3">
      <div class="w-10 h-10 bg-amber rounded-lg flex items-center justify-center">
       <svg class="w-6 h-6 text-navy" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
       </svg>
      </div>
      <h1 id="site-title" class="text-xl font-semibold text-cream">Learning Management System</h1>
     </div>
    </div>
   </header><!-- Main Content -->
   <main class="flex-1 flex items-center justify-center px-6 py-10">
    <div class="w-full max-w-sm">
     <div class="bg-white rounded-xl shadow-sm border border-slate/10 p-8">
      <h2 id="login-heading" class="text-2xl font-semibold mb-2 flex items-center gap-2"><span class="w-1.5 h-5 bg-amber rounded-full"></span> Login</h2>
      <p class="text-slate mb-6">Access your learning dashboard</p><!-- Error Message (shown when needed) -->
      <div id="error-message" class="hidden mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
       <p id="error-text" class="text-red-700 text-sm"></p>
      </div>

      <form method="post">
       <div class="mb-4"><label for="username" class="block text-sm font-medium text-navy mb-2">Username</label> <input name="username" type="text" placeholder="Enter your username" class="w-full px-4 py-2.5 border border-slate/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber focus:border-transparent text-navy" required>
       </div>
       <div class="mb-6"><label for="password" class="block text-sm font-medium text-navy mb-2">Password</label> <input name="password" type="password" placeholder="Enter your password" class="w-full px-4 py-2.5 border border-slate/20 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber focus:border-transparent text-navy" required>
       </div>
       <button type="submit" class="w-full bg-amber hover:bg-amber/90 text-navy font-medium py-2.5 rounded-lg transition-colors mb-4"> Login </button>
      </form>
      <div class="text-center">
       <p class="text-slate text-sm">Don't have an account? <a id="register-link" href="<?= BASE_URL ?>/public/register.php" class="text-amber hover:text-amber/90 font-medium">Register here</a></p>
      </div>
     </div>
    </div>
   </main><!-- Footer -->
   <footer class="bg-navy/5 border-t border-slate/10">
    <div class="max-w-6xl mx-auto px-6 py-6 text-center text-slate text-sm">
     © 2026 Learning Management System. All rights reserved.
    </div>
   </footer>
  </div>
  <script>
    const defaultConfig = {
      site_title: 'Learning Management System',
      login_heading: 'Login',
      register_text: 'Register here',
      background_color: '#E8E2DB',
      header_color: '#1A3263',
      accent_color: '#FAB95B',
      text_color: '#1A3263',
      secondary_color: '#547792'
    };

    async function onConfigChange(config) {
      const siteTitle = document.getElementById('site-title');
      const loginHeading = document.getElementById('login-heading');
      const registerLink = document.getElementById('register-link');

      if (siteTitle) {
        siteTitle.textContent = config.site_title || defaultConfig.site_title;
      }
      if (loginHeading) {
        loginHeading.innerHTML = `<span class="w-1.5 h-5 bg-amber rounded-full"></span>${config.login_heading || defaultConfig.login_heading}`;
      }
      if (registerLink) {
        registerLink.textContent = config.register_text || defaultConfig.register_text;
      }
    }

    function mapToCapabilities(config) {
      return {
        recolorables: [
          {
            get: () => config.background_color || defaultConfig.background_color,
            set: (value) => { config.background_color = value; window.elementSdk.setConfig({ background_color: value }); }
          },
          {
            get: () => config.header_color || defaultConfig.header_color,
            set: (value) => { config.header_color = value; window.elementSdk.setConfig({ header_color: value }); }
          },
          {
            get: () => config.text_color || defaultConfig.text_color,
            set: (value) => { config.text_color = value; window.elementSdk.setConfig({ text_color: value }); }
          },
          {
            get: () => config.accent_color || defaultConfig.accent_color,
            set: (value) => { config.accent_color = value; window.elementSdk.setConfig({ accent_color: value }); }
          },
          {
            get: () => config.secondary_color || defaultConfig.secondary_color,
            set: (value) => { config.secondary_color = value; window.elementSdk.setConfig({ secondary_color: value }); }
          }
        ],
        borderables: [],
        fontEditable: {
          get: () => config.font_family || 'DM Sans',
          set: (value) => { config.font_family = value; window.elementSdk.setConfig({ font_family: value }); }
        },
        fontSizeable: {
          get: () => config.font_size || 16,
          set: (value) => { config.font_size = value; window.elementSdk.setConfig({ font_size: value }); }
        }
      };
    }

    function mapToEditPanelValues(config) {
      return new Map([
        ['site_title', config.site_title || defaultConfig.site_title],
        ['login_heading', config.login_heading || defaultConfig.login_heading],
        ['register_text', config.register_text || defaultConfig.register_text]
      ]);
    }

    function handleSubmit(event) {
      event.preventDefault();
      
      const username = document.getElementById('username').value;
      const password = document.getElementById('password').value;
      
      // Clear previous errors
      document.getElementById('error-message').classList.add('hidden');
      
      if (username && password) {
        console.log('Form submitted:', { username, password });
      }
    }

    if (window.elementSdk) {
      window.elementSdk.init({
        defaultConfig,
        onConfigChange,
        mapToCapabilities,
        mapToEditPanelValues
      });
    }
  </script>
 <script>(function(){function c(){var b=a.contentDocument||a.contentWindow.document;if(b){var d=b.createElement('script');d.innerHTML="window.__CF$cv$params={r:'9c4e7feb656bbc4b',t:'MTc2OTU4MjQ0MC4wMDAwMDA='};var a=document.createElement('script');a.nonce='';a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";b.getElementsByTagName('head')[0].appendChild(d)}}if(document.body){var a=document.createElement('iframe');a.height=1;a.width=1;a.style.position='absolute';a.style.top=0;a.style.left=0;a.style.border='none';a.style.visibility='hidden';document.body.appendChild(a);if('loading'!==document.readyState)c();else if(window.addEventListener)document.addEventListener('DOMContentLoaded',c);else{var e=document.onreadystatechange||function(){};document.onreadystatechange=function(b){e(b);'loading'!==document.readyState&&(document.onreadystatechange=e,c())}}}})();</script></body>
</html>
