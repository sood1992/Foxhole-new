<!-- Global Search Modal -->
<div id="globalSearchModal" class="search-modal" style="display: none;">
    <div class="search-modal-backdrop"></div>
    <div class="search-modal-content">
        <div class="search-input-wrapper">
            <span class="search-icon">🔍</span>
            <input
                type="text"
                id="globalSearchInput"
                placeholder="Search projects, tasks, people..."
                class="search-input"
                autocomplete="off"
            />
            <div class="search-shortcut">
                <kbd>ESC</kbd> to close
            </div>
        </div>

        <div id="searchResults" class="search-results" style="display: none;">
            <!-- Projects Section -->
            <div id="projectsSection" class="search-section" style="display: none;">
                <div class="search-section-title">📁 Projects</div>
                <div id="projectsList" class="search-items"></div>
            </div>

            <!-- Tasks Section -->
            <div id="tasksSection" class="search-section" style="display: none;">
                <div class="search-section-title">✓ Tasks</div>
                <div id="tasksList" class="search-items"></div>
            </div>

            <!-- Users Section -->
            <div id="usersSection" class="search-section" style="display: none;">
                <div class="search-section-title">👥 People</div>
                <div id="usersList" class="search-items"></div>
            </div>

            <!-- No Results -->
            <div id="noResults" class="search-no-results" style="display: none;">
                <p>No results found</p>
            </div>
        </div>

        <div id="searchPlaceholder" class="search-placeholder">
            <p>Start typing to search...</p>
            <div class="search-tips">
                <div class="search-tip">
                    <span class="search-tip-icon">💡</span>
                    <span>Search across projects, tasks, and team members</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.search-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 120px;
}

.search-modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
}

.search-modal-content {
    position: relative;
    width: 90%;
    max-width: 600px;
    background: var(--bg-secondary);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-2xl);
    border: 1px solid var(--border);
    overflow: hidden;
    max-height: 600px;
    display: flex;
    flex-direction: column;
}

.search-input-wrapper {
    display: flex;
    align-items: center;
    padding: var(--space-6);
    border-bottom: 1px solid var(--border);
    gap: var(--space-4);
}

.search-icon {
    font-size: 20px;
}

.search-input {
    flex: 1;
    border: none;
    background: transparent;
    font-size: var(--font-lg);
    color: var(--text-primary);
    outline: none;
    font-weight: 500;
}

.search-input::placeholder {
    color: var(--text-tertiary);
}

.search-shortcut {
    display: flex;
    gap: var(--space-2);
}

.search-shortcut kbd {
    padding: 4px 8px;
    background: var(--bg-tertiary);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: var(--font-xs);
    font-family: monospace;
    color: var(--text-secondary);
}

.search-results {
    flex: 1;
    overflow-y: auto;
    max-height: 450px;
}

.search-section {
    padding: var(--space-4) 0;
    border-bottom: 1px solid var(--border-light);
}

.search-section:last-child {
    border-bottom: none;
}

.search-section-title {
    padding: var(--space-2) var(--space-6);
    font-size: var(--font-xs);
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.search-items {
    display: flex;
    flex-direction: column;
}

.search-item {
    padding: var(--space-4) var(--space-6);
    cursor: pointer;
    transition: all 0.15s ease;
    border-left: 3px solid transparent;
    text-decoration: none;
    color: inherit;
    display: block;
}

.search-item:hover {
    background: var(--bg-tertiary);
    border-left-color: var(--primary);
}

.search-item-title {
    font-size: var(--font-base);
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: var(--space-1);
}

.search-item-meta {
    font-size: var(--font-xs);
    color: var(--text-secondary);
    display: flex;
    gap: var(--space-3);
    align-items: center;
}

.search-item-badge {
    padding: 2px 8px;
    border-radius: var(--radius-sm);
    font-size: var(--font-xs);
    font-weight: 600;
    background: var(--bg-tertiary);
    color: var(--text-secondary);
}

.search-placeholder,
.search-no-results {
    padding: var(--space-10);
    text-align: center;
    color: var(--text-secondary);
}

.search-tips {
    margin-top: var(--space-6);
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
}

.search-tip {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-4);
    background: var(--bg-tertiary);
    border-radius: var(--radius-md);
    text-align: left;
    font-size: var(--font-sm);
}

.search-tip-icon {
    font-size: 20px;
}
</style>
