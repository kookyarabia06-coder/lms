<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$u = current_user();

// Dynamically define BASE_URL if not defined
if(!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . '/lms');
}
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<nav class="sidebar lms-sidebar">
  <div class="mb-3"><strong><i class="fa-solid fa-chalkboard-user"></i>Learning Management System</strong></div>
  <ul class="nav flex-column">

    <li class="nav-item">
      <a class="nav-link" href="<?= BASE_URL ?>/public/dashboard.php">
        <i class="fa fa-tachometer-alt"></i> Dashboard
      </a>
    </li>

    <!-- Courses parent menu -->
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="collapse" href="#coursesSubMenu" role="button" aria-expanded="false" aria-controls="coursesSubMenu">
        <i class="fa fa-book"></i> Courses <i class="fa fa-caret-down float-end"></i>
      </a>
      <div class="collapse" id="coursesSubMenu">
        <ul class="nav flex-column ms-3">
          <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>/public/courses.php">
              <i class="fa fa-list"></i> All Courses
            </a>
          </li>
          <?php if($u && is_student()): ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>/public/my_courses.php">
              <i class="fa fa-graduation-cap"></i> My Courses
            </a>
          </li>
          <?php endif; ?>
          <?php if($u && (is_proponent() || is_admin())): ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>/admin/courses_crud.php?act=addform">
              <i class="fa fa-plus"></i> Add Course
            </a>
          </li>
          <?php endif; ?>
        </ul>
      </div>
    </li>

    <?php if($u && is_admin()): ?>
      <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/admin/users_crud.php">
          <i class="fa fa-users"></i> User Management
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/admin/news_crud.php">
          <i class="fa fa-newspaper"></i> News
        </a>
      </li>
    <?php endif; ?>

    <li class="nav-item">
      <a class="nav-link" href="<?= BASE_URL ?>/public/logout.php">
        <i class="fa fa-sign-out-alt"></i> Logout
      </a>
    </li>

  </ul>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
