/**
 * Quick Actions - Keyboard Shortcuts
 * Global keyboard shortcuts for productivity
 */

(function() {
    'use strict';

    // Command palette state
    let commandPaletteOpen = false;
    let searchResults = [];
    let selectedIndex = 0;

    // Keyboard shortcuts mapping
    const shortcuts = {
        // Navigation
        'g d': { action: 'goToDashboard', description: 'Go to Dashboard' },
        'g t': { action: 'goToTasks', description: 'Go to Tasks' },
        'g c': { action: 'goToCalendar', description: 'Go to Calendar' },
        'g h': { action: 'goToChat', description: 'Go to Chat' },
        'g p': { action: 'goToProjects', description: 'Go to Projects' },
        'g a': { action: 'goToAnalytics', description: 'Go to Analytics' },

        // Actions
        '?': { action: 'showHelp', description: 'Show keyboard shortcuts' },
        '/': { action: 'focusSearch', description: 'Focus search' },
        'n': { action: 'newTask', description: 'New task (when on tasks page)' },

        // Time tracking
        't t': { action: 'toggleTimer', description: 'Toggle time tracker' },

        // Notifications
        'n n': { action: 'openNotifications', description: 'Open notifications' }
    };

    // Key sequence tracker
    let keySequence = '';
    let keySequenceTimeout = null;

    // Initialize
    function init() {
        createCommandPalette();
        createHelpModal();
        attachKeyboardListeners();
    }

    // Create command palette
    function createCommandPalette() {
        const palette = document.createElement('div');
        palette.id = 'commandPalette';
        palette.className = 'command-palette';
        palette.innerHTML = `
            <div class="command-palette-backdrop"></div>
            <div class="command-palette-content">
                <div class="command-palette-input">
                    <span class="command-palette-icon">🔍</span>
                    <input type="text" id="commandInput" placeholder="Type a command or search..." autocomplete="off">
                </div>
                <div class="command-palette-results" id="commandResults">
                    <!-- Results will be populated here -->
                </div>
            </div>
        `;

        document.body.appendChild(palette);

        // Input event
        const input = document.getElementById('commandInput');
        input.addEventListener('input', handleCommandInput);
        input.addEventListener('keydown', handleCommandKeydown);

        // Close on backdrop click
        palette.querySelector('.command-palette-backdrop').addEventListener('click', closeCommandPalette);
    }

    // Create help modal
    function createHelpModal() {
        const modal = document.createElement('div');
        modal.id = 'helpModal';
        modal.className = 'help-modal';
        modal.innerHTML = `
            <div class="help-modal-backdrop"></div>
            <div class="help-modal-content">
                <div class="help-modal-header">
                    <h3>⌨️ Keyboard Shortcuts</h3>
                    <button class="help-modal-close" onclick="closeHelpModal()">&times;</button>
                </div>
                <div class="help-modal-body">
                    <div class="shortcuts-grid">
                        <div class="shortcuts-section">
                            <h4>Navigation</h4>
                            <div class="shortcut-item">
                                <kbd>G</kbd> <kbd>D</kbd>
                                <span>Dashboard</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>G</kbd> <kbd>T</kbd>
                                <span>Tasks</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>G</kbd> <kbd>C</kbd>
                                <span>Calendar</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>G</kbd> <kbd>H</kbd>
                                <span>Chat</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>G</kbd> <kbd>P</kbd>
                                <span>Projects</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>G</kbd> <kbd>A</kbd>
                                <span>Analytics</span>
                            </div>
                        </div>

                        <div class="shortcuts-section">
                            <h4>Actions</h4>
                            <div class="shortcut-item">
                                <kbd>Ctrl/Cmd</kbd> + <kbd>K</kbd>
                                <span>Command palette</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>?</kbd>
                                <span>Show help</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>/</kbd>
                                <span>Focus search</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>N</kbd>
                                <span>New task</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>T</kbd> <kbd>T</kbd>
                                <span>Toggle timer</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>Esc</kbd>
                                <span>Close modals</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Close on backdrop click
        modal.querySelector('.help-modal-backdrop').addEventListener('click', closeHelpModal);
    }

    // Attach keyboard listeners
    function attachKeyboardListeners() {
        document.addEventListener('keydown', function(e) {
            // Ignore if typing in input/textarea
            if (e.target.matches('input, textarea') && !e.ctrlKey && !e.metaKey) {
                return;
            }

            // Ctrl/Cmd + K - Command palette
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                toggleCommandPalette();
                return;
            }

            // Escape - Close modals
            if (e.key === 'Escape') {
                if (commandPaletteOpen) {
                    closeCommandPalette();
                }
                closeHelpModal();
                return;
            }

            // Build key sequence
            if (!e.ctrlKey && !e.metaKey && !e.altKey) {
                clearTimeout(keySequenceTimeout);
                keySequence += e.key.toLowerCase();

                // Check if sequence matches a shortcut
                if (shortcuts[keySequence]) {
                    e.preventDefault();
                    executeAction(shortcuts[keySequence].action);
                    keySequence = '';
                } else {
                    // Reset sequence after 1 second
                    keySequenceTimeout = setTimeout(() => {
                        keySequence = '';
                    }, 1000);
                }
            }
        });
    }

    // Toggle command palette
    function toggleCommandPalette() {
        if (commandPaletteOpen) {
            closeCommandPalette();
        } else {
            openCommandPalette();
        }
    }

    function openCommandPalette() {
        const palette = document.getElementById('commandPalette');
        palette.classList.add('active');
        document.getElementById('commandInput').focus();
        commandPaletteOpen = true;
        loadDefaultCommands();
    }

    function closeCommandPalette() {
        const palette = document.getElementById('commandPalette');
        palette.classList.remove('active');
        document.getElementById('commandInput').value = '';
        commandPaletteOpen = false;
        selectedIndex = 0;
    }

    // Handle command input
    function handleCommandInput(e) {
        const query = e.target.value.toLowerCase();

        if (!query) {
            loadDefaultCommands();
            return;
        }

        // Filter commands
        const filtered = Object.entries(shortcuts)
            .filter(([key, cmd]) => {
                return cmd.description.toLowerCase().includes(query) ||
                       key.toLowerCase().includes(query);
            })
            .map(([key, cmd]) => ({
                key: key,
                description: cmd.description,
                action: cmd.action
            }));

        displayCommandResults(filtered);
    }

    // Handle command navigation
    function handleCommandKeydown(e) {
        const results = document.querySelectorAll('.command-result-item');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, results.length - 1);
            updateSelection(results);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, 0);
            updateSelection(results);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (results[selectedIndex]) {
                results[selectedIndex].click();
            }
        }
    }

    // Update selection
    function updateSelection(results) {
        results.forEach((item, index) => {
            if (index === selectedIndex) {
                item.classList.add('selected');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
            }
        });
    }

    // Load default commands
    function loadDefaultCommands() {
        const commands = Object.entries(shortcuts).map(([key, cmd]) => ({
            key: key,
            description: cmd.description,
            action: cmd.action
        }));
        displayCommandResults(commands);
    }

    // Display command results
    function displayCommandResults(commands) {
        const container = document.getElementById('commandResults');

        if (commands.length === 0) {
            container.innerHTML = '<div class="command-no-results">No commands found</div>';
            return;
        }

        let html = '';
        commands.forEach((cmd, index) => {
            html += `
                <div class="command-result-item ${index === selectedIndex ? 'selected' : ''}"
                     onclick="executeAction('${cmd.action}')"
                     data-action="${cmd.action}">
                    <div class="command-result-keys">
                        ${cmd.key.split(' ').map(k => `<kbd>${k.toUpperCase()}</kbd>`).join(' ')}
                    </div>
                    <div class="command-result-desc">${cmd.description}</div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // Execute action
    window.executeAction = function(action) {
        closeCommandPalette();

        switch (action) {
            case 'goToDashboard':
                window.location.href = getCurrentPath() + '/index.php';
                break;
            case 'goToTasks':
                window.location.href = getCurrentPath() + '/tasks.php';
                break;
            case 'goToCalendar':
                window.location.href = getCurrentPath() + '/calendar.php';
                break;
            case 'goToChat':
                window.location.href = getCurrentPath() + '/chat.php';
                break;
            case 'goToProjects':
                window.location.href = '../admin/projects.php';
                break;
            case 'goToAnalytics':
                window.location.href = '../admin/analytics.php';
                break;
            case 'showHelp':
                document.getElementById('helpModal').classList.add('active');
                break;
            case 'focusSearch':
                const searchInput = document.querySelector('input[type="search"], input[placeholder*="search" i]');
                if (searchInput) searchInput.focus();
                break;
            case 'newTask':
                // Implement based on page
                break;
            case 'toggleTimer':
                // Click the timer button if present
                const timerBtn = document.querySelector('[onclick*="startTimer"], [onclick*="stopTimer"]');
                if (timerBtn) timerBtn.click();
                break;
            case 'openNotifications':
                const notifBtn = document.getElementById('notificationsBtn');
                if (notifBtn) notifBtn.click();
                break;
        }
    };

    // Get current path (admin, employee, manager)
    function getCurrentPath() {
        const path = window.location.pathname;
        if (path.includes('/admin/')) return '../admin';
        if (path.includes('/employee/')) return '../employee';
        if (path.includes('/manager/')) return '../manager';
        return '.';
    }

    // Close help modal
    window.closeHelpModal = function() {
        document.getElementById('helpModal').classList.remove('active');
    };

    // Initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
