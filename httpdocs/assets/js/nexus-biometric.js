/**
 * NEXUS Biometric Authentication
 * Handles fingerprint/face unlock for the native Android app
 */
(function() {
    'use strict';

    // Only run in native Capacitor environment
    const isNativeApp = typeof Capacitor !== 'undefined' && Capacitor.isNativePlatform && Capacitor.isNativePlatform();

    if (!isNativeApp) {
        console.log('[Biometric] Not in native app, skipping biometric setup');
        return;
    }

    // Check if plugin is available
    const NativeBiometric = Capacitor.Plugins.NativeBiometric;

    if (!NativeBiometric) {
        console.log('[Biometric] NativeBiometric plugin not available');
        return;
    }

    const BiometricAuth = {
        isAvailable: false,
        biometryType: null,
        credentialsKey: 'nexus_biometric_credentials',
        promptDismissedKey: 'nexus_biometric_prompt_dismissed',
        promptRemindLaterKey: 'nexus_biometric_remind_later',

        async init() {
            try {
                // Check if biometric authentication is available
                const result = await NativeBiometric.isAvailable();
                this.isAvailable = result.isAvailable;
                this.biometryType = result.biometryType; // FACE, FINGERPRINT, IRIS, MULTIPLE

                console.log('[Biometric] Available:', this.isAvailable, 'Type:', this.biometryType);

                if (this.isAvailable) {
                    this.setupLoginPage();
                    this.setupSettingsToggle();
                    this.checkAutoPrompt();
                }
            } catch (error) {
                console.error('[Biometric] Init error:', error);
            }
        },

        // Check if we should show the auto-prompt after login
        async checkAutoPrompt() {
            console.log('[Biometric] Checking auto-prompt conditions...');

            // Only prompt if user is logged in
            const isLoggedIn = window.NEXUS?.userId || document.querySelector('.user-menu, .profile-menu, [data-user-id]');
            if (!isLoggedIn) {
                console.log('[Biometric] Not logged in, skipping prompt');
                return;
            }

            // Don't prompt on login page
            if (window.location.pathname.includes('/login')) {
                console.log('[Biometric] On login page, skipping prompt');
                return;
            }

            // Check if already has credentials stored
            const hasCredentials = await this.hasStoredCredentials();
            if (hasCredentials) {
                console.log('[Biometric] Already has credentials, skipping prompt');
                return;
            }

            // Check if user permanently dismissed
            const dismissed = localStorage.getItem(this.promptDismissedKey);
            if (dismissed === 'true') {
                console.log('[Biometric] User dismissed permanently, skipping prompt');
                return;
            }

            // Check if user said "remind me later" - wait 3 days
            const remindLater = localStorage.getItem(this.promptRemindLaterKey);
            if (remindLater) {
                const remindDate = parseInt(remindLater, 10);
                const threeDaysMs = 3 * 24 * 60 * 60 * 1000;
                if (Date.now() - remindDate < threeDaysMs) {
                    console.log('[Biometric] Remind later active, skipping prompt');
                    return;
                }
            }

            // Check for fresh login indicator (set by login success) or NEXUS flag
            const justLoggedIn = sessionStorage.getItem('nexus_just_logged_in') || window.NEXUS?.justLoggedIn;
            const promptShownThisSession = sessionStorage.getItem('nexus_biometric_prompt_shown');

            console.log('[Biometric] justLoggedIn:', justLoggedIn, 'promptShownThisSession:', promptShownThisSession);

            // Show prompt on fresh login (if not already shown this session)
            if (justLoggedIn && !promptShownThisSession) {
                sessionStorage.removeItem('nexus_just_logged_in');
                sessionStorage.setItem('nexus_biometric_prompt_shown', 'true');

                console.log('[Biometric] Showing setup prompt in 1.5 seconds...');
                // Small delay to let page load
                setTimeout(() => this.showSetupPrompt(), 1500);
            } else {
                console.log('[Biometric] Conditions not met for prompt');
            }
        },

        // Show the biometric setup prompt modal
        showSetupPrompt() {
            // First, check if there's an existing modal in the page (from footer.php)
            const existingModal = document.getElementById('biometric-setup-modal');
            if (existingModal) {
                this.showExistingModal(existingModal);
                return;
            }

            // Fallback: create our own modal
            const biometryName = this.biometryType === 'FACE' ? 'Face ID' : (this.biometryType ? 'Fingerprint' : 'Biometric');
            const biometryIcon = this.biometryType === 'FACE' ? 'face-viewfinder' : 'fingerprint';
            const biometryNameLower = biometryName ? biometryName.toLowerCase() : 'biometric';

            const modal = document.createElement('div');
            modal.className = 'biometric-prompt-modal';
            modal.innerHTML = `
                <div class="biometric-prompt-content">
                    <div class="biometric-prompt-icon">
                        <i class="fa-solid fa-${biometryIcon}"></i>
                    </div>
                    <h3>Enable ${biometryName} Login?</h3>
                    <p>Sign in faster and more securely using your ${biometryNameLower}. Your credentials are stored safely on this device.</p>
                    <div class="biometric-prompt-buttons">
                        <button type="button" class="btn-enable">
                            <i class="fa-solid fa-${biometryIcon}"></i>
                            Enable ${biometryName}
                        </button>
                        <button type="button" class="btn-later">Remind Me Later</button>
                        <button type="button" class="btn-dismiss">Don't Ask Again</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            this.addPromptStyles();

            // Animate in
            requestAnimationFrame(() => modal.classList.add('visible'));

            // Handle button clicks
            modal.querySelector('.btn-enable').addEventListener('click', async () => {
                modal.classList.remove('visible');
                setTimeout(() => modal.remove(), 300);
                await this.setupBiometricFromPrompt();
            });

            modal.querySelector('.btn-later').addEventListener('click', () => {
                localStorage.setItem(this.promptRemindLaterKey, Date.now().toString());
                modal.classList.remove('visible');
                setTimeout(() => modal.remove(), 300);
            });

            modal.querySelector('.btn-dismiss').addEventListener('click', () => {
                localStorage.setItem(this.promptDismissedKey, 'true');
                modal.classList.remove('visible');
                setTimeout(() => modal.remove(), 300);
            });
        },

        // Show the existing modal from footer.php
        showExistingModal(modal) {
            console.log('[Biometric] Showing existing modal from footer');
            modal.style.display = 'flex';

            const setupBtn = document.getElementById('btn-setup-biometric-now');
            const skipBtn = document.getElementById('btn-skip-biometric');

            console.log('[Biometric] Setup button found:', !!setupBtn);
            console.log('[Biometric] Skip button found:', !!skipBtn);

            if (setupBtn) {
                // Remove any existing handlers by cloning
                const newSetupBtn = setupBtn.cloneNode(true);
                setupBtn.parentNode.replaceChild(newSetupBtn, setupBtn);

                newSetupBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('[Biometric] Setup button clicked!');
                    modal.style.display = 'none';
                    await this.setupBiometricFromPrompt();
                });
            }

            if (skipBtn) {
                // Remove any existing handlers by cloning
                const newSkipBtn = skipBtn.cloneNode(true);
                skipBtn.parentNode.replaceChild(newSkipBtn, skipBtn);

                newSkipBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    console.log('[Biometric] Skip button clicked');
                    localStorage.setItem(this.promptRemindLaterKey, Date.now().toString());
                    modal.style.display = 'none';
                });
            }

            // Close on backdrop click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    console.log('[Biometric] Backdrop clicked');
                    localStorage.setItem(this.promptRemindLaterKey, Date.now().toString());
                    modal.style.display = 'none';
                }
            });
        },

        // Setup biometric from the prompt
        async setupBiometricFromPrompt() {
            try {
                console.log('[Biometric] Starting setup from prompt...');

                // Ensure styles are loaded for password modal and toast
                this.addStyles();
                console.log('[Biometric] Styles added');

                // Get user email first
                const userEmail = window.NEXUS?.userEmail;
                console.log('[Biometric] User email:', userEmail);

                if (!userEmail) {
                    console.log('[Biometric] No user email found!');
                    this.showToast('Could not get user info. Please try in Settings.', 'error');
                    return;
                }

                // Prompt for password first (before fingerprint)
                console.log('[Biometric] About to show password prompt...');
                const password = await this.promptForPassword();
                console.log('[Biometric] Password entered:', password ? 'yes' : 'no');

                if (!password) {
                    console.log('[Biometric] Password prompt cancelled');
                    return;
                }

                // Verify biometric
                console.log('[Biometric] Requesting biometric verification...');
                await NativeBiometric.verifyIdentity({
                    reason: 'Confirm your identity to enable biometric login',
                    title: 'Enable Biometric Login',
                    subtitle: 'Use your fingerprint or face',
                    description: 'This will allow you to sign in without entering your password'
                });
                console.log('[Biometric] Biometric verified successfully');

                // Store credentials
                console.log('[Biometric] Storing credentials...');
                await NativeBiometric.setCredentials({
                    username: userEmail,
                    password: password,
                    server: 'hour-timebank.ie'
                });

                console.log('[Biometric] Setup complete from prompt');
                this.showToast('Biometric login enabled!', 'success');

            } catch (error) {
                console.error('[Biometric] Setup from prompt error:', error);
                const errorMsg = error?.message || error?.code || String(error);
                if (errorMsg !== 'User cancelled' && error?.code !== 'BIOMETRIC_DISMISSED' && !errorMsg.includes('cancel')) {
                    this.showToast('Failed to set up biometric login: ' + errorMsg, 'error');
                }
            }
        },

        addPromptStyles() {
            if (document.getElementById('biometric-prompt-styles')) return;

            const style = document.createElement('style');
            style.id = 'biometric-prompt-styles';
            style.textContent = `
                .biometric-prompt-modal {
                    position: fixed;
                    inset: 0;
                    background: rgba(0, 0, 0, 0.6);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 100010;
                    padding: 20px;
                    opacity: 0;
                    transition: opacity 0.3s;
                }

                .biometric-prompt-modal.visible {
                    opacity: 1;
                }

                .biometric-prompt-content {
                    background: var(--htb-card-bg, white);
                    border-radius: 24px;
                    padding: 32px;
                    max-width: 380px;
                    width: 100%;
                    text-align: center;
                    transform: scale(0.9) translateY(20px);
                    transition: transform 0.3s;
                }

                .biometric-prompt-modal.visible .biometric-prompt-content {
                    transform: scale(1) translateY(0);
                }

                .biometric-prompt-icon {
                    width: 80px;
                    height: 80px;
                    margin: 0 auto 20px;
                    background: linear-gradient(135deg, #6366f1, #8b5cf6);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 8px 32px rgba(99, 102, 241, 0.35);
                }

                .biometric-prompt-icon i {
                    font-size: 36px;
                    color: white;
                }

                .biometric-prompt-content h3 {
                    margin: 0 0 12px 0;
                    font-size: 1.4rem;
                    font-weight: 700;
                    color: var(--htb-text-main, #1f2937);
                }

                .biometric-prompt-content p {
                    margin: 0 0 24px 0;
                    font-size: 0.95rem;
                    color: var(--htb-text-muted, #6b7280);
                    line-height: 1.5;
                }

                .biometric-prompt-buttons {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                .biometric-prompt-buttons button {
                    width: 100%;
                    padding: 14px 20px;
                    border-radius: 12px;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    border: none;
                    transition: transform 0.2s, box-shadow 0.2s;
                }

                .biometric-prompt-buttons .btn-enable {
                    background: linear-gradient(135deg, #6366f1, #8b5cf6);
                    color: white;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
                }

                .biometric-prompt-buttons .btn-enable:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
                }

                .biometric-prompt-buttons .btn-later {
                    background: var(--htb-bg-secondary, #f3f4f6);
                    color: var(--htb-text-main, #374151);
                }

                .biometric-prompt-buttons .btn-dismiss {
                    background: transparent;
                    color: var(--htb-text-muted, #9ca3af);
                    font-size: 0.9rem;
                    padding: 10px;
                }

                .biometric-prompt-buttons .btn-dismiss:hover {
                    color: var(--htb-text-main, #6b7280);
                }

                /* Dark mode */
                [data-theme="dark"] .biometric-prompt-content {
                    background: var(--htb-card-bg, #1e293b);
                }

                [data-theme="dark"] .biometric-prompt-buttons .btn-later {
                    background: #334155;
                    color: #e2e8f0;
                }
            `;
            document.head.appendChild(style);
        },

        // Add biometric login button to login page
        setupLoginPage() {
            const loginForm = document.querySelector('form[action*="login"], #loginForm, .login-form');
            if (!loginForm) return;

            // Check if credentials are stored
            this.hasStoredCredentials().then(hasCredentials => {
                if (!hasCredentials) {
                    console.log('[Biometric] No stored credentials');
                    return;
                }

                // Add biometric login button
                const biometricBtn = document.createElement('button');
                biometricBtn.type = 'button';
                biometricBtn.className = 'btn btn-biometric';
                biometricBtn.innerHTML = `
                    <i class="fa-solid fa-${this.biometryType === 'FACE' ? 'face-viewfinder' : 'fingerprint'}"></i>
                    <span>Login with ${this.biometryType === 'FACE' ? 'Face ID' : 'Fingerprint'}</span>
                `;

                biometricBtn.addEventListener('click', () => this.loginWithBiometric());

                // Insert before the login form
                const container = document.createElement('div');
                container.className = 'biometric-login-container';
                container.innerHTML = '<div class="biometric-divider"><span>or</span></div>';
                container.appendChild(biometricBtn);

                loginForm.parentNode.insertBefore(container, loginForm.nextSibling);

                // Add styles
                this.addStyles();
            });
        },

        // Add toggle in user settings
        setupSettingsToggle() {
            // Look for security settings section
            const securitySection = document.querySelector('.security-settings, #security-settings, [data-section="security"]');
            if (securitySection) {
                this.addBiometricToggle(securitySection);
            }

            // Also check for account settings page
            const settingsPage = document.querySelector('.settings-page, .account-settings');
            if (settingsPage && !securitySection) {
                this.addBiometricToggle(settingsPage);
            }
        },

        addBiometricToggle(container) {
            this.hasStoredCredentials().then(hasCredentials => {
                const toggleHtml = `
                    <div class="biometric-setting">
                        <div class="setting-info">
                            <i class="fa-solid fa-${this.biometryType === 'FACE' ? 'face-viewfinder' : 'fingerprint'}"></i>
                            <div>
                                <strong>${this.biometryType === 'FACE' ? 'Face ID' : 'Fingerprint'} Login</strong>
                                <span>Use biometric authentication to sign in quickly</span>
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="biometricToggle" ${hasCredentials ? 'checked' : ''}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                `;

                const settingEl = document.createElement('div');
                settingEl.innerHTML = toggleHtml;
                container.appendChild(settingEl.firstElementChild);

                // Handle toggle
                document.getElementById('biometricToggle').addEventListener('change', (e) => {
                    if (e.target.checked) {
                        this.enableBiometric();
                    } else {
                        this.disableBiometric();
                    }
                });
            });
        },

        async hasStoredCredentials() {
            try {
                // Check if we have credentials stored
                const credentials = await NativeBiometric.getCredentials({
                    server: 'hour-timebank.ie'
                });
                return !!credentials && !!credentials.username;
            } catch (error) {
                return false;
            }
        },

        async enableBiometric() {
            try {
                // Verify biometric first
                const verified = await NativeBiometric.verifyIdentity({
                    reason: 'Enable biometric login',
                    title: 'Biometric Setup',
                    subtitle: 'Confirm your identity',
                    description: 'Use your fingerprint or face to enable quick login'
                });

                if (!verified) {
                    console.log('[Biometric] Verification cancelled');
                    document.getElementById('biometricToggle').checked = false;
                    return;
                }

                // Get current user's credentials from the session/page
                const userId = window.NEXUS?.userId;
                const userEmail = window.NEXUS?.userEmail;

                if (!userId || !userEmail) {
                    this.showToast('Please log in first to enable biometric login', 'error');
                    document.getElementById('biometricToggle').checked = false;
                    return;
                }

                // Prompt for password to store
                const password = await this.promptForPassword();
                if (!password) {
                    document.getElementById('biometricToggle').checked = false;
                    return;
                }

                // Store credentials securely
                await NativeBiometric.setCredentials({
                    username: userEmail,
                    password: password,
                    server: 'hour-timebank.ie'
                });

                this.showToast('Biometric login enabled!', 'success');
                console.log('[Biometric] Credentials stored');

            } catch (error) {
                console.error('[Biometric] Enable error:', error);
                this.showToast('Failed to enable biometric login', 'error');
                document.getElementById('biometricToggle').checked = false;
            }
        },

        async disableBiometric() {
            try {
                await NativeBiometric.deleteCredentials({
                    server: 'hour-timebank.ie'
                });
                this.showToast('Biometric login disabled', 'info');
                console.log('[Biometric] Credentials deleted');
            } catch (error) {
                console.error('[Biometric] Disable error:', error);
            }
        },

        async loginWithBiometric() {
            console.log('[Biometric] Starting biometric login...');
            try {
                // Verify biometric
                console.log('[Biometric] Requesting biometric verification for login...');
                await NativeBiometric.verifyIdentity({
                    reason: 'Log in to Hour Timebank',
                    title: 'Biometric Login',
                    subtitle: 'Verify your identity',
                    description: 'Use your fingerprint or face to sign in',
                    useFallback: true,
                    fallbackTitle: 'Use Password',
                    maxAttempts: 3
                });
                console.log('[Biometric] Biometric verified for login');

                // Get stored credentials
                console.log('[Biometric] Getting stored credentials...');
                const credentials = await NativeBiometric.getCredentials({
                    server: 'hour-timebank.ie'
                });
                console.log('[Biometric] Credentials retrieved:', credentials ? 'yes' : 'no');

                if (!credentials || !credentials.username || !credentials.password) {
                    console.log('[Biometric] No valid credentials found');
                    this.showToast('No saved credentials found', 'error');
                    return;
                }

                // Submit login form
                console.log('[Biometric] Performing login with stored credentials...');
                this.performLogin(credentials.username, credentials.password);

            } catch (error) {
                console.error('[Biometric] Login error:', error);
                const errorMsg = error?.message || error?.code || String(error);
                if (!errorMsg.includes('cancel') && error?.code !== 'BIOMETRIC_DISMISSED') {
                    this.showToast('Biometric login failed: ' + errorMsg, 'error');
                }
            }
        },

        performLogin(email, password) {
            // Find and fill the login form
            const emailInput = document.querySelector('input[type="email"], input[name="email"], input[name="username"]');
            const passwordInput = document.querySelector('input[type="password"], input[name="password"]');
            const loginForm = document.querySelector('form[action*="login"], #loginForm, .login-form');

            if (emailInput && passwordInput && loginForm) {
                emailInput.value = email;
                passwordInput.value = password;

                // Submit the form
                loginForm.submit();
            } else {
                // Fallback: POST directly
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/login';
                form.innerHTML = `
                    <input type="hidden" name="email" value="${this.escapeHtml(email)}">
                    <input type="hidden" name="password" value="${this.escapeHtml(password)}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        },

        promptForPassword() {
            return new Promise((resolve) => {
                const modal = document.createElement('div');
                modal.className = 'biometric-modal';
                modal.innerHTML = `
                    <div class="biometric-modal-content">
                        <h3>Enter Your Password</h3>
                        <p>Your password will be stored securely and protected by your biometric.</p>
                        <input type="password" id="biometricPassword" placeholder="Enter your password" autofocus>
                        <div class="biometric-modal-buttons">
                            <button type="button" class="btn-cancel">Cancel</button>
                            <button type="button" class="btn-confirm">Confirm</button>
                        </div>
                    </div>
                `;

                document.body.appendChild(modal);

                const passwordInput = modal.querySelector('#biometricPassword');
                const cancelBtn = modal.querySelector('.btn-cancel');
                const confirmBtn = modal.querySelector('.btn-confirm');

                cancelBtn.addEventListener('click', () => {
                    modal.remove();
                    resolve(null);
                });

                confirmBtn.addEventListener('click', () => {
                    const password = passwordInput.value;
                    modal.remove();
                    resolve(password || null);
                });

                passwordInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        const password = passwordInput.value;
                        modal.remove();
                        resolve(password || null);
                    }
                });

                // Focus input
                setTimeout(() => passwordInput.focus(), 100);
            });
        },

        showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `biometric-toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => toast.classList.add('visible'), 10);
            setTimeout(() => {
                toast.classList.remove('visible');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        addStyles() {
            if (document.getElementById('biometric-styles')) return;

            const style = document.createElement('style');
            style.id = 'biometric-styles';
            style.textContent = `
                .biometric-login-container {
                    margin: 20px 0;
                    text-align: center;
                }

                .biometric-divider {
                    display: flex;
                    align-items: center;
                    margin: 16px 0;
                    color: var(--htb-text-muted, #6b7280);
                }

                .biometric-divider::before,
                .biometric-divider::after {
                    content: '';
                    flex: 1;
                    height: 1px;
                    background: var(--htb-border, #e5e7eb);
                }

                .biometric-divider span {
                    padding: 0 12px;
                    font-size: 0.85rem;
                }

                .btn-biometric {
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    padding: 14px 24px;
                    background: linear-gradient(135deg, #6366f1, #8b5cf6);
                    color: white;
                    border: none;
                    border-radius: 12px;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: transform 0.2s, box-shadow 0.2s;
                }

                .btn-biometric:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
                }

                .btn-biometric i {
                    font-size: 1.25rem;
                }

                .biometric-setting {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 16px;
                    background: var(--htb-card-bg, white);
                    border-radius: 12px;
                    border: 1px solid var(--htb-border, #e5e7eb);
                    margin-bottom: 12px;
                }

                .biometric-setting .setting-info {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }

                .biometric-setting .setting-info i {
                    font-size: 1.5rem;
                    color: #6366f1;
                }

                .biometric-setting .setting-info strong {
                    display: block;
                    color: var(--htb-text-main, #1f2937);
                }

                .biometric-setting .setting-info span {
                    font-size: 0.85rem;
                    color: var(--htb-text-muted, #6b7280);
                }

                .toggle-switch {
                    position: relative;
                    width: 50px;
                    height: 28px;
                }

                .toggle-switch input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }

                .toggle-slider {
                    position: absolute;
                    cursor: pointer;
                    inset: 0;
                    background: #e5e7eb;
                    border-radius: 28px;
                    transition: 0.3s;
                }

                .toggle-slider::before {
                    content: '';
                    position: absolute;
                    width: 22px;
                    height: 22px;
                    left: 3px;
                    bottom: 3px;
                    background: white;
                    border-radius: 50%;
                    transition: 0.3s;
                }

                .toggle-switch input:checked + .toggle-slider {
                    background: #6366f1;
                }

                .toggle-switch input:checked + .toggle-slider::before {
                    transform: translateX(22px);
                }

                .biometric-modal {
                    position: fixed;
                    inset: 0;
                    background: rgba(0, 0, 0, 0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 100002;
                    padding: 20px;
                }

                .biometric-modal-content {
                    background: var(--htb-card-bg, white);
                    border-radius: 16px;
                    padding: 24px;
                    max-width: 400px;
                    width: 100%;
                }

                .biometric-modal-content h3 {
                    margin: 0 0 8px 0;
                    font-size: 1.25rem;
                    color: var(--htb-text-main, #1f2937);
                }

                .biometric-modal-content p {
                    margin: 0 0 16px 0;
                    font-size: 0.9rem;
                    color: var(--htb-text-muted, #6b7280);
                }

                .biometric-modal-content input {
                    width: 100%;
                    padding: 12px 16px;
                    border: 1px solid var(--htb-border, #d1d5db);
                    border-radius: 8px;
                    font-size: 1rem;
                    margin-bottom: 16px;
                    box-sizing: border-box;
                    background: var(--htb-bg-secondary, #f9fafb);
                    color: var(--htb-text-main, #1f2937);
                }

                .biometric-modal-buttons {
                    display: flex;
                    gap: 12px;
                    justify-content: flex-end;
                }

                .biometric-modal-buttons button {
                    padding: 10px 20px;
                    border-radius: 8px;
                    font-weight: 600;
                    cursor: pointer;
                    border: none;
                }

                .biometric-modal-buttons .btn-cancel {
                    background: var(--htb-bg-secondary, #f3f4f6);
                    color: var(--htb-text-main, #374151);
                }

                .biometric-modal-buttons .btn-confirm {
                    background: #6366f1;
                    color: white;
                }

                .biometric-toast {
                    position: fixed;
                    bottom: 20px;
                    left: 50%;
                    transform: translateX(-50%) translateY(100px);
                    padding: 12px 24px;
                    background: #1f2937;
                    color: white;
                    border-radius: 8px;
                    font-size: 0.9rem;
                    z-index: 100003;
                    transition: transform 0.3s;
                }

                .biometric-toast.visible {
                    transform: translateX(-50%) translateY(0);
                }

                .biometric-toast.success {
                    background: #059669;
                }

                .biometric-toast.error {
                    background: #dc2626;
                }

                /* Dark mode */
                [data-theme="dark"] .biometric-modal-content {
                    background: var(--htb-card-bg, #1e293b);
                }

                [data-theme="dark"] .toggle-slider {
                    background: #374151;
                }
            `;
            document.head.appendChild(style);
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => BiometricAuth.init());
    } else {
        BiometricAuth.init();
    }

    // Expose globally
    window.NexusBiometric = BiometricAuth;

    // Manual trigger for testing: call window.NexusBiometric.showSetupPrompt() from console
    // Or add ?biometric_test=1 to URL to force show prompt
    if (window.location.search.includes('biometric_test=1')) {
        setTimeout(() => {
            console.log('[Biometric] Test mode - forcing prompt display');
            BiometricAuth.showSetupPrompt();
        }, 1000);
    }

})();
