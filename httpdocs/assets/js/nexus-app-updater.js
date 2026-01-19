/**
 * NEXUS App Updater
 * Checks for app updates and prompts users to download the latest version
 * Only runs on Capacitor/mobile apps, not on web browsers
 */
(function() {
    'use strict';

    window.NexusAppUpdater = {
        // App version - UPDATE THIS when building a new APK
        // Must match versionName in capacitor/android/app/build.gradle
        APP_VERSION: '1.0',

        // How often to check for updates (once per day)
        CHECK_INTERVAL_MS: 24 * 60 * 60 * 1000,

        // Storage key for last check timestamp
        LAST_CHECK_KEY: 'nexus_app_update_check',

        // Storage key for dismissed version (user chose "Later")
        DISMISSED_VERSION_KEY: 'nexus_app_dismissed_version',

        /**
         * Initialize the updater - call on app startup
         */
        init: function() {
            // Only run on native Capacitor apps
            if (!this.isNativeApp()) {
                console.log('[AppUpdater] Not a native app, skipping');
                return;
            }

            console.log('[AppUpdater] Initializing, current version:', this.APP_VERSION);

            // Check for updates (but not too frequently)
            this.checkForUpdateIfNeeded();
        },

        /**
         * Check if running as native Capacitor app
         */
        isNativeApp: function() {
            return typeof Capacitor !== 'undefined' &&
                   Capacitor.isNativePlatform &&
                   Capacitor.isNativePlatform();
        },

        /**
         * Check for updates if enough time has passed since last check
         */
        checkForUpdateIfNeeded: function() {
            const lastCheck = parseInt(localStorage.getItem(this.LAST_CHECK_KEY) || '0', 10);
            const now = Date.now();

            // Check at most once per day
            if (now - lastCheck < this.CHECK_INTERVAL_MS) {
                console.log('[AppUpdater] Checked recently, skipping');
                return;
            }

            this.checkForUpdate();
        },

        /**
         * Force check for updates (bypasses time check)
         */
        checkForUpdate: async function() {
            try {
                console.log('[AppUpdater] Checking for updates...');

                const response = await fetch('/api/app/check-version', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Capacitor-App': 'true'
                    },
                    body: JSON.stringify({
                        version: this.APP_VERSION,
                        platform: 'android'
                    })
                });

                if (!response.ok) {
                    console.log('[AppUpdater] Server returned error:', response.status);
                    return;
                }

                const data = await response.json();
                console.log('[AppUpdater] Server response:', data);

                // Update last check time
                localStorage.setItem(this.LAST_CHECK_KEY, Date.now().toString());

                if (data.update_available) {
                    this.handleUpdateAvailable(data);
                }

            } catch (error) {
                console.error('[AppUpdater] Check failed:', error);
            }
        },

        /**
         * Handle update available response
         */
        handleUpdateAvailable: function(data) {
            const dismissedVersion = localStorage.getItem(this.DISMISSED_VERSION_KEY);

            // If user dismissed this version, don't prompt again (unless force update)
            if (!data.force_update && dismissedVersion === data.current_version) {
                console.log('[AppUpdater] User dismissed this version, not prompting');
                return;
            }

            // Show update prompt
            this.showUpdatePrompt(data);
        },

        /**
         * Show the update prompt modal
         */
        showUpdatePrompt: function(data) {
            // Remove any existing modal
            const existing = document.getElementById('app-update-modal');
            if (existing) existing.remove();

            const isForced = data.force_update;

            // Build release notes HTML
            let releaseNotesHtml = '';
            if (data.release_notes && Object.keys(data.release_notes).length > 0) {
                releaseNotesHtml = '<div class="app-update-notes">';
                for (const [version, notes] of Object.entries(data.release_notes)) {
                    releaseNotesHtml += `<div class="app-update-version">Version ${version}</div>`;
                    releaseNotesHtml += '<ul>';
                    notes.forEach(note => {
                        releaseNotesHtml += `<li>${this.escapeHtml(note)}</li>`;
                    });
                    releaseNotesHtml += '</ul>';
                }
                releaseNotesHtml += '</div>';
            }

            const modal = document.createElement('div');
            modal.id = 'app-update-modal';
            modal.className = 'app-update-modal' + (isForced ? ' app-update-forced' : '');
            modal.innerHTML = `
                <div class="app-update-backdrop"></div>
                <div class="app-update-content">
                    <div class="app-update-icon">
                        <i class="fa-solid fa-rocket"></i>
                    </div>
                    <h2 class="app-update-title">
                        ${isForced ? 'Update Required' : 'Update Available'}
                    </h2>
                    <p class="app-update-message">
                        ${this.escapeHtml(data.update_message || 'A new version of the app is available.')}
                    </p>
                    <p class="app-update-versions">
                        Your version: <strong>${this.APP_VERSION}</strong><br>
                        Latest version: <strong>${data.current_version}</strong>
                    </p>
                    ${releaseNotesHtml}
                    <div class="app-update-buttons">
                        <button class="app-update-btn app-update-btn-primary" onclick="NexusAppUpdater.downloadUpdate('${data.update_url}')">
                            <i class="fa-solid fa-download"></i> Update Now
                        </button>
                        ${!isForced ? `
                        <button class="app-update-btn app-update-btn-secondary" onclick="NexusAppUpdater.dismissUpdate('${data.current_version}')">
                            Later
                        </button>
                        ` : ''}
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // Add styles if not already added
            this.injectStyles();
        },

        /**
         * Download the update
         */
        downloadUpdate: function(url) {
            console.log('[AppUpdater] Downloading update from:', url);

            // Open in system browser for download
            if (typeof Capacitor !== 'undefined' && Capacitor.Plugins && Capacitor.Plugins.Browser) {
                Capacitor.Plugins.Browser.open({ url: url });
            } else {
                window.open(url, '_system');
            }

            // Close modal
            const modal = document.getElementById('app-update-modal');
            if (modal) modal.remove();
        },

        /**
         * Dismiss the update prompt (user chose "Later")
         */
        dismissUpdate: function(version) {
            console.log('[AppUpdater] User dismissed update for version:', version);
            localStorage.setItem(this.DISMISSED_VERSION_KEY, version);

            const modal = document.getElementById('app-update-modal');
            if (modal) modal.remove();
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Inject CSS styles for the modal
         */
        injectStyles: function() {
            if (document.getElementById('app-update-styles')) return;

            const styles = document.createElement('style');
            styles.id = 'app-update-styles';
            styles.textContent = `
                .app-update-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    z-index: 99999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }

                .app-update-backdrop {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.7);
                }

                .app-update-content {
                    position: relative;
                    background: #1e1e2e;
                    border-radius: 16px;
                    padding: 30px;
                    max-width: 400px;
                    width: 100%;
                    text-align: center;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
                }

                .app-update-icon {
                    width: 70px;
                    height: 70px;
                    background: linear-gradient(135deg, #4f46e5, #7c3aed);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                }

                .app-update-icon i {
                    font-size: 32px;
                    color: white;
                }

                .app-update-title {
                    color: #cdd6f4;
                    font-size: 24px;
                    font-weight: 700;
                    margin: 0 0 15px;
                }

                .app-update-message {
                    color: #a6adc8;
                    font-size: 16px;
                    line-height: 1.5;
                    margin: 0 0 15px;
                }

                .app-update-versions {
                    color: #6c7086;
                    font-size: 14px;
                    margin: 0 0 20px;
                }

                .app-update-versions strong {
                    color: #cdd6f4;
                }

                .app-update-notes {
                    background: #181825;
                    border-radius: 8px;
                    padding: 15px;
                    margin: 0 0 20px;
                    text-align: left;
                    max-height: 150px;
                    overflow-y: auto;
                }

                .app-update-version {
                    color: #89b4fa;
                    font-weight: 600;
                    font-size: 14px;
                    margin-bottom: 8px;
                }

                .app-update-notes ul {
                    margin: 0 0 10px;
                    padding-left: 20px;
                    color: #a6adc8;
                    font-size: 13px;
                }

                .app-update-notes li {
                    margin-bottom: 4px;
                }

                .app-update-buttons {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                .app-update-btn {
                    padding: 14px 24px;
                    border-radius: 10px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    border: none;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    transition: transform 0.2s, opacity 0.2s;
                }

                .app-update-btn:active {
                    transform: scale(0.98);
                }

                .app-update-btn-primary {
                    background: linear-gradient(135deg, #4f46e5, #7c3aed);
                    color: white;
                }

                .app-update-btn-secondary {
                    background: #313244;
                    color: #cdd6f4;
                }

                .app-update-forced .app-update-title {
                    color: #f38ba8;
                }
            `;
            document.head.appendChild(styles);
        }
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => NexusAppUpdater.init());
    } else {
        NexusAppUpdater.init();
    }

})();
