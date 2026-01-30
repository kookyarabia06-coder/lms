<?php


require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();





$userId = $_SESSION['user']['id'];

// Fetch all courses with enrollment info for current user
$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.description, c.thumbnail, c.file_pdf, c.file_video,
           e.status AS enroll_status, e.progress
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
    WHERE c.is_active = 1
    ORDER BY c.id DESC
");
$stmt->execute([$userId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Calculate counters
$counter = ['ongoing' => 0, 'completed' => 0, 'not_enrolled' => 0];
foreach ($courses as $c) {
    if (!$c['enroll_status']) $counter['not_enrolled']++;
    elseif ($c['enroll_status'] === 'ongoing') $counter['ongoing']++;
    elseif ($c['enroll_status'] === 'completed') $counter['completed']++;
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>LMS Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/path/to/your/style.css"> <!-- adjust path -->
    <style>
        /* Layout Fix */
        body {
            display: flex;
            min-height: 100vh;
            margin: 0;
        }

        .sidebar {
            width: 240px;
            min-height: 100vh;
            padding: 1rem;
            background-color: #fff;
            border-right: 1px solid #ddd;
            position: fixed;
        }

        .main {
            margin-left: 240px;
            padding: 20px;
            flex: 1;
            background-color: #f9f9f9;
        }

        .counter-card {
            padding: 1.5rem;
            border-radius: 0.5rem;
            color: #fff;
            cursor: pointer;
            text-align: center;
        }

        .counter-card.bg-ongoing {
            background-color: #28a745;
        }

        .counter-card.bg-completed {
            background-color: #6c757d;
        }

        .counter-card.bg-not-enrolled {
            background-color: #007bff;
        }

        .card a {
            text-decoration: none;
            color: inherit;
        }

        .progress-bar {
            transition: width 0.8s ease;
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }

            .sidebar {
                position: relative;
                width: 100%;
            }

            .main {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/../inc/sidebar.php'; ?>

    <div class="main">
        <h3>All Courses</h3>

        <!-- Counters -->
        <div class="row my-4 g-3">
            <div class="col-md-4">
                <div class="counter-card bg-ongoing">
                    <h3><?= $counter['ongoing'] ?></h3>
                    <p>Ongoing Courses</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="counter-card bg-completed">
                    <h3><?= $counter['completed'] ?></h3>
                    <p>Completed Courses</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="counter-card bg-not-enrolled">
                    <h3><?= $counter['not_enrolled'] ?></h3>
                    <p>Not Enrolled</p>
                </div>
            </div>
        </div>

        <!-- Courses -->
<div class="row row-cols-1 row-cols-md-4 g-4 mt-3">
<?php foreach ($courses as $c):
    $totalDuration = 0;
        if ($c['file_pdf']) $totalDuration += 60;  // 1 min PDF
        if ($c['file_video']) $totalDuration += 300; // 5 min video

        $secondsSpent = isset($c['progress']) ? (int)$c['progress'] : 0;

        // Calculate percentage
        if ($totalDuration > 0) {
            $progressPercent = min(100, round(($secondsSpent / $totalDuration) * 100));
        } else {
            $progressPercent = 0;
        }
    $completedAt = $c['completed_at'] ?? null;
    $startedAt = $c['started_at'] ?? null;
    $totalTime = $c['total_time_seconds'] ?? 0;
    $courseUrl = BASE_URL . "/public/course_view.php?id={$c['id']}";

    // Convert total time seconds to H:i format
   $secondsSpent = isset($c['progress']) ? (int)$c['progress'] : 0;

    $days = floor($secondsSpent / 86400); // 1 day = 86400 sec
    $hours = floor(($secondsSpent % 86400) / 3600);
    $minutes = floor(($secondsSpent % 3600) / 60);
    $seconds = $secondsSpent % 60;

    $formattedTime = '';
    if($days > 0) $formattedTime .= $days . 'd ';
    if($hours > 0 || $days > 0) $formattedTime .= $hours . 'h ';
    if($minutes > 0 || $hours > 0 || $days > 0) $formattedTime .= $minutes . 'm ';
    $formattedTime .= $seconds . 's';

?>
    <div class="col">
        <a href="<?= $courseUrl ?>" style="text-decoration:none; color:inherit;">
            <div class="card shadow-sm h-100">
                <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'placeholder.png') ?>" class="card-img-top" alt="Course Image">
                <div class="card-body d-flex flex-column">
                    <h6 class="mb-2">
                        <?= htmlspecialchars($c['title']) ?>
                        <?php if ($c['enroll_status'] === 'ongoing'): ?>
                            <span class="badge bg-warning">Ongoing</span>
                        <?php elseif ($c['enroll_status'] === 'completed'): ?>
                            <span class="badge bg-success">Completed</span>
                        <?php else: ?>
                            <span class="badge bg-primary">Not Enrolled</span>
                        <?php endif; ?>
                    </h6>
                            <small class="text-muted d-block">Total time: <?= $formattedTime ?></small>

                    <?php if ($c['enroll_status'] && $c['enroll_status'] !== 'expired'): ?>
                        <!-- Progress bar -->
                        <div class="progress mb-1" style="height:15px;">
                            <div class="progress-bar" role="progressbar" style="width: <?= $progressPercent ?>%; font-weight:bold; font-size:0.9rem;" aria-valuenow="<?= $progressPercent ?>" aria-valuemin="0" aria-valuemax="100">
                                <?= $progressPercent ?>%
                            </div>
                        </div>

                        <!-- Timestamps -->
                        <?php if ($startedAt): ?>
                            <small class="text-muted d-block">Started: <?= date('M d, Y H:i', strtotime($startedAt)) ?></small>
                        <?php endif; ?>
                        <?php if ($c['enroll_status'] === 'completed' && $completedAt): ?>
                            <small class="text-muted d-block">Completed: <?= date('M d, Y H:i', strtotime($completedAt)) ?></small>
                        <?php endif; ?>
                        <?php if ($totalTime): ?>
                            <small class="text-muted d-block">Total time: <?= $formattedTime ?></small>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>