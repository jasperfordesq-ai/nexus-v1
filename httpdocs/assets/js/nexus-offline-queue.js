/**
 * NEXUS Offline Queue
 * Client-side helper for queuing actions when offline
 * Works with Background Sync in the service worker
 */

window.NexusOffline = (function() {
    'use strict';

    const DB_NAME = 'nexus-offline-queue';
    const DB_VERSION = 1;

    /**
     * Open IndexedDB database
     */
    function openDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                if (!db.objectStoreNames.contains('messages')) {
                    const store = db.createObjectStore('messages', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('timestamp', 'timestamp');
                }

                if (!db.objectStoreNames.contains('transactions')) {
                    const store = db.createObjectStore('transactions', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('timestamp', 'timestamp');
                }

                if (!db.objectStoreNames.contains('forms')) {
                    const store = db.createObjectStore('forms', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('timestamp', 'timestamp');
                }
            };
        });
    }

    /**
     * Add item to IndexedDB store
     */
    async function addToStore(storeName, data) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const transaction = db.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.add({ ...data, timestamp: Date.now() });

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get pending items count
     */
    async function getPendingCount(storeName) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const transaction = db.transaction(storeName, 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.count();

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get all pending items
     */
    async function getPendingItems(storeName) {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const transaction = db.transaction(storeName, 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.getAll();

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Register background sync
     */
    async function registerSync(tag) {
        if ('serviceWorker' in navigator && 'sync' in window.SyncManager) {
            const registration = await navigator.serviceWorker.ready;
            await registration.sync.register(tag);
            console.log('[OfflineQueue] Registered sync:', tag);
            return true;
        }
        return false;
    }

    /**
     * Send message to service worker
     */
    async function postToSW(type, payload) {
        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.ready;
            if (registration.active) {
                registration.active.postMessage({ type, payload });
                return true;
            }
        }
        return false;
    }

    /**
     * Queue a message for offline sending
     */
    async function queueMessage(recipientId, content, conversationId = null) {
        const data = {
            recipientId,
            content,
            conversationId,
            queuedAt: new Date().toISOString()
        };

        // Add to local IndexedDB
        const id = await addToStore('messages', data);
        console.log('[OfflineQueue] Queued message:', id);

        // Notify service worker
        await postToSW('QUEUE_MESSAGE', data);

        // Try to register sync
        await registerSync('sync-messages');

        // Show user feedback
        showOfflineToast('Message queued - will send when online');

        return id;
    }

    /**
     * Queue a time credit transaction for offline sending
     */
    async function queueTransaction(recipientId, amount, description, listingId = null) {
        const data = {
            recipientId,
            amount,
            description,
            listingId,
            queuedAt: new Date().toISOString()
        };

        // Add to local IndexedDB
        const id = await addToStore('transactions', data);
        console.log('[OfflineQueue] Queued transaction:', id);

        // Notify service worker
        await postToSW('QUEUE_TRANSACTION', data);

        // Try to register sync
        await registerSync('sync-transactions');

        // Show user feedback
        showOfflineToast('Transaction queued - will process when online');

        return id;
    }

    /**
     * Queue a generic form submission
     */
    async function queueForm(url, data, method = 'POST') {
        const formData = {
            url,
            data,
            method,
            queuedAt: new Date().toISOString()
        };

        // Add to local IndexedDB
        const id = await addToStore('forms', formData);
        console.log('[OfflineQueue] Queued form:', id);

        // Notify service worker
        await postToSW('QUEUE_FORM', formData);

        // Try to register sync
        await registerSync('sync-forms');

        // Show user feedback
        showOfflineToast('Form saved - will submit when online');

        return id;
    }

    /**
     * Show offline toast notification
     */
    function showOfflineToast(message) {
        // Use existing toast system if available
        if (window.NexusMobile && window.NexusMobile.showToast) {
            window.NexusMobile.showToast(message, 'warning');
            return;
        }

        if (window.CivicOneMobile && window.CivicOneMobile.showToast) {
            window.CivicOneMobile.showToast(message, 'warning');
            return;
        }

        // Fallback toast
        const toast = document.createElement('div');
        toast.className = 'offline-queue-toast';
        toast.innerHTML = `
            <i class="fas fa-cloud-upload-alt"></i>
            <span>${message}</span>
        `;
        toast.style.cssText = `
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: #f59e0b;
            color: #000;
            padding: 12px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideDown 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    /**
     * Check if we're offline
     */
    function isOffline() {
        return !navigator.onLine;
    }

    /**
     * Enhanced fetch that queues on failure
     */
    async function fetchWithOfflineQueue(url, options = {}, queueType = 'forms') {
        // If online, try normal fetch
        if (navigator.onLine) {
            try {
                const response = await fetch(url, options);
                if (response.ok) {
                    return response;
                }
            } catch (e) {
                console.log('[OfflineQueue] Fetch failed, queuing...');
            }
        }

        // Queue for later
        if (options.method === 'POST' || options.method === 'PUT') {
            let data;
            try {
                data = options.body ? JSON.parse(options.body) : {};
            } catch (e) {
                data = { raw: options.body };
            }

            if (queueType === 'messages') {
                await queueMessage(data.recipient_id, data.content, data.conversation_id);
            } else if (queueType === 'transactions') {
                await queueTransaction(data.recipient_id, data.amount, data.description, data.listing_id);
            } else {
                await queueForm(url, data, options.method);
            }

            // Return a fake response so the UI can continue
            return new Response(JSON.stringify({ queued: true, offline: true }), {
                status: 202,
                headers: { 'Content-Type': 'application/json' }
            });
        }

        throw new Error('Cannot queue GET requests');
    }

    /**
     * Get status of offline queue
     */
    async function getQueueStatus() {
        const [messages, transactions, forms] = await Promise.all([
            getPendingCount('messages'),
            getPendingCount('transactions'),
            getPendingCount('forms')
        ]);

        return {
            messages,
            transactions,
            forms,
            total: messages + transactions + forms
        };
    }

    /**
     * Show queue status badge
     */
    async function updateQueueBadge() {
        const status = await getQueueStatus();

        if (status.total > 0) {
            let badge = document.getElementById('offline-queue-badge');
            if (!badge) {
                badge = document.createElement('div');
                badge.id = 'offline-queue-badge';
                badge.style.cssText = `
                    position: fixed;
                    top: 70px;
                    right: 10px;
                    background: #f59e0b;
                    color: #000;
                    padding: 6px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    cursor: pointer;
                `;
                badge.onclick = () => showQueueDetails();
                document.body.appendChild(badge);
            }

            badge.innerHTML = `<i class="fas fa-clock"></i> ${status.total} pending`;
        } else {
            const badge = document.getElementById('offline-queue-badge');
            if (badge) badge.remove();
        }
    }

    /**
     * Show queue details modal
     */
    async function showQueueDetails() {
        const status = await getQueueStatus();
        const [messages, transactions, forms] = await Promise.all([
            getPendingItems('messages'),
            getPendingItems('transactions'),
            getPendingItems('forms')
        ]);

        const modal = document.createElement('div');
        modal.className = 'offline-queue-modal';
        modal.innerHTML = `
            <div class="offline-queue-modal-content">
                <h3>Pending Offline Actions</h3>
                <p>These will be sent when you're back online:</p>

                ${messages.length > 0 ? `
                    <div class="queue-section">
                        <h4><i class="fas fa-envelope"></i> Messages (${messages.length})</h4>
                        <ul>
                            ${messages.map(m => `<li>To: User #${m.recipientId}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}

                ${transactions.length > 0 ? `
                    <div class="queue-section">
                        <h4><i class="fas fa-coins"></i> Transactions (${transactions.length})</h4>
                        <ul>
                            ${transactions.map(t => `<li>${t.amount} credits to User #${t.recipientId}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}

                ${forms.length > 0 ? `
                    <div class="queue-section">
                        <h4><i class="fas fa-file-alt"></i> Forms (${forms.length})</h4>
                        <ul>
                            ${forms.map(f => `<li>${f.url}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}

                <button class="close-modal-btn" onclick="this.closest('.offline-queue-modal').remove()">Close</button>
            </div>
        `;
        modal.style.cssText = `
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10001;
            padding: 20px;
        `;

        const style = document.createElement('style');
        style.textContent = `
            .offline-queue-modal-content {
                background: #1e293b;
                border-radius: 12px;
                padding: 20px;
                max-width: 400px;
                width: 100%;
                color: #f1f5f9;
            }
            .offline-queue-modal-content h3 { margin: 0 0 10px 0; color: #f59e0b; }
            .offline-queue-modal-content p { margin: 0 0 15px 0; color: #94a3b8; }
            .queue-section { margin-bottom: 15px; }
            .queue-section h4 { font-size: 14px; margin: 0 0 8px 0; }
            .queue-section ul { margin: 0; padding-left: 20px; }
            .queue-section li { font-size: 13px; color: #94a3b8; }
            .close-modal-btn {
                width: 100%;
                padding: 12px;
                background: #334155;
                border: none;
                border-radius: 8px;
                color: #f1f5f9;
                cursor: pointer;
                margin-top: 15px;
            }
        `;
        modal.appendChild(style);
        document.body.appendChild(modal);
    }

    // Update badge periodically and on online/offline events
    if (typeof window !== 'undefined') {
        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideUp {
                from { transform: translate(-50%, 100%); opacity: 0; }
                to { transform: translate(-50%, 0); opacity: 1; }
            }
            @keyframes slideDown {
                from { transform: translate(-50%, 0); opacity: 1; }
                to { transform: translate(-50%, 100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // Check queue on load
        document.addEventListener('DOMContentLoaded', updateQueueBadge);

        // Update on online event
        window.addEventListener('online', () => {
            console.log('[OfflineQueue] Back online, triggering sync...');
            updateQueueBadge();
        });

        // Update periodically
        setInterval(updateQueueBadge, 30000);
    }

    // Public API
    return {
        queueMessage,
        queueTransaction,
        queueForm,
        fetchWithOfflineQueue,
        getQueueStatus,
        getPendingItems,
        isOffline,
        updateQueueBadge,
        showQueueDetails
    };
})();
