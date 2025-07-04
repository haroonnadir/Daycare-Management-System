<?php
// get_conversation.php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("No conversation ID provided");
}

$conversation_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Verify the user is part of this conversation
$stmt = $conn->prepare("SELECT COUNT(*) FROM conversation_participants 
                       WHERE conversation_id = ? AND user_id = ?");
$stmt->bind_param("ii", $conversation_id, $user_id);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

if ($count == 0) {
    die("You are not part of this conversation");
}

// Get conversation details and messages
$stmt = $conn->prepare("SELECT c.subject, 
                        m.id, m.sender_id, m.content, m.sent_at, m.is_read, m.media_path, m.message_type,
                        u.name as sender_name, u.role as sender_role
                 FROM messages m
                 JOIN users u ON m.sender_id = u.id
                 JOIN conversations c ON m.conversation_id = c.id
                 WHERE m.conversation_id = ?
                 ORDER BY m.sent_at ASC");
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$result = $stmt->get_result();

// Get the other participant's name
$participant_stmt = $conn->prepare("SELECT u.name 
                                   FROM conversation_participants cp
                                   JOIN users u ON cp.user_id = u.id
                                   WHERE cp.conversation_id = ? AND cp.user_id != ?");
$participant_stmt->bind_param("ii", $conversation_id, $user_id);
$participant_stmt->execute();
$participant_result = $participant_stmt->get_result();
$participant = $participant_result->fetch_assoc();
$participant_name = $participant ? $participant['name'] : 'Unknown';
$participant_stmt->close();

// Output the conversation HTML
?>
<div class="conversation-header">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= htmlspecialchars($participant_name) ?></h3>
        <button class="btn btn-sm btn-outline-secondary" onclick="showConversationList()">
            <i class="fas fa-arrow-left"></i> Back
        </button>
    </div>
</div>

<div class="conversation-messages" style="flex: 1; overflow-y: auto; padding: 15px;">
    <?php while ($message = $result->fetch_assoc()): ?>
        <div class="message mb-3 <?= $message['sender_id'] == $user_id ? 'sent' : 'received' ?>">
            <div class="card <?= $message['sender_id'] == $user_id ? 'text-end' : '' ?> 
                            <?= $message['message_type'] === 'announcement' ? 'border-warning' : '' ?>">
                <div class="card-body p-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted">
                            <?= htmlspecialchars($message['sender_name']) ?>
                            <?php if ($message['message_type'] === 'announcement'): ?>
                                <span class="badge bg-warning text-dark ms-1">Announcement</span>
                            <?php endif; ?>
                        </small>
                        <small class="text-muted"><?= date('M j, Y g:i a', strtotime($message['sent_at'])) ?></small>
                    </div>
                    <p class="card-text mb-1"><?= nl2br(htmlspecialchars($message['content'])) ?></p>
                    
                    <?php if (!empty($message['media_path'])): ?>
                        <?php
                        $file_ext = strtolower(pathinfo($message['media_path'], PATHINFO_EXTENSION));
                        $image_exts = ['jpg', 'jpeg', 'png', 'gif'];
                        $video_exts = ['mp4', 'webm'];
                        $audio_exts = ['mp3', 'wav'];
                        ?>
                        
                        <?php if (in_array($file_ext, $image_exts)): ?>
                            <img src="<?= htmlspecialchars($message['media_path']) ?>" class="img-thumbnail mt-2" style="max-width: 200px;">
                        <?php elseif (in_array($file_ext, $video_exts)): ?>
                            <video controls class="mt-2" style="max-width: 200px;">
                                <source src="<?= htmlspecialchars($message['media_path']) ?>" type="video/<?= $file_ext ?>">
                            </video>
                        <?php elseif (in_array($file_ext, $audio_exts)): ?>
                            <audio controls class="mt-2">
                                <source src="<?= htmlspecialchars($message['media_path']) ?>" type="audio/<?= $file_ext ?>">
                            </audio>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($message['media_path']) ?>" class="btn btn-sm btn-secondary mt-2" download>
                                <i class="fas fa-download"></i> Download Attachment
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<div class="conversation-reply p-3 border-top">
    <form id="replyForm" enctype="multipart/form-data">
        <input type="hidden" name="conversation_id" value="<?= $conversation_id ?>">
        <div class="input-group">
            <textarea class="form-control" name="message" placeholder="Type your message..." rows="1" required></textarea>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        <div class="mt-2">
            <input type="file" name="attachment" id="attachment" class="form-control form-control-sm" accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
            <small class="text-muted">Max size: 5MB</small>
        </div>
    </form>
</div>

<script>
function showConversationList() {
    document.querySelector('.conversation-detail').innerHTML = `
        <div class="conversation-placeholder">
            <i class="fas fa-comment-dots"></i>
            <h3>Select a conversation</h3>
            <p>Choose an existing conversation or start a new one</p>
        </div>
    `;
}

// Handle reply form submission
document.getElementById('replyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('send_message.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadConversation(<?= $conversation_id ?>);
            this.reset();
        } else {
            alert('Error: ' + data.message);
        }
    });
});
</script>