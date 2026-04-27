<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$pdo = $pdo;
$current_user_id = $_SESSION['user']['id'];

// Mark unviewed training requests as viewed for this user
$update_viewed = $pdo->prepare("
    UPDATE pm_training_requests 
    SET viewed_at = NOW() 
    WHERE viewed_at IS NULL 
    AND status IN ('pending', 'approved')
");
$update_viewed->execute();

// Get user's committee
$stmt = $pdo->prepare("SELECT committee_id FROM user_departments WHERE user_id = ?");
$stmt->execute([$current_user_id]);
$user_committee_row = $stmt->fetch();
$user_committee_id = $user_committee_row['committee_id'] ?? null;

// Get all committees for filter dropdown
$committees_stmt = $pdo->query("SELECT DISTINCT id, name FROM committees ORDER BY name");
$committees = $committees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter parameters
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : '';
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : '';
$filter_committee = isset($_GET['committee']) ? (int)$_GET['committee'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where_clauses = [
    "pta.user_id = ?"  // Only trainings user is part of
];
$params = [$current_user_id];

if (!empty($filter_year)) {
    $where_clauses[] = "YEAR(ptr.date_start) = ?";
    $params[] = $filter_year;
}

if (!empty($filter_month)) {
    $where_clauses[] = "MONTH(ptr.date_start) = ?";
    $params[] = $filter_month;
}

if (!empty($filter_committee)) {
    $where_clauses[] = "com.id = ?";
    $params[] = $filter_committee;
}

if (!empty($search_query)) {
    $where_clauses[] = "(ptr.title LIKE ? OR ptr.venue LIKE ? OR ptr.remarks LIKE ? OR ptr.hospital_order_no LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = implode(" AND ", $where_clauses);

$query = "SELECT 
    ptr.*,
    COALESCE(CONCAT(u.fname, ' ', u.lname), u.username, 'Unknown') as requester_name,
    pta.attended,
    COALESCE(com.name, '-') as committee_name
    FROM pm_training_requests ptr
    INNER JOIN pm_training_attendance pta ON ptr.id = pta.pm_training_request_id
    LEFT JOIN users u ON ptr.requester_id = u.id
    LEFT JOIN user_departments ud ON ud.user_id = ptr.requester_id
    LEFT JOIN committees com ON ud.committee_id = com.id
    WHERE $where_sql
    GROUP BY ptr.id, pta.user_id
    ORDER BY ptr.date_start DESC";


$stmt = $pdo->prepare($query);
$stmt->execute($params);
$trainings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available years
$years_stmt = $pdo->query("SELECT DISTINCT YEAR(date_start) as year FROM pm_training_requests ORDER BY year DESC");
$years = $years_stmt->fetchAll(PDO::FETCH_ASSOC);

// Count active filters
$active_filters = 0;
if (!empty($filter_year)) $active_filters++;
if (!empty($filter_month)) $active_filters++;
if (!empty($filter_committee)) $active_filters++;
if (!empty($search_query)) $active_filters++;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Training List - LMS</title>
     <link href="<?= BASE_URL ?>/assets/css/traininglist.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

</head>
<body>
    <?php include __DIR__ . '/../inc/header.php'; ?>
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2> My Training List</h2>
            </div>
        </div>

        <!-- Filter Section - No Card, buttons on right -->
        <div class="filter-section">
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="form-label">Year</label>
                        <select name="year" class="form-select">
                            <option value="">All Years</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y['year'] ?>" <?= ($filter_year == $y['year']) ? 'selected' : '' ?>>
                                    <?= $y['year'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="form-label">Month</label>
                        <select name="month" class="form-select">
                            <option value="">All Months</option>
                            <option value="1" <?= ($filter_month == 1) ? 'selected' : '' ?>>January</option>
                            <option value="2" <?= ($filter_month == 2) ? 'selected' : '' ?>>February</option>
                            <option value="3" <?= ($filter_month == 3) ? 'selected' : '' ?>>March</option>
                            <option value="4" <?= ($filter_month == 4) ? 'selected' : '' ?>>April</option>
                            <option value="5" <?= ($filter_month == 5) ? 'selected' : '' ?>>May</option>
                            <option value="6" <?= ($filter_month == 6) ? 'selected' : '' ?>>June</option>
                            <option value="7" <?= ($filter_month == 7) ? 'selected' : '' ?>>July</option>
                            <option value="8" <?= ($filter_month == 8) ? 'selected' : '' ?>>August</option>
                            <option value="9" <?= ($filter_month == 9) ? 'selected' : '' ?>>September</option>
                            <option value="10" <?= ($filter_month == 10) ? 'selected' : '' ?>>October</option>
                            <option value="11" <?= ($filter_month == 11) ? 'selected' : '' ?>>November</option>
                            <option value="12" <?= ($filter_month == 12) ? 'selected' : '' ?>>December</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="form-label">Committee</label>
                        <select name="committee" class="form-select">
                            <option value="">All Committees</option>
                            <?php foreach ($committees as $comm): ?>
                                <option value="<?= $comm['id'] ?>" <?= ($filter_committee == $comm['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($comm['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="search-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Title, Venue, HO No..." value="<?= htmlspecialchars($search_query) ?>">
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-item">
                <span class="stat-label">Total Trainings:</span>
                <span class="stat-number"><?= count($trainings) ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Attended:</span>
                <span class="stat-number"><?= count(array_filter($trainings, fn($t) => $t['attended'])) ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Not Attended:</span>
                <span class="stat-number"><?= count(array_filter($trainings, fn($t) => !$t['attended'])) ?></span>
            </div>
            <?php if ($active_filters > 0): ?>
                <div class="stat-item">
                    <span class="stat-label">Filters Active:</span>
                    <span class="stat-number"><?= $active_filters ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Active Filters Notification -->
        <?php if ($active_filters > 0): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle"></i>
                <strong>Filters Applied:</strong>
                <?php 
                    $filter_texts = [];
                    if (!empty($filter_year)) $filter_texts[] = "Year: " . $filter_year;
                    if (!empty($filter_month)) {
                        $months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                        $filter_texts[] = "Month: " . $months[$filter_month];
                    }
                    if (!empty($filter_committee)) {
                        $comm = array_filter($committees, fn($c) => $c['id'] == $filter_committee);
                        if (!empty($comm)) {
                            $comm = reset($comm);
                            $filter_texts[] = "Committee: " . htmlspecialchars($comm['name']);
                        }
                    }
                    if (!empty($search_query)) $filter_texts[] = "Search: \"" . htmlspecialchars($search_query) . "\"";
                    echo implode(" | ", $filter_texts);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Training List Table - No Card -->
        <div class="table-wrapper">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Venue</th>
                            <th>Start Date</th>
                            <th>Start Time</th>
                            <th>End Date</th>
                            <th>Requester</th>
                            <th>Committee</th>
                            <th>Attended</th>
                            <!-- <th>Status</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($trainings)): ?>
                            <?php foreach ($trainings as $training): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($training['title']) ?></strong></td>
                                    <td><?= htmlspecialchars($training['venue']) ?></td>
                                    <td><?= date('M d, Y', strtotime($training['date_start'])) ?></td>
                                    <td><?= !empty($training['time_start']) ? date('h:i A', strtotime($training['time_start'])) : '-' ?></td>
                                    <td><?= date('M d, Y', strtotime($training['date_end'])) ?></td>
                                    <td><?= htmlspecialchars($training['requester_name'] ?? 'Unknown') ?></td>
                                    <td><?= htmlspecialchars($training['committee_name'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($training['attended']): ?>
                                            <span class="badge badge-success">Yes</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = match($training['status']) {
                                            'complete' => 'badge-success',
                                            'approved' => 'badge-info',
                                            'pending' => 'badge-warning',
                                            default => 'badge-secondary'
                                        };
                                        ?>
                                        <!-- <span class="badge <?= $status_class ?>">
                                            <?= ucfirst($training['status']) ?>
                                        </span> -->
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fas fa-inbox" style="font-size: 2rem; color: #dee2e6;"></i>
                                    <p class="text-muted mt-2">No training records found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../inc/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
