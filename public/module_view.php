<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$u = current_user();

$moduleId = intval($_GET['id'] ?? 0);
if (!$moduleId) die('Invalid module ID');

// Fetch module
$stmt = $pdo->prepare('SELECT m.*, u.fname, u.lname, u.username as creator_name, c.name as committee_name 
                        FROM modules m 
                        LEFT JOIN users u ON m.created_by = u.id 
                        LEFT JOIN committees c ON m.committee_id = c.id 
                        WHERE m.id = ?');
$stmt->execute([$moduleId]);
$module = $stmt->fetch();
if (!$module) die('Module not found');

// Fetch or create module progress for this user
$progress = null;
$pdfProgress = [];

$stmt = $pdo->prepare('SELECT * FROM module_progress WHERE module_id = ? AND user_id = ?');
$stmt->execute([$moduleId, $u['id']]);
$progress = $stmt->fetch();

if (!$progress) {
    // Create new progress record
    $stmt = $pdo->prepare('INSERT INTO module_progress (module_id, user_id, last_accessed) VALUES (?, ?, NOW())');
    $stmt->execute([$moduleId, $u['id']]);
    $progressId = $pdo->lastInsertId();
    
    $progress = [
        'id' => $progressId,
        'pdf_completed' => 0,
        'video_completed' => 0,
        'pdf_progress' => 0,
        'video_progress' => 0,
        'pdf_total_pages' => 0,
        'video_position' => 0,
        'completed_at' => null
    ];
} else {
    $progressId = $progress['id'];
}

// Fetch PDF progress (viewed pages) if PDF exists
if ($module['file_pdf']) {
    $stmt = $pdo->prepare('SELECT page_number FROM module_pdf_progress WHERE progress_id = ? ORDER BY page_number');
    $stmt->execute([$progressId]);
    $pdfProgress = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Handle AJAX PDF page tracking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pdf_page'])) {
    $page = intval($_POST['pdf_page']);
    $totalPages = intval($_POST['total_pages'] ?? 0);
    
    try {
        $pdo->beginTransaction();

        // Get current progress
        $stmt = $pdo->prepare('SELECT * FROM module_progress WHERE id = ? FOR UPDATE');
        $stmt->execute([$progressId]);
        $currentProgress = $stmt->fetch();

        // If first page view, set total pages
        if ($currentProgress['pdf_total_pages'] == 0 && $totalPages > 0) {
            $stmt = $pdo->prepare('UPDATE module_progress SET pdf_total_pages = ? WHERE id = ?');
            $stmt->execute([$totalPages, $progressId]);
        }

        // Record page view
        $stmt = $pdo->prepare('INSERT IGNORE INTO module_pdf_progress (progress_id, page_number, viewed_at) VALUES (?, ?, NOW())');
        $stmt->execute([$progressId, $page]);

        // Count viewed pages
        $stmt = $pdo->prepare('SELECT COUNT(*) as viewed FROM module_pdf_progress WHERE progress_id = ?');
        $stmt->execute([$progressId]);
        $viewedCount = $stmt->fetchColumn();

        // Get total pages
        $totalPages = $currentProgress['pdf_total_pages'] > 0 ? $currentProgress['pdf_total_pages'] : $totalPages;
        
        // Calculate PDF progress percentage
        $pdfProgressPercent = $totalPages > 0 ? round(($viewedCount / $totalPages) * 100) : 0;
        $pdfCompleted = ($pdfProgressPercent >= 100) ? 1 : 0;
        
        // Update module_progress
        $stmt = $pdo->prepare('UPDATE module_progress SET pdf_progress = ?, pdf_completed = ? WHERE id = ?');
        $stmt->execute([$pdfProgressPercent, $pdfCompleted, $progressId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'pdf_progress' => $pdfProgressPercent,
            'pdf_completed' => $pdfCompleted,
            'viewed_count' => $viewedCount,
            'total_pages' => $totalPages
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("PDF Tracking Error: " . $e->getMessage());
        echo json_encode(['success' => false]);
    }
    exit;
}

// Handle AJAX video progress tracking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['video_position'])) {
    $position = intval($_POST['video_position']);
    $duration = intval($_POST['duration'] ?? 0);
    $completed = intval($_POST['completed'] ?? 0);
    
    try {
        $pdo->beginTransaction();

        // Calculate video progress percentage
        $videoProgressPercent = $duration > 0 ? round(($position / $duration) * 100) : 0;
        
        // Update video progress
        $stmt = $pdo->prepare('UPDATE module_progress SET video_position = ?, video_progress = ?, video_completed = ? WHERE id = ?');
        $stmt->execute([$position, $videoProgressPercent, $completed, $progressId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'video_progress' => $videoProgressPercent,
            'video_completed' => $completed
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Video Tracking Error: " . $e->getMessage());
        echo json_encode(['success' => false]);
    }
    exit;
}

// Validate file existence
$pdfPath = __DIR__ . '/../uploads/pdf/' . $module['file_pdf'];
$pdfExists = $module['file_pdf'] && file_exists($pdfPath);

$videoPath = __DIR__ . '/../uploads/video/' . $module['file_video'];
$videoExists = $module['file_video'] && file_exists($videoPath);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($module['title']) ?> - Module</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/moduleview.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
</head>
<body>
    <a href="#mainContent" class="skip-link">Skip to main content</a>

    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

    <div id="toastContainer" class="toast-notification"></div>
    <div id="fullscreenHint" class="fullscreen-hint" style="display: none;">
        <i class="fas fa-keyboard"></i> Press ESC to exit full screen
    </div>

    <div class="main-content-wrapper" id="mainContent" tabindex="-1">
        <!-- Module Header -->
        <div class="material-card">
            <div class="material-header">
                <div class="material-title">
                    <i class="fas fa-book-open text-primary" aria-hidden="true"></i>
                    <h5 class="mb-0"><?= htmlspecialchars($module['title']) ?></h5>
                </div>
            </div>
            <p class="text-muted mb-0"><?= nl2br(htmlspecialchars($module['description'])) ?></p>
            <div class="mt-3 pt-3 border-top">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-2">
                            <i class="fas fa-user text-primary" aria-hidden="true"></i>
                        </div>
                        <span class="text-muted">
                            <strong>Created by:</strong> <?= htmlspecialchars($module['creator_name'] ?? 'Unknown') ?>
                        </span>
                    </div>
                    <?php if ($module['committee_name']): ?>
                        <div>
                            <span class="committee-badge">
                                <i class="fas fa-users me-1"></i>
                                <?= htmlspecialchars($module['committee_name']) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Progress Section -->
        <?php if (is_student()): ?>
        <div class="material-card">
            <div class="material-header">
                <div class="material-title">
                    <i class="fas fa-chart-line text-primary" aria-hidden="true"></i>
                    <h5 class="mb-0">Your Progress</h5>
                </div>
            </div>

            <div class="row mb-3">
                <?php if ($module['file_pdf']): ?>
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-file-pdf text-danger me-2"></i>
                        <span>PDF Material: 
                            <strong>
                                <span id="pdfStatusText">
                                    <?= ($progress['pdf_completed'] ?? 0) == 1 ? 'Completed' : 'Ongoing' ?>
                                </span>
                            </strong>
                        </span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($module['file_video']): ?>
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-video text-primary me-2"></i>
                        <span>Video Material: 
                            <strong>
                                <span id="videoStatusText">
                                    <?= ($progress['video_completed'] ?? 0) == 1 ? 'Completed' : 'Ongoing' ?>
                                </span>
                            </strong>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="pdf-progress-container">
                <div class="pdf-progress-text">
                    <span>Overall Module Progress</span>
                    <span id="progressPercent">
                        <?php
                        // Calculate overall progress based on PDF and video
                        $total = 0;
                        $completed = 0;
                        if ($module['file_pdf']) {
                            $total++;
                            if ($progress['pdf_completed'] ?? 0) $completed++;
                        }
                        if ($module['file_video']) {
                            $total++;
                            if ($progress['video_completed'] ?? 0) $completed++;
                        }
                        $overallProgress = $total > 0 ? round(($completed / $total) * 100) : 0;
                        echo $overallProgress . '%';
                        ?>
                    </span>
                </div>
                <div class="pdf-progress-bar">
                    <div class="pdf-progress-fill" id="progressBar" style="width: <?= $overallProgress ?>%;"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- PDF Content - PDF.js Viewer -->
        <?php if ($module['file_pdf'] && $pdfExists): ?>
        <div class="material-card" id="pdfMaterialCard">
            <div class="material-header">
                <div class="material-title">
                    <i class="fas fa-file-pdf text-danger"></i>
                    <h5 class="mb-0">Module PDF Material</h5>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if (is_student()): ?>
                    <span class="material-status <?= ($progress['pdf_completed'] ?? 0) == 1 ? 'status-completed' : 'status-pending' ?>" id="pdfStatusBadge">
                        <?php if (($progress['pdf_completed'] ?? 0) == 1): ?>
                            <i class="fas fa-check-circle me-1"></i>Completed
                        <?php else: ?>
                            <i class="fas fa-clock me-1"></i>Ongoing
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                    <button class="btn btn-outline-primary" id="fullscreenPdfBtn" title="Toggle fullscreen mode (ESC to exit)">
                        <i class="fas fa-expand"></i>
                    </button>
                </div>
            </div>

            <div class="pdf-viewer-container" id="pdfViewerContainer">
                <div id="pdfPagesContainer" class="pdf-pages-container"></div>
            </div>
        </div>
        <?php elseif ($module['file_pdf'] && !$pdfExists): ?>
        <div class="material-card">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                PDF file is temporarily unavailable. Please contact support.
            </div>
        </div>
        <?php endif; ?>

        <!-- Video Content -->
        <?php if ($module['file_video'] && $videoExists): ?>
        <div class="material-card">
            <div class="material-header">
                <div class="material-title">
                    <i class="fas fa-video text-primary"></i>
                    <h5 class="mb-0">Module Video</h5>
                </div>
                <?php if (is_student()): ?>
                <span class="material-status <?= ($progress['video_completed'] ?? 0) == 1 ? 'status-completed' : 'status-pending' ?>" id="videoStatusBadge">
                    <?php if (($progress['video_completed'] ?? 0) == 1): ?>
                        <i class="fas fa-check-circle me-1"></i>Completed
                    <?php else: ?>
                        <i class="fas fa-clock me-1"></i>Ongoing
                    <?php endif; ?>
                </span>
                <?php endif; ?>
            </div>

            <div class="video-player-container">
                <video-js id="moduleVideo"
                         class="video-js vjs-default-skin"
                         controls
                         preload="auto"
                         width="100%"
                         height="100%"
                         data-setup='{"fluid": false}'>
                    <source src="<?= BASE_URL ?>/uploads/video/<?= htmlspecialchars(basename($module['file_video'])) ?>" type="video/mp4">
                </video-js>
            </div>
        </div>
        <?php elseif ($module['file_video'] && !$videoExists): ?>
        <div class="material-card">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Video file is temporarily unavailable. Please contact support.
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

        <?php if (is_student()): ?>
        // Student mode with progress tracking
        const moduleData = {
            id: <?= $moduleId ?>,
            hasPdf: <?= $module['file_pdf'] ? 'true' : 'false' ?>,
            hasVideo: <?= $module['file_video'] ? 'true' : 'false' ?>,
            pdfCompleted: <?= ($progress['pdf_completed'] ?? 0) ?>,
            videoCompleted: <?= ($progress['video_completed'] ?? 0) ?>,
            pdfProgress: <?= ($progress['pdf_progress'] ?? 0) ?>,
            videoProgress: <?= ($progress['video_progress'] ?? 0) ?>,
            pdfTotalPages: <?= ($progress['pdf_total_pages'] ?? 0) ?>,
            videoPosition: <?= ($progress['video_position'] ?? 0) ?>
        };

        // State management
        let state = {
            pdfDoc: null,
            totalPages: moduleData.pdfTotalPages,
            viewedSet: new Set(<?= json_encode($pdfProgress) ?>),
            serverConfirmed: new Set(<?= json_encode($pdfProgress) ?>),
            pdfCompleted: moduleData.pdfCompleted,
            pdfProgress: moduleData.pdfProgress,
            isFullscreen: false,
            currentPage: 1,
            pageCache: {}
        };
        
        if (moduleData.hasVideo) {
            state.videoCompleted = moduleData.videoCompleted;
            state.videoProgress = moduleData.videoProgress;
        }

        // DOM Elements
        const elements = {
            pagesContainer: document.getElementById('pdfPagesContainer'),
            fullscreenBtn: document.getElementById('fullscreenPdfBtn'),
            pdfCard: document.getElementById('pdfMaterialCard'),
            fullscreenHint: document.getElementById('fullscreenHint'),
            progressPercent: document.getElementById('progressPercent'),
            progressBar: document.getElementById('progressBar'),
            pdfStatusBadge: document.getElementById('pdfStatusBadge'),
            pdfStatusText: document.getElementById('pdfStatusText'),
            videoStatusBadge: document.getElementById('videoStatusBadge'),
            videoStatusText: document.getElementById('videoStatusText')
        };

        // Update overall progress display
        function updateOverallProgress() {
            let total = 0;
            let completed = 0;
            
            if (moduleData.hasPdf) {
                total++;
                if (state.pdfCompleted) completed++;
            }
            if (moduleData.hasVideo) {
                total++;
                if (state.videoCompleted) completed++;
            }
            
            const percent = total > 0 ? Math.round((completed / total) * 100) : 0;
            
            if (elements.progressPercent) {
                elements.progressPercent.textContent = percent + '%';
            }
            if (elements.progressBar) {
                elements.progressBar.style.width = percent + '%';
            }
        }

        // Update PDF badge with percentage
        function updatePdfBadge() {
            if (!moduleData.hasPdf || !elements.pdfStatusBadge) return;
            
            if (state.pdfCompleted) {
                elements.pdfStatusBadge.className = 'material-status status-completed';
                elements.pdfStatusBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i>Completed';
                if (elements.pdfStatusText) elements.pdfStatusText.textContent = 'Completed';
            } else {
                elements.pdfStatusBadge.className = 'material-status status-pending';
                if (state.pdfProgress > 0) {
                    elements.pdfStatusBadge.innerHTML = '<i class="fas fa-clock me-1"></i>' + state.pdfProgress + '%';
                    if (elements.pdfStatusText) elements.pdfStatusText.textContent = state.pdfProgress + '%';
                } else {
                    elements.pdfStatusBadge.innerHTML = '<i class="fas fa-clock me-1"></i>Ongoing';
                    if (elements.pdfStatusText) elements.pdfStatusText.textContent = 'Ongoing';
                }
            }
        }

        // Update Video badge with percentage
        function updateVideoBadge() {
            if (!moduleData.hasVideo || !elements.videoStatusBadge) return;
            
            if (state.videoCompleted) {
                elements.videoStatusBadge.className = 'material-status status-completed';
                elements.videoStatusBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i>Completed';
                if (elements.videoStatusText) elements.videoStatusText.textContent = 'Completed';
            } else {
                elements.videoStatusBadge.className = 'material-status status-pending';
                if (state.videoProgress > 0) {
                    elements.videoStatusBadge.innerHTML = '<i class="fas fa-clock me-1"></i>' + state.videoProgress + '%';
                    if (elements.videoStatusText) elements.videoStatusText.textContent = state.videoProgress + '%';
                } else {
                    elements.videoStatusBadge.innerHTML = '<i class="fas fa-clock me-1"></i>Ongoing';
                    if (elements.videoStatusText) elements.videoStatusText.textContent = 'Ongoing';
                }
            }
        }

        // PDF Viewer
        if (elements.pagesContainer && <?= $pdfExists ? 'true' : 'false' ?>) {
            const pdfUrl = '<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars(basename($module['file_pdf'])) ?>';
            
            pdfjsLib.getDocument(pdfUrl).promise
                .then(function(pdf) {
                    state.pdfDoc = pdf;
                    state.totalPages = pdf.numPages;
                    
                    updatePdfBadge();
                    updateOverallProgress();

                    elements.pagesContainer.innerHTML = '';

                    for (let num = 1; num <= Math.min(3, pdf.numPages); num++) {
                        renderPage(num);
                    }

                    setTimeout(() => {
                        for (let num = 4; num <= pdf.numPages; num++) {
                            renderPage(num);
                        }
                    }, 500);

                    setTimeout(checkVisiblePages, 1000);
                })
                .catch(function(error) {
                    console.error('Error loading PDF:', error);
                    elements.pagesContainer.innerHTML = '<div class="alert alert-danger m-3">Error loading PDF. Please try again.</div>';
                });

            function renderPage(num) {
                if (state.pageCache[num]) {
                    const pageWrapper = state.pageCache[num].cloneNode(true);
                    elements.pagesContainer.appendChild(pageWrapper);
                    return;
                }

                state.pdfDoc.getPage(num).then(function(page) {
                    const container = document.getElementById('pdfViewerContainer');
                    const containerWidth = container ? container.clientWidth - 60 : 800;
                    const viewport = page.getViewport({ scale: 1 });
                    const scale = containerWidth / viewport.width;
                    const scaledViewport = page.getViewport({ scale: scale });

                    const pageWrapper = document.createElement('div');
                    pageWrapper.className = 'pdf-page-wrapper';
                    pageWrapper.id = `pdf-page-${num}`;

                    const canvas = document.createElement('canvas');
                    canvas.className = 'pdf-page-canvas';
                    canvas.height = scaledViewport.height;
                    canvas.width = scaledViewport.width;
                    pageWrapper.appendChild(canvas);

                    if (state.viewedSet.has(num)) {
                        const badge = document.createElement('div');
                        badge.className = 'page-viewed-badge';
                        badge.id = `page-badge-${num}`;
                        badge.innerHTML = '<i class="fas fa-check"></i>';
                        pageWrapper.appendChild(badge);
                    }

                    elements.pagesContainer.appendChild(pageWrapper);

                    page.render({
                        canvasContext: canvas.getContext('2d'),
                        viewport: scaledViewport
                    });

                    state.pageCache[num] = pageWrapper.cloneNode(true);

                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting && !state.viewedSet.has(num) && !state.pdfCompleted) {
                                trackPageView(num);

                                if (!document.getElementById(`page-badge-${num}`)) {
                                    const badge = document.createElement('div');
                                    badge.className = 'page-viewed-badge';
                                    badge.id = `page-badge-${num}`;
                                    badge.innerHTML = '<i class="fas fa-check"></i>';
                                    pageWrapper.appendChild(badge);
                                }
                            }
                        });
                    }, { threshold: 0.5 });

                    observer.observe(pageWrapper);
                });
            }

            function trackPageView(pageNum) {
                if (state.serverConfirmed.has(pageNum) || state.pdfCompleted) return;

                if (!state.viewedSet.has(pageNum)) {
                    state.viewedSet.add(pageNum);
                    
                    const pdfProgressPercent = Math.round((state.viewedSet.size / state.totalPages) * 100);
                    state.pdfProgress = pdfProgressPercent;
                    
                    if (pdfProgressPercent >= 100) {
                        state.pdfCompleted = true;
                    }
                    
                    updatePdfBadge();
                    updateOverallProgress();
                    
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: {
                            pdf_page: pageNum,
                            total_pages: state.totalPages
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                state.serverConfirmed.add(pageNum);
                                if (response.pdf_completed) {
                                    state.pdfCompleted = true;
                                }
                                if (response.pdf_progress !== undefined) {
                                    state.pdfProgress = response.pdf_progress;
                                }
                                updatePdfBadge();
                                updateOverallProgress();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error tracking page:', error);
                        }
                    });
                }
            }

            function checkVisiblePages() {
                if (state.pdfCompleted || !elements.pagesContainer) return;

                const container = document.getElementById('pdfViewerContainer');
                if (!container) return;

                const containerRect = container.getBoundingClientRect();

                for (let num = 1; num <= state.totalPages; num++) {
                    const pageElement = document.getElementById(`pdf-page-${num}`);
                    if (!pageElement) continue;

                    const pageRect = pageElement.getBoundingClientRect();
                    const isVisible = (
                        pageRect.top < containerRect.bottom &&
                        pageRect.bottom > containerRect.top
                    );

                    if (isVisible && !state.viewedSet.has(num) && !state.serverConfirmed.has(num) && !state.pdfCompleted) {
                        trackPageView(num);

                        if (!document.getElementById(`page-badge-${num}`)) {
                            const badge = document.createElement('div');
                            badge.className = 'page-viewed-badge';
                            badge.id = `page-badge-${num}`;
                            badge.innerHTML = '<i class="fas fa-check"></i>';
                            pageElement.appendChild(badge);
                        }
                    } else if (isVisible) {
                        state.currentPage = num;
                    }
                }
            }

            let scrollTimeout;
            document.getElementById('pdfViewerContainer')?.addEventListener('scroll', function() {
                if (!scrollTimeout) {
                    scrollTimeout = setTimeout(function() {
                        checkVisiblePages();
                        scrollTimeout = null;
                    }, 200);
                }
            });
        }

        // Video Player
        <?php if ($module['file_video'] && $videoExists): ?>
        videojs('moduleVideo').ready(function() {
            const player = this;
            let completionReported = state.videoCompleted;
            let saveTimeout = null;

            if (moduleData.videoPosition > 0 && !state.videoCompleted) {
                player.currentTime(moduleData.videoPosition);
            }

            function saveVideoProgress(position, duration, completed) {
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: {
                        video_position: position,
                        duration: duration,
                        completed: completed
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            if (response.video_progress !== undefined) {
                                state.videoProgress = response.video_progress;
                            }
                            if (response.video_completed) {
                                state.videoCompleted = true;
                            }
                            updateVideoBadge();
                            updateOverallProgress();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error tracking video:', error);
                    }
                });
            }

            player.on('timeupdate', function() {
                if (completionReported || state.videoCompleted) return;

                const position = Math.floor(player.currentTime());
                const duration = Math.floor(player.duration());
                
                if (duration === 0) return;
                
                const percent = Math.round((position / duration) * 100);
                
                state.videoProgress = percent;
                updateVideoBadge();
                updateOverallProgress();
                
                if (percent >= 95 && !completionReported) {
                    completionReported = true;
                    state.videoCompleted = true;
                    state.videoProgress = 100;
                    updateVideoBadge();
                    updateOverallProgress();
                    
                    saveVideoProgress(position, duration, 1);
                } else {
                    if (saveTimeout) clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(function() {
                        if (!completionReported && !state.videoCompleted) {
                            saveVideoProgress(position, duration, 0);
                        }
                    }, 5000);
                }
            });

            player.on('ended', function() {
                if (!completionReported && !state.videoCompleted) {
                    completionReported = true;
                    state.videoCompleted = true;
                    state.videoProgress = 100;
                    updateVideoBadge();
                    updateOverallProgress();
                    
                    saveVideoProgress(player.duration(), player.duration(), 1);
                }
            });
        });
        <?php endif; ?>

        // Fullscreen Toggle
        if (elements.fullscreenBtn && elements.pdfCard) {
            elements.fullscreenBtn.addEventListener('click', function() {
                state.isFullscreen = !state.isFullscreen;

                if (state.isFullscreen) {
                    elements.pdfCard.classList.add('pdf-fullscreen-mode');
                    elements.fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i>';
                    if (elements.fullscreenHint) elements.fullscreenHint.style.display = 'block';

                    setTimeout(() => {
                        if (elements.fullscreenHint) elements.fullscreenHint.style.display = 'none';
                    }, 3000);
                } else {
                    elements.pdfCard.classList.remove('pdf-fullscreen-mode');
                    elements.fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
                    if (elements.fullscreenHint) elements.fullscreenHint.style.display = 'none';
                }

                setTimeout(() => window.dispatchEvent(new Event('resize')), 100);
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && state.isFullscreen) {
                state.isFullscreen = false;
                if (elements.pdfCard) elements.pdfCard.classList.remove('pdf-fullscreen-mode');
                if (elements.fullscreenBtn) elements.fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
                if (elements.fullscreenHint) elements.fullscreenHint.style.display = 'none';
            }

            if (state.isFullscreen && moduleData.hasPdf) {
                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    if (state.currentPage < state.totalPages) {
                        document.getElementById(`pdf-page-${state.currentPage + 1}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    if (state.currentPage > 1) {
                        document.getElementById(`pdf-page-${state.currentPage - 1}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            }
        });

        // Toast notification function
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show`;
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.getElementById('toastContainer');
            container.innerHTML = '';
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }

        // Initial progress update
        updatePdfBadge();
        <?php if ($module['file_video'] && $videoExists): ?>
        updateVideoBadge();
        <?php endif; ?>
        updateOverallProgress();
        
        <?php else: ?>
        // Non-student mode - view only, no progress tracking
        const moduleData = {
            hasPdf: <?= $module['file_pdf'] ? 'true' : 'false' ?>,
            hasVideo: <?= $module['file_video'] ? 'true' : 'false' ?>
        };

        // DOM Elements for non-student
        const elements = {
            pagesContainer: document.getElementById('pdfPagesContainer'),
            fullscreenBtn: document.getElementById('fullscreenPdfBtn'),
            pdfCard: document.getElementById('pdfMaterialCard'),
            fullscreenHint: document.getElementById('fullscreenHint')
        };

        // Simple state for non-student
        let state = {
            pdfDoc: null,
            totalPages: 0,
            isFullscreen: false,
            currentPage: 1,
            pageCache: {}
        };

        // PDF Viewer (view only, no tracking)
        if (elements.pagesContainer && <?= $pdfExists ? 'true' : 'false' ?>) {
            const pdfUrl = '<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars(basename($module['file_pdf'])) ?>';
            
            pdfjsLib.getDocument(pdfUrl).promise
                .then(function(pdf) {
                    state.pdfDoc = pdf;
                    state.totalPages = pdf.numPages;
                    elements.pagesContainer.innerHTML = '';

                    for (let num = 1; num <= Math.min(3, pdf.numPages); num++) {
                        renderPage(num);
                    }

                    setTimeout(() => {
                        for (let num = 4; num <= pdf.numPages; num++) {
                            renderPage(num);
                        }
                    }, 500);
                })
                .catch(function(error) {
                    console.error('Error loading PDF:', error);
                    elements.pagesContainer.innerHTML = '<div class="alert alert-danger m-3">Error loading PDF. Please try again.</div>';
                });

            function renderPage(num) {
                if (state.pageCache[num]) {
                    const pageWrapper = state.pageCache[num].cloneNode(true);
                    elements.pagesContainer.appendChild(pageWrapper);
                    return;
                }

                state.pdfDoc.getPage(num).then(function(page) {
                    const container = document.getElementById('pdfViewerContainer');
                    const containerWidth = container ? container.clientWidth - 60 : 800;
                    const viewport = page.getViewport({ scale: 1 });
                    const scale = containerWidth / viewport.width;
                    const scaledViewport = page.getViewport({ scale: scale });

                    const pageWrapper = document.createElement('div');
                    pageWrapper.className = 'pdf-page-wrapper';
                    pageWrapper.id = `pdf-page-${num}`;

                    const canvas = document.createElement('canvas');
                    canvas.className = 'pdf-page-canvas';
                    canvas.height = scaledViewport.height;
                    canvas.width = scaledViewport.width;
                    pageWrapper.appendChild(canvas);

                    elements.pagesContainer.appendChild(pageWrapper);

                    page.render({
                        canvasContext: canvas.getContext('2d'),
                        viewport: scaledViewport
                    });

                    state.pageCache[num] = pageWrapper.cloneNode(true);
                });
            }

            // Update current page on scroll for keyboard navigation
            let scrollTimeout;
            document.getElementById('pdfViewerContainer')?.addEventListener('scroll', function() {
                if (!scrollTimeout) {
                    scrollTimeout = setTimeout(function() {
                        const container = document.getElementById('pdfViewerContainer');
                        if (!container) return;
                        
                        const containerRect = container.getBoundingClientRect();
                        for (let num = 1; num <= state.totalPages; num++) {
                            const pageElement = document.getElementById(`pdf-page-${num}`);
                            if (!pageElement) continue;
                            
                            const pageRect = pageElement.getBoundingClientRect();
                            const isVisible = (
                                pageRect.top < containerRect.bottom &&
                                pageRect.bottom > containerRect.top
                            );
                            
                            if (isVisible) {
                                state.currentPage = num;
                                break;
                            }
                        }
                        scrollTimeout = null;
                    }, 200);
                }
            });
        }

        // Video Player (view only, no tracking)
        <?php if ($module['file_video'] && $videoExists): ?>
        videojs('moduleVideo').ready(function() {
            const player = this;
            // No progress tracking for non-students
        });
        <?php endif; ?>

        // Fullscreen Toggle
        if (elements.fullscreenBtn && elements.pdfCard) {
            elements.fullscreenBtn.addEventListener('click', function() {
                state.isFullscreen = !state.isFullscreen;

                if (state.isFullscreen) {
                    elements.pdfCard.classList.add('pdf-fullscreen-mode');
                    elements.fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i>';
                    if (elements.fullscreenHint) elements.fullscreenHint.style.display = 'block';

                    setTimeout(() => {
                        if (elements.fullscreenHint) elements.fullscreenHint.style.display = 'none';
                    }, 3000);
                } else {
                    elements.pdfCard.classList.remove('pdf-fullscreen-mode');
                    elements.fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
                    if (elements.fullscreenHint) elements.fullscreenHint.style.display = 'none';
                }

                setTimeout(() => window.dispatchEvent(new Event('resize')), 100);
            });
        }

        // Keyboard shortcuts for fullscreen
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && state.isFullscreen) {
                state.isFullscreen = false;
                if (elements.pdfCard) elements.pdfCard.classList.remove('pdf-fullscreen-mode');
                if (elements.fullscreenBtn) elements.fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
                if (elements.fullscreenHint) elements.fullscreenHint.style.display = 'none';
            }

            if (state.isFullscreen && moduleData.hasPdf) {
                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    if (state.currentPage < state.totalPages) {
                        document.getElementById(`pdf-page-${state.currentPage + 1}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    if (state.currentPage > 1) {
                        document.getElementById(`pdf-page-${state.currentPage - 1}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            }
        });
        <?php endif; ?>
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>