<?php
/**
 * Cookie Policy Page - Modern Theme
 * Displays comprehensive cookie information
 *
 * CSS extracted to: httpdocs/assets/css/modern-template-extracts.css
 * Section: views/modern/pages/cookie-policy.php
 */

use Nexus\Core\TenantContext;

$pageTitle = $pageTitle ?? 'Cookie Policy';
$basePath = TenantContext::getBasePath();

require __DIR__ . '/../../layouts/modern/header.php';
?>

<div class="page-container">
    <div class="content-wrapper mte-cookie-policy--wrapper">

        <!-- Back Link -->
        <a href="<?= htmlspecialchars($basePath) ?>/legal" class="back-link mte-cookie-policy--back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Legal Hub
        </a>

        <!-- Page Header -->
        <div class="mte-cookie-policy--header">
            <h1 class="mte-cookie-policy--title">
                <i class="fa-solid fa-cookie-bite mte-cookie-policy--title-icon"></i>
                Cookie Policy
            </h1>
            <p class="mte-cookie-policy--subtitle">
                Learn about the cookies we use and how to manage your preferences
            </p>
            <p class="mte-cookie-policy--last-updated">
                <i class="fa-solid fa-calendar"></i>
                Last Updated: <?= htmlspecialchars($lastUpdated) ?>
            </p>
        </div>

        <!-- Quick Summary -->
        <div class="mte-cookie-policy--summary-box">
            <h2 class="mte-cookie-policy--summary-title">
                <i class="fa-solid fa-info-circle"></i> Quick Summary
            </h2>
            <p class="mte-cookie-policy--summary-text">
                We use cookies to make <?= htmlspecialchars($tenantName) ?> work and improve your experience.
                You can control which cookies we use through your preferences.
            </p>
            <a href="<?= htmlspecialchars($basePath) ?>/cookie-preferences" class="btn btn-primary">
                <i class="fa-solid fa-sliders"></i>
                Manage Cookie Preferences
            </a>
        </div>

        <!-- What Are Cookies -->
        <section class="mte-cookie-policy--section">
            <h2 class="mte-cookie-policy--section-title">
                <i class="fa-solid fa-question-circle mte-cookie-policy--section-title-icon"></i>
                What Are Cookies?
            </h2>
            <p class="mte-cookie-policy--section-text">
                Cookies are small text files that are placed on your device when you visit a website.
                They help websites remember your preferences and understand how you use the site.
            </p>
            <p class="mte-cookie-policy--section-text">
                Cookies can be "session" cookies (which are deleted when you close your browser) or
                "persistent" cookies (which remain on your device until they expire or you delete them).
            </p>
        </section>

        <!-- Cookies We Use -->
        <section class="mte-cookie-policy--section">
            <h2 class="mte-cookie-policy--section-title">
                <i class="fa-solid fa-list mte-cookie-policy--section-title-icon"></i>
                Cookies We Use
            </h2>

            <!-- Essential Cookies -->
            <?php if (!empty($cookies['essential'])): ?>
            <div class="mte-cookie-policy--category-card">
                <h3 class="mte-cookie-policy--category-header">
                    <i class="fa-solid fa-shield-halved mte-cookie-policy--category-icon"></i>
                    Essential Cookies (<?= count($cookies['essential']) ?>)
                    <span class="mte-cookie-policy--badge mte-cookie-policy--badge-required">Required</span>
                </h3>
                <p class="mte-cookie-policy--category-desc">
                    These cookies are necessary for the website to function and cannot be switched off.
                    They are usually only set in response to actions made by you such as logging in or filling in forms.
                </p>

                <div class="mte-cookie-policy--table-wrapper">
                    <table class="mte-cookie-policy--table">
                        <thead>
                            <tr>
                                <th>Cookie Name</th>
                                <th>Purpose</th>
                                <th>Duration</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cookies['essential'] as $cookie): ?>
                            <tr>
                                <td>
                                    <code class="mte-cookie-policy--cookie-name"><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                                </td>
                                <td><?= htmlspecialchars($cookie['purpose']) ?></td>
                                <td class="mte-cookie-policy--table-nowrap"><?= htmlspecialchars($cookie['duration']) ?></td>
                                <td><?= htmlspecialchars($cookie['third_party']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Functional Cookies -->
            <?php if (!empty($cookies['functional'])): ?>
            <div class="mte-cookie-policy--category-card">
                <h3 class="mte-cookie-policy--category-header">
                    <i class="fa-solid fa-gear mte-cookie-policy--category-icon"></i>
                    Functional Cookies (<?= count($cookies['functional']) ?>)
                    <span class="mte-cookie-policy--badge mte-cookie-policy--badge-optional">Optional</span>
                </h3>
                <p class="mte-cookie-policy--category-desc">
                    These cookies enable enhanced functionality and personalization, such as remembering your theme preference or language settings.
                </p>

                <div class="mte-cookie-policy--table-wrapper">
                    <table class="mte-cookie-policy--table">
                        <thead>
                            <tr>
                                <th>Cookie Name</th>
                                <th>Purpose</th>
                                <th>Duration</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cookies['functional'] as $cookie): ?>
                            <tr>
                                <td>
                                    <code class="mte-cookie-policy--cookie-name"><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                                </td>
                                <td><?= htmlspecialchars($cookie['purpose']) ?></td>
                                <td class="mte-cookie-policy--table-nowrap"><?= htmlspecialchars($cookie['duration']) ?></td>
                                <td><?= htmlspecialchars($cookie['third_party']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Analytics Cookies -->
            <?php if (!empty($cookies['analytics'])): ?>
            <div class="mte-cookie-policy--category-card">
                <h3 class="mte-cookie-policy--category-header">
                    <i class="fa-solid fa-chart-line mte-cookie-policy--category-icon"></i>
                    Analytics Cookies (<?= count($cookies['analytics']) ?>)
                    <span class="mte-cookie-policy--badge mte-cookie-policy--badge-optional">Optional</span>
                </h3>
                <p class="mte-cookie-policy--category-desc">
                    These cookies help us understand how visitors interact with our website by collecting and reporting information anonymously.
                </p>

                <div class="mte-cookie-policy--table-wrapper">
                    <table class="mte-cookie-policy--table">
                        <thead>
                            <tr>
                                <th>Cookie Name</th>
                                <th>Purpose</th>
                                <th>Duration</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cookies['analytics'] as $cookie): ?>
                            <tr>
                                <td>
                                    <code class="mte-cookie-policy--cookie-name"><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                                </td>
                                <td><?= htmlspecialchars($cookie['purpose']) ?></td>
                                <td class="mte-cookie-policy--table-nowrap"><?= htmlspecialchars($cookie['duration']) ?></td>
                                <td><?= htmlspecialchars($cookie['third_party']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Marketing Cookies -->
            <?php if (!empty($cookies['marketing'])): ?>
            <div class="mte-cookie-policy--category-card">
                <h3 class="mte-cookie-policy--category-header">
                    <i class="fa-solid fa-bullhorn mte-cookie-policy--category-icon"></i>
                    Marketing Cookies (<?= count($cookies['marketing']) ?>)
                    <span class="mte-cookie-policy--badge mte-cookie-policy--badge-optional">Optional</span>
                </h3>
                <p class="mte-cookie-policy--category-desc">
                    These cookies may be set through our site by our advertising partners to build a profile of your interests.
                </p>

                <div class="mte-cookie-policy--table-wrapper">
                    <table class="mte-cookie-policy--table">
                        <thead>
                            <tr>
                                <th>Cookie Name</th>
                                <th>Purpose</th>
                                <th>Duration</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cookies['marketing'] as $cookie): ?>
                            <tr>
                                <td>
                                    <code class="mte-cookie-policy--cookie-name"><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                                </td>
                                <td><?= htmlspecialchars($cookie['purpose']) ?></td>
                                <td class="mte-cookie-policy--table-nowrap"><?= htmlspecialchars($cookie['duration']) ?></td>
                                <td><?= htmlspecialchars($cookie['third_party']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- How to Control Cookies -->
        <section class="mte-cookie-policy--section">
            <h2 class="mte-cookie-policy--section-title">
                <i class="fa-solid fa-sliders mte-cookie-policy--section-title-icon"></i>
                How to Control Cookies
            </h2>
            <p class="mte-cookie-policy--section-text">
                You can control and manage cookies in several ways:
            </p>
            <ul class="mte-cookie-policy--list">
                <li><strong>Cookie Preferences:</strong> Use our <a href="<?= htmlspecialchars($basePath) ?>/cookie-preferences">Cookie Preferences</a> page to enable or disable specific cookie categories.</li>
                <li><strong>Browser Settings:</strong> Most browsers allow you to control cookies through their settings. You can set your browser to refuse all cookies or to indicate when a cookie is being sent.</li>
                <li><strong>Delete Cookies:</strong> You can delete all cookies that are already on your device through your browser settings.</li>
            </ul>
            <div class="mte-cookie-policy--warning-box">
                <p class="mte-cookie-policy--warning-text">
                    <strong><i class="fa-solid fa-exclamation-triangle"></i> Note:</strong>
                    If you choose to disable cookies, some features of our website may not function properly.
                </p>
            </div>
        </section>

        <!-- Contact -->
        <section class="mte-cookie-policy--cta-section">
            <h2 class="mte-cookie-policy--cta-title">
                <i class="fa-solid fa-envelope"></i> Questions About Cookies?
            </h2>
            <p class="mte-cookie-policy--cta-text">
                If you have any questions about our use of cookies, please don't hesitate to contact us.
            </p>
            <a href="<?= htmlspecialchars($basePath) ?>/contact" class="mte-cookie-policy--cta-btn">
                <i class="fa-solid fa-paper-plane"></i>
                Contact Us
            </a>
        </section>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
