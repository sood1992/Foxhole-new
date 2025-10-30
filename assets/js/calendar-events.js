/**
 * Calendar Events Enhancement
 * Adds shoot schedule functionality to FullCalendar
 * Include this after FullCalendar initialization
 */

// Event type colors
const EVENT_COLORS = {
    'shoot': '#FF6B6B',
    'edit': '#4ECDC4',
    'review': '#FFE66D',
    'meeting': '#95E1D3',
    'deadline': '#F38181',
    'delivery': '#AA96DA',
    'other': '#CCCCCC'
};

// Initialize calendar with events API
function initializeCalendarEvents(calendarInstance, options = {}) {
    const projectId = options.projectId || null;

    // Override events source to use calendar-events API
    const originalEventsSource = {
        url: '/api/calendar-events.php',
        method: 'GET',
        extraParams: function() {
            return {
                project_id: projectId
            };
        },
        failure: function() {
            alert('Error loading calendar events');
        }
    };

    // Add event sources
    calendarInstance.addEventSource(originalEventsSource);

    // Handle event clicks
    calendarInstance.setOption('eventClick', function(info) {
        showEventDetails(info.event);
    });

    // Handle date clicks
    calendarInstance.setOption('dateClick', function(info) {
        openAddEventModal(info.date);
    });

    // Event drag and drop
    calendarInstance.setOption('editable', true);
    calendarInstance.setOption('eventDrop', function(info) {
        updateEventDateTime(info.event);
    });
}

// Show event details modal
function showEventDetails(event) {
    const props = event.extendedProps;

    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3>${escapeHtml(event.title)}</h3>
                <button class="close-modal" onclick="this.closest('.modal').remove()">×</button>
            </div>
            <div style="padding: 20px 0;">
                <div style="margin-bottom: 16px;">
                    <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Event Type</div>
                    <span class="badge" style="background: ${event.backgroundColor}; color: white;">
                        ${props.type}
                    </span>
                </div>

                ${props.description ? `
                    <div style="margin-bottom: 16px;">
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Description</div>
                        <div style="padding: 12px; background: var(--bg-secondary); border-radius: 6px;">
                            ${escapeHtml(props.description).replace(/\n/g, '<br>')}
                        </div>
                    </div>
                ` : ''}

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 16px;">
                    <div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Start</div>
                        <div>${formatDateTime(event.start)}</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">End</div>
                        <div>${formatDateTime(event.end)}</div>
                    </div>
                </div>

                ${props.location ? `
                    <div style="margin-bottom: 16px;">
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Location</div>
                        <div><i class="fas fa-map-marker-alt"></i> ${escapeHtml(props.location)}</div>
                    </div>
                ` : ''}

                ${props.project_name ? `
                    <div style="margin-bottom: 16px;">
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Project</div>
                        <div><i class="fas fa-folder"></i> ${escapeHtml(props.project_name)}</div>
                    </div>
                ` : ''}

                ${props.equipment_needed ? `
                    <div style="margin-bottom: 16px;">
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Equipment Needed</div>
                        <div style="padding: 12px; background: var(--bg-tertiary); border-radius: 6px;">
                            ${escapeHtml(props.equipment_needed).replace(/\n/g, '<br>')}
                        </div>
                    </div>
                ` : ''}

                ${props.notes ? `
                    <div style="margin-bottom: 16px;">
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Notes</div>
                        <div style="padding: 12px; background: var(--bg-tertiary); border-radius: 6px;">
                            ${escapeHtml(props.notes).replace(/\n/g, '<br>')}
                        </div>
                    </div>
                ` : ''}

                <div style="margin-bottom: 16px;">
                    <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Created by</div>
                    <div>${escapeHtml(props.created_by)}</div>
                </div>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">Close</button>
                <button class="btn btn-danger" onclick="deleteEvent('${event.id}')">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
}

// Open add event modal
function openAddEventModal(date = null) {
    const startDate = date ? formatDateForInput(date) : '';

    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.id = 'addEventModal';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3>Schedule Event</h3>
                <button class="close-modal" onclick="this.closest('.modal').remove()">×</button>
            </div>
            <form id="addEventForm" onsubmit="submitNewEvent(event)">
                <div class="form-group">
                    <label>Event Type</label>
                    <select name="event_type" class="form-control" required onchange="updateEventColor(this)">
                        <option value="shoot">🎥 Shoot</option>
                        <option value="edit">✂️ Edit Session</option>
                        <option value="review">👁️ Client Review</option>
                        <option value="meeting">👥 Meeting</option>
                        <option value="deadline">🚩 Deadline</option>
                        <option value="delivery">🚚 Delivery</option>
                        <option value="other">📅 Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Title <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="event_title" class="form-control" required>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Start Date/Time <span style="color: var(--danger);">*</span></label>
                            <input type="datetime-local" name="start_datetime" class="form-control" required value="${startDate}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>End Date/Time <span style="color: var(--danger);">*</span></label>
                            <input type="datetime-local" name="end_datetime" class="form-control" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="all_day" value="1">
                        All Day Event
                    </label>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" class="form-control" placeholder="e.g., Studio A, Client Office">
                </div>

                <div class="form-group">
                    <label>Equipment Needed</label>
                    <textarea name="equipment_needed" class="form-control" rows="3" placeholder="List equipment needed for this event"></textarea>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Event</button>
                </div>
            </form>
        </div>
    `;

    document.body.appendChild(modal);
}

// Submit new event
function submitNewEvent(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);

    fetch('/api/calendar-events.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            document.getElementById('addEventModal').remove();
            window.location.reload(); // Reload to show new event
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error creating event');
    });
}

// Update event date/time after drag
function updateEventDateTime(event) {
    fetch('/api/calendar-events.php', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            event_id: event.id,
            start_datetime: event.startStr,
            end_datetime: event.endStr
        })
    })
    .then(r => r.json())
    .then(result => {
        if (!result.success) {
            alert('Error updating event');
            event.revert();
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error updating event');
        event.revert();
    });
}

// Delete event
function deleteEvent(eventId) {
    if (!confirm('Delete this event?')) return;

    fetch('/api/calendar-events.php', {
        method: 'DELETE',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ event_id: eventId })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    });
}

// Add calendar legend
function addCalendarLegend(containerSelector) {
    const container = document.querySelector(containerSelector);
    if (!container) return;

    const legend = document.createElement('div');
    legend.className = 'calendar-legend';
    legend.style.cssText = 'display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; padding: 16px; background: var(--bg-tertiary); border-radius: 8px;';

    legend.innerHTML = Object.entries(EVENT_COLORS).map(([type, color]) => `
        <span style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
            <span style="width: 16px; height: 16px; background: ${color}; border-radius: 3px;"></span>
            ${type.charAt(0).toUpperCase() + type.slice(1)}
        </span>
    `).join('');

    container.insertBefore(legend, container.firstChild);
}

// Utility functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDateTime(date) {
    if (!date) return '';
    return new Date(date).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
}

function formatDateForInput(date) {
    const d = new Date(date);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function updateEventColor(select) {
    const type = select.value;
    const color = EVENT_COLORS[type] || '#CCCCCC';
    select.style.borderLeft = `4px solid ${color}`;
}

// Make functions globally available
window.openAddEventModal = openAddEventModal;
window.submitNewEvent = submitNewEvent;
window.deleteEvent = deleteEvent;
window.initializeCalendarEvents = initializeCalendarEvents;
window.addCalendarLegend = addCalendarLegend;
