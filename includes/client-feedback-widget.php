<?php
/**
 * Client Feedback Widget
 * Include this in project detail pages
 * Usage: include '../includes/client-feedback-widget.php';
 */

// Ensure $projectId is available
if (!isset($projectId)) {
    $projectId = $_GET['id'] ?? null;
}

$canAddFeedback = in_array($_SESSION['role'], ['admin', 'manager']);
?>

<style>
.feedback-item {
    background: var(--card-bg);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 20px;
    margin-bottom: 16px;
    transition: all 0.2s ease;
}

.feedback-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.feedback-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 12px;
}

.feedback-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--heading-color);
    margin-bottom: 4px;
}

.feedback-badges {
    display: flex;
    gap: 8px;
}

.feedback-body {
    padding: 12px 16px;
    background: var(--bg-secondary);
    border-radius: var(--radius-sm);
    margin-bottom: 12px;
    font-size: 14px;
    line-height: 1.6;
    color: var(--text-primary);
}

.feedback-meta {
    display: flex;
    gap: 16px;
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 12px;
}

.feedback-responses {
    border-top: 1px solid var(--border-light);
    padding-top: 12px;
    margin-top: 12px;
}

.response-item {
    padding: 12px;
    background: var(--bg-tertiary);
    border-left: 3px solid var(--primary);
    border-radius: 4px;
    margin-bottom: 8px;
}

.response-author {
    font-weight: 600;
    color: var(--heading-color);
    margin-bottom: 4px;
    font-size: 13px;
}

.response-text {
    font-size: 14px;
    color: var(--text-primary);
    line-height: 1.5;
}

.response-time {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 4px;
}

.feedback-actions {
    display: flex;
    gap: 8px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--card-bg);
    padding: 30px;
    border-radius: var(--radius-lg);
    max-width: 600px;
    width: 90%;
    max-height: 85vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h3 {
    margin: 0;
    font-size: 20px;
}

.close-modal {
    background: none;
    border: none;
    font-size: 24px;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.close-modal:hover {
    background: var(--bg-secondary);
    color: var(--heading-color);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
}
</style>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-comments"></i> Client Feedback</h3>
        <?php if ($canAddFeedback): ?>
        <button class="btn btn-primary" onclick="openAddFeedbackModal()">
            <i class="fas fa-plus"></i> Add Feedback
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div id="feedback-list">
            <div class="empty-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading feedback...</p>
            </div>
        </div>
    </div>
</div>

<!-- Add Feedback Modal -->
<?php if ($canAddFeedback): ?>
<div id="addFeedbackModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Client Feedback</h3>
            <button class="close-modal" onclick="closeAddFeedbackModal()">×</button>
        </div>
        <form id="addFeedbackForm" onsubmit="submitFeedback(event)">
            <div class="form-group">
                <label>Feedback Title <span style="color: var(--danger);">*</span></label>
                <input type="text" name="feedback_title" class="form-control" required placeholder="e.g., Client wants logo bigger">
            </div>

            <div class="form-group">
                <label>Feedback Details <span style="color: var(--danger);">*</span></label>
                <textarea name="feedback_text" class="form-control" rows="4" required placeholder="Detailed feedback from client..."></textarea>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Assign To</label>
                <select name="assigned_to" class="form-control">
                    <option value="">Select team member</option>
                    <?php
                    // Get team members for this project
                    $teamQuery = $db->prepare("
                        SELECT DISTINCT u.id, u.full_name
                        FROM users u
                        JOIN tasks t ON t.assigned_to = u.id
                        WHERE t.project_id = ?
                        ORDER BY u.full_name
                    ");
                    $teamQuery->execute([$projectId]);
                    $teamList = $teamQuery->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($teamList as $member):
                    ?>
                        <option value="<?php echo $member['id']; ?>"><?php echo e($member['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Related Task (Optional)</label>
                <select name="task_id" class="form-control">
                    <option value="">None - Project-wide feedback</option>
                    <?php
                    // Get tasks for this project
                    $taskQuery = $db->prepare("
                        SELECT id, task_name
                        FROM tasks
                        WHERE project_id = ?
                        ORDER BY task_name
                    ");
                    $taskQuery->execute([$projectId]);
                    $taskList = $taskQuery->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($taskList as $task):
                    ?>
                        <option value="<?php echo $task['id']; ?>"><?php echo e($task['task_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeAddFeedbackModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Feedback</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Response Modal -->
<div id="respondModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Respond to Feedback</h3>
            <button class="close-modal" onclick="closeRespondModal()">×</button>
        </div>
        <form id="respondForm" onsubmit="submitResponse(event)">
            <input type="hidden" name="feedback_id" id="respond_feedback_id">

            <div class="form-group">
                <label>Your Response</label>
                <textarea name="response_text" class="form-control" rows="4" required placeholder="Your response or update..."></textarea>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeRespondModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Response</button>
            </div>
        </form>
    </div>
</div>

<script>
// Load feedback on page load
document.addEventListener('DOMContentLoaded', function() {
    loadFeedback();
});

function loadFeedback() {
    fetch('/api/client-feedback.php?project_id=<?php echo $projectId; ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                displayFeedback(data.feedbacks);
            }
        })
        .catch(err => console.error('Error loading feedback:', err));
}

function displayFeedback(feedbacks) {
    const container = document.getElementById('feedback-list');

    if (!feedbacks || feedbacks.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-comments"></i>
                <p>No client feedback yet</p>
                <?php if ($canAddFeedback): ?>
                <button class="btn btn-primary" onclick="openAddFeedbackModal()">Add First Feedback</button>
                <?php endif; ?>
            </div>
        `;
        return;
    }

    container.innerHTML = feedbacks.map(feedback => {
        const priorityClass = {
            'urgent': 'badge-danger',
            'high': 'badge-warning',
            'medium': 'badge-info',
            'low': 'badge-secondary'
        }[feedback.priority];

        const statusClass = {
            'completed': 'badge-success',
            'in_progress': 'badge-warning',
            'pending': 'badge-secondary'
        }[feedback.status];

        return `
            <div class="feedback-item">
                <div class="feedback-header">
                    <div style="flex: 1;">
                        <div class="feedback-title">${escapeHtml(feedback.feedback_title)}</div>
                        <div class="feedback-meta">
                            <span><i class="fas fa-user"></i> ${escapeHtml(feedback.added_by_name)}</span>
                            ${feedback.assigned_to_name ? `<span><i class="fas fa-arrow-right"></i> ${escapeHtml(feedback.assigned_to_name)}</span>` : ''}
                            ${feedback.due_date ? `<span><i class="fas fa-calendar"></i> Due: ${formatDate(feedback.due_date)}</span>` : ''}
                            ${feedback.task_name ? `<span><i class="fas fa-tasks"></i> ${escapeHtml(feedback.task_name)}</span>` : ''}
                        </div>
                    </div>
                    <div class="feedback-badges">
                        <span class="badge ${priorityClass}">${feedback.priority}</span>
                        <span class="badge ${statusClass}">${feedback.status.replace('_', ' ')}</span>
                    </div>
                </div>

                <div class="feedback-body">
                    ${escapeHtml(feedback.feedback_text).replace(/\n/g, '<br>')}
                </div>

                ${feedback.response_count > 0 ? `
                    <div class="feedback-responses">
                        <div style="font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px;">
                            ${feedback.response_count} Response${feedback.response_count > 1 ? 's' : ''}
                        </div>
                        <button class="btn btn-sm btn-secondary" onclick="viewFeedbackResponses(${feedback.id})">
                            View Responses
                        </button>
                    </div>
                ` : ''}

                <div class="feedback-actions" style="margin-top: 12px;">
                    ${feedback.status !== 'completed' ? `
                        <button class="btn btn-sm btn-primary" onclick="openRespondModal(${feedback.id})">
                            <i class="fas fa-reply"></i> Respond
                        </button>
                        <?php if ($canAddFeedback): ?>
                        <button class="btn btn-sm btn-success" onclick="markFeedbackComplete(${feedback.id})">
                            <i class="fas fa-check"></i> Mark Complete
                        </button>
                        <?php endif; ?>
                    ` : ''}
                </div>
            </div>
        `;
    }).join('');
}

function openAddFeedbackModal() {
    document.getElementById('addFeedbackModal').classList.add('active');
}

function closeAddFeedbackModal() {
    document.getElementById('addFeedbackModal').classList.remove('active');
    document.getElementById('addFeedbackForm').reset();
}

function submitFeedback(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    data.project_id = <?php echo $projectId; ?>;

    fetch('/api/client-feedback.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            closeAddFeedbackModal();
            loadFeedback();
            alert('Feedback added successfully!');
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error adding feedback');
    });
}

function openRespondModal(feedbackId) {
    document.getElementById('respond_feedback_id').value = feedbackId;
    document.getElementById('respondModal').classList.add('active');
}

function closeRespondModal() {
    document.getElementById('respondModal').classList.remove('active');
    document.getElementById('respondForm').reset();
}

function submitResponse(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);

    fetch('/api/client-feedback.php', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            closeRespondModal();
            loadFeedback();
            alert('Response added successfully!');
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error submitting response');
    });
}

function markFeedbackComplete(feedbackId) {
    if (!confirm('Mark this feedback as completed?')) return;

    fetch('/api/client-feedback.php', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            feedback_id: feedbackId,
            status: 'completed'
        })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            loadFeedback();
            alert('Feedback marked as completed!');
        } else {
            alert('Error: ' + result.message);
        }
    });
}

function viewFeedbackResponses(feedbackId) {
    // Implement full response view
    window.location.href = `project-detail.php?id=<?php echo $projectId; ?>&feedback=${feedbackId}`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>
