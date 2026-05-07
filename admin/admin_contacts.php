<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

// Only admin can access
if (!is_admin() && !is_superadmin()) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $selected_ids = $_POST['selected_ids'] ?? [];
    if (!empty($selected_ids)) {
        $ids = implode(',', array_map('intval', $selected_ids));
        
        if ($_POST['bulk_action'] === 'mark_read') {
            $pdo->exec("UPDATE contact_messages SET is_read = 1 WHERE id IN ($ids)");
        } elseif ($_POST['bulk_action'] === 'mark_unread') {
            $pdo->exec("UPDATE contact_messages SET is_read = 0 WHERE id IN ($ids)");
        } elseif ($_POST['bulk_action'] === 'delete') {
            $pdo->exec("DELETE FROM contact_messages WHERE id IN ($ids)");
        }
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?filter=' . ($_GET['filter'] ?? 'all'));
        exit;
    }
}

// Handle single actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    switch ($action) {
        case 'mark_read':
            $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
            $stmt->execute([$id]);
            break;
        case 'mark_unread':
            $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 0 WHERE id = ?");
            $stmt->execute([$id]);
            break;
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
            $stmt->execute([$id]);
            break;
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?filter=' . ($_GET['filter'] ?? 'all'));
    exit;
}

// Get filter
$filter = $_GET['filter'] ?? 'all';

// Get counts
$unread_count = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
$read_count = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 1")->fetchColumn();
$total_count = $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();

// Get messages based on filter
if ($filter === 'unread') {
    $messages = $pdo->query("SELECT * FROM contact_messages WHERE is_read = 0 ORDER BY created_at DESC")->fetchAll();
} elseif ($filter === 'read') {
    $messages = $pdo->query("SELECT * FROM contact_messages WHERE is_read = 1 ORDER BY created_at DESC")->fetchAll();
} else {
    $messages = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - Admin</title>
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
     <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #1a73e8;
            --danger-color: #d93025;
            --success-color: #188038;
            --text-primary: #202124;
            --text-secondary: #5f6368;
            --border-light: #e0e0e0;
            --bg-hover: #f1f3f4;
            --bg-unread: #f2f6fc;
            --bg-selected: #e8f0fe;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
            --shadow-md: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: var(--text-primary);
            font-size: 14px;
            line-height: 1.5;
        }

        /* Layout */
        .main-content-wrapper {
            margin-left: 280px;
            padding: 24px 32px;
            min-height: 100vh;
        }

        /* Header */
        .page-header {
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 400;
            color: #170678;
            margin: 0 0 12px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header h1 i {
            color: var(--primary-color);
            font-size: 28px;
        }

        .header-stats {
            display: flex;
            gap: 24px;
            color: var(--text-secondary);
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-item i {
            font-size: 14px;
        }

        .stat-badge {
            background: var(--primary-color);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            margin-left: 4px;
        }

        /* Filter Bar */
        .filter-bar {
            background: var(--white);
            border-radius: 8px 8px 0 0;
            padding: 12px 16px;
            border: 1px solid var(--border-light);
            border-bottom: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .filter-group {
            display: flex;
            gap: 4px;
        }

        .filter-btn {
            padding: 6px 16px;
            border-radius: 18px;
            font-weight: 500;
            font-size: 14px;
            text-decoration: none;
            color: var(--text-secondary);
            transition: all 0.2s;
        }

        .filter-btn:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .filter-btn.active {
            background: var(--bg-unread);
            color: var(--primary-color);
        }

        .filter-btn i {
            margin-right: 6px;
            font-size: 14px;
        }

        /* Bulk Actions */
        .bulk-actions {
            display: flex;
            gap: 8px;
            opacity: 0.4;
            pointer-events: none;
            transition: opacity 0.2s;
        }

        .bulk-actions.active {
            opacity: 1;
            pointer-events: all;
        }

        .bulk-action-btn {
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 13px;
            border: 1px solid var(--border-light);
            background: var(--white);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
        }

        .bulk-action-btn:hover {
            background: var(--bg-hover);
            border-color: var(--text-secondary);
        }

        .bulk-action-btn.delete:hover {
            background: #fce8e6;
            border-color: var(--danger-color);
            color: var(--danger-color);
        }

        /* Search Box */
        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 14px;
        }

        .search-box input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            border: 1px solid var(--border-light);
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            transition: all 0.2s;
        }

        .search-box input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 1px 4px rgba(26,115,232,0.2);
        }

        .search-box input::placeholder {
            color: var(--text-secondary);
        }

        /* Messages Container */
        .messages-container {
            background: var(--white);
            border: 1px solid var(--border-light);
            border-radius: 0 0 8px 8px;
            overflow: hidden;
        }

        /* Message Row */
        .message-row {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-light);
            transition: background 0.2s;
            cursor: pointer;
            position: relative;
        }

        .message-row:hover {
            background: var(--bg-hover);
        }

        .message-row.selected {
            background: var(--bg-selected);
        }

        .message-row.unread {
            background: var(--bg-unread);
        }

        .message-row.unread .sender-name,
        .message-row.unread .message-subject {
            font-weight: 600;
        }

        /* Checkbox */
        .message-checkbox {
            margin-right: 16px;
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        /* Sender */
        .sender-info {
            width: 200px;
            display: flex;
            flex-direction: column;
            margin-right: 16px;
        }

        .sender-name {
            font-weight: 500;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sender-email {
            font-size: 12px;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Message Content */
        .message-content {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-right: 16px;
        }

        .message-subject {
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 300px;
        }

        .message-preview {
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-preview::before {
            content: "—";
            margin: 0 4px;
            color: var(--text-secondary);
        }

        .new-badge {
            background: var(--primary-color);
            color: white;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
            margin-left: 8px;
            white-space: nowrap;
        }

        /* Date */
        .message-date {
            width: 150px;
            text-align: right;
            color: var(--text-secondary);
            font-size: 12px;
            white-space: nowrap;
        }

        /* Actions */
        .message-actions {
            display: flex;
            gap: 4px;
            margin-left: 8px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .message-row:hover .message-actions {
            opacity: 1;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 14px;
        }

        .action-btn:hover {
            background: var(--bg-hover);
        }

        .action-btn.mark-read:hover {
            color: var(--success-color);
        }

        .action-btn.mark-unread:hover {
            color: var(--primary-color);
        }

        .action-btn.delete:hover {
            background: #fce8e6;
            color: var(--danger-color);
        }

        .action-btn.reply:hover {
            color: var(--primary-color);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--white);
            border: 1px solid var(--border-light);
            border-radius: 8px;
        }

        .empty-state i {
            font-size: 64px;
            color: #dadce0;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 18px;
            font-weight: 400;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-secondary);
        }

        /* Select All Bar */
        .select-all-bar {
            background: var(--bg-selected);
            padding: 8px 16px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 13px;
        }

        .select-all-bar.hidden {
            display: none;
        }

        .select-all-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .clear-selection {
            color: var(--primary-color);
            text-decoration: none;
            cursor: pointer;
        }

        .clear-selection:hover {
            text-decoration: underline;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: var(--white);
            margin: 50px auto;
            padding: 0;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            animation: slideIn 0.3s;
            position: relative;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid var(--border-light);
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            word-break: break-word;
            padding-right: 30px;
        }

        .close-modal {
            color: var(--text-secondary);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 20px;
            top: 15px;
            line-height: 1;
            z-index: 10;
        }

        .close-modal:hover {
            color: var(--danger-color);
        }

        .modal-body {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        .modal-sender-info {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-light);
        }

        .modal-date {
            color: var(--text-secondary);
            font-size: 13px;
            margin-bottom: 20px;
        }

        .modal-message {
            background: #f8f9fa;
            padding: 50px;
            border-radius: 6px;
            border-left: 4px solid var(--primary-color);
        }

        .modal-message p {
            margin: 15px 0 0;
            line-height: 1.9;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .modal-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid var(--border-light);
            border-radius: 0 0 8px 8px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .modal-footer .action-btn {
            width: auto;
            padding: 8px 16px;
            border-radius: 4px;
            opacity: 1;
            height: auto;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .modal-footer .action-btn.reply {
            background: var(--primary-color);
            color: white;
        }

        .modal-footer .action-btn.reply:hover {
            background: #1557b0;
        }

        .modal-footer .action-btn.close {
            background: var(--border-light);
            color: var(--text-primary);
        }

        .modal-footer .action-btn.close:hover {
            background: #d5d5d5;
        }

        /* ADDED: Custom Delete Confirmation Modal for Contact Messages */
        .contact-delete-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10001;
            justify-content: center;
            align-items: center;
        }

        .contact-delete-modal.active {
            display: flex;
        }

        .contact-delete-content {
            background: white;
            border-radius: 12px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.2s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .contact-delete-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .contact-delete-header i {
            font-size: 22px;
            color: #dc3545;
        }

        .contact-delete-header h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: #111827;
        }

        .contact-delete-body {
            padding: 20px 24px;
        }

        .contact-delete-body p {
            color: #4b5563;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .contact-delete-body .message-info {
            background: #f9fafb;
            padding: 12px;
            border-radius: 8px;
            margin: 12px 0;
            border-left: 3px solid #dc3545;
        }

        .contact-delete-body .message-info strong {
            display: block;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .contact-delete-body .message-info small {
            color: #64748b;
            font-size: 12px;
        }

        .contact-warning-note {
            background: #fef3c7;
            padding: 12px;
            border-radius: 8px;
            margin-top: 16px;
            font-size: 13px;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .contact-warning-note i {
            color: #f59e0b;
        }

        .contact-delete-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .contact-delete-footer button {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .contact-delete-footer .btn-cancel-contact {
            background: #f3f4f6;
            color: #374151;
        }

        .contact-delete-footer .btn-cancel-contact:hover {
            background: #e5e7eb;
        }

        .contact-delete-footer .btn-confirm-contact {
            background: #dc3545;
            color: white;
        }

        .contact-delete-footer .btn-confirm-contact:hover {
            background: #c82333;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .message-preview {
                display: none;
            }
        }

        @media (max-width: 992px) {
            .main-content-wrapper {
                margin-left: 0;
                padding: 16px;
            }

            .sender-info {
                width: 150px;
            }

            .message-date {
                width: 100px;
            }
            
            .modal-content {
                margin: 20px auto;
                width: 95%;
            }
        }

        @media (max-width: 768px) {
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                width: 100%;
            }

            .message-row {
                flex-wrap: wrap;
                gap: 8px;
            }

            .sender-info {
                width: 100%;
                margin-right: 0;
            }

            .message-content {
                width: 100%;
                margin-right: 0;
            }

            .message-date {
                width: auto;
                text-align: left;
            }

            .message-actions {
                opacity: 1;
                margin-left: auto;
            }
            
            .modal-footer {
                flex-direction: column;
            }
            
            .modal-footer .action-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

    <div class="main-content-wrapper">
        <!-- Page Header -->
        <div class="page-header">
            <h3>
                Contact Messages
            </h3>
            <div class="header-stats">
                <div class="stat-item">
                </div>
                <div class="stat-item">
                    <?php if ($unread_count > 0): ?>  
                    <?php endif; ?>
                </div>
                <div class="stat-item">  
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div class="filter-group">
                    <a href="?filter=all" class="filter-btn <?= $filter == 'all' ? 'active' : '' ?>">
                        <i class="fas fa-inbox"></i>
                        All
                    </a>
                    <a href="?filter=unread" class="filter-btn <?= $filter == 'unread' ? 'active' : '' ?>">
                        <i class="fas fa-envelope-open-text"></i>
                        Unread
                    </a>
                    <a href="?filter=read" class="filter-btn <?= $filter == 'read' ? 'active' : '' ?>">
                        <i class="fas fa-check-circle"></i>
                        Read
                    </a>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <button class="bulk-action-btn" onclick="bulkAction('mark_read')">
                        <i class="fas fa-check"></i> Mark Read
                    </button>
                    <button class="bulk-action-btn" onclick="bulkAction('mark_unread')">
                        <i class="fas fa-envelope"></i> Mark Unread
                    </button>
                    <button class="bulk-action-btn delete" onclick="bulkAction('delete')">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>

            <!-- Search -->
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search messages..." id="searchInput">
            </div>
        </div>

        <!-- Select All Bar -->
        <div class="select-all-bar hidden" id="selectAllBar">
            <label class="select-all-checkbox">
                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()">
                <span>Select all messages</span>
            </label>
            <span class="clear-selection" onclick="clearSelection()">Clear selection</span>
        </div>

        <!-- Messages -->
        <?php if (empty($messages)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No messages found</h3>
                <p>Your inbox is empty. Check back later for new messages.</p>
            </div>
        <?php else: ?>
            <form method="POST" id="messageForm">
                <div class="messages-container">
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-row <?= $msg['is_read'] ? '' : 'unread' ?>" data-id="<?= $msg['id'] ?>" data-name="<?= htmlspecialchars($msg['name']) ?>" data-subject="<?= htmlspecialchars($msg['subject']) ?>">
                            <input type="checkbox" name="selected_ids[]" value="<?= $msg['id'] ?>" class="message-checkbox" onchange="updateSelection()">
                            
                            <div class="sender-info">
                                <span class="sender-name"><?= htmlspecialchars($msg['name']) ?></span>
                                <span class="sender-email"><?= htmlspecialchars($msg['email']) ?></span>
                            </div>

                            <div class="message-content">
                                <span class="message-subject"><?= htmlspecialchars($msg['subject']) ?></span>
                                <?php if (!$msg['is_read']): ?>
                                    <span class="new-badge">NEW</span>
                                <?php endif; ?>
                                <span class="message-preview"><?= htmlspecialchars(substr($msg['message'], 0, 100)) ?>...</span>
                            </div>

                            <div class="message-date">
                                <?= date('M j, Y g:i A', strtotime($msg['created_at'])) ?>
                            </div>

                            <div class="message-actions">
                                <?php if (!$msg['is_read']): ?>
                                    <a href="?action=mark_read&id=<?= $msg['id'] ?>&filter=<?= $filter ?>" class="action-btn mark-read" title="Mark as read">
                                        <i class="fas fa-check"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="?action=mark_unread&id=<?= $msg['id'] ?>&filter=<?= $filter ?>" class="action-btn mark-unread" title="Mark as unread">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <a href="mailto:<?= htmlspecialchars($msg['email']) ?>?subject=Re: <?= urlencode($msg['subject']) ?>" class="action-btn reply" title="Reply">
                                    <i class="fas fa-reply"></i>
                                </a>
                                
                                <a href="#" 
                                   class="action-btn delete delete-message-link" 
                                   title="Delete"
                                   data-message-id="<?= $msg['id'] ?>"
                                   data-message-subject="<?= htmlspecialchars($msg['subject']) ?>"
                                   data-message-sender="<?= htmlspecialchars($msg['name']) ?>">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Message Detail Modal -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeMessageModal()">&times;</span>
            <div class="modal-header">
                <h3 id="modalSubject"></h3>
            </div>
            <div class="modal-body">
                <div class="modal-sender-info">
                    <strong>From:</strong> <span id="modalName"></span> (<span id="modalEmail"></span>)
                </div>
                <div class="modal-date">
                    <strong>Date:</strong> <span id="modalDate"></span>
                </div>
                <div class="modal-message">
                    <strong>Message:</strong>
                    <p id="modalMessage"></p>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="modalReplyBtn" class="action-btn reply" target="_blank">
                    <i class="fas fa-reply"></i> Reply
                </a>
                <button class="action-btn close" onclick="closeMessageModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- ADDED: Custom Delete Confirmation Modal for Contact Messages -->
    <div class="contact-delete-modal" id="contactDeleteModal">
        <div class="contact-delete-content">
            <div class="contact-delete-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Delete Message</h3>
            </div>
            <div class="contact-delete-body">
                <p>Are you sure you want to delete this message?</p>
                <div class="message-info">
                    <strong id="deleteMessageSubject">Message Subject</strong>
                    <small id="deleteMessageSender">From: Sender</small>
                </div>
                <div class="contact-warning-note">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Warning: This action cannot be undone. The message will be permanently deleted.</span>
                </div>
            </div>
            <div class="contact-delete-footer">
                <button class="btn-cancel-contact" id="cancelContactDeleteBtn">Cancel</button>
                <button class="btn-confirm-contact" id="confirmContactDeleteBtn">Delete Message</button>
            </div>
        </div>
    </div>

    <script>
        // Prepare message data from PHP
        const messageData = <?php 
            $messagesData = [];
            foreach ($messages as $msg) {
                $messagesData[$msg['id']] = [
                    'id' => $msg['id'],
                    'name' => htmlspecialchars($msg['name']),
                    'email' => htmlspecialchars($msg['email']),
                    'subject' => htmlspecialchars($msg['subject']),
                    'message' => htmlspecialchars($msg['message']),
                    'date' => date('F j, Y g:i A', strtotime($msg['created_at']))
                ];
            }
            echo json_encode($messagesData);
        ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const messages = document.querySelectorAll('.message-row');

            messages.forEach(message => {
                const senderName = message.querySelector('.sender-name').textContent.toLowerCase();
                const senderEmail = message.querySelector('.sender-email').textContent.toLowerCase();
                const subject = message.querySelector('.message-subject').textContent.toLowerCase();
                
                if (senderName.includes(searchTerm) || senderEmail.includes(searchTerm) || subject.includes(searchTerm)) {
                    message.style.display = 'flex';
                } else {
                    message.style.display = 'none';
                }
            });
        });

        // Selection handling
        const bulkActions = document.getElementById('bulkActions');
        const selectAllBar = document.getElementById('selectAllBar');
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const messageForm = document.getElementById('messageForm');

        function updateSelection() {
            const checkboxes = document.querySelectorAll('.message-checkbox');
            const checkedCount = document.querySelectorAll('.message-checkbox:checked').length;
            
            // Update bulk actions visibility
            if (checkedCount > 0) {
                bulkActions.classList.add('active');
                selectAllBar.classList.remove('hidden');
                
                // Update select all checkbox
                selectAllCheckbox.checked = checkedCount === checkboxes.length;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
            } else {
                bulkActions.classList.remove('active');
                selectAllBar.classList.add('hidden');
            }
        }

        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.message-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateSelection();
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.message-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelection();
        }

        function bulkAction(action) {
            const checkedIds = Array.from(document.querySelectorAll('.message-checkbox:checked')).map(cb => cb.value);
            
            if (checkedIds.length === 0) return;
            
            let confirmMessage = '';
            if (action === 'delete') {
                confirmMessage = 'Are you sure you want to delete the selected messages? This action cannot be undone.';
            } else {
                confirmMessage = `Are you sure you want to mark ${checkedIds.length} message(s) as ${action === 'mark_read' ? 'read' : 'unread'}?`;
            }
            
            if (!confirm(confirmMessage)) return;
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.name = 'bulk_action';
            actionInput.value = action;
            form.appendChild(actionInput);
            
            checkedIds.forEach(id => {
                const input = document.createElement('input');
                input.name = 'selected_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }

        // Modal functions for message view
        function showMessageDetails(messageId) {
            const msg = messageData[messageId];
            if (!msg) return;
            
            document.getElementById('modalSubject').textContent = msg.subject;
            document.getElementById('modalName').textContent = msg.name;
            document.getElementById('modalEmail').textContent = msg.email;
            document.getElementById('modalDate').textContent = msg.date;
            document.getElementById('modalMessage').textContent = msg.message;
            
            // Update reply button
            const replyBtn = document.getElementById('modalReplyBtn');
            replyBtn.href = `mailto:${msg.email}?subject=Re: ${encodeURIComponent(msg.subject)}`;
            
            // Show modal
            document.getElementById('messageModal').style.display = 'block';
            
            // Prevent body scrolling when modal is open
            document.body.style.overflow = 'hidden';
        }

        function closeMessageModal() {
            document.getElementById('messageModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('messageModal');
            if (event.target == modal) {
                closeMessageModal();
            }
            const deleteModal = document.getElementById('contactDeleteModal');
            if (event.target == deleteModal) {
                closeContactDeleteModal();
            }
        }

        // Click on message row to view details
        document.querySelectorAll('.message-row').forEach(row => {
            row.addEventListener('click', function(e) {
                // Don't trigger if clicking on checkbox or actions
                if (e.target.type === 'checkbox' || e.target.closest('.action-btn') || e.target.closest('.message-actions')) {
                    return;
                }
                
                const messageId = this.dataset.id;
                showMessageDetails(messageId);
            });
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMessageModal();
                closeContactDeleteModal();
            }
        });

        // ADDED: Custom Delete Modal Logic for Contact Messages
        const contactDeleteModal = document.getElementById('contactDeleteModal');
        const deleteMessageSubject = document.getElementById('deleteMessageSubject');
        const deleteMessageSender = document.getElementById('deleteMessageSender');
        const confirmContactDeleteBtn = document.getElementById('confirmContactDeleteBtn');
        const cancelContactDeleteBtn = document.getElementById('cancelContactDeleteBtn');
        
        let pendingDeleteUrl = null;

        // Function to close modal
        function closeContactDeleteModal() {
            if (contactDeleteModal) {
                contactDeleteModal.classList.remove('active');
            }
            pendingDeleteUrl = null;
        }

        // Function to show delete modal
        function showContactDeleteModal(messageId, messageSubject, messageSender) {
            deleteMessageSubject.textContent = messageSubject;
            deleteMessageSender.textContent = 'From: ' + messageSender;
            pendingDeleteUrl = '?action=delete&id=' + messageId + '&filter=<?= $filter ?>';
            contactDeleteModal.classList.add('active');
        }

        // Confirm delete button
        if (confirmContactDeleteBtn) {
            confirmContactDeleteBtn.onclick = function() {
                if (pendingDeleteUrl) {
                    window.location.href = pendingDeleteUrl;
                }
            };
        }

        // Cancel button
        if (cancelContactDeleteBtn) {
            cancelContactDeleteBtn.onclick = closeContactDeleteModal;
        }

        // Override delete links
        document.querySelectorAll('.delete-message-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const messageId = this.getAttribute('data-message-id');
                const messageSubject = this.getAttribute('data-message-subject');
                const messageSender = this.getAttribute('data-message-sender');
                showContactDeleteModal(messageId, messageSubject, messageSender);
            });
        });
    </script>
</body>
</html>