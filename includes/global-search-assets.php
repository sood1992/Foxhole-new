<!-- Global Search Assets -->
<?php include __DIR__ . '/global-search.php'; ?>
<script src="../assets/js/global-search.js"></script>

<!-- Search Trigger Button (Add to topbar) -->
<button onclick="document.getElementById('globalSearchModal').style.display='flex'; document.getElementById('globalSearchInput').focus();" class="search-trigger-btn" title="Search (Cmd/Ctrl+K)">
    <span>🔍</span>
    <span class="search-trigger-text">Search</span>
    <kbd class="search-trigger-kbd">⌘K</kbd>
</button>

<style>
.search-trigger-btn {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-4);
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: var(--font-sm);
}

.search-trigger-btn:hover {
    background: var(--bg-tertiary);
    border-color: var(--primary);
    color: var(--text-primary);
}

.search-trigger-text {
    color: var(--text-tertiary);
}

.search-trigger-kbd {
    padding: 2px 6px;
    background: var(--bg-tertiary);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: var(--font-xs);
    font-family: monospace;
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .search-trigger-text,
    .search-trigger-kbd {
        display: none;
    }
}
</style>
