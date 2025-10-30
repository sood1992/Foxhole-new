# 🎬 Agency Features Implementation Guide

## 📦 What's Been Added

Three major features specifically designed for creative agencies:

1. **Client Feedback & Approval System** - Seamless PM→Employee communication
2. **Budget & Expense Tracking** - Project profitability management
3. **Shoot Schedule System** - Color-coded calendar for shoots/edits

---

## 🚀 INSTALLATION STEPS

### Step 1: Deploy Database Schema

Run this SQL file on your database:
```bash
mysql -u your_user -p your_database < add-agency-features.sql
```

Or via phpMyAdmin:
1. Open phpMyAdmin
2. Select your database
3. Go to "SQL" tab
4. Copy content from `add-agency-features.sql`
5. Click "Go"

### Step 2: Verify Tables Created

Check these tables exist:
- `client_feedback`
- `feedback_responses`
- `project_budgets`
- `project_expenses`
- `calendar_events`
- `event_type_colors`

### Step 3: Test API Endpoints

All APIs are ready to use:
- `https://your-site.com/api/client-feedback.php`
- `https://your-site.com/api/budget.php`
- `https://your-site.com/api/calendar-events.php`

---

## 1️⃣ CLIENT FEEDBACK SYSTEM

### How It Works

**Workflow:**
1. PM receives feedback from client
2. PM adds feedback in portal → assigns to employee
3. Employee sees feedback in their dashboard
4. Employee responds/completes changes
5. PM marks as completed

### API Usage

**Add Feedback (PM/Manager):**
```javascript
fetch('/api/client-feedback.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        project_id: 123,
        task_id: 456, // optional
        feedback_title: "Client wants shorter intro",
        feedback_text: "Client feedback: Make the intro 5 seconds instead of 10",
        priority: "high",
        assigned_to: 789, // employee user ID
        due_date: "2025-11-05"
    })
})
```

**Get All Feedback for Project:**
```javascript
fetch('/api/client-feedback.php?project_id=123')
```

**Add Response (Employee):**
```javascript
fetch('/api/client-feedback.php', {
    method: 'PUT',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        feedback_id: 1,
        response_text: "Shortened the intro to 5 seconds. Ready for review."
    })
})
```

**Update Status:**
```javascript
fetch('/api/client-feedback.php', {
    method: 'PUT',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        feedback_id: 1,
        status: "completed"
    })
})
```

### UI Components Needed

#### 1. In Project Detail Page (admin/project-detail.php)

Add "Client Feedback" tab:

```html
<div class="card">
    <div class="card-header">
        <h3>Client Feedback</h3>
        <button class="btn btn-primary" onclick="openAddFeedbackModal()">
            <i class="fas fa-plus"></i> Add Feedback
        </button>
    </div>
    <div class="card-body">
        <div id="feedback-list">
            <!-- Feedback items loaded via JavaScript -->
        </div>
    </div>
</div>
```

**Feedback Item Display:**
```html
<div class="feedback-item" data-id="1">
    <div class="feedback-header">
        <h4>Client wants shorter intro</h4>
        <span class="badge badge-high">High Priority</span>
        <span class="badge badge-in-progress">In Progress</span>
    </div>
    <div class="feedback-body">
        <p>Client feedback: Make the intro 5 seconds instead of 10</p>
        <div class="feedback-meta">
            <span>Assigned to: John Doe</span>
            <span>Due: Nov 5, 2025</span>
        </div>
    </div>
    <div class="feedback-responses">
        <div class="response">
            <strong>John Doe:</strong> Shortened the intro to 5 seconds. Ready for review.
        </div>
    </div>
    <div class="feedback-actions">
        <button onclick="respondToFeedback(1)">Respond</button>
        <button onclick="markComplete(1)">Mark Complete</button>
    </div>
</div>
```

#### 2. Employee Dashboard Widget (employee/index.php)

Add "My Feedback" widget:

```html
<div class="dashboard-card">
    <div class="card-header">
        <h3>Client Feedback Assigned to Me</h3>
    </div>
    <div class="card-body">
        <div id="my-feedback-list">
            <!-- Load feedback via API -->
        </div>
    </div>
</div>
```

---

## 2️⃣ BUDGET & EXPENSE SYSTEM

### How It Works

**Workflow:**
1. PM sets project budget (total + breakdown)
2. Team logs time (auto-calculated labor cost)
3. Team adds expenses (equipment, travel, etc.)
4. System calculates: Budget vs Actual
5. Real-time tracking of remaining budget

### API Usage

**Set Project Budget:**
```javascript
fetch('/api/budget.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: "update_budget",
        project_id: 123,
        total_budget: 10000,
        labor_budget: 6000,
        equipment_budget: 2000,
        materials_budget: 1500,
        other_budget: 500,
        notes: "Client approved budget"
    })
})
```

**Add Expense:**
```javascript
fetch('/api/budget.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: "add_expense",
        project_id: 123,
        expense_category: "equipment", // equipment, travel, materials, stock_footage, etc.
        expense_title: "Camera Rental",
        description: "Sony A7S III for shoot",
        amount: 500,
        expense_date: "2025-10-30",
        receipt_file: "uploads/receipts/receipt123.pdf"
    })
})
```

**Get Budget Summary:**
```javascript
fetch('/api/budget.php?project_id=123')
    .then(r => r.json())
    .then(data => {
        console.log(data.summary);
        // {
        //   total_budget: 10000,
        //   labor_cost: 3200,
        //   expenses_total: 1500,
        //   total_spent: 4700,
        //   remaining: 5300,
        //   percent_used: 47
        // }
    });
```

**Approve/Reject Expense:**
```javascript
fetch('/api/budget.php', {
    method: 'PUT',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        expense_id: 1,
        action: "approve" // or "reject"
    })
})
```

### UI Components Needed

#### 1. Budget Dashboard (admin/project-detail.php)

Add "Budget" tab:

```html
<div class="card">
    <div class="card-header">
        <h3>Project Budget</h3>
        <button class="btn btn-primary" onclick="editBudget()">Edit Budget</button>
    </div>
    <div class="card-body">
        <!-- Budget Summary Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="budget-card">
                    <div class="budget-value">$10,000</div>
                    <div class="budget-label">Total Budget</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="budget-card">
                    <div class="budget-value">$4,700</div>
                    <div class="budget-label">Spent</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="budget-card">
                    <div class="budget-value">$5,300</div>
                    <div class="budget-label">Remaining</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="budget-card">
                    <div class="budget-value">47%</div>
                    <div class="budget-label">Used</div>
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress-bar-container">
            <div class="progress-bar" style="width: 47%; background: green;"></div>
        </div>

        <!-- Breakdown -->
        <h4>Budget Breakdown</h4>
        <table class="table">
            <tr>
                <td>Labor Cost</td>
                <td>$3,200</td>
                <td><span class="badge">Auto-calculated</span></td>
            </tr>
            <tr>
                <td>Equipment</td>
                <td>$800</td>
                <td>Budget: $2,000</td>
            </tr>
            <tr>
                <td>Materials</td>
                <td>$700</td>
                <td>Budget: $1,500</td>
            </tr>
        </table>

        <!-- Expenses List -->
        <h4>Recent Expenses <button onclick="addExpense()">+ Add Expense</button></h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="expenses-list">
                <!-- Load via API -->
            </tbody>
        </table>
    </div>
</div>
```

#### 2. Budget Widget in Master Dashboard

Add to admin/master-dashboard.php:

```html
<div class="col-md-4">
    <div class="card">
        <div class="card-header">
            <h3>Budget Overview</h3>
        </div>
        <div class="card-body">
            <!-- Show projects over/under budget -->
        </div>
    </div>
</div>
```

---

## 3️⃣ SHOOT SCHEDULE SYSTEM

### How It Works

**Event Types & Colors:**
- 🎥 **Shoot** (Red #FF6B6B) - Video/photo shoots
- ✂️ **Edit** (Teal #4ECDC4) - Editing sessions
- 👁️ **Review** (Yellow #FFE66D) - Client reviews
- 👥 **Meeting** (Green #95E1D3) - Team meetings
- 🚩 **Deadline** (Pink #F38181) - Project deadlines
- 🚚 **Delivery** (Purple #AA96DA) - Deliverable due
- 📅 **Other** (Gray #CCCCCC) - Other events

### API Usage

**Create Shoot Event:**
```javascript
fetch('/api/calendar-events.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        event_type: "shoot",
        event_title: "Product Photography Session",
        start_datetime: "2025-11-01 09:00:00",
        end_datetime: "2025-11-01 17:00:00",
        project_id: 123,
        location: "Studio A",
        assigned_users: "10,15,20", // comma-separated user IDs
        equipment_needed: "Sony A7S III, Lighting Kit, Backdrop",
        notes: "Bring white backdrop, client will be on site"
    })
})
```

**Get Calendar Events:**
```javascript
fetch('/api/calendar-events.php?start=2025-11-01&end=2025-11-30')
```

**Update Event:**
```javascript
fetch('/api/calendar-events.php', {
    method: 'PUT',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        event_id: 1,
        status: "completed"
    })
})
```

### UI Updates Needed

#### 1. Update Calendar Pages

Modify calendar.php files to add event type selector:

```html
<!-- Add to calendar.php (all panels) -->
<div class="calendar-controls">
    <select id="event-type-filter">
        <option value="">All Events</option>
        <option value="shoot">🎥 Shoots</option>
        <option value="edit">✂️ Edits</option>
        <option value="review">👁️ Reviews</option>
        <option value="meeting">👥 Meetings</option>
        <option value="deadline">🚩 Deadlines</option>
        <option value="delivery">🚚 Deliveries</option>
    </select>

    <button class="btn btn-primary" onclick="openAddEventModal()">
        + Add Event
    </button>
</div>

<!-- Color Legend -->
<div class="calendar-legend">
    <span class="legend-item" style="background: #FF6B6B">🎥 Shoot</span>
    <span class="legend-item" style="background: #4ECDC4">✂️ Edit</span>
    <span class="legend-item" style="background: #FFE66D">👁️ Review</span>
    <span class="legend-item" style="background: #95E1D3">👥 Meeting</span>
    <span class="legend-item" style="background: #F38181">🚩 Deadline</span>
    <span class="legend-item" style="background: #AA96DA">🚚 Delivery</span>
</div>
```

#### 2. Event Creation Modal

```html
<div id="addEventModal" class="modal">
    <div class="modal-content">
        <h3>Schedule Event</h3>
        <form id="addEventForm">
            <div class="form-group">
                <label>Event Type</label>
                <select name="event_type" required>
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
                <label>Title</label>
                <input type="text" name="event_title" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Start Date/Time</label>
                    <input type="datetime-local" name="start_datetime" required>
                </div>
                <div class="form-group">
                    <label>End Date/Time</label>
                    <input type="datetime-local" name="end_datetime" required>
                </div>
            </div>

            <div class="form-group">
                <label>Project (optional)</label>
                <select name="project_id">
                    <option value="">Select project</option>
                    <!-- Load from projects -->
                </select>
            </div>

            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" placeholder="e.g., Studio A, Client Office">
            </div>

            <div class="form-group">
                <label>Assign Team Members</label>
                <select name="assigned_users" multiple>
                    <!-- Load from users -->
                </select>
            </div>

            <div class="form-group">
                <label>Equipment Needed</label>
                <textarea name="equipment_needed" placeholder="List equipment needed for this event"></textarea>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" placeholder="Additional notes"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Create Event</button>
        </form>
    </div>
</div>
```

#### 3. Update FullCalendar Integration

```javascript
// Update calendar initialization to use new API
calendarInstance = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    events: function(info, successCallback, failureCallback) {
        // Use new calendar-events API
        fetch(`/api/calendar-events.php?start=${info.startStr}&end=${info.endStr}`)
            .then(response => response.json())
            .then(data => {
                successCallback(data.events);
            })
            .catch(error => {
                failureCallback(error);
            });
    },
    eventClick: function(info) {
        showEventDetails(info.event);
    },
    // Allow drag and drop
    editable: true,
    eventDrop: function(info) {
        updateEventDateTime(info.event.id, info.event.startStr, info.event.endStr);
    }
});
```

---

## 📊 USAGE EXAMPLES

### Example 1: Video Project Workflow

1. **PM creates project**: "Product Video for Acme Corp"
2. **PM sets budget**: $5,000 total
3. **PM schedules shoot**: "Product Shoot" → Red event on calendar
4. **Team shoots**: Logs time automatically
5. **PM adds expense**: "Equipment rental $300"
6. **Editor edits**: Schedules "Edit Session" → Teal event
7. **Client reviews**: PM gets feedback "Make logo bigger"
8. **PM adds feedback**: Assigns to editor
9. **Editor sees feedback**: Makes changes, responds
10. **PM marks complete**: Project delivered

### Example 2: Photography Session

1. **Create calendar event**: "Corporate Headshots"
   - Type: Shoot 🎥
   - Location: Client Office, Floor 3
   - Team: Photographer + Assistant
   - Equipment: 2x Cameras, Lighting Kit, Backdrop
2. **Team sees event** in their calendar
3. **Day of shoot**: Check equipment list
4. **Post-shoot**: Add editing session event
5. **Track expenses**: Travel, equipment, etc.

---

## 🎨 STYLING TIPS

### Color Scheme for Event Types

```css
.event-shoot { background: #FF6B6B; }
.event-edit { background: #4ECDC4; }
.event-review { background: #FFE66D; }
.event-meeting { background: #95E1D3; }
.event-deadline { background: #F38181; }
.event-delivery { background: #AA96DA; }
.event-other { background: #CCCCCC; }
```

### Priority Badges

```css
.badge-urgent { background: #ec4561; color: white; }
.badge-high { background: #f8b739; color: white; }
.badge-medium { background: #17b06b; color: white; }
.badge-low { background: #6c757d; color: white; }
```

---

## ✅ NEXT STEPS TO COMPLETE IMPLEMENTATION

1. **Run Database Schema** (add-agency-features.sql)
2. **Add Client Feedback UI** to project detail page
3. **Add Budget Widget** to project detail page
4. **Update Calendar** with event type selector
5. **Add Employee Feedback Widget** to employee dashboard
6. **Test All Features** thoroughly
7. **Train Team** on new workflows

---

## 🔧 TECHNICAL NOTES

### Security
- All APIs check user authentication
- Role-based permissions enforced
- Only managers can add/edit feedback and budgets
- Employees can only see assigned feedback
- XSS protection via prepared statements

### Performance
- Indexes added for fast queries
- Efficient JOIN operations
- API responses are JSON cached
- Calendar uses date range queries

### Data Integrity
- Foreign key constraints
- Cascade deletes where appropriate
- Transaction support for critical operations
- Audit trail via created_at/updated_at

---

## 💡 PRO TIPS

1. **Client Feedback**: Use priority levels wisely - "Urgent" should be rare
2. **Budget**: Update regularly, review weekly
3. **Calendar**: Color coding makes scheduling intuitive
4. **Expenses**: Attach receipts for audit trail
5. **Team Assignment**: Assign specific people to events for accountability

---

## 🆘 TROUBLESHOOTING

**Q: APIs returning 401 Unauthorized**
A: User not logged in. Check session.

**Q: Calendar events not showing**
A: Check date range in API call matches calendar view

**Q: Budget not calculating labor correctly**
A: Ensure users have hourly_rate set in database

**Q: Feedback not appearing for employee**
A: Check assigned_to matches employee user ID

---

## 📞 SUPPORT

If you need help implementing these features or want to customize them further, these APIs are fully documented and ready to integrate with your UI!

**All backend work is done - just needs frontend UI integration!**
