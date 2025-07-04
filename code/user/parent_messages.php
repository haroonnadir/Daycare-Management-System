<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../index.php");
    exit();
}

$parent_id = $_SESSION['user_id'];

// Get all conversations for this parent
$conversations_sql = "SELECT c.id, c.subject, c.last_message_at,
                            COUNT(m.id) AS message_count,
                            SUM(CASE WHEN m.is_read = FALSE AND m.sender_id != $parent_id THEN 1 ELSE 0 END) AS unread_count,
                            (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY sent_at DESC LIMIT 1) AS last_message,
                            (SELECT name FROM users
                             WHERE id IN (SELECT user_id FROM conversation_participants
                                                  WHERE conversation_id = c.id AND user_id != $parent_id) LIMIT 1) AS participant_name
                            FROM conversations c
                            JOIN conversation_participants cp ON c.id = cp.conversation_id
                            LEFT JOIN messages m ON c.id = m.conversation_id
                            WHERE cp.user_id = $parent_id
                            GROUP BY c.id
                            ORDER BY c.last_message_at DESC";
$conversations_result = mysqli_query($conn, $conversations_sql);

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Daycare Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Styles remain the same as in the previously combined code */
  :root {
  /* Color Scheme */
  --primary: #4361ee;
  --primary-dark: #3f37c9;
  --primary-light: #4895ef;
  --danger: #f72585;
  --success: #4cc9f0;
  --warning: #f8961e;
  --info: #3a86ff;
  --light: #f8f9fa;
  --dark: #212529;
  --gray: #6c757d;
  --light-gray: #e9ecef;
  --white: #ffffff;
  
  /* Spacing */
  --space-xs: 0.25rem;
  --space-sm: 0.5rem;
  --space-md: 1rem;
  --space-lg: 1.5rem;
  --space-xl: 2rem;
  
  /* Typography */
  --font-base: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  --font-size-sm: 0.875rem;
  --font-size-md: 1rem;
  --font-size-lg: 1.25rem;
  --font-size-xl: 1.5rem;
  
  /* Borders */
  --border-radius-sm: 4px;
  --border-radius-md: 8px;
  --border-radius-lg: 12px;
  --border-radius-circle: 50%;
  
  /* Shadows */
  --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
  --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
  --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
  
  /* Transitions */
  --transition-fast: 0.15s;
  --transition-normal: 0.3s;
  --transition-slow: 0.5s;
}

/* Base Styles */
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: var(--font-base);
  background-color: #f5f7fa;
  color: var(--dark);
  line-height: 1.6;
}

a {
  text-decoration: none;
  color: inherit;
}

img {
  max-width: 100%;
  height: auto;
}

/* Layout */
.main-content {
  padding: var(--space-xl);
  max-width: 1400px;
  margin: 0 auto;
}

.profile-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--space-xl);
  padding-bottom: var(--space-md);
  border-bottom: 1px solid var(--light-gray);
}

.messages-container {
  display: flex;
  gap: var(--space-xl);
  height: calc(100vh - 180px);
}

/* Conversations List */
.conversations-list {
  flex: 0 0 350px;
  background-color: var(--white);
  border-radius: var(--border-radius-lg);
  box-shadow: var(--shadow-md);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.conversations-header {
  padding: var(--space-md);
  border-bottom: 1px solid var(--light-gray);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: var(--white);
  position: sticky;
  top: 0;
  z-index: 10;
}

.conversations-header h3 {
  margin: 0;
  color: var(--primary);
  font-size: var(--font-size-lg);
}

/* Conversation Items */
.conversation-item {
  padding: var(--space-md);
  border-bottom: 1px solid var(--light-gray);
  cursor: pointer;
  transition: all var(--transition-fast);
  display: block;
}

.conversation-item:hover {
  background-color: rgba(67, 97, 238, 0.05);
}

.conversation-item.active {
  background-color: rgba(67, 97, 238, 0.1);
  border-left: 3px solid var(--primary);
}

.conversation-item.unread {
  font-weight: 600;
}

.conversation-participant {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--space-xs);
  font-weight: 600;
}

.conversation-subject {
  color: var(--primary);
  margin-bottom: var(--space-xs);
  font-size: var(--font-size-sm);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.conversation-preview {
  color: var(--gray);
  font-size: var(--font-size-sm);
  margin-bottom: var(--space-xs);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

.conversation-meta {
  display: flex;
  justify-content: space-between;
  font-size: var(--font-size-sm);
  color: var(--gray);
}

.unread-badge {
  background-color: var(--primary);
  color: var(--white);
  border-radius: var(--border-radius-circle);
  width: 20px;
  height: 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: var(--font-size-sm);
  margin-left: var(--space-xs);
}

/* Conversation Detail */
.conversation-detail {
  flex: 1;
  background-color: var(--white);
  border-radius: var(--border-radius-lg);
  box-shadow: var(--shadow-md);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.conversation-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  padding: var(--space-xl);
  text-align: center;
  color: var(--gray);
}

.conversation-placeholder i {
  font-size: 3rem;
  margin-bottom: var(--space-md);
  color: var(--light-gray);
}

.conversation-placeholder h3 {
  margin-bottom: var(--space-xs);
  color: var(--dark);
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-xs);
  padding: var(--space-sm) var(--space-md);
  border-radius: var(--border-radius-md);
  font-weight: 500;
  font-size: var(--font-size-md);
  transition: all var(--transition-normal);
  cursor: pointer;
  border: none;
  line-height: 1;
}

.btn-primary {
  background-color: var(--primary);
  color: var(--white);
}

.btn-primary:hover {
  background-color: var(--primary-dark);
  transform: translateY(-1px);
  box-shadow: var(--shadow-sm);
}

.btn-danger {
  background-color: var(--danger);
  color: var(--white);
}

.btn-danger:hover {
  background-color: #d1145a;
  transform: translateY(-1px);
  box-shadow: var(--shadow-sm);
}

.btn-icon {
  width: 40px;
  height: 40px;
  border-radius: var(--border-radius-circle);
  padding: 0;
}

/* Modal */
.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.5);
  z-index: 1000;
  align-items: center;
  justify-content: center;
}

.modal-content {
  background-color: var(--white);
  border-radius: var(--border-radius-lg);
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: var(--shadow-lg);
  animation: modalFadeIn var(--transition-normal) ease-out;
}

@keyframes modalFadeIn {
  from { opacity: 0; transform: translateY(-20px); }
  to { opacity: 1; transform: translateY(0); }
}

.modal-header {
  padding: var(--space-md);
  background-color: var(--primary);
  color: var(--white);
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 10;
}

.modal-header h3 {
  margin: 0;
  font-size: var(--font-size-lg);
}

.modal-header .close {
  font-size: var(--font-size-xl);
  cursor: pointer;
  transition: transform var(--transition-fast);
}

.modal-header .close:hover {
  transform: rotate(90deg);
}

.modal-body {
  padding: var(--space-md);
}

/* Forms */
.form-group {
  margin-bottom: var(--space-md);
}

.form-group label {
  display: block;
  margin-bottom: var(--space-xs);
  font-weight: 500;
  color: var(--dark);
}

.form-control {
  width: 100%;
  padding: var(--space-sm) var(--space-md);
  border: 1px solid var(--light-gray);
  border-radius: var(--border-radius-md);
  font-family: var(--font-base);
  transition: border-color var(--transition-fast);
}

.form-control:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.2);
}

textarea.form-control {
  min-height: 120px;
  resize: vertical;
}

.form-submit {
  text-align: right;
  margin-top: var(--space-lg);
}

/* Responsive Design */
@media (max-width: 992px) {
  .messages-container {
    flex-direction: column;
    height: auto;
  }
  
  .conversations-list {
    flex: 1;
    max-height: 400px;
  }
}

@media (max-width: 768px) {
  .main-content {
    padding: var(--space-md);
  }
  
  .profile-header {
    flex-direction: column;
    align-items: flex-start;
    gap: var(--space-md);
  }
  
  .conversation-item {
    padding: var(--space-sm);
  }
}

@media (max-width: 576px) {
  .modal-content {
    width: 95%;
  }
  
  .conversation-meta {
    flex-direction: column;
    gap: var(--space-xs);
  }
}

/* Utility Classes */
.text-truncate {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.text-muted {
  color: var(--gray);
}

.text-primary {
  color: var(--primary);
}

.bg-light {
  background-color: var(--light);
}

.shadow-sm {
  box-shadow: var(--shadow-sm);
}

.rounded {
  border-radius: var(--border-radius-md);
}

.mb-2 {
  margin-bottom: var(--space-sm);
}

.mb-3 {
  margin-bottom: var(--space-md);
}

.mb-4 {
  margin-bottom: var(--space-lg);
}
    </style>
</head>
<body>
    <div class="main-content">
        <div class="profile-header">
            <h1><i class="fas fa-envelope"></i> Messages</h1>
            <div class="profile-actions">
                <a href="parent_dashboard.php" class="btn btn-danger">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="messages-container">
            <div class="conversations-list">
                <div class="conversations-header">
                    <h3>Conversations</h3>
                    <button class="new-conversation-btn" onclick="openNewConversationModal()">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>

                <?php if (mysqli_num_rows($conversations_result) > 0): ?>
                    <?php while ($conversation = mysqli_fetch_assoc($conversations_result)): ?>
                        <a href="parent_view_messages.php?conversation_id=<?= $conversation['id'] ?>" class="conversation-item <?= $conversation['unread_count'] > 0 ? 'unread' : '' ?>">
                            <div class="conversation-participant">
                                <span><?= htmlspecialchars($conversation['participant_name']) ?></span>
                                <?php if ($conversation['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?= $conversation['unread_count'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="conversation-subject"><?= htmlspecialchars($conversation['subject']) ?></div>
                            <div class="conversation-preview">
                                <?= htmlspecialchars(substr($conversation['last_message'], 0, 50)) ?>
                                <?= strlen($conversation['last_message']) > 50 ? '...' : '' ?>
                            </div>
                            <div class="conversation-meta">
                                <span><?= date('M j', strtotime($conversation['last_message_at'])) ?></span>
                                <span><?= $conversation['message_count'] ?> messages</span>
                            </div>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="conversation-placeholder">
                        <i class="fas fa-comments"></i>
                        <h3>No conversations yet</h3>
                        <p>Start a new conversation with daycare staff</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="conversation-detail">
                <div class="conversation-placeholder">
                    <i class="fas fa-comment-dots"></i>
                    <h3>Select a conversation</h3>
                    <p>Choose an existing conversation from the list to view messages</p>
                </div>
            </div>
        </div>
    </div>

    <div id="newConversationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>New Message</h3>
                <span class="close" onclick="closeModal('newConversationModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="newConversationForm" method="POST" action="parent_view_messages.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="start_new_conversation" value="true">
                    <div class="form-group">
                        <label for="recipient">To:</label>
                        <select id="recipient" name="recipient" required>
                            <option value="">-- Select Staff Member --</option>
                            <?php
                            $staff_sql = "SELECT id, name FROM users WHERE role IN ('staff', 'admin')";
                            $staff_result = mysqli_query($conn, $staff_sql);
                            while ($staff = mysqli_fetch_assoc($staff_result)) {
                                echo '<option value="' . $staff['id'] . '">' . htmlspecialchars($staff['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject:</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Message:</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="attachments">Attachments:</label>
                        <input type="file" id="attachments" name="attachments[]" multiple>
                    </div>
                    <div class="form-submit">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openNewConversationModal() {
            document.getElementById('newConversationModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>