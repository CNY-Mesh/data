/**
 * AutoRefresh - Reusable AJAX auto-refresh functionality
 * Supports automatic content refreshing with user controls
 */
class AutoRefresh {
    constructor(refreshUrl, options = {}) {
        this.refreshUrl = refreshUrl;
        this.options = {
            interval: 30000,
            contentContainerId: 'refreshableContent',
            refreshButtonId: 'refreshNow',
            toggleButtonId: 'toggleAutoRefresh',
            countdownId: 'refreshCountdown',
            loadingSpinnerId: 'loadingSpinner',
            onRefresh: null,
            ...options
        };
        
        this.intervalId = null;
        this.countdownIntervalId = null;
        this.isEnabled = true;
        this.isRefreshing = false;
        this.remainingTime = this.options.interval / 1000;
        
        this.initializeElements();
        this.initializeControls();
        this.start();
    }
    
    initializeElements() {
        this.container = document.getElementById(this.options.contentContainerId);
        this.refreshButton = document.getElementById(this.options.refreshButtonId);
        this.toggleButton = document.getElementById(this.options.toggleButtonId);
        this.countdownElement = document.getElementById(this.options.countdownId);
        this.loadingSpinner = document.getElementById(this.options.loadingSpinnerId);
    }
    
    initializeControls() {
        if (this.refreshButton) {
            this.refreshButton.addEventListener('click', () => this.refresh());
        }
        
        if (this.toggleButton) {
            this.toggleButton.addEventListener('click', () => this.toggle());
        }
    }
    
    start() {
        if (this.intervalId) return;
        
        this.isEnabled = true;
        this.remainingTime = this.options.interval / 1000;
        
        // Start main refresh interval
        this.intervalId = setInterval(() => {
            if (this.isEnabled && !this.isRefreshing) {
                this.refresh();
            }
        }, this.options.interval);
        
        // Start countdown
        this.startCountdown();
        
        // Update toggle button
        this.updateToggleButton();
    }
    
    stop() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        
        if (this.countdownIntervalId) {
            clearInterval(this.countdownIntervalId);
            this.countdownIntervalId = null;
        }
        
        this.isEnabled = false;
        this.updateToggleButton();
    }
    
    startCountdown() {
        if (this.countdownIntervalId) {
            clearInterval(this.countdownIntervalId);
        }
        
        this.remainingTime = this.options.interval / 1000;
        
        this.countdownIntervalId = setInterval(() => {
            this.remainingTime--;
            
            if (this.countdownElement) {
                this.countdownElement.textContent = this.remainingTime;
            }
            
            if (this.remainingTime <= 0) {
                this.remainingTime = this.options.interval / 1000;
            }
        }, 1000);
    }
    
    toggle() {
        if (this.isEnabled) {
            this.stop();
        } else {
            this.start();
        }
    }
    
    updateToggleButton() {
        if (!this.toggleButton) return;
        
        if (this.isEnabled) {
            this.toggleButton.innerHTML = '<i class="fas fa-pause"></i>';
            this.toggleButton.className = 'btn btn-sm btn-outline-secondary';
            this.toggleButton.title = 'Pause auto-refresh';
        } else {
            this.toggleButton.innerHTML = '<i class="fas fa-play"></i>';
            this.toggleButton.className = 'btn btn-sm btn-outline-success';
            this.toggleButton.title = 'Resume auto-refresh';
        }
    }
    
    async refresh() {
        if (this.isRefreshing) return;
        
        this.isRefreshing = true;
        
        // Show loading indicators
        if (this.loadingSpinner) {
            this.loadingSpinner.classList.remove('d-none');
        }
        
        if (this.refreshButton) {
            this.refreshButton.disabled = true;
            const originalIcon = this.refreshButton.querySelector('i');
            if (originalIcon) {
                originalIcon.className = 'fas fa-sync-alt fa-spin';
            }
        }
        
        try {
            const response = await fetch(this.refreshUrl);
            const data = await response.json();
            
            if (data.content && this.container) {
                this.container.innerHTML = data.content;
                
                // Call custom refresh callback
                if (this.options.onRefresh && typeof this.options.onRefresh === 'function') {
                    this.options.onRefresh(data);
                }
            }
            
            // Reset countdown after successful refresh
            this.remainingTime = this.options.interval / 1000;
            
        } catch (error) {
            console.error('Auto-refresh failed:', error);
        } finally {
            this.isRefreshing = false;
            
            // Hide loading indicators
            if (this.loadingSpinner) {
                this.loadingSpinner.classList.add('d-none');
            }
            
            if (this.refreshButton) {
                this.refreshButton.disabled = false;
                const originalIcon = this.refreshButton.querySelector('i');
                if (originalIcon) {
                    originalIcon.className = 'fas fa-sync-alt';
                }
            }
        }
    }
}

// Legacy support - simpler constructor for backward compatibility
window.createAutoRefresh = function(containerId, refreshUrl, interval = 30000) {
    return new AutoRefresh(refreshUrl, {
        contentContainerId: containerId,
        interval: interval,
        refreshButtonId: 'manual-refresh',
        toggleButtonId: 'toggle-auto-refresh',
        countdownId: null,
        loadingSpinnerId: 'refresh-indicator'
    });
};
