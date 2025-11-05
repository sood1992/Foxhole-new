/**
 * SYNTO INTERACTIONS - JAVASCRIPT UTILITIES
 * Enhanced interactions for Synto Dashboard Template
 * Version: 3.1.0
 */

// ============================================
// SIDEBAR TOGGLE & INTERACTIONS
// ============================================
const SyntoSidebar = {
  init() {
    this.setupToggle();
    this.setupMenuHighlighting();
    this.setupHoverEffects();
  },

  setupToggle() {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');

    if (toggleBtn) {
      toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        document.body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
      });
    }

    // Restore sidebar state
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
      sidebar?.classList.add('collapsed');
      document.body.classList.add('sidebar-collapsed');
    }
  },

  setupMenuHighlighting() {
    const currentPath = window.location.pathname;
    const menuItems = document.querySelectorAll('.menu-item');

    menuItems.forEach(item => {
      const href = item.getAttribute('href');
      if (href && currentPath.includes(href.split('?')[0])) {
        item.classList.add('active');
      }
    });
  },

  setupHoverEffects() {
    const mainMenuItems = document.querySelectorAll('.main-menu-item');

    mainMenuItems.forEach(item => {
      item.addEventListener('mouseenter', function() {
        this.style.transform = 'translateX(2px)';
      });

      item.addEventListener('mouseleave', function() {
        if (!this.classList.contains('active')) {
          this.style.transform = 'translateX(0)';
        }
      });
    });
  }
};

// ============================================
// DROPDOWN MENUS
// ============================================
const SyntoDropdown = {
  init() {
    document.querySelectorAll('[data-dropdown]').forEach(trigger => {
      trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const menu = trigger.nextElementSibling;

        // Close other dropdowns
        document.querySelectorAll('[data-dropdown-menu]').forEach(m => {
          if (m !== menu) m.classList.add('hidden');
        });

        menu.classList.toggle('hidden');
      });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', () => {
      document.querySelectorAll('[data-dropdown-menu]').forEach(menu => {
        menu.classList.add('hidden');
      });
    });
  }
};

// ============================================
// PROGRESS BAR ANIMATIONS
// ============================================
const SyntoProgressBars = {
  init() {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const bar = entry.target.querySelector('.progress-bar-fill');
          if (bar) {
            const width = bar.dataset.progress || bar.getAttribute('data-width');
            setTimeout(() => {
              bar.style.width = width + '%';
            }, 100);
            observer.unobserve(entry.target);
          }
        }
      });
    }, { threshold: 0.1 });

    document.querySelectorAll('.progress-bar-container').forEach(container => {
      observer.observe(container.parentElement);
    });
  }
};

// ============================================
// CARD HOVER EFFECTS
// ============================================
const SyntoCards = {
  init() {
    document.querySelectorAll('.stats-card, .card').forEach(card => {
      card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-4px)';
      });

      card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
      });
    });
  }
};

// ============================================
// TABLE ENHANCEMENTS
// ============================================
const SyntoTable = {
  init() {
    this.setupSorting();
    this.setupRowHover();
    this.setupSearch();
  },

  setupSorting() {
    document.querySelectorAll('th[data-sortable]').forEach(header => {
      header.style.cursor = 'pointer';
      header.innerHTML += ' <i class="ri-arrow-up-down-line" style="font-size: 12px; opacity: 0.5;"></i>';

      header.addEventListener('click', function() {
        const table = this.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const columnIndex = Array.from(this.parentNode.children).indexOf(this);
        const isAscending = this.classList.contains('sort-asc');

        // Reset all headers
        table.querySelectorAll('th').forEach(th => {
          th.classList.remove('sort-asc', 'sort-desc');
        });

        // Sort rows
        rows.sort((a, b) => {
          const aValue = a.children[columnIndex].textContent.trim();
          const bValue = b.children[columnIndex].textContent.trim();

          if (isAscending) {
            return bValue.localeCompare(aValue, undefined, { numeric: true });
          } else {
            return aValue.localeCompare(bValue, undefined, { numeric: true });
          }
        });

        // Update DOM
        rows.forEach(row => tbody.appendChild(row));
        this.classList.toggle('sort-asc', !isAscending);
        this.classList.toggle('sort-desc', isAscending);
      });
    });
  },

  setupRowHover() {
    document.querySelectorAll('.data-table tbody tr').forEach(row => {
      row.addEventListener('mouseenter', function() {
        this.style.transform = 'scale(1.01)';
      });

      row.addEventListener('mouseleave', function() {
        this.style.transform = 'scale(1)';
      });
    });
  },

  setupSearch() {
    document.querySelectorAll('[data-table-search]').forEach(searchInput => {
      const tableId = searchInput.dataset.tableSearch;
      const table = document.getElementById(tableId);

      if (table) {
        searchInput.addEventListener('input', function() {
          const searchTerm = this.value.toLowerCase();
          const rows = table.querySelectorAll('tbody tr');

          rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
          });
        });
      }
    });
  }
};

// ============================================
// TOAST NOTIFICATIONS
// ============================================
const SyntoToast = {
  show(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `synto-toast synto-toast-${type}`;
    toast.innerHTML = `
      <div class="synto-toast-icon">
        <i class="ri-${this.getIcon(type)}-line"></i>
      </div>
      <div class="synto-toast-content">
        <p>${message}</p>
      </div>
      <button class="synto-toast-close" onclick="this.parentElement.remove()">
        <i class="ri-close-line"></i>
      </button>
    `;

    // Create container if doesn't exist
    let container = document.getElementById('synto-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'synto-toast-container';
      container.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 12px;
      `;
      document.body.appendChild(container);
    }

    container.appendChild(toast);

    // Auto remove
    setTimeout(() => {
      toast.style.animation = 'slideOutRight 0.3s ease-out';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },

  getIcon(type) {
    const icons = {
      success: 'checkbox-circle',
      error: 'error-warning',
      warning: 'alert',
      info: 'information'
    };
    return icons[type] || icons.info;
  }
};

// Add toast styles
const toastStyles = document.createElement('style');
toastStyles.textContent = `
  .synto-toast {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    min-width: 300px;
    max-width: 400px;
    animation: slideInRight 0.3s ease-out;
  }

  .synto-toast-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
  }

  .synto-toast-success .synto-toast-icon {
    background: #ECFDF5;
    color: #10B981;
  }

  .synto-toast-error .synto-toast-icon {
    background: #FEF2F2;
    color: #EF4444;
  }

  .synto-toast-warning .synto-toast-icon {
    background: #FFFBEB;
    color: #F59E0B;
  }

  .synto-toast-info .synto-toast-icon {
    background: #EFF6FF;
    color: #3B82F6;
  }

  .synto-toast-content {
    flex: 1;
  }

  .synto-toast-content p {
    margin: 0;
    font-size: 14px;
    color: #111827;
  }

  .synto-toast-close {
    width: 24px;
    height: 24px;
    border: none;
    background: transparent;
    cursor: pointer;
    color: #6B7280;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .synto-toast-close:hover {
    color: #111827;
  }

  @keyframes slideInRight {
    from {
      transform: translateX(100%);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }

  @keyframes slideOutRight {
    from {
      transform: translateX(0);
      opacity: 1;
    }
    to {
      transform: translateX(100%);
      opacity: 0;
    }
  }
`;
document.head.appendChild(toastStyles);

// ============================================
// MODAL DIALOGS
// ============================================
const SyntoModal = {
  open(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
      setTimeout(() => modal.classList.add('show'), 10);
    }
  },

  close(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('show');
      setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = '';
      }, 300);
    }
  }
};

// ============================================
// FORM VALIDATION
// ============================================
const SyntoForm = {
  validate(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;

    let isValid = true;
    const inputs = form.querySelectorAll('[required]');

    inputs.forEach(input => {
      if (!input.value.trim()) {
        this.showError(input, 'This field is required');
        isValid = false;
      } else {
        this.clearError(input);
      }
    });

    return isValid;
  },

  showError(input, message) {
    this.clearError(input);
    input.classList.add('error');
    input.style.borderColor = '#EF4444';

    const error = document.createElement('div');
    error.className = 'form-error';
    error.style.cssText = 'color: #EF4444; font-size: 12px; margin-top: 4px;';
    error.textContent = message;

    input.parentNode.appendChild(error);
  },

  clearError(input) {
    input.classList.remove('error');
    input.style.borderColor = '';

    const error = input.parentNode.querySelector('.form-error');
    if (error) error.remove();
  }
};

// ============================================
// THEME CONFIGURATION
// ============================================
const SyntoTheme = {
  config: {
    theme: localStorage.getItem('synto-theme') || 'light',
    sidebarStyle: localStorage.getItem('synto-sidebar-style') || 'vertical',
    navbarStyle: localStorage.getItem('synto-navbar-style') || 'sticky',
    layoutWidth: localStorage.getItem('synto-layout-width') || 'fluid',
    direction: localStorage.getItem('synto-direction') || 'ltr'
  },

  apply() {
    document.documentElement.classList.toggle('dark', this.config.theme === 'dark');
    document.documentElement.dir = this.config.direction;
    document.body.dataset.sidebarStyle = this.config.sidebarStyle;
    document.body.dataset.navbarStyle = this.config.navbarStyle;
    document.body.dataset.layoutWidth = this.config.layoutWidth;
  },

  setTheme(theme) {
    this.config.theme = theme;
    localStorage.setItem('synto-theme', theme);
    this.apply();
  },

  toggle() {
    this.setTheme(this.config.theme === 'light' ? 'dark' : 'light');
  }
};

// ============================================
// CHART HELPERS (for ApexCharts integration)
// ============================================
const SyntoCharts = {
  // Circle/Radial Chart
  circleChart(elementId, percentage, color = '#4F46E5') {
    if (typeof ApexCharts === 'undefined') return;

    const options = {
      chart: {
        type: 'radialBar',
        height: 120,
        sparkline: { enabled: true }
      },
      series: [percentage],
      colors: [color],
      plotOptions: {
        radialBar: {
          hollow: { size: '60%' },
          track: { background: '#E5E7EB' },
          dataLabels: {
            show: true,
            value: {
              show: true,
              fontSize: '16px',
              fontWeight: 600,
              color: '#111827',
              formatter: (val) => val + '%'
            }
          }
        }
      }
    };

    const chart = new ApexCharts(document.querySelector(elementId), options);
    chart.render();
  },

  // Sparkline Area Chart
  sparklineChart(elementId, data, color = '#10B981') {
    if (typeof ApexCharts === 'undefined') return;

    const options = {
      chart: {
        type: 'area',
        height: 80,
        sparkline: { enabled: true },
        toolbar: { show: false }
      },
      stroke: {
        curve: 'smooth',
        width: 2
      },
      fill: {
        type: 'gradient',
        gradient: {
          shadeIntensity: 1,
          opacityFrom: 0.4,
          opacityTo: 0.1
        }
      },
      series: [{ name: 'Value', data: data }],
      colors: [color],
      tooltip: { enabled: false }
    };

    const chart = new ApexCharts(document.querySelector(elementId), options);
    chart.render();
  }
};

// ============================================
// COPY TO CLIPBOARD
// ============================================
function syntoClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    SyntoToast.show('Copied to clipboard!', 'success', 2000);
  }).catch(() => {
    SyntoToast.show('Failed to copy', 'error', 2000);
  });
}

// ============================================
// BREADCRUMB SYSTEM - PERMANENT PAGE IDENTIFIER
// ============================================
const SyntoBreadcrumb = {
  init() {
    this.updateBreadcrumb();
  },

  updateBreadcrumb() {
    const breadcrumbContainer = document.getElementById('breadcrumb-path');
    if (!breadcrumbContainer) return;

    const currentPath = window.location.pathname;
    const currentFile = currentPath.split('/').pop().split('.')[0];
    const currentDir = currentPath.includes('/admin/') ? 'admin' :
                       currentPath.includes('/manager/') ? 'manager' :
                       currentPath.includes('/employee/') ? 'employee' : '';

    // Page mapping with icons
    const pageMap = {
      // Admin pages
      'index': { section: 'Dashboard', page: 'Overview', icon: 'ri-dashboard-line' },
      'projects': { section: 'Projects', page: 'All Projects', icon: 'ri-folder-line' },
      'team': { section: 'Team', page: 'Team Members', icon: 'ri-team-line' },
      'users': { section: 'Team', page: 'Manage Users', icon: 'ri-user-settings-line' },
      'tasks': { section: 'Tasks', page: 'All Tasks', icon: 'ri-task-line' },
      'reports': { section: 'Reports', page: 'All Reports', icon: 'ri-file-chart-line' },
      'analytics': { section: 'Analytics', page: 'Advanced Analytics', icon: 'ri-line-chart-line' },
      'calendar': { section: 'Calendar', page: 'Full Calendar', icon: 'ri-calendar-line' },
      'chat': { section: 'Chat', page: 'Messages', icon: 'ri-chat-3-line' },
      'email-config': { section: 'Settings', page: 'Email Configuration', icon: 'ri-mail-settings-line' },
      'budgets': { section: 'Finance', page: 'Budgets', icon: 'ri-funds-line' },
      'profit-loss': { section: 'Finance', page: 'Profit & Loss', icon: 'ri-line-chart-line' },
      'gamification': { section: 'Gamification', page: 'Leaderboard', icon: 'ri-trophy-line' },
      'bulk-operations': { section: 'Operations', page: 'Bulk Operations', icon: 'ri-stack-line' },
      'profile': { section: 'Account', page: 'My Profile', icon: 'ri-user-line' },

      // Employee pages
      'my-tasks': { section: 'Tasks', page: 'My Tasks', icon: 'ri-task-line' },
      'create-task': { section: 'Tasks', page: 'Create Task', icon: 'ri-add-line' },
      'my-projects': { section: 'Projects', page: 'My Projects', icon: 'ri-folder-line' },
      'time-logs': { section: 'Time Tracking', page: 'Time Logs', icon: 'ri-time-line' },
      'my-stats': { section: 'Statistics', page: 'My Statistics', icon: 'ri-bar-chart-line' },

      // Productivity Tools
      'daily-planning': { section: 'Productivity', page: 'Daily Planning', icon: 'ri-calendar-check-line' },
      'morning-ritual': { section: 'Productivity', page: 'Morning Ritual', icon: 'ri-sun-line' },
      'eisenhower-matrix': { section: 'Productivity', page: 'Eisenhower Matrix', icon: 'ri-layout-grid-line' },
      'pomodoro': { section: 'Productivity', page: 'Pomodoro Timer', icon: 'ri-timer-line' },
      'time-boxing': { section: 'Productivity', page: 'Time Boxing', icon: 'ri-calendar-event-line' },
      'focus-mode': { section: 'Productivity', page: 'Focus Mode', icon: 'ri-focus-3-line' },
      'goals': { section: 'Productivity', page: 'Goals Tracking', icon: 'ri-flag-line' },
      'weekly-review': { section: 'Productivity', page: 'Weekly Review', icon: 'ri-article-line' },

      // Manager pages
      'create-project': { section: 'Projects', page: 'Create Project', icon: 'ri-folder-add-line' },
      'manage-users': { section: 'Team', page: 'Manage Users', icon: 'ri-user-add-line' },
    };

    const pageInfo = pageMap[currentFile] || { section: 'Foxhole', page: currentFile.replace(/-/g, ' '), icon: 'ri-file-line' };

    // Build breadcrumb HTML
    const roleLabel = currentDir.charAt(0).toUpperCase() + currentDir.slice(1);

    let breadcrumbHTML = `
      <span style="color: var(--synto-text-primary); font-weight: 500;">${roleLabel}</span>
      <i class="ri-arrow-right-s-line" style="color: var(--synto-text-tertiary); font-size: 18px;"></i>
      <i class="${pageInfo.icon}" style="color: var(--synto-primary); font-size: 16px;"></i>
      <span style="color: var(--synto-text-primary); font-weight: 500;">${pageInfo.section}</span>
      <i class="ri-arrow-right-s-line" style="color: var(--synto-text-tertiary); font-size: 18px;"></i>
      <span style="color: var(--synto-text-secondary);">${pageInfo.page}</span>
    `;

    breadcrumbContainer.innerHTML = breadcrumbHTML;
  }
};

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', () => {
  SyntoSidebar.init();
  SyntoDropdown.init();
  SyntoProgressBars.init();
  SyntoCards.init();
  SyntoTable.init();
  SyntoTheme.apply();
  SyntoBreadcrumb.init(); // Initialize breadcrumb system
});

// Export for global use
window.Synto = {
  Sidebar: SyntoSidebar,
  Dropdown: SyntoDropdown,
  ProgressBars: SyntoProgressBars,
  Cards: SyntoCards,
  Table: SyntoTable,
  Toast: SyntoToast,
  Modal: SyntoModal,
  Form: SyntoForm,
  Theme: SyntoTheme,
  Charts: SyntoCharts,
  Breadcrumb: SyntoBreadcrumb,
  clipboard: syntoClipboard
};
