<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$db = getDBConnection();
$currentUser = getCurrentUser();

// Get all projects for channel selection
$projects = $db->query("
    SELECT id, project_name, client_name
    FROM projects
    WHERE status IN ('planning', 'in_progress', 'review')
    ORDER BY project_name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Chat - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/ultra-premium.css">
    <style>
    .chat-container {
        display: grid;
        grid-template-columns: 250px 1fr;
        gap: 20px;
        height: calc(100vh - 200px);
    }

    .chat-channels {
        background: white;
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 16px;
        overflow-y: auto;
    }

    .chat-channels h3 {
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--text-secondary);
        margin-bottom: 12px;
    }

    .channel-item {
        padding: 10px 12px;
        border-radius: var(--radius-sm);
        cursor: pointer;
        margin-bottom: 4px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .channel-item:hover {
        background: var(--bg-tertiary);
    }

    .channel-item.active {
        background: var(--primary);
        color: white;
    }

    .chat-main {
        background: white;
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .chat-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
    }

    .chat-header h3 {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
    }

    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .message {
        display: flex;
        gap: 12px;
    }

    .message-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        flex-shrink: 0;
    }

    .message-content {
        flex: 1;
    }

    .message-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
    }

    .message-sender {
        font-weight: 600;
        font-size: 14px;
    }

    .message-time {
        font-size: 12px;
        color: var(--text-secondary);
    }

    .message-text {
        font-size: 14px;
        line-height: 1.5;
        color: var(--text-primary);
        word-wrap: break-word;
    }

    .message.own {
        flex-direction: row-reverse;
    }

    .message.own .message-content {
        text-align: right;
    }

    .message.own .message-avatar {
        background: var(--green);
    }

    .chat-input {
        padding: 16px 20px;
        border-top: 1px solid var(--border);
    }

    .chat-input-form {
        display: flex;
        gap: 12px;
    }

    .chat-input-form input {
        flex: 1;
        padding: 10px 16px;
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        font-size: 14px;
    }

    .chat-input-form button {
        padding: 10px 20px;
    }

    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: var(--text-secondary);
        text-align: center;
    }

    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 16px;
    }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <?php include '../includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <h1>💬 Team Chat</h1>
                <div class="topbar-actions">
                    <?php include '../includes/notifications-dropdown.php'; ?>
                </div>
            </div>

            <div class="content">
                <div class="chat-container">
                    <!-- Channels Sidebar -->
                    <div class="chat-channels">
                        <h3>Channels</h3>
                        <div class="channel-item active" data-channel-type="team" data-channel-id="">
                            <span>👥</span>
                            <span>General Team</span>
                        </div>

                        <h3 style="margin-top: 20px;">Projects</h3>
                        <?php foreach ($projects as $project): ?>
                        <div class="channel-item" data-channel-type="project" data-channel-id="<?php echo $project['id']; ?>">
                            <span>📁</span>
                            <span><?php echo e($project['project_name']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Chat Main -->
                    <div class="chat-main">
                        <div class="chat-header">
                            <h3 id="channelName">👥 General Team</h3>
                        </div>

                        <div class="chat-messages" id="chatMessages">
                            <div class="empty-state">
                                <div class="empty-state-icon">💬</div>
                                <p>No messages yet. Start the conversation!</p>
                            </div>
                        </div>

                        <div class="chat-input">
                            <form class="chat-input-form" id="chatForm">
                                <input type="text" id="messageInput" placeholder="Type a message..." autocomplete="off" required>
                                <button type="submit" class="btn btn-primary">Send</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    const currentUserId = <?php echo $currentUser['id']; ?>;
    const currentUserName = '<?php echo e($currentUser['full_name']); ?>';
    let currentChannelType = 'team';
    let currentChannelId = null;
    let lastMessageTime = null;
    let pollInterval = null;

    // Switch channel
    document.querySelectorAll('.channel-item').forEach(item => {
        item.addEventListener('click', function() {
            // Update active state
            document.querySelectorAll('.channel-item').forEach(i => i.classList.remove('active'));
            this.classList.add('active');

            // Update current channel
            currentChannelType = this.dataset.channelType;
            currentChannelId = this.dataset.channelId || null;

            // Update header
            const channelName = this.textContent.trim();
            document.getElementById('channelName').textContent = channelName;

            // Load messages
            lastMessageTime = null;
            loadMessages(false);
        });
    });

    // Load messages
    function loadMessages(poll = false) {
        let url = `../api/chat.php?channel_type=${currentChannelType}`;
        if (currentChannelId) url += `&channel_id=${currentChannelId}`;
        if (poll && lastMessageTime) url += `&since=${encodeURIComponent(lastMessageTime)}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (poll) {
                        // Append new messages
                        if (data.messages.length > 0) {
                            data.messages.forEach(msg => appendMessage(msg));
                            lastMessageTime = data.messages[data.messages.length - 1].created_at;
                        }
                    } else {
                        // Replace all messages
                        displayMessages(data.messages);
                        if (data.messages.length > 0) {
                            lastMessageTime = data.messages[data.messages.length - 1].created_at;
                        }
                    }
                }
            })
            .catch(error => console.error('Error loading messages:', error));
    }

    // Display messages
    function displayMessages(messages) {
        const container = document.getElementById('chatMessages');

        if (messages.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">💬</div>
                    <p>No messages yet. Start the conversation!</p>
                </div>
            `;
            return;
        }

        container.innerHTML = '';
        messages.forEach(msg => appendMessage(msg));
        scrollToBottom();
    }

    // Append single message
    function appendMessage(msg) {
        const container = document.getElementById('chatMessages');

        // Remove empty state if present
        const emptyState = container.querySelector('.empty-state');
        if (emptyState) emptyState.remove();

        const isOwn = msg.sender_id == currentUserId;
        const time = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        const initial = msg.sender_name.charAt(0).toUpperCase();

        const messageEl = document.createElement('div');
        messageEl.className = `message ${isOwn ? 'own' : ''}`;
        messageEl.innerHTML = `
            <div class="message-avatar">${initial}</div>
            <div class="message-content">
                <div class="message-header">
                    <span class="message-sender">${escapeHtml(msg.sender_name)}</span>
                    <span class="message-time">${time}${msg.is_edited === '1' ? ' (edited)' : ''}</span>
                </div>
                <div class="message-text">${escapeHtml(msg.message)}</div>
            </div>
        `;

        container.appendChild(messageEl);
        scrollToBottom();
    }

    // Send message
    document.getElementById('chatForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const input = document.getElementById('messageInput');
        const message = input.value.trim();

        if (!message) return;

        fetch('../api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message: message,
                channel_type: currentChannelType,
                channel_id: currentChannelId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                appendMessage(data.message);
                lastMessageTime = data.message.created_at;
            } else {
                alert('Failed to send message: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error sending message: ' + error);
        });
    });

    // Scroll to bottom
    function scrollToBottom() {
        const container = document.getElementById('chatMessages');
        container.scrollTop = container.scrollHeight;
    }

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Start polling for new messages
    function startPolling() {
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(() => {
            loadMessages(true);
        }, 3000); // Poll every 3 seconds
    }

    // Initial load
    loadMessages(false);
    startPolling();

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        if (pollInterval) clearInterval(pollInterval);
    });
    </script>
    <script src="../assets/js/theme.js"></script>
</body>
</html>
