/**
 * RT/RW Net Application JavaScript
 * Common functions and utilities
 */

// Global variables
let currentPage = 1;
let itemsPerPage = 10;
let searchTimeout;

// Initialize application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize modals
    initializeModals();
    
    // Initialize form validation
    initializeFormValidation();
    
    // Initialize search functionality
    initializeSearch();
    
    // Initialize auto-refresh
    initializeAutoRefresh();
    
    // Initialize number formatting
    initializeNumberFormatting();
    
    // Initialize date pickers
    initializeDatePickers();
    
    console.log('RT/RW Net Application initialized successfully');
}

// Tooltip initialization
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Modal initialization
function initializeModals() {
    // Auto-focus first input in modals
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            const firstInput = modal.querySelector('input, select, textarea');
            if (firstInput) {
                firstInput.focus();
            }
        });
    });
}

// Form validation
function initializeFormValidation() {
    // Bootstrap form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // Custom validation rules
    addCustomValidationRules();
}

// Custom validation rules
function addCustomValidationRules() {
    // Phone number validation
    document.querySelectorAll('input[type="tel"]').forEach(input => {
        input.addEventListener('input', function() {
            const phoneRegex = /^[0-9+\-\s()]+$/;
            if (this.value && !phoneRegex.test(this.value)) {
                this.setCustomValidity('Format nomor telepon tidak valid');
            } else {
                this.setCustomValidity('');
            }
        });
    });
    
    // Email validation
    document.querySelectorAll('input[type="email"]').forEach(input => {
        input.addEventListener('input', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value && !emailRegex.test(this.value)) {
                this.setCustomValidity('Format email tidak valid');
            } else {
                this.setCustomValidity('');
            }
        });
    });
    
    // IP address validation
    document.querySelectorAll('input[data-type="ip"]').forEach(input => {
        input.addEventListener('input', function() {
            const ipRegex = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
            if (this.value && !ipRegex.test(this.value)) {
                this.setCustomValidity('Format IP address tidak valid');
            } else {
                this.setCustomValidity('');
            }
        });
    });
}

// Search functionality
function initializeSearch() {
    const searchInputs = document.querySelectorAll('.search-input');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch(this.value, this.dataset.target);
            }, 500);
        });
    });
}

// Perform search
function performSearch(query, target) {
    const targetElement = document.querySelector(target);
    if (!targetElement) return;
    
    const rows = targetElement.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const match = text.includes(query.toLowerCase());
        row.style.display = match ? '' : 'none';
    });
    
    updateSearchResults(rows, query);
}

// Update search results
function updateSearchResults(rows, query) {
    const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
    const resultCount = document.querySelector('.search-results');
    if (resultCount) {
        resultCount.textContent = `Menampilkan ${visibleRows.length} dari ${rows.length} data`;
    }
}

// Auto-refresh functionality
function initializeAutoRefresh() {
    const autoRefreshElements = document.querySelectorAll('[data-auto-refresh]');
    autoRefreshElements.forEach(element => {
        const interval = parseInt(element.dataset.autoRefresh) * 1000;
        if (interval > 0) {
            setInterval(() => {
                refreshElement(element);
            }, interval);
        }
    });
}

// Refresh element content
function refreshElement(element) {
    const url = element.dataset.refreshUrl || window.location.href;
    const target = element.dataset.refreshTarget || element;
    
    fetch(url)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.querySelector(target.tagName + (target.className ? '.' + target.className.replace(/\s+/g, '.') : ''));
            
            if (newContent) {
                target.innerHTML = newContent.innerHTML;
                showNotification('Data berhasil diperbarui', 'success');
            }
        })
        .catch(error => {
            console.error('Error refreshing element:', error);
        });
}

// Number formatting
function initializeNumberFormatting() {
    // Format currency inputs
    document.querySelectorAll('input[data-type="currency"]').forEach(input => {
        input.addEventListener('input', function() {
            this.value = formatCurrency(this.value);
        });
    });
    
    // Format number inputs
    document.querySelectorAll('input[data-type="number"]').forEach(input => {
        input.addEventListener('input', function() {
            this.value = formatNumber(this.value);
        });
    });
}

// Format currency
function formatCurrency(value) {
    // Remove non-numeric characters except decimal point
    const numericValue = value.replace(/[^0-9.]/g, '');
    
    // Format as currency
    if (numericValue) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(parseFloat(numericValue));
    }
    
    return '';
}

// Format number
function formatNumber(value) {
    const numericValue = value.replace(/[^0-9]/g, '');
    if (numericValue) {
        return new Intl.NumberFormat('id-ID').format(parseInt(numericValue));
    }
    return '';
}

// Date picker initialization
function initializeDatePickers() {
    // Set default date format
    document.querySelectorAll('input[type="date"]').forEach(input => {
        if (!input.value) {
            input.value = new Date().toISOString().split('T')[0];
        }
    });
}

// Utility functions
function showNotification(message, type = 'info', duration = 3000) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto-remove after duration
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, duration);
}

function showLoading(element) {
    const originalContent = element.innerHTML;
    element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    element.disabled = true;
    
    return function hideLoading() {
        element.innerHTML = originalContent;
        element.disabled = false;
    };
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function formatDuration(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) {
        return `${hours}h ${minutes}m ${secs}s`;
    } else if (minutes > 0) {
        return `${minutes}m ${secs}s`;
    } else {
        return `${secs}s`;
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Teks berhasil disalin ke clipboard', 'success');
    }).catch(err => {
        console.error('Error copying text: ', err);
        showNotification('Gagal menyalin teks', 'danger');
    });
}

function downloadCSV(data, filename) {
    const csv = convertToCSV(data);
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    
    window.URL.revokeObjectURL(url);
}

function convertToCSV(data) {
    if (!data || data.length === 0) return '';
    
    const headers = Object.keys(data[0]);
    const csvHeaders = headers.join(',');
    
    const csvRows = data.map(row => {
        return headers.map(header => {
            const value = row[header];
            return typeof value === 'string' && value.includes(',') ? `"${value}"` : value;
        }).join(',');
    });
    
    return [csvHeaders, ...csvRows].join('\n');
}

// AJAX helpers
function makeRequest(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    const mergedOptions = { ...defaultOptions, ...options };
    
    return fetch(url, mergedOptions)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('Request failed:', error);
            showNotification('Terjadi kesalahan saat memuat data', 'danger');
            throw error;
        });
}

function submitForm(form, callback) {
    const formData = new FormData(form);
    const hideLoading = showLoading(form.querySelector('button[type="submit"]'));
    
    fetch(form.action, {
        method: form.method,
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showNotification(data.message || 'Operasi berhasil', 'success');
            if (callback) callback(data);
        } else {
            showNotification(data.message || 'Operasi gagal', 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Form submission failed:', error);
        showNotification('Terjadi kesalahan saat menyimpan data', 'danger');
    });
}

// Page-specific functions
function refreshDashboard() {
    const dashboardElements = document.querySelectorAll('.dashboard-widget');
    dashboardElements.forEach(element => {
        refreshElement(element);
    });
}

function updateOnlineStatus() {
    makeRequest('monitoring.php?action=get_online_status')
        .then(data => {
            const statusElements = document.querySelectorAll('.online-status');
            statusElements.forEach(element => {
                const customerId = element.dataset.customerId;
                const status = data.customers[customerId];
                element.className = `badge ${status ? 'status-online' : 'status-offline'}`;
                element.textContent = status ? 'Online' : 'Offline';
            });
        })
        .catch(error => {
            console.error('Failed to update online status:', error);
        });
}

function exportData(type, filters = {}) {
    const params = new URLSearchParams(filters);
    params.append('export', type);
    
    window.location.href = `reports.php?${params.toString()}`;
}

// Initialize page-specific functionality based on current page
function initializePageSpecific() {
    const currentPath = window.location.pathname;
    
    if (currentPath.includes('index.php') || currentPath.endsWith('/')) {
        // Dashboard specific
        setInterval(refreshDashboard, 300000); // Refresh every 5 minutes
    }
    
    if (currentPath.includes('monitoring.php')) {
        // Monitoring specific
        setInterval(updateOnlineStatus, 30000); // Update every 30 seconds
    }
}

// Call page-specific initialization
initializePageSpecific();

// Export functions for global use
window.RTRWNet = {
    showNotification,
    showLoading,
    confirmAction,
    formatBytes,
    formatDuration,
    copyToClipboard,
    downloadCSV,
    makeRequest,
    submitForm,
    refreshDashboard,
    updateOnlineStatus,
    exportData
};