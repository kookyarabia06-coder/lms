<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

$userId = $_SESSION['user']['id'];

// Fetch all enrolled courses with badge status
$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.description, c.thumbnail, c.created_at, c.expires_at,
           e.progress, e.total_time_seconds, e.badge_issued,
           CASE
               WHEN e.status = 'ongoing' AND c.expires_at IS NOT NULL AND c.expires_at < NOW() THEN 'expired'
               ELSE e.status
           END AS enroll_status,
           (
               SELECT GROUP_CONCAT(comm.name SEPARATOR '||')
               FROM committees comm
               INNER JOIN course_departments cd ON comm.id = cd.committee_id
               WHERE cd.course_id = c.id
           ) AS committee_names
    FROM courses c
    JOIN enrollments e ON e.course_id = c.id
    WHERE e.user_id = ?
    ORDER BY c.id DESC
");
$stmt->execute([$userId]);
$myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process committee names into arrays
foreach ($myCourses as &$course) {
    if (!empty($course['committee_names'])) {
        $course['committees'] = explode('||', $course['committee_names']);
    } else {
        $course['committees'] = [];
    }
}

// Separate badged courses (badge_issued = 1)
$badgedCourses = array_filter($myCourses, function($course) {
    return $course['badge_issued'] == 1;
});

// Keep only non-badged courses for the main grid (badge_issued = 0)
$allCourses = array_filter($myCourses, function($course) {
    return $course['badge_issued'] == 0;
});
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Courses - LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/course.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.main { margin-left: 240px; padding: 20px; }
.card-img-top { height: 150px; object-fit: cover; }

/* Committee badge styles */
.committee-badge {
    display: inline-block;
    background-color: #8227a9;
    color: white;
    padding: 0.25rem 0.5rem;
    margin: 0.125rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
    border: 1px solid rgba(255,255,255,0.2);
}

.committee-container {
    margin: 10px 0;
    padding: 5px 0;
    border-top: 1px solid #f0f0f0;
    border-bottom: 1px solid #f0f0f0;
}

.committee-label {
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 5px;
    font-weight: 600;
}

.committee-label i {
    color: #8227a9;
    margin-right: 4px;
    font-size: 0.7rem;
}

/* Two-column layout - align with sidebar */
.two-column-layout {
    display: flex;
    gap: 30px;
    margin-left: 280px;
    padding: 20px 30px 20px 20px;
    max-width: calc(100% - 280px);
    min-height: calc(100vh - 40px);
    align-items: stretch;
}

/* Left column - All Courses Grid */
.courses-grid {
    flex: 1;
    min-width: 0;
}

.modern-courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
}

.modern-section-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 20px;
    color: #0f172a;
}

/* Right column - Badged Courses Panel */
.badged-panel {
    width: 320px;
    flex-shrink: 0;
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 500px;
}

.badged-header {
    background: linear-gradient(135deg, #2c3e66 0%, #1a2a4a 100%);
    color: white;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.badged-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.badged-header i {
    font-size: 1.3rem;
}

/* Search box inside badge panel */
.badged-search {
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
    flex-shrink: 0;
}

.badged-search input {
    width: 100%;
    padding: 8px 12px 8px 35px;
    border: 1px solid #dee2e6;
    border-radius: 50px;
    font-size: 13px;
    outline: none;
    transition: all 0.3s ease;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="%236c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>');
    background-repeat: no-repeat;
    background-position: 12px center;
}

.badged-search input:focus {
    border-color: #fd7e14;
    box-shadow: 0 0 0 3px rgba(253, 126, 20, 0.1);
}

.badged-list {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    min-height: 0;
}

.badged-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    margin-bottom: 12px;
    background: #f8f9fa;
    border-radius: 12px;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
}

.badged-item:hover {
    background: #fff3e0;
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(253, 126, 20, 0.15);
}

.badged-thumbnail {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    object-fit: cover;
    flex-shrink: 0;
    background: #e9ecef;
}

.badged-info {
    flex: 1;
}

.badged-title {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 4px;
    color: #1e293b;
}

.badged-medal {
    flex-shrink: 0;
    background: #fd7e14;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.empty-badged {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.empty-badged i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: #dee2e6;
}

.empty-badged p {
    margin: 0;
    font-size: 0.9rem;
}

/* Badge ribbon on course cards - removed since badged courses don't appear here */
.modern-card-img {
    position: relative;
}

/* No badge ribbon needed anymore, but keeping for reference */

/* Responsive */
@media (max-width: 1200px) {
    .two-column-layout {
        margin-left: 280px;
        padding: 20px 20px 20px 20px;
        gap: 20px;
    }
    
    .badged-panel {
        width: 280px;
    }
}

@media (max-width: 992px) {
    .two-column-layout {
        flex-direction: column;
        margin-left: 0;
        padding: 20px;
        max-width: 100%;
    }
    
    .badged-panel {
        width: 100%;
        margin-top: 20px;
        min-height: 300px;
    }
    
    .badged-list {
        max-height: 300px;
    }
}
</style>
</head>
<body>
    
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>
    
    <div class="two-column-layout">
        <!-- LEFT COLUMN - All Non-Badged Courses Grid -->
        <div class="courses-grid">
            <h2 class="modern-section-title">My Courses</h2>
            
            <?php if (!empty($allCourses)): ?>
                <div class="modern-courses-grid">
                    <?php foreach ($allCourses as $c): ?>
                    <div class="modern-course-card">
                        <div class="modern-card-img">
                            <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'placeholder.png') ?>" 
                                 alt="<?= htmlspecialchars($c['title']) ?>"
                                 onerror="this.src='<?= BASE_URL ?>/uploads/images/placeholder.png'">
                        </div>
                        
                        <div class="modern-card-body">
                            <div class="modern-card-title">
                                <h6><?= htmlspecialchars($c['title']) ?></h6>
                                <?php if ($c['enroll_status']): ?>
                                    <?php if ($c['enroll_status'] === 'ongoing'): ?>
                                        <span class="modern-badge badge-ongoing">Ongoing</span>
                                    <?php elseif ($c['enroll_status'] === 'completed'): ?>
                                        <span class="modern-badge badge-completed">Completed</span>
                                    <?php elseif ($c['enroll_status'] === 'expired'): ?>
                                        <span class="modern-badge badge-expired">Expired</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="modern-badge badge-notenrolled">Not Enrolled</span>
                                <?php endif; ?>
                            </div>
                            
                            <p><?= htmlspecialchars(substr($c['description'], 0, 120)) ?>...</p>

                            <!-- Program Committee Display -->
                            <?php if (!empty($c['committees'])): ?>
                            <div class="committee-container">
                                <div class="committee-label">
                                    <i class="fas fa-users"></i> Program Committee:
                                </div>
                                <div>
                                    <?php foreach ($c['committees'] as $committee): ?>
                                        <span class="committee-badge">
                                            <?= htmlspecialchars($committee) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Progress Bar -->
                            <?php if ($c['enroll_status'] && $c['enroll_status'] !== 'expired'): ?>
                                <div class="modern-progress-container mt-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small><i class="fas fa-tasks"></i> Progress:</small>
                                        <?php if ($c['enroll_status'] === 'completed'): ?>
                                        <small class="text-success">
                                            <i class="fas fa-check-circle"></i> Completed
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                        // If course is completed, force progress to 100%
                                        if ($c['enroll_status'] === 'completed') {
                                            $progressPercent = 100;
                                        } else {
                                            $progressPercent = intval($c['progress'] ?? 0);
                                        }

                                        // Ensure progress doesn't exceed 100%
                                        if ($progressPercent > 100) $progressPercent = 100;
                                    ?>
                                    <div class="modern-progress">
                                        <div class="modern-progress-bar
                                            <?= $c['enroll_status'] === 'completed' ? 'bg-success' : 'bg-info' ?>"
                                            style="width: <?= $progressPercent ?>%;">
                                        </div>
                                    </div>
                                    <small class="text-end d-block mt-1 fw-bold">
                                        <?php if ($c['enroll_status'] === 'completed'): ?>
                                            <span class="text-success">✓ Completed</span>
                                        <?php else: ?>
                                            <?= $progressPercent ?>% completed
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endif; ?>

                            <div class="modern-course-info">
                                <?php
                                    $startDate = date('M, d, Y', strtotime($c['created_at']));
                                    $expiryDate = $c['expires_at']
                                        ? date('M, d, Y', strtotime($c['expires_at']))
                                        : 'No expiry';
                                ?>
                                <p><strong>Start:</strong> <span><?= $startDate ?></span></p>
                                <p><strong>Expires:</strong> <span><?= $expiryDate ?></span></p>
                            </div>

                            <div class="modern-card-actions">
                                <a href="<?= BASE_URL ?>/public/course_preview.php?id=<?= $c['id'] ?>"
                                   class="modern-btn-warning modern-btn-sm"
                                   title="Preview course content">
                                    <i class="fas fa-eye"></i> Preview
                                </a>            
                                <?php if ($c['enroll_status'] === 'expired'): ?>
                                    <a href="#"
                                       class="modern-btn-sm modern-btn-secondary" style="cursor: not-allowed;"
                                       onclick="return confirm('This course is already expired. You can no longer enroll or continue.');">Expired</a>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $c['id'] ?>" class="modern-btn-primary modern-btn-sm">Start / Continue</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>    
                </div>
            <?php else: ?>
                <p>You are not enrolled in any courses yet.</p>
            <?php endif; ?>
        </div>
        
        <!-- RIGHT COLUMN - Badged Courses Panel with Search -->
        <div class="badged-panel">
            <div class="badged-header">
                <i class="fas fa-medal"></i>
                <h4>Badged Courses</h4>
            </div>
            
            <div class="badged-search">
                <input type="text" id="badgeSearchInput" placeholder="Search badged courses...">
            </div>
            
            <div class="badged-list" id="badgedList">
                <?php if (!empty($badgedCourses)): ?>
                    <?php foreach ($badgedCourses as $badge): ?>
                        <a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $badge['id'] ?>" class="badged-item" data-title="<?= strtolower(htmlspecialchars($badge['title'])) ?>">
                            <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($badge['thumbnail'] ?: 'placeholder.png') ?>" 
                                 class="badged-thumbnail"
                                 alt="<?= htmlspecialchars($badge['title']) ?>"
                                 onerror="this.src='<?= BASE_URL ?>/uploads/images/placeholder.png'">
                            <div class="badged-info">
                                <div class="badged-title"><?= htmlspecialchars($badge['title']) ?></div>
                            </div>
                            <div class="badged-medal">
                                <i class="fas fa-medal"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-badged" id="emptyBadgedMessage">
                        <i class="fas fa-medal"></i>
                        <p>No badges earned yet</p>
                        <small class="text-muted">Complete courses to earn badges</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Real-time search for badged courses
    const searchInput = document.getElementById('badgeSearchInput');
    const badgedList = document.getElementById('badgedList');
    const badgedItems = badgedList ? badgedList.querySelectorAll('.badged-item') : [];
    const emptyMessage = document.getElementById('emptyBadgedMessage');
    
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase().trim();
            let visibleCount = 0;
            
            badgedItems.forEach(item => {
                const title = item.getAttribute('data-title') || item.querySelector('.badged-title').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || searchTerm === '') {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Show/hide empty message
            if (emptyMessage) {
                if (visibleCount === 0 && badgedItems.length > 0) {
                    emptyMessage.style.display = 'block';
                } else {
                    emptyMessage.style.display = 'none';
                }
            }
        });
    }
    </script>
</body>
</html>