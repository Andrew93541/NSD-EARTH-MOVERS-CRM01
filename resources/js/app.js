import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

// Global Notification Center Alpine Component
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

                // Subscribe to user-specific private channel
                const userChannel = window.Echo.private(`App.Models.User.${userId}`);
                userChannel.listen('.NotificationDispatched', (e) => {
                    this.handleIncoming(e);
                }).listen('NotificationDispatched', (e) => {
                    this.handleIncoming(e);
                });

                // Subscribe to tenant company channel
                if (companyId) {
                    const companyChannel = window.Echo.private(`company.${companyId}`);
                    companyChannel.listen('.NotificationDispatched', (e) => {
                        this.handleIncoming(e);
                    }).listen('NotificationDispatched', (e) => {
                        this.handleIncoming(e);
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

            // Trigger micro-animation
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

// Start Alpine
Alpine.start();

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel.
 */
import './echo';
