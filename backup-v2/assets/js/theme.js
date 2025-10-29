/**
 * Theme Toggle System
 * Handles light/dark mode switching
 */

(function() {
    'use strict';

    // Get saved theme or default to light
    function getTheme() {
        return localStorage.getItem('theme') || 'light';
    }

    // Set theme
    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        updateToggleButton(theme);
    }

    // Toggle theme
    function toggleTheme() {
        const currentTheme = getTheme();
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        setTheme(newTheme);
    }

    // Update toggle button appearance
    function updateToggleButton(theme) {
        const slider = document.querySelector('.theme-toggle-slider');
        if (slider) {
            slider.textContent = theme === 'dark' ? '🌙' : '☀️';
        }
    }

    // Initialize theme on page load
    function initTheme() {
        const savedTheme = getTheme();
        setTheme(savedTheme);
    }

    // Create theme toggle button
    function createThemeToggle() {
        const toggle = document.createElement('div');
        toggle.className = 'theme-toggle';
        toggle.setAttribute('role', 'button');
        toggle.setAttribute('aria-label', 'Toggle theme');
        toggle.onclick = toggleTheme;

        const slider = document.createElement('div');
        slider.className = 'theme-toggle-slider';
        slider.textContent = getTheme() === 'dark' ? '🌙' : '☀️';

        toggle.appendChild(slider);
        return toggle;
    }

    // Add theme toggle to topbar
    function addThemeToggleToTopbar() {
        const topbarActions = document.querySelector('.topbar-actions');
        if (topbarActions) {
            const toggle = createThemeToggle();
            topbarActions.insertBefore(toggle, topbarActions.firstChild);
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initTheme();
            addThemeToggleToTopbar();
        });
    } else {
        initTheme();
        addThemeToggleToTopbar();
    }

    // Expose toggle function globally
    window.toggleTheme = toggleTheme;
})();
