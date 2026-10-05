/**
 * Notification System
 * Handles real-time notifications for all user roles
 */

class NotificationManager {
    constructor() {
        this.baseUrl = '/LechGo_Final/public/notifications';
        this.updateInterval = 30000; // 30 seconds
        this.intervalId = null;
        
        this.init();
    }
    
    init() {
        // Initialize elements
        this.bell = document.getElementById('notificationBell');
        this.badge = document.getElementById('notificationBadge');
        this.dropdown = document.getElementById('notificationDropdown');
        this.list = document.getElementById('notificationList');
        this.markAllBtn = document.getElementById('markAllRead');
        
        if (!this.bell) return;
        
        // Event listeners
        this.bell.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleDropdown();
        });
        
        this.markAllBtn.addEventListener('click', () => {
            this.markAllAsRead();
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.dropdown.contains(e.target) && !this.bell.contains(e.target)) {
                this.closeDropdown();
            }
        });
        
        // Initial load
        this.updateCount();
        
        // Start polling
        this.startPolling();
    }
    
    startPolling() {
        this.intervalId = setInterval(() => {
            this.updateCount();
        }, this.updateInterval);
    }
    
    stopPolling() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }
    
    async updateCount() {
        try {
            const response = await fetch(`${this.baseUrl}?action=count`);
            const data = await response.json();
            
            if (data.success) {
                this.updateBadge(data.count);
            }
        } catch (error) {
            console.error('Error fetching notification count:', error);
        }
    }
    
    updateBadge(count) {
        if (count > 0) {
            this.badge.textContent = count > 99 ? '99+' : count;
            this.badge.style.display = 'flex';
            this.bell.classList.add('has-notifications');
        } else {
            this.badge.style.display = 'none';
            this.bell.classList.remove('has-notifications');
        }
    }
    
    async toggleDropdown() {
        if (this.dropdown.classList.contains('active')) {
            this.closeDropdown();
        } else {
            await this.openDropdown();
        }
    }
    
    async openDropdown() {
        this.dropdown.classList.add('active');
        await this.loadNotifications();
    }
    
    closeDropdown() {
        this.dropdown.classList.remove('active');
    }
    
    async loadNotifications() {
        this.list.innerHTML = '<div class="notification-loading">Loading...</div>';
        
        try {
            const response = await fetch(`${this.baseUrl}?action=list`);
            const data = await response.json();
            
            if (data.success) {
                this.renderNotifications(data.notifications);
            } else {
                this.list.innerHTML = '<div class="notification-empty">Failed to load notifications</div>';
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            this.list.innerHTML = '<div class="notification-empty">Error loading notifications</div>';
        }
    }
    
    renderNotifications(notifications) {
        if (notifications.length === 0) {
            this.list.innerHTML = '<div class="notification-empty">No notifications</div>';
            return;
        }
        
        this.list.innerHTML = notifications.map(notif => this.createNotificationHTML(notif)).join('');
        
        // Add click handlers
        this.list.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                const id = item.dataset.id;
                const link = item.dataset.link;
                const isRead = item.dataset.read === '1';
                
                if (!isRead) {
                    this.markAsRead(id);
                }
                
                if (link) {
                    window.location.href = link;
                }
            });
            
            // Delete button
            const deleteBtn = item.querySelector('.notification-delete');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.deleteNotification(item.dataset.id);
                });
            }
        });
    }
    
    createNotificationHTML(notif) {
        const isRead = notif.is_read === '1' || notif.is_read === 1;
        const timeAgo = this.getTimeAgo(notif.created_at);
        
        return `
            <div class="notification-item ${isRead ? 'read' : 'unread'}" 
                 data-id="${notif.id}" 
                 data-link="${notif.link || ''}"
                 data-read="${notif.is_read}">
                <div class="notification-content">
                    <div class="notification-title">${this.escapeHtml(notif.title)}</div>
                    <div class="notification-message">${this.escapeHtml(notif.message)}</div>
                    <div class="notification-time">${timeAgo}</div>
                </div>
                <button class="notification-delete" aria-label="Delete notification">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        `;
    }
    
    async markAsRead(id) {
        try {
            const response = await fetch(`${this.baseUrl}?action=mark-read`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            
            const data = await response.json();
            if (data.success) {
                this.updateCount();
                const item = this.list.querySelector(`[data-id="${id}"]`);
                if (item) {
                    item.classList.remove('unread');
                    item.classList.add('read');
                }
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }
    
    async markAllAsRead() {
        try {
            const response = await fetch(`${this.baseUrl}?action=mark-all-read`, {
                method: 'POST'
            });
            
            const data = await response.json();
            if (data.success) {
                this.updateCount();
                this.list.querySelectorAll('.notification-item').forEach(item => {
                    item.classList.remove('unread');
                    item.classList.add('read');
                });
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    }
    
    async deleteNotification(id) {
        try {
            const response = await fetch(`${this.baseUrl}?action=delete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            
            const data = await response.json();
            if (data.success) {
                const item = this.list.querySelector(`[data-id="${id}"]`);
                if (item) {
                    item.remove();
                }
                this.updateCount();
                
                // Check if list is empty
                if (this.list.children.length === 0) {
                    this.list.innerHTML = '<div class="notification-empty">No notifications</div>';
                }
            }
        } catch (error) {
            console.error('Error deleting notification:', error);
        }
    }
    
    getTimeAgo(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diff = Math.floor((now - time) / 1000); // seconds
        
        if (diff < 60) return 'Just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
        
        return time.toLocaleDateString();
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.notificationManager = new NotificationManager();
    });
} else {
    window.notificationManager = new NotificationManager();
}
