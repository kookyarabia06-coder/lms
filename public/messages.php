<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$user = current_user();
$userId = $user['id'];
$userRole = $user['role'] ?? 'user';

$canReplyRoles = ['admin', 'proponent', 'user'];
$canReply = in_array($userRole, $canReplyRoles);

$action = $_GET['action'] ?? 'inbox';

// Mark message as read
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $messageId = (int)$_GET['id'];
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$messageId, $userId]);
    header('Location: messages.php?action=view&id=' . $messageId);
    exit;
}

// Delete message
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $messageId = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT sender_id, receiver_id FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $msg = $stmt->fetch();
    
    if ($msg) {
        if ($msg['sender_id'] == $userId) {
            $stmt = $pdo->prepare("UPDATE messages SET is_deleted_sender = 1 WHERE id = ?");
            $stmt->execute([$messageId]);
        }
        if ($msg['receiver_id'] == $userId) {
            $stmt = $pdo->prepare("UPDATE messages SET is_deleted_receiver = 1 WHERE id = ?");
            $stmt->execute([$messageId]);
        }
    }
    header('Location: messages.php');
    exit;
}

// Delete conversation (soft delete - current user's side only)
if (isset($_GET['delete_conv']) && isset($_GET['conv_user'])) {
    $convUserId = (int)$_GET['conv_user'];
    
    // Soft delete messages
    $stmt = $pdo->prepare("UPDATE messages SET is_deleted_sender = 1 WHERE sender_id = ? AND receiver_id = ?");
    $stmt->execute([$userId, $convUserId]);
    
    $stmt = $pdo->prepare("UPDATE messages SET is_deleted_receiver = 1 WHERE sender_id = ? AND receiver_id = ?");
    $stmt->execute([$convUserId, $userId]);
    
    // Add current user to deleted_by list
    $stmt = $pdo->prepare("SELECT id, deleted_by FROM conversations WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
    $stmt->execute([$userId, $convUserId, $convUserId, $userId]);
    $conv = $stmt->fetch();
    
    if ($conv) {
        $deletedBy = $conv['deleted_by'] ? json_decode($conv['deleted_by'], true) : [];
        if (!in_array($userId, $deletedBy)) {
            $deletedBy[] = $userId;
        }
        $stmt = $pdo->prepare("UPDATE conversations SET deleted_by = ? WHERE id = ?");
        $stmt->execute([json_encode($deletedBy), $conv['id']]);
    }
    
    header('Location: messages.php');
    exit;
}

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    if (!$canReply) {
        $_SESSION['error'] = 'You do not have permission to send messages.';
        header('Location: messages.php');
        exit;
    }
    
    $receiverId = (int)$_POST['receiver_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    if ($receiverId && $subject && $message) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, parent_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $receiverId, $subject, $message, $parentId]);
        $newMessageId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("SELECT id FROM conversations WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
        $stmt->execute([$userId, $receiverId, $receiverId, $userId]);
        $conv = $stmt->fetch();
        
        if ($conv) {
            // Remove receiver from deleted_by so the conversation reappears for them
            $stmt = $pdo->prepare("
                UPDATE conversations 
                SET last_message_id = ?, 
                    updated_at = NOW(),
                    deleted_by = CASE 
                        WHEN deleted_by IS NULL THEN NULL 
                        ELSE JSON_REMOVE(deleted_by, JSON_UNQUOTE(JSON_SEARCH(deleted_by, 'one', ?))) 
                    END
                WHERE id = ?
            ");
            $stmt->execute([$newMessageId, (string)$receiverId, $conv['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO conversations (user1_id, user2_id, last_message_id, updated_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$userId, $receiverId, $newMessageId]);
        }
        
        $_SESSION['success'] = 'Message sent successfully!';
        header('Location: messages.php?action=view&id=' . $newMessageId);
        exit;
    } else {
        $_SESSION['error'] = 'Please fill in all fields';
        header('Location: messages.php');
        exit;
    }
}

// Get unread count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0 AND is_deleted_receiver = 0");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetch()['count'];

// Get conversations
$stmt = $pdo->prepare("
    SELECT c.*, u1.id as user1_id, u1.fname as user1_fname, u1.lname as user1_lname, u1.role as user1_role,
           u2.id as user2_id, u2.fname as user2_fname, u2.lname as user2_lname, u2.role as user2_role,
           m.message as last_message, m.created_at as last_message_time, m.sender_id as last_sender,
           (SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND sender_id = CASE WHEN c.user1_id = ? THEN c.user2_id ELSE c.user1_id END AND is_read = 0 AND is_deleted_receiver = 0) as unread
    FROM conversations c
    JOIN users u1 ON c.user1_id = u1.id
    JOIN users u2 ON c.user2_id = u2.id
    LEFT JOIN messages m ON c.last_message_id = m.id
    WHERE (c.user1_id = ? OR c.user2_id = ?)
      AND (c.deleted_by IS NULL OR NOT JSON_CONTAINS(c.deleted_by, ?))
    ORDER BY c.updated_at DESC
");
$stmt->execute([$userId, $userId, $userId, $userId, json_encode($userId)]);
$conversations = $stmt->fetchAll();

// Get single conversation for view
if ($action === 'view' && isset($_GET['id'])) {
    $messageId = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT sender_id, receiver_id FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $msg = $stmt->fetch();
    
    if ($msg) {
        $otherUserId = ($msg['sender_id'] == $userId) ? $msg['receiver_id'] : $msg['sender_id'];
        
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND receiver_id = ? AND is_read = 0");
        $stmt->execute([$userId, $otherUserId, $otherUserId, $userId, $userId]);
        
        $stmt = $pdo->prepare("
            SELECT m.*, u.fname as sender_fname, u.lname as sender_lname,
                   ru.fname as receiver_fname, ru.lname as receiver_lname,
                   u.role as sender_role, ru.role as receiver_role
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            JOIN users ru ON m.receiver_id = ru.id
            WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
              AND NOT (m.sender_id = ? AND m.is_deleted_sender = 1)
              AND NOT (m.receiver_id = ? AND m.is_deleted_receiver = 1)
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$userId, $otherUserId, $otherUserId, $userId, $userId, $userId]);
        $conversationMessages = $stmt->fetchAll();
    }
}

// Get all users for new message
$users = [];
if ($canReply) {
    if ($userRole == 'admin') {
        // Admin can message everyone
        $stmt = $pdo->prepare("SELECT id, fname, lname, email, role FROM users WHERE id != ? ORDER BY fname ASC");
        $stmt->execute([$userId]);
    } elseif ($userRole == 'proponent') {
        // Proponent can message admin, proponent, and users
        $stmt = $pdo->prepare("SELECT id, fname, lname, email, role FROM users WHERE id != ? AND role IN ('admin', 'proponent', 'user') ORDER BY fname ASC");
        $stmt->execute([$userId]);
    } elseif ($userRole == 'user') {
        // Regular users can only message admin and proponent
        $stmt = $pdo->prepare("SELECT id, fname, lname, email, role FROM users WHERE id != ? AND role IN ('admin', 'proponent') ORDER BY fname ASC");
        $stmt->execute([$userId]);
    }
    $users = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/messages.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    
<div class="lms-sidebar-container">
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>
    
<div class="main-content">
<div class="messages-container">
           
<div class="messages-header">
<div></div> 
<h2 class="h3"><i class="bi bi-chat-dots me-2 text-primary"></i>Messages</h2>
<div class="new-message-btn">
    <?php if($canReply): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
            <i class="bi bi-pencil-square me-2"></i>New Message
        </button>
    <?php else: ?>
        <span class="badge bg-secondary p-2" title="Read-only mode">
            <i class="bi bi-eye me-1"></i>Read Only
        </span>
    <?php endif; ?>
</div></div>

<?php if(isset($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show text-center" style="max-width: 600px; margin: 0 auto 20px auto;">
<i class="bi bi-check-circle me-2"></i>
<?= $_SESSION['success']; unset($_SESSION['success']); ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show text-center" style="max-width: 600px; margin: 0 auto 20px auto;">
<i class="bi bi-exclamation-triangle me-2"></i>
<?= $_SESSION['error']; unset($_SESSION['error']); ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
            
<div class="messages-wrapper">
<!-- Conversations Sidebar -->
<div class="conversations-sidebar">
<div class="conversations-header">
<h5 class="mb-0">Conversations</h5>
<small class="text-muted"><?= $unreadCount ?> unread</small>
</div>
                    
<div class="conversations-list">
<?php if(empty($conversations)): ?>
<div class="empty-state">
<i class="bi bi-chat"></i>
<h6>No conversations yet</h6>
<p class="small">No messages to display</p>
</div>
<?php else: ?>
<?php foreach($conversations as $conv): ?>
<?php 
$otherUser = ($conv['user1_id'] == $userId) 
? ['id' => $conv['user2_id'], 'fname' => $conv['user2_fname'], 'lname' => $conv['user2_lname'], 'role' => $conv['user2_role']]
 : ['id' => $conv['user1_id'], 'fname' => $conv['user1_fname'], 'lname' => $conv['user1_lname'], 'role' => $conv['user1_role']];
$initials = strtoupper(substr($otherUser['fname'] ?? '', 0, 1) . substr($otherUser['lname'] ?? '', 0, 1));
$unread = $conv['unread'] ?? 0;
$roleBadge = $otherUser['role'] == 'admin' ? '<span class="badge bg-danger ms-1">Admin</span>' : 
             ($otherUser['role'] == 'proponent' ? '<span class="badge bg-primary ms-1">Program Manager</span>' : 
             ($otherUser['role'] == 'user' ? '<span class="badge bg-secondary ms-1">Employee</span>' : ''));
?>
<div class="conversation-item <?= ($action === 'view' && isset($otherUserId) && $otherUserId == $otherUser['id']) ? 'active' : '' ?>" style="position: relative;">
    <a href="messages.php?action=view&id=<?= $conv['last_message_id'] ?>" class="text-decoration-none d-flex align-items-center flex-grow-1" style="color: inherit;">
        <div class="conversation-avatar"><?= $initials ?: 'U' ?></div>
        <div class="conversation-info">
            <div class="conversation-name">
                <span class="text-dark fw-bold"><?= htmlspecialchars($otherUser['fname'] ?? '') ?> <?= htmlspecialchars($otherUser['lname'] ?? '') ?></span>
                <?= $roleBadge ?>
            </div>
            <div class="conversation-last-message text-dark" style="display: flex; justify-content: space-between; align-items: center;">
                <span><?= htmlspecialchars(substr($conv['last_message'] ?? 'No messages yet', 0, 30)) ?>...</span>
                <span class="conversation-time" style="font-size: 0.7rem; color: #6c757d;"><?= $conv['last_message_time'] ? date('M d', strtotime($conv['last_message_time'])) : '' ?></span>
            </div>
            <?php if($unread > 0): ?>
            <div style="text-align: right;">
                <span class="unread-badge"><?= $unread ?></span>
            </div>
            <?php endif; ?>
        </div>
    </a>
    <span class="text-danger" 
       style="position: absolute; top: 8px; right: 10px; font-size: 14px; cursor: pointer; z-index: 2;"
       onclick="confirmDeleteConv(<?= $otherUser['id'] ?>, '<?= htmlspecialchars($otherUser['fname'] . ' ' . $otherUser['lname']) ?>')"
       title="Delete conversation">
        <i class="bi bi-x-lg"></i>
    </span>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<?php if(!$canReply): ?>
<div class="readonly-badge mx-3 mb-3">
    <i class="bi bi-info-circle"></i> You are in read-only mode.
</div>
<?php endif; ?>
</div>
                
<!-- Message Area -->
<div class="message-area">
    <?php if($action === 'view' && isset($conversationMessages)): ?>
        <div class="message-header">
            <div class="d-flex align-items-center">
                <?php 
                $otherUserInfo = null;
                foreach($users as $u) {
                    if($u['id'] == $otherUserId) { $otherUserInfo = $u; break; }
                }
                if(!$otherUserInfo) {
                    $otherUserInfo = $otherUser ?? ['fname' => 'User', 'lname' => '', 'role' => ''];
                }
                $initials = $otherUserInfo ? strtoupper(substr($otherUserInfo['fname'] ?? '', 0, 1) . substr($otherUserInfo['lname'] ?? '', 0, 1)) : 'U';
                ?>
                <div class="conversation-avatar me-3" style="width: 40px; height: 40px; font-size: 16px;"><?= $initials ?></div>
                <div>
                    <h5 class="mb-0"><?= htmlspecialchars($otherUserInfo['fname'] ?? '') ?> <?= htmlspecialchars($otherUserInfo['lname'] ?? '') ?></h5>
                        <small class="text-muted">
                            <?php 
                            $role = $otherUserInfo['role'] ?? 'user';
                            echo $role === 'user' ? 'Employee' : ($role === 'proponent' ? 'Program Manager' : ucfirst($role));
                            ?>
                        </small>
                </div>
            </div>
        </div>
        
        <div class="messages-container-inner" id="messagesContainer">
            <?php foreach($conversationMessages as $msg): ?>
                <div class="message-bubble <?= $msg['sender_id'] == $userId ? 'message-sent' : 'message-received' ?> fade-in">
                    <div class="message-content">
                        <?php if($msg['sender_id'] != $userId): ?>
                            <small class="fw-bold d-block mb-1">
                                <?= htmlspecialchars($msg['sender_fname'] . ' ' . $msg['sender_lname']) ?>
                                <?php if($msg['sender_role'] == 'admin'): ?><span class="badge bg-danger ms-1">Admin</span>
                                <?php elseif($msg['sender_role'] == 'proponent'): ?><span class="badge bg-primary ms-1">Proponent</span>
                                <?php endif; ?>
                            </small>
                        <?php else: ?>
                            <small class="fw-bold d-block mb-1 text-muted">You</small>
                        <?php endif; ?>
                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                    </div>
                    <div class="message-time">
                        <?= date('M d, Y h:i A', strtotime($msg['created_at'])) ?>
                        <?php if($msg['sender_id'] == $userId && $msg['is_read']): ?><i class="bi bi-check2-all ms-1" title="Read"></i>
                        <?php elseif($msg['sender_id'] == $userId): ?><i class="bi bi-check2 ms-1" title="Sent"></i>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if($canReply): ?>
            <div class="message-input-area">
                <form method="POST" action="messages.php">
                    <input type="hidden" name="receiver_id" value="<?= $otherUserId ?>">
                    <input type="hidden" name="parent_id" value="<?= $messageId ?>">
                    <input type="hidden" name="subject" value="Message">
                    <div class="input-group">
                        <textarea name="message" class="form-control" rows="2" placeholder="Type your reply..." required></textarea>
                        <button type="submit" name="send_message" class="btn btn-primary"><i class="bi bi-send"></i> Send</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="message-input-area">
                <div class="alert alert-secondary mb-0 text-center">
                    <i class="bi bi-info-circle me-2"></i>Read-only mode. You cannot reply.
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="d-flex align-items-center justify-content-center h-100">
            <div class="empty-state">
                <i class="bi bi-chat-dots"></i>
                <h5>No conversation selected</h5>
                <p class="text-muted">Choose a conversation from the sidebar</p>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>
</div>
</div>

<?php if($canReply): ?>
<!-- Compose Modal -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-primary"></i>New Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="messages.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">To:</label>
                        <div class="searchable-select-wrapper" style="position: relative;">
                            <input type="text" 
                                   id="recipientSearch" 
                                   class="form-control" 
                                   placeholder="Search recipient..." 
                                   autocomplete="off"
                                   onkeyup="filterRecipients(this.value)"
                                   onfocus="filterRecipients(this.value)">
                            <input type="hidden" name="receiver_id" id="receiverId" value="">
                            <div id="recipientDropdown" class="searchable-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; max-height: 250px; overflow-y: auto; background: #fff; border: 1px solid #ced4da; border-top: none; border-radius: 0 0 6px 6px; z-index: 1050;">
                                <?php foreach($users as $u): 
                                    $roleLabel = $u['role'] === 'user' ? 'Employee' : ($u['role'] === 'proponent' ? 'Program Manager' : ucfirst($u['role']));
                                ?>
                                <div class="recipient-option" 
                                     data-id="<?= $u['id'] ?>" 
                                     data-name="<?= htmlspecialchars(strtolower($u['fname'] . ' ' . $u['lname'])) ?>"
                                     data-role="<?= htmlspecialchars(strtolower($roleLabel)) ?>"
                                     onclick="selectRecipient(<?= $u['id'] ?>, '<?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?>', '<?= htmlspecialchars($roleLabel) ?>')"
                                     style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0;">
                                    <strong><?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?></strong>
                                    <span class="text-muted ms-2">(<?= htmlspecialchars($roleLabel) ?>)</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="subject" value="Message">
                    <div class="mb-3">
                        <label class="form-label">Message:</label>
                        <textarea name="message" class="form-control" rows="6" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_message" class="btn btn-primary"><i class="bi bi-send me-2"></i>Send</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConvModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                <h6 class="mt-3 mb-2">Delete this conversation?</h6>
                <p class="text-muted small mb-3" id="deleteConvName"></p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="deleteConvLink" class="btn btn-danger btn-sm">Delete</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function scrollToBottom() {
        const container = document.getElementById('messagesContainer');
        if (container) container.scrollTop = container.scrollHeight;
    }
    document.addEventListener('DOMContentLoaded', scrollToBottom);
    
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'form-control form-control-sm mt-2';
        searchInput.placeholder = 'Search conversations...';
        const header = document.querySelector('.conversations-header');
        if (header) {
            header.appendChild(searchInput);
            searchInput.addEventListener('keyup', function() {
                const value = this.value.toLowerCase();
                document.querySelectorAll('.conversation-item').forEach(item => {
                    const text = item.textContent.toLowerCase();
                    item.style.display = text.includes(value) ? 'flex' : 'none';
                });
            });
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }, 3000);
        });
    });

	    function confirmDeleteConv(userId, userName) {
        document.getElementById('deleteConvName').textContent = 'All messages with ' + userName + ' will be permanently deleted.';
        document.getElementById('deleteConvLink').href = 'messages.php?delete_conv=1&conv_user=' + userId;
        new bootstrap.Modal(document.getElementById('deleteConvModal')).show();
    }
	
    function filterRecipients(query) {
        const dropdown = document.getElementById('recipientDropdown');
        const options = dropdown.querySelectorAll('.recipient-option');
        let visible = 0;
        
        query = query.toLowerCase().trim();
        
        options.forEach(option => {
            const name = option.getAttribute('data-name') || '';
            const role = option.getAttribute('data-role') || '';
            
            if (query === '' || name.includes(query) || role.includes(query)) {
                option.style.display = '';
                visible++;
            } else {
                option.style.display = 'none';
            }
        });
        
        dropdown.style.display = visible > 0 ? 'block' : 'none';
    }
    
    function selectRecipient(id, name, role) {
        document.getElementById('recipientSearch').value = name + ' (' + role + ')';
        document.getElementById('receiverId').value = id;
        document.getElementById('recipientDropdown').style.display = 'none';
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const wrapper = document.querySelector('.searchable-select-wrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            document.getElementById('recipientDropdown').style.display = 'none';
        }
    });
    
    // Validate recipient on form submit
    document.querySelector('#composeModal form')?.addEventListener('submit', function(e) {
        const receiverId = document.getElementById('receiverId').value;
        if (!receiverId) {
            e.preventDefault();
            alert('Please select a recipient from the dropdown.');
        }
    });


</script>
</body>
</html>