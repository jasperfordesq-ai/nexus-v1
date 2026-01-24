<?php
/**
 * Cookie Policy Page - CivicOne Theme (GOV.UK Design System)
 * WCAG 2.1 AA Compliant
 */

use Nexus\Core\TenantContext;

$pageTitle = $pageTitle ?? 'Cookie Policy';
$basePath = TenantContext::getBasePath();

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <a href="<?= htmlspecialchars($basePath) ?>/legal" class="govuk-back-link">Back to Legal Hub</a>

    <main class="govuk-main-wrapper" id="main-content" role="main">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h1 class="govuk-heading-xl">
                    Cookies
                </h1>

                <p class="govuk-body-l">
                    <?= htmlspecialchars($tenantName) ?> puts small files (known as 'cookies') onto your computer
                    to collect information about how you browse the site.
                </p>

                <p class="govuk-body">
                    Last updated: <strong><?= htmlspecialchars($lastUpdated) ?></strong>
                </p>

                <!-- What Are Cookies -->
                <h2 class="govuk-heading-m">What are cookies?</h2>

                <p class="govuk-body">
                    Cookies are small text files that are placed on your device when you visit a website.
                    Websites use cookies to:
                </p>

                <ul class="govuk-list govuk-list--bullet">
                    <li>make the website work</li>
                    <li>remember your settings</li>
                    <li>improve how the website works</li>
                    <li>understand how people use the website</li>
                </ul>

                <!-- Manage Cookie Preferences -->
                <div class="govuk-inset-text">
                    You can <a href="<?= htmlspecialchars($basePath) ?>/cookie-preferences" class="govuk-link">change your cookie settings</a> at any time.
                </div>

                <!-- Essential Cookies -->
                <?php if (!empty($cookies['essential'])): ?>
                <h2 class="govuk-heading-m">Essential cookies</h2>

                <p class="govuk-body">
                    Essential cookies keep your information secure while you use <?= htmlspecialchars($tenantName) ?>.
                    We do not need to ask permission to use them.
                </p>

                <table class="govuk-table">
                    <caption class="govuk-table__caption govuk-table__caption--s">
                        <?= htmlspecialchars($tenantName) ?> essential cookies
                    </caption>
                    <thead class="govuk-table__head">
                        <tr class="govuk-table__row">
                            <th scope="col" class="govuk-table__header">Name</th>
                            <th scope="col" class="govuk-table__header">Purpose</th>
                            <th scope="col" class="govuk-table__header">Expires</th>
                        </tr>
                    </thead>
                    <tbody class="govuk-table__body">
                        <?php foreach ($cookies['essential'] as $cookie): ?>
                        <tr class="govuk-table__row">
                            <th scope="row" class="govuk-table__header">
                                <code><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                            </th>
                            <td class="govuk-table__cell">
                                <?= htmlspecialchars($cookie['purpose']) ?>
                            </td>
                            <td class="govuk-table__cell">
                                <?= htmlspecialchars($cookie['duration']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- Functional Cookies -->
                <?php if (!empty($cookies['functional'])): ?>
                <h2 class="govuk-heading-m">Functional cookies (optional)</h2>

                <p class="govuk-body">
                    Functional cookies help us make <?= htmlspecialchars($tenantName) ?> more useful by remembering
                    your settings. We ask for your consent before using these cookies.
                </p>

                <table class="govuk-table">
                    <caption class="govuk-table__caption govuk-table__caption--s">
                        <?= htmlspecialchars($tenantName) ?> functional cookies
                    </caption>
                    <thead class="govuk-table__head">
                        <tr class="govuk-table__row">
                            <th scope="col" class="govuk-table__header">Name</th>
                            <th scope="col" class="govuk-table__header">Purpose</th>
                            <th scope="col" class="govuk-table__header">Expires</th>
                        </tr>
                    </thead>
                    <tbody class="govuk-table__body">
                        <?php foreach ($cookies['functional'] as $cookie): ?>
                        <tr class="govuk-table__row">
                            <th scope="row" class="govuk-table__header">
                                <code><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                            </th>
                            <td class="govuk-table__cell">
                                <?= htmlspecialchars($cookie['purpose']) ?>
                            </td>
                            <td class="govuk-table__cell">
                                <?= htmlspecialchars($cookie['duration']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- Analytics Cookies -->
                <?php if (!empty($cookies['analytics'])): ?>
                <h2 class="govuk-heading-m">Analytics cookies (optional)</h2>

                <p class="govuk-body">
                    With your permission, we use analytics software to collect data about how you use
                    <?= htmlspecialchars($tenantName) ?>. This information helps us to improve our service.
                </p>

                <p class="govuk-body">
                    Analytics software stores information about:
                </p>

                <ul class="govuk-list govuk-list--bullet">
                    <li>the pages you visit</li>
                    <li>how long you spend on each page</li>
                    <li>how you got to the site</li>
                    <li>what you click on while you're visiting the site</li>
                </ul>

                <p class="govuk-body">
                    We do not collect or store your personal information (for example your name or address)
                    so this information cannot be used to identify who you are.
                </p>

                <table class="govuk-table">
                    <caption class="govuk-table__caption govuk-table__caption--s">
                        <?= htmlspecialchars($tenantName) ?> analytics cookies
                    </caption>
                    <thead class="govuk-table__head">
                        <tr class="govuk-table__row">
                            <th scope="col" class="govuk-table__header">Name</th>
                            <th scope="col" class="govuk-table__header">Purpose</th>
                            <th scope="col" class="govuk-table__header">Expires</th>
                        </tr>
                    </thead>
                    <tbody class="govuk-table__body">
                        <?php foreach ($cookies['analytics'] as $cookie): ?>
                        <tr class="govuk-table__row">
                            <th scope="row" class="govuk-table__header">
                                <code><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                            </th>
                            <td class="govuk-table__cell">
                                <?= htmlspecialchars($cookie['purpose']) ?>
                            </td>
                            <td class="govuk-table__cell">
                                <?= htmlspecialchars($cookie['duration']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- Marketing Cookies -->
                <?php if (!empty($cookies['marketing'])): ?>
                <h2 class="govuk-heading-m">Marketing cookies (optional)</h2>

                <p class="govuk-body">
                    We may use marketing cookies to show you relevant content based on your interests.
                    We ask for your consent before using these cookies.
                </p>

                <table class="govuk-table">
                    <caption class="govuk-table__caption govuk-table__caption--s">
                        <?= htmlspecialchars($tenantName) ?> marketing cookies
                    </caption>
                    <thead class="govuk-table__head">
                        <tr class="govuk-table__row">
                            <th scope="col" class="govuk-table__header">Name</th>
                            <th scope="col" class="govuk-table__header">Purpose</th>
                            <th scope="col" class="govuk-table__header">Expires</th>
                        </tr>
                    </thead>
                    <tbody class="govuk-table__body">
                        <?php foreach ($cookies['marketing'] as $cookie): ?>
                        <tr class="govuk-table__row">
                            <th scope="row" class="govuk-table__header">
                                <code><?= htmlspecialchars($cookie['cookie_name']) ?></code>
                            </th>
                            <td class="govuk-table__cell">
                                <?= htmlspecialchars($cookie['purpose']) ?>
                            </td>
                            <td class="govuk-table__cell">
                                <?= htmlspecialchars($cookie['duration']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- How to Control Cookies -->
                <h2 class="govuk-heading-m">How to control cookies</h2>

                <p class="govuk-body">
                    You can control and manage cookies in several ways:
                </p>

                <h3 class="govuk-heading-s">Cookie settings on this website</h3>

                <p class="govuk-body">
                    You can <a href="<?= htmlspecialchars($basePath) ?>/cookie-preferences" class="govuk-link">change your cookie settings</a>
                    at any time to enable or disable optional cookies.
                </p>

                <h3 class="govuk-heading-s">Browser settings</h3>

                <p class="govuk-body">
                    Most web browsers allow some control of cookies through the browser settings.
                    Find out how to manage cookies on popular browsers:
                </p>

                <ul class="govuk-list govuk-list--bullet">
                    <li><a href="https://support.google.com/chrome/answer/95647" class="govuk-link" rel="noopener noreferrer" target="_blank">Google Chrome (opens in new tab)</a></li>
                    <li><a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer" class="govuk-link" rel="noopener noreferrer" target="_blank">Mozilla Firefox (opens in new tab)</a></li>
                    <li><a href="https://support.microsoft.com/en-us/windows/manage-cookies-in-microsoft-edge-view-allow-block-delete-and-use-168dab11-0753-043d-7c16-ede5947fc64d" class="govuk-link" rel="noopener noreferrer" target="_blank">Microsoft Edge (opens in new tab)</a></li>
                    <li><a href="https://support.apple.com/en-gb/guide/safari/sfri11471/mac" class="govuk-link" rel="noopener noreferrer" target="_blank">Safari (opens in new tab)</a></li>
                </ul>

                <div class="govuk-warning-text">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text">
                        <span class="govuk-warning-text__assistive">Warning</span>
                        If you choose to disable cookies, some features of <?= htmlspecialchars($tenantName) ?>
                        may not work as expected. Essential cookies will still be used for security purposes.
                    </strong>
                </div>

                <!-- Further Information -->
                <h2 class="govuk-heading-m">Further information</h2>

                <p class="govuk-body">
                    For more information about cookies and how we use your data, read our
                    <a href="<?= htmlspecialchars($basePath) ?>/privacy" class="govuk-link">Privacy Policy</a>.
                </p>

                <p class="govuk-body">
                    If you have questions about cookies or how we use your data, you can
                    <a href="<?= htmlspecialchars($basePath) ?>/contact" class="govuk-link">contact us</a>.
                </p>

            </div>

            <!-- Sidebar -->
            <div class="govuk-grid-column-one-third">
                <aside class="govuk-related-navigation" role="complementary">
                    <h2 class="govuk-heading-s" id="related-nav-heading">
                        Related content
                    </h2>
                    <nav role="navigation" aria-labelledby="related-nav-heading">
                        <ul class="govuk-list govuk-!-font-size-16">
                            <li>
                                <a class="govuk-link" href="<?= htmlspecialchars($basePath) ?>/cookie-preferences">
                                    Manage cookie preferences
                                </a>
                            </li>
                            <li>
                                <a class="govuk-link" href="<?= htmlspecialchars($basePath) ?>/privacy">
                                    Privacy Policy
                                </a>
                            </li>
                            <li>
                                <a class="govuk-link" href="<?= htmlspecialchars($basePath) ?>/terms">
                                    Terms of Service
                                </a>
                            </li>
                            <li>
                                <a class="govuk-link" href="<?= htmlspecialchars($basePath) ?>/legal">
                                    Legal information
                                </a>
                            </li>
                        </ul>
                    </nav>
                </aside>
            </div>
        </div>
    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
