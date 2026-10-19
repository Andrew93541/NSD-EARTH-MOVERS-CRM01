import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import './echo';

window.Alpine = Alpine;
window.Chart = Chart;

// Global Toast Notification Manager
window.toastManager = function () {
    return {
        toasts: [],
        init() {
            window.addEventListener('show-toast', (e) => {
                this.add(e.detail.message, e.detail.type || 'info', e.detail.duration || 4500);
            });
        },
        add(message, type = 'info', duration = 4500) {
            const id = Date.now() + Math.random();
            const toast = { id, message, type, visible: true };
            this.toasts.push(toast);

            setTimeout(() => {
                toast.visible = false;
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 300);
            }, duration);
        }
    };
};

window.showToast = function (message, type = 'info', duration = 4500) {
    window.dispatchEvent(new CustomEvent('show-toast', {
        detail: { message, type, duration }
    }));
};

// Global Notification Center & WebSocket Coordinator
window.notificationCenter = function () {
    return {
        open: false,
        unreadCount: 0,
        notifications: [],
        connectionState: 'disconnected',
        hasNewPing: false,

        init() {
            this.fetchFeed();

            const userId = window.buildFlowConfig?.userId;
            const companyId = window.buildFlowConfig?.companyId;

            // Connect to Echo private channels if available
            if (window.Echo && userId) {
                // Monitor WebSocket connection state
                if (window.Echo.connector && window.Echo.connector.pusher) {
                    const pusher = window.Echo.connector.pusher;
                    if (pusher.connection) {
                        this.connectionState = pusher.connection.state || 'connecting';
                        pusher.connection.bind('state_change', (states) => {
                            this.connectionState = states.current;
                            if (states.current === 'connected') {
                                this.fetchFeed(); // Reconcile unread upon reconnection
                            }
                        });
                        pusher.connection.bind('connected', () => {
                            this.connectionState = 'connected';
                        });
                        pusher.connection.bind('disconnected', () => {
                            this.connectionState = 'disconnected';
                        });
                        pusher.connection.bind('unavailable', () => {
                            this.connectionState = 'disconnected';
                        });
                    }
                }

                // 1. Subscribe to user-specific private channel
                const userChannel = window.Echo.private(`App.Models.User.${userId}`);
                userChannel.listen('.NotificationDispatched', (e) => {
                    this.handleIncoming(e);
                }).listen('NotificationDispatched', (e) => {
                    this.handleIncoming(e);
                }).listen('.PurchaseRequestUpdated', (e) => {
                    window.showToast(`Purchase Request ${e.pr_number} is now ${e.status_label}.`, 'info');
                }).listen('PurchaseRequestUpdated', (e) => {
                    window.showToast(`Purchase Request ${e.pr_number} is now ${e.status_label}.`, 'info');
                });

                // 2. Subscribe to tenant company channel
                if (companyId) {
                    const companyChannel = window.Echo.private(`company.${companyId}`);
                    companyChannel.listen('.NotificationDispatched', (e) => {
                        this.handleIncoming(e);
                    }).listen('NotificationDispatched', (e) => {
                        this.handleIncoming(e);
                    }).listen('.ProjectStatusUpdated', (e) => {
                        window.showToast(`Project '${e.name}' status updated to ${e.status_label}.`, 'info');
                        window.dispatchEvent(new CustomEvent('project-status-changed', { detail: e }));
                    }).listen('ProjectStatusUpdated', (e) => {
                        window.showToast(`Project '${e.name}' status updated to ${e.status_label}.`, 'info');
                        window.dispatchEvent(new CustomEvent('project-status-changed', { detail: e }));
                    }).listen('.MaterialStockUpdated', (e) => {
                        if (e.is_low_stock) {
                            window.showToast(`⚠️ Low Stock Alert: ${e.material_name} (${e.current_balance} ${e.unit_of_measure}) at ${e.warehouse_name}`, 'warning', 6000);
                        }
                    }).listen('MaterialStockUpdated', (e) => {
                        if (e.is_low_stock) {
                            window.showToast(`⚠️ Low Stock Alert: ${e.material_name} (${e.current_balance} ${e.unit_of_measure}) at ${e.warehouse_name}`, 'warning', 6000);
                        }
                    }).listen('.AttendanceUpdated', (e) => {
                        window.showToast(`Daily attendance submitted for site ${e.site_name} (${e.records_count} workers).`, 'info');
                    }).listen('AttendanceUpdated', (e) => {
                        window.showToast(`Daily attendance submitted for site ${e.site_name} (${e.records_count} workers).`, 'info');
                    });
                }
            }

            // Fallback & synchronization: reconcile unread count every 60 seconds
            setInterval(() => {
                this.fetchFeed();
            }, 60000);

            // Reconcile when user refocuses window
            window.addEventListener('focus', () => {
                this.fetchFeed();
            });

            // Clean up listeners on page unload
            window.addEventListener('beforeunload', () => {
                if (window.Echo && userId) {
                    window.Echo.leave(`App.Models.User.${userId}`);
                    if (companyId) {
                        window.Echo.leave(`company.${companyId}`);
                    }
                }
            });
        },

        async fetchFeed() {
            try {
                const res = await fetch('/notifications/feed', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.unreadCount = data.unread_count || 0;
                    this.notifications = data.notifications || [];
                }
            } catch (err) {
                // Graceful fallback: silently continue if offline
            }
        },

        handleIncoming(event) {
            // Deduplicate: avoid duplicate visual entries
            if (this.notifications.some(n => n.id === event.id)) {
                return;
            }

            this.notifications.unshift(event);
            if (this.notifications.length > 10) {
                this.notifications.pop();
            }
            this.unreadCount++;

            // Trigger toast alert for important incoming notifications
            window.showToast(event.title + ': ' + event.message, event.type || 'info');

            // Trigger micro-animation on bell
            this.hasNewPing = true;
            setTimeout(() => {
                this.hasNewPing = false;
            }, 3000);
        },

        async markSingleRead(item) {
            item.is_read = true;
            if (this.unreadCount > 0) this.unreadCount--;

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                await fetch(`/notifications/${item.id}/read`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
            } catch (e) {}
        },

        async markAllAsRead() {
            this.notifications.forEach(n => n.is_read = true);
            this.unreadCount = 0;

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                await fetch('/notifications/mark-all-read', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                window.showToast('All notifications marked as read', 'success', 2500);
            } catch (e) {}
        },

        openRecord(item) {
            this.markSingleRead(item);
            if (item.action_url) {
                window.location.href = item.action_url;
            }
        }
    };
};

// Global Dark Mode Controller Component
window.darkModeManager = function () {
    return {
        isDark: false,
        init() {
            this.isDark = document.documentElement.classList.contains('dark');
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            mediaQuery.addEventListener('change', (e) => {
                if (!localStorage.getItem('theme')) {
                    this.applyTheme(e.matches ? 'dark' : 'light', false);
                }
            });
            window.addEventListener('theme-changed', (e) => {
                this.isDark = e.detail.isDark;
            });
        },
        toggle() {
            this.applyTheme(this.isDark ? 'light' : 'dark', true);
        },
        applyTheme(mode, persist = true) {
            if (mode === 'dark') {
                document.documentElement.classList.add('dark');
                if (persist) localStorage.setItem('theme', 'dark');
                this.isDark = true;
            } else {
                document.documentElement.classList.remove('dark');
                if (persist) localStorage.setItem('theme', 'light');
                this.isDark = false;
            }
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { isDark: this.isDark } }));
        }
    };
};

// Start Alpine
Alpine.start();
