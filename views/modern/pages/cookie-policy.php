<?php
/**
 * Cookie Policy Page - Modern Theme
 * Displays comprehensive cookie information
 */

use Nexus\Core\TenantContext;

$pageTitle = $pageTitle ?? 'Cookie Policy';
$basePath = TenantContext::getBasePath();

require __DIR__ . '/../../layouts/modern/header.php';
?>

<div class="page-container">
    <div class="content-wrapper" style="max-width: 900px; margin: 0 auto; padding: 2rem 1rem;">

        <!-- Back Link -->
        <a href="<?= htmlspecialchars($basePath) ?>/legal" class="back-link" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--color-primary-600); text-decoration: none; margin-bottom: 2rem; font-weight: 500;">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Legal Hub
        </a>

        <!-- Page Header -->
        <div style="margin-bottom: 3rem;">
            <h1 style="font-size: 2.5rem; font-weight: 700; color: var(--color-text); margin: 0 0 1rem 0; display: flex; align-items: center; gap: 1rem;">
                <i class="fa-solid fa-cookie-bite" style="color: var(--color-primary-500); font-size: 2rem;"></i>
                Cookie Policy
            </h1>
            <p style="font-size: 1.125rem; color: var(--color-text-secondary); margin: 0 0 0.5rem 0;">
                Learn about the cookies we use and how to manage your preferences
            </p>
            <p style="font-size: 0.875rem; color: var(--color-text-secondary); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-calendar"></i>
                Last Updated: <?= htmlspecialchars($lastUpdated) ?>
            </p>
        </div>

        <!-- Quick Summary -->
        <div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1)); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; border-left: 4px solid var(--color-primary-500);">
            <h2 style="font-size: 1.25rem; font-weight: 600; margin: 0 0 1rem 0; color: var(--color-text);">
                <i class="fa-solid fa-info-circle"></i> Quick Summary
            </h2>
            <p style="margin: 0 0 0.75rem 0; line-height: 1.6; color: var(--color-text);">
                We use cookies to make <?= htmlspecialchars($tenantName) ?> work and improve your experience.
                You can control which cookies we use through your preferences.
            </p>
            <a href="<?= htmlspecialchars($basePath) ?>/cookie-preferences" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-sliders"></i>
                Manage Cookie Preferences
            </a>
        </div>

        <!-- What Are Cookies -->
        <section style="margin-bottom: 3rem;">
            <h2 style="font-size: 1.75rem; font-weight: 700; margin: 0 0 1rem 0; color: var(--color-text); display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-question-circle" style="color: var(--color-primary-500);"></i>
                What Are Cookies?
            </h2>
            <p style="line-height: 1.6; color: var(--color-text-secondary); margin-bottom: 1rem;">
                Cookies are small text files that are placed on your device when you visit a website.
                They help websites remember your preferences and understand how you use the site.
            </p>
            <p style="line-height: 1.6; color: var(--color-text-secondary);">
                Cookies can be "session" cookies (which are deleted when you close your browser) or
                "persistent" cookies (which remain on your device until they expire or you delete them).
            </p>
        </section>

        <!-- Cookies We Use -->
        <section style="margin-bottom: 3rem;">
            <h2 style="font-size: 1.75rem; font-weight: 700; margin: 0 0 1.5rem 0; color: var(--color-text); display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-list" style="color: var(--color-primary-500);"></i>
                Cookies We Use
            </h2>

            <!-- Essential Cookies -->
            <?php if (!empty($cookies['essential'])): ?>
            <div style="background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid var(--color-gray-200); box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <h3 style="font-size: 1.25rem; font-weight: 600; margin: 0 0 0.5rem 0; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-shield-halved" style="color: var(--color-primary-500);"></i>
                    Essential Cookies (<?= count($cookies['essential']) ?>)
                    <span style="background: var(--color-gray-200); color: var(--color-gray-700); font-size: 0.75rem; padding: 0.25rem 0.625rem; border-radius: 6px; font-weight: 600; text-transform: uppercase;">Required</span>
                </h3>
                <p style="color: var(--color-text-secondary); margin: 0 0 1rem 0; line-height: 1.6;">
                    These cookies are necessary for the website to function and cannot be switched off.
                    They are usually only set in response to actions made by you such as logging in or filling in forms.
                </p>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--color-gray-200);">
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Cookie Name</th>
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Purpose</th>
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Duration</th>
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cookies['essential'] as $cookie): ?>
                            <tr style="border-bottom: 1px solid var(--color-gray-100);">
                                <td style="padding: 0.75rem; font-weight: 500; color: var(--color-text);">
                                    <code style="background: var(--color-gray-100); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8125rem;"><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                                </td>
                                <td style="padding: 0.75rem; color: var(--color-text-secondary);"><?= htmlspecialchars($cookie['purpose']) ?></td>
                                <td style="padding: 0.75rem; color: var(--color-text-secondary); white-space: nowrap;"><?= htmlspecialchars($cookie['duration']) ?></td>
                                <td style="padding: 0.75rem; color: var(--color-text-secondary);"><?= htmlspecialchars($cookie['third_party']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Functional Cookies -->
            <?php if (!empty($cookies['functional'])): ?>
            <div style="background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid var(--color-gray-200); box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <h3 style="font-size: 1.25rem; font-weight: 600; margin: 0 0 0.5rem 0; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-gear" style="color: var(--color-primary-500);"></i>
                    Functional Cookies (<?= count($cookies['functional']) ?>)
                    <span style="background: var(--color-blue-100); color: var(--color-blue-700); font-size: 0.75rem; padding: 0.25rem 0.625rem; border-radius: 6px; font-weight: 600; text-transform: uppercase;">Optional</span>
                </h3>
                <p style="color: var(--color-text-secondary); margin: 0 0 1rem 0; line-height: 1.6;">
                    These cookies enable enhanced functionality and personalization, such as remembering your theme preference or language settings.
                </p>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--color-gray-200);">
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Cookie Name</th>
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Purpose</th>
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Duration</th>
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cookies['functional'] as $cookie): ?>
                            <tr style="border-bottom: 1px solid var(--color-gray-100);">
                                <td style="padding: 0.75rem; font-weight: 500; color: var(--color-text);">
                                    <code style="background: var(--color-gray-100); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8125rem;"><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                                </td>
                                <td style="padding: 0.75rem; color: var(--color-text-secondary);"><?= htmlspecialchars($cookie['purpose']) ?></td>
                                <td style="padding: 0.75rem; color: var(--color-text-secondary); white-space: nowrap;"><?= htmlspecialchars($cookie['duration']) ?></td>
                                <td style="padding: 0.75rem; color: var(--color-text-secondary);"><?= htmlspecialchars($cookie['third_party']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Analytics Cookies -->
            <?php if (!empty($cookies['analytics'])): ?>
            <div style="background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid var(--color-gray-200); box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <h3 style="font-size: 1.25rem; font-weight: 600; margin: 0 0 0.5rem 0; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-chart-line" style="color: var(--color-primary-500);"></i>
                    Analytics Cookies (<?= count($cookies['analytics']) ?>)
                    <span style="background: var(--color-blue-100); color: var(--color-blue-700); font-size: 0.75rem; padding: 0.25rem 0.625rem; border-radius: 6px; font-weight: 600; text-transform: uppercase;">Optional</span>
                </h3>
                <p style="color: var(--color-text-secondary); margin: 0 0 1rem 0; line-height: 1.6;">
                    These cookies help us understand how visitors interact with our website by collecting and reporting information anonymously.
                </p>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--color-gray-200);">
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Cookie Name</th>
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Purpose</th>
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Duration</th>
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cookies['analytics'] as $cookie): ?>
                            <tr style="border-bottom: 1px solid var(--color-gray-100);">
                                <td style="padding: 0.75rem; font-weight: 500; color: var(--color-text);">
                                    <code style="background: var(--color-gray-100); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8125rem;"><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                                </td>
                                <td style="padding: 0.75rem; color: var(--color-text-secondary);"><?= htmlspecialchars($cookie['purpose']) ?></td>
                                <td style="padding: 0.75rem; color: var(--color-text-secondary); white-space: nowrap;"><?= htmlspecialchars($cookie['duration']) ?></td>
                                <td style="padding: 0.75rem; color: var(--color-text-secondary);"><?= htmlspecialchars($cookie['third_party']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Marketing Cookies -->
            <?php if (!empty($cookies['marketing'])): ?>
            <div style="background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid var(--color-gray-200); box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <h3 style="font-size: 1.25rem; font-weight: 600; margin: 0 0 0.5rem 0; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-bullhorn" style="color: var(--color-primary-500);"></i>
                    Marketing Cookies (<?= count($cookies['marketing']) ?>)
                    <span style="background: var(--color-blue-100); color: var(--color-blue-700); font-size: 0.75rem; padding: 0.25rem 0.625rem; border-radius: 6px; font-weight: 600; text-transform: uppercase;">Optional</span>
                </h3>
                <p style="color: var(--color-text-secondary); margin: 0 0 1rem 0; line-height: 1.6;">
                    These cookies may be set through our site by our advertising partners to build a profile of your interests.
                </p>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--color-gray-200);">
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Cookie Name</th>
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Purpose</th>
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Duration</th>
                                <th style="text-align: left; padding: 0.75rem; font-weight: 600; color: var(--color-text);">Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cookies['marketing'] as $cookie): ?>
                            <tr style="border-bottom: 1px solid var(--color-gray-100);">
                                <td style="padding: 0.75rem; font-weight: 500; color: var(--color-text);">
                                    <code style="background: var(--color-gray-100); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8125rem;"><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                                </td>
                                <td style="padding: 0.75rem; color: var(--color-text-secondary);"><?= htmlspecialchars($cookie['purpose']) ?></td>
                                <td style="padding: 0.75rem; color: var(--color-text-secondary); white-space: nowrap;"><?= htmlspecialchars($cookie['duration']) ?></td>
                                <td style="padding: 0.75rem; color: var(--color-text-secondary);"><?= htmlspecialchars($cookie['third_party']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- How to Control Cookies -->
        <section style="margin-bottom: 3rem;">
            <h2 style="font-size: 1.75rem; font-weight: 700; margin: 0 0 1rem 0; color: var(--color-text); display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-sliders" style="color: var(--color-primary-500);"></i>
                How to Control Cookies
            </h2>
            <p style="line-height: 1.6; color: var(--color-text-secondary); margin-bottom: 1rem;">
                You can control and manage cookies in several ways:
            </p>
            <ul style="line-height: 1.8; color: var(--color-text-secondary); margin-bottom: 1.5rem;">
                <li><strong>Cookie Preferences:</strong> Use our <a href="<?= htmlspecialchars($basePath) ?>/cookie-preferences" style="color: var(--color-primary-600); text-decoration: underline;">Cookie Preferences</a> page to enable or disable specific cookie categories.</li>
                <li><strong>Browser Settings:</strong> Most browsers allow you to control cookies through their settings. You can set your browser to refuse all cookies or to indicate when a cookie is being sent.</li>
                <li><strong>Delete Cookies:</strong> You can delete all cookies that are already on your device through your browser settings.</li>
            </ul>
            <div style="background: var(--color-yellow-50); border-left: 4px solid var(--color-warning); padding: 1rem; border-radius: 8px;">
                <p style="margin: 0; color: var(--color-text); line-height: 1.6;">
                    <strong><i class="fa-solid fa-exclamation-triangle"></i> Note:</strong>
                    If you choose to disable cookies, some features of our website may not function properly.
                </p>
            </div>
        </section>

        <!-- Contact -->
        <section style="background: linear-gradient(135deg, var(--color-primary-500), var(--color-primary-600)); border-radius: 12px; padding: 2rem; text-align: center; color: white;">
            <h2 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 1rem 0;">
                <i class="fa-solid fa-envelope"></i> Questions About Cookies?
            </h2>
            <p style="margin: 0 0 1.5rem 0; line-height: 1.6; opacity: 0.95;">
                If you have any questions about our use of cookies, please don't hesitate to contact us.
            </p>
            <a href="<?= htmlspecialchars($basePath) ?>/contact" class="btn" style="background: white; color: var(--color-primary-600); display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; text-decoration: none;">
                <i class="fa-solid fa-paper-plane"></i>
                Contact Us
            </a>
        </section>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
