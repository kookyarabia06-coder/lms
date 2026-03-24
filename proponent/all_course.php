<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

// Only admins and proponents can access this page
if (!is_admin() && !is_proponent() && !is_superadmin()) {
    http_response_code(403);
    exit('Access denied');
}

// First, check if updated_at column exists and add it if not
try {
    $pdo->query("SELECT updated_at FROM courses LIMIT 1");
} catch (Exception $e) {
    // Column doesn't exist, add it
    try {
        $pdo->exec("ALTER TABLE courses ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at");
    } catch (Exception $e) {
        // Column might already exist
    }
}

// Show ALL courses but filter by status - only approved courses
$stmt = $pdo->query("
    SELECT c.*, u.username 
    FROM courses c 
    LEFT JOIN users u ON c.proponent_id = u.id 
    WHERE c.status = 'approved'
    ORDER BY c.updated_at DESC, c.created_at DESC
");
$courses = $stmt->fetchAll();

// Fetch committees for each course
foreach ($courses as &$course) {
    try {
        $comm_stmt = $pdo->prepare("
            SELECT c.id, c.name 
            FROM committees c
            INNER JOIN course_departments cd ON c.id = cd.department_id
            WHERE cd.course_id = :course_id
            ORDER BY c.name
        ");
        $comm_stmt->execute([':course_id' => $course['id']]);
        $course['committees'] = $comm_stmt->fetchAll();
    } catch (Exception $e) {
        $course['committees'] = [];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>All Courses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/course.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
     <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <style>
        .committee-badge {
            display: inline-block;
            background-color: #8227a9;
            color: white;
            padding: 0.25rem 0.5rem;
            margin: 0.125rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .owned-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.3rem;
            background-color: #28a745;
            color: white;
            border-radius: 0.25rem;
            margin-left: 0.25rem;
        }
        .search-container {
            margin-bottom: 30px;
            position: relative;
        }
        .search-box {
            position: relative;
            width: 100%;
            max-width: 500px;
        }
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 16px;
            z-index: 10;
        }
        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 50px;
            font-size: 15px;
            outline: none;
            transition: all 0.3s ease;
            background: white;
        }
        .search-box input:focus {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.15);
        }
        .search-box input::placeholder {
            color: #a0aec0;
            font-size: 14px;
        }
        .search-results-count {
            margin-left: 15px;
            color: #6c757d;
            font-size: 14px;
            background: #f8f9fa;
            padding: 5px 12px;
            border-radius: 20px;
        }
        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        .no-results i {
            font-size: 48px;
            color: #cbd5e0;
            margin-bottom: 15px;
        }
        .no-results h5 {
            color: #4a5568;
            margin-bottom: 5px;
        }
        .no-results p {
            color: #718096;
        }
        .program-committee-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 2px;
        }
    </style>
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="modern-courses-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>All Courses</h3>
        
        <!-- Search Bar -->
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="courseSearch" placeholder="Search courses by title..." autocomplete="off">
        </div>
    </div>

    <?php if (empty($courses)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> No courses found.
        </div>
    <?php else: ?>
        <!-- Search Results Count -->
        <div class="d-flex justify-content-end mb-3">
            <span class="search-results-count" id="resultsCount">Showing <?= count($courses) ?> courses</span>
        </div>

        <div class="modern-courses-grid" id="coursesGrid">
            <?php foreach ($courses as $c): ?>
                <div class="modern-course-card course-item" data-title="<?= strtolower(htmlspecialchars($c['title'])) ?>">
                    <div class="modern-card-img">
                        <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'Course Image.png') ?>" alt="Course Image">
                    </div>
                    <div class="modern-card-body">
                        <div class="modern-card-title">
                            <h6 class="course-title">
                                <?= htmlspecialchars($c['title']) ?>
                                <?php if ($c['proponent_id'] == $_SESSION['user']['id']): ?>
                                    <span class="owned-badge">Your Course</span>
                                <?php endif; ?>
                            </h6>
                        </div>
                        <p><?= htmlspecialchars(substr($c['description'], 0, 100)) ?>...</p>

                        <!-- Program Committees -->
                        <?php if (!empty($c['committees'])): ?>
                            <div class="mb-3">
                                <div class="program-committee-label">
                                    <i class="fas fa-users me-1" style="color: #8227a9;"></i> Program Committee:
                                </div>
                                <?php foreach ($c['committees'] as $comm): ?>
                                    <span class="committee-badge"><?= htmlspecialchars($comm['name']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="modern-course-info">
                            <?php
                                $startDate = date('M d, Y', strtotime($c['created_at']));
                                $expiryDate = $c['expires_at']
                                    ? date('M d, Y', strtotime($c['expires_at']))
                                    : 'No expiry';

                                // Format updated date
                                $updatedDate = !empty($c['updated_at']) 
                                    ? date('M d, Y h:i A', strtotime($c['updated_at'])) 
                                    : 'Never';
                            ?>
                            <p><strong>Created by:</strong> <span><?= htmlspecialchars($c['username'] ?? 'Unknown') ?></span></p>
                            <p><strong>Start:</strong> <span><?= $startDate ?></span></p>
                            <p><strong>Expires:</strong> <span><?= $expiryDate ?></span></p>
                            <p><strong>Last Edited:</strong> 
                                <span class="<?= $c['updated_at'] ? 'text-primary' : 'text-muted' ?>">
                                    <?= $updatedDate ?>
                                    <?php if($c['updated_at']): ?>
                                        <i class="fas fa-pen-alt ms-1" style="font-size: 11px;"></i>
                                    <?php endif; ?>
                                </span>
                            </p>
                        </div>
                        <div class="modern-card-actions">
                            <!-- View button only - Everyone can view -->
                            <a href="<?= BASE_URL ?>/proponent/view_course.php?id=<?= $c['id'] ?>" class="modern-btn-primary modern-btn-sm" style="flex: 1;">
                                <i class="fas fa-eye me-2"></i>View Course
                            </a>
                        </div>  
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- No Results Template (hidden by default) -->
        <div class="no-results" id="noResults" style="display: none;">
            <i class="fas fa-search"></i>
            <h5>No courses found</h5>
            <p>Try adjusting your search term</p>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('courseSearch');
    const courseItems = document.querySelectorAll('.course-item');
    const coursesGrid = document.getElementById('coursesGrid');
    const noResults = document.getElementById('noResults');
    const resultsCount = document.getElementById('resultsCount');
    
    if (searchInput && courseItems.length > 0) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase().trim();
            let visibleCount = 0;
            
            courseItems.forEach(item => {
                const title = item.getAttribute('data-title');
                
                if (title.includes(searchTerm) || searchTerm === '') {
                    item.style.display = ''; // Show
                    visibleCount++;
                } else {
                    item.style.display = 'none'; // Hide
                }
            });
            
            // Update results count
            if (resultsCount) {
                resultsCount.textContent = `Showing ${visibleCount} of ${courseItems.length} courses`;
            }
            
            // Show/hide no results message
            if (noResults) {
                if (visibleCount === 0) {
                    noResults.style.display = 'block';
                    coursesGrid.style.display = 'none';
                } else {
                    noResults.style.display = 'none';
                    coursesGrid.style.display = 'grid';
                }
            }
        });
        
        // Add a small delay for better UX (prevents lag while typing)
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // The keyup handler already does the filtering
                // This just ensures we don't have any performance issues
            }, 100);
        });
        
        // Clear search function (optional - can be triggered by Esc key)
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                this.dispatchEvent(new Event('keyup'));
            }
        });
    }
});
</script>

</body>
</html>