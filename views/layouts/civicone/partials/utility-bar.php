    <!-- Utility Bar (Account/Platform controls) - Above service navigation -->
    <div class="govuk-width-container">
        <nav class="civicone-utility-bar govuk-!-padding-top-1 govuk-!-padding-bottom-1" aria-label="Account and platform controls">
            <ul class="govuk-list civicone-utility-list">

                <?php
                $basePath = \Nexus\Core\TenantContext::getBasePath();
                $showPlatform = !empty($_SESSION['is_god']);
                $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
                $protocol = $isSecure ? 'https://' : 'http://';
                ?>

                <?php if ($showPlatform): ?>
                <!-- Platform Switcher (God users only) -->
                <li class="civicone-utility-item civicone-utility-item--dropdown">
                    <button type="button"
                            class="govuk-body-s civicone-utility-button"
                            aria-expanded="false"
                            aria-controls="platform-dropdown">
                        Platform
                        <svg class="civicone-utility-chevron" width="10" height="6" viewBox="0 0 10 6" aria-hidden="true">
                            <path fill="currentColor" d="M0 0h10L5 6z"/>
                        </svg>
                    </button>
                    <ul class="civicone-utility-dropdown" id="platform-dropdown" hidden>
                        <?php
                        $tenants = [];
                        try {
                            $tenants = \Nexus\Models\Tenant::all() ?? [];
                        } catch (\Exception $e) {
                            $tenants = [];
                        }
                        foreach ($tenants as $pt):
                            if (!empty($pt['domain'])) {
                                $link = $protocol . $pt['domain'];
                            } else {
                                $link = '/' . ($pt['slug'] ?? '');
                                if (($pt['id'] ?? 0) == 1) $link = '/';
                            }
                        ?>
                        <li><a href="<?= htmlspecialchars($link) ?>" class="govuk-link"><?= htmlspecialchars($pt['name'] ?? 'Unknown') ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- High Contrast Toggle -->
                <li class="civicone-utility-item">
                    <button type="button" id="contrast-toggle" class="govuk-body-s civicone-utility-button" aria-pressed="false">
                        <span aria-hidden="true">◐</span> Contrast
                    </button>
                </li>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                    $nUserId = $_SESSION['user_id'];
                    $nUnread = \Nexus\Models\Notification::countUnread($nUserId);
                    $nRecent = \Nexus\Models\Notification::getLatest($nUserId, 5);
                    $msgUnread = 0;
                    try {
                        if (class_exists('Nexus\Models\MessageThread')) {
                            $msgThreads = Nexus\Models\MessageThread::getForUser($nUserId);
                            foreach ($msgThreads as $msgThread) {
                                if (!empty($msgThread['unread_count'])) {
                                    $msgUnread += (int)$msgThread['unread_count'];
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $msgUnread = 0;
                    }
                    ?>

                    <!-- Messages -->
                    <li class="civicone-utility-item">
                        <a href="<?= $basePath ?>/messages" class="govuk-body-s civicone-utility-link">
                            Messages<?php if ($msgUnread > 0): ?> <span class="govuk-tag govuk-tag--red"><?= $msgUnread > 99 ? '99+' : $msgUnread ?></span><?php endif; ?>
                        </a>
                    </li>

                    <!-- Notifications -->
                    <li class="civicone-utility-item">
                        <button type="button" class="govuk-body-s civicone-utility-button" data-action="open-notifications">
                            Notifications<?php if ($nUnread > 0): ?> <span class="govuk-tag govuk-tag--red" id="notif-badge"><?= $nUnread > 99 ? '99+' : $nUnread ?></span><?php endif; ?>
                        </button>
                    </li>

                    <!-- Account Dropdown -->
                    <li class="civicone-utility-item civicone-utility-item--dropdown civicone-utility-item--account">
                        <button type="button"
                                class="govuk-body-s civicone-utility-button civicone-utility-button--account"
                                aria-expanded="false"
                                aria-controls="account-dropdown">
                            <img src="<?= $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp' ?>" alt="" class="civicone-utility-avatar" width="24" height="24">
                            <?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'Account')[0]) ?>
                            <svg class="civicone-utility-chevron" width="10" height="6" viewBox="0 0 10 6" aria-hidden="true">
                                <path fill="currentColor" d="M0 0h10L5 6z"/>
                            </svg>
                        </button>
                        <ul class="civicone-utility-dropdown civicone-utility-dropdown--right" id="account-dropdown" hidden>
                            <li><a href="<?= $basePath ?>/profile/<?= $_SESSION['user_id'] ?>" class="govuk-link">My profile</a></li>
                            <li><a href="<?= $basePath ?>/dashboard" class="govuk-link">Dashboard</a></li>
                            <li><a href="<?= $basePath ?>/wallet" class="govuk-link">Wallet</a></li>
                            <li><a href="<?= $basePath ?>/settings" class="govuk-link">Settings</a></li>
                            <?php if ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])): ?>
                            <li class="civicone-utility-dropdown-separator"></li>
                            <li><a href="<?= $basePath ?>/admin" class="govuk-link">Admin panel</a></li>
                            <?php endif; ?>
                            <li class="civicone-utility-dropdown-separator"></li>
                            <li><a href="<?= $basePath ?>/logout" class="govuk-link">Sign out</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- Sign In / Register -->
                    <li class="civicone-utility-item">
                        <a href="<?= $basePath ?>/login" class="govuk-body-s civicone-utility-link">Sign in</a>
                    </li>
                    <li class="civicone-utility-item">
                        <a href="<?= $basePath ?>/register" class="govuk-body-s civicone-utility-link civicone-utility-link--highlight">Create account</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- Notifications Drawer -->
    <div id="notif-drawer-overlay" class="civicone-drawer-overlay" hidden></div>
    <aside id="notif-drawer" class="civicone-drawer" role="dialog" aria-labelledby="notif-drawer-title" aria-modal="true" hidden>
        <div class="civicone-drawer__header">
            <h2 id="notif-drawer-title" class="govuk-heading-s govuk-!-margin-bottom-0">Notifications</h2>
            <button type="button" class="civicone-drawer__close" aria-label="Close notifications">
                <span aria-hidden="true">×</span>
            </button>
        </div>
        <div id="notif-list" class="civicone-drawer__content">
            <?php if (empty($nRecent)): ?>
                <p class="govuk-body govuk-!-margin-top-4 govuk-!-text-align-centre">No notifications yet.</p>
            <?php else: ?>
                <ul class="govuk-list">
                    <?php foreach ($nRecent as $n):
                        $notifLink = $n['link'] ?: '#';
                        if ($notifLink !== '#' && strpos($notifLink, 'http') !== 0 && strpos($notifLink, $basePath) !== 0) {
                            $notifLink = $basePath . $notifLink;
                        }
                    ?>
                    <li class="civicone-notification<?= $n['is_read'] ? '' : ' civicone-notification--unread' ?>">
                        <a href="<?= htmlspecialchars($notifLink) ?>" class="govuk-link" data-notif-id="<?= $n['id'] ?>">
                            <?= htmlspecialchars($n['message']) ?>
                        </a>
                        <p class="govuk-body-s govuk-!-margin-top-1 govuk-!-margin-bottom-0" style="color: #505a5f;">
                            <?= date('j M Y, g:i a', strtotime($n['created_at'])) ?>
                        </p>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="civicone-drawer__footer">
            <a href="<?= $basePath ?>/notifications" class="govuk-link">View all notifications</a>
            <button type="button" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" onclick="window.nexusNotifications?.markAllRead(this)">
                Mark all as read
            </button>
        </div>
    </aside>

    <!-- Utility Bar Dropdown JavaScript -->
    <script>
    (function() {
        // Dropdown toggle functionality
        const dropdownButtons = document.querySelectorAll('.civicone-utility-item--dropdown > button');

        dropdownButtons.forEach(function(btn) {
            const dropdown = btn.nextElementSibling;
            if (!dropdown) return;

            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const isOpen = btn.getAttribute('aria-expanded') === 'true';

                // Close all other dropdowns
                dropdownButtons.forEach(function(otherBtn) {
                    otherBtn.setAttribute('aria-expanded', 'false');
                    const otherDropdown = otherBtn.nextElementSibling;
                    if (otherDropdown) otherDropdown.setAttribute('hidden', '');
                });

                // Toggle current
                if (!isOpen) {
                    btn.setAttribute('aria-expanded', 'true');
                    dropdown.removeAttribute('hidden');
                }
            });

            // Keyboard navigation
            btn.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && btn.getAttribute('aria-expanded') === 'true') {
                    btn.setAttribute('aria-expanded', 'false');
                    dropdown.setAttribute('hidden', '');
                    btn.focus();
                }
            });

            dropdown.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    btn.setAttribute('aria-expanded', 'false');
                    dropdown.setAttribute('hidden', '');
                    btn.focus();
                }
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.civicone-utility-item--dropdown')) {
                dropdownButtons.forEach(function(btn) {
                    btn.setAttribute('aria-expanded', 'false');
                    const dropdown = btn.nextElementSibling;
                    if (dropdown) dropdown.setAttribute('hidden', '');
                });
            }
        });

        // Notifications drawer
        const notifDrawer = document.getElementById('notif-drawer');
        const notifOverlay = document.getElementById('notif-drawer-overlay');
        const notifCloseBtn = notifDrawer?.querySelector('.civicone-drawer__close');

        window.nexusNotifDrawer = {
            open: function() {
                if (notifDrawer && notifOverlay) {
                    notifDrawer.removeAttribute('hidden');
                    notifOverlay.removeAttribute('hidden');
                    notifDrawer.focus();
                }
            },
            close: function() {
                if (notifDrawer && notifOverlay) {
                    notifDrawer.setAttribute('hidden', '');
                    notifOverlay.setAttribute('hidden', '');
                }
            }
        };

        document.querySelectorAll('[data-action="open-notifications"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                window.nexusNotifDrawer.open();
            });
        });

        if (notifOverlay) {
            notifOverlay.addEventListener('click', function() {
                window.nexusNotifDrawer.close();
            });
        }

        if (notifCloseBtn) {
            notifCloseBtn.addEventListener('click', function() {
                window.nexusNotifDrawer.close();
            });
        }

        // Contrast toggle
        const contrastToggle = document.getElementById('contrast-toggle');
        if (contrastToggle) {
            contrastToggle.addEventListener('click', function() {
                const isPressed = this.getAttribute('aria-pressed') === 'true';
                this.setAttribute('aria-pressed', !isPressed);
                document.documentElement.classList.toggle('high-contrast', !isPressed);
                localStorage.setItem('civicone-high-contrast', !isPressed ? 'true' : 'false');
            });

            // Restore preference
            if (localStorage.getItem('civicone-high-contrast') === 'true') {
                contrastToggle.setAttribute('aria-pressed', 'true');
                document.documentElement.classList.add('high-contrast');
            }
        }
    })();
    </script>
    <?php endif; ?>
