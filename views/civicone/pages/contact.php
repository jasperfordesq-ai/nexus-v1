<?php
/**
 * Contact Page - GOV.UK Design System
 * Template D: Form/Flow
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$pageTitle = 'Contact us';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="govuk-width-container">

    <!-- Breadcrumbs -->
    <nav class="govuk-breadcrumbs" aria-label="Breadcrumb">
        <ol class="govuk-breadcrumbs__list">
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
            </li>
            <li class="govuk-breadcrumbs__list-item" aria-current="page">
                Contact us
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h1 class="govuk-heading-xl">Contact us</h1>

                <p class="govuk-body-l">
                    We'd love to hear from you. Whether you have questions about timebanking,
                    need support, or just want to say hello.
                </p>

                <?php if (isset($_GET['success'])): ?>
                <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Success</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">
                            Your message has been sent successfully.
                        </p>
                        <p class="govuk-body">We'll get back to you within 2 working days.</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" data-module="govuk-error-summary">
                    <h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>
                    <div class="govuk-error-summary__body">
                        <ul class="govuk-list govuk-error-summary__list">
                            <li>
                                <a href="#message"><?= htmlspecialchars($_GET['error'] ?? 'Please check your message') ?></a>
                            </li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <form action="<?= $basePath ?>/contact/submit" method="POST" novalidate>
                    <?= Csrf::input() ?>

                    <!-- Name -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--m" for="name">
                            Your name
                        </label>
                        <input class="govuk-input"
                               id="name"
                               name="name"
                               type="text"
                               autocomplete="name"
                               value="<?= isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '' ?>"
                               required>
                    </div>

                    <!-- Email -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--m" for="email">
                            Email address
                        </label>
                        <div id="email-hint" class="govuk-hint">
                            We'll use this to reply to your message
                        </div>
                        <input class="govuk-input"
                               id="email"
                               name="email"
                               type="email"
                               autocomplete="email"
                               aria-describedby="email-hint"
                               spellcheck="false"
                               value="<?= isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : '' ?>"
                               required>
                    </div>

                    <!-- Subject -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--m" for="subject">
                            Subject
                        </label>
                        <select class="govuk-select" id="subject" name="subject">
                            <option value="General Inquiry">General inquiry</option>
                            <option value="Support">Support request</option>
                            <option value="Partnership">Partnership</option>
                            <option value="Feedback">Feedback</option>
                        </select>
                    </div>

                    <!-- Message -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--m" for="message">
                            Message
                        </label>
                        <div id="message-hint" class="govuk-hint">
                            Please include as much detail as possible
                        </div>
                        <textarea class="govuk-textarea"
                                  id="message"
                                  name="message"
                                  rows="5"
                                  aria-describedby="message-hint"
                                  required></textarea>
                    </div>

                    <button type="submit" class="govuk-button" data-module="govuk-button">
                        Send message
                    </button>

                </form>

            </div>

            <!-- Sidebar with contact info -->
            <div class="govuk-grid-column-one-third">
                <aside class="govuk-!-margin-top-6" role="complementary">

                    <h2 class="govuk-heading-m">Other ways to contact us</h2>

                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Address</h3>
                    <p class="govuk-body">
                        hOUR Timebank CLG<br>
                        Main Street, Skibbereen<br>
                        Co. Cork, Ireland
                    </p>

                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Email</h3>
                    <p class="govuk-body">
                        <a href="mailto:hello@hourtimebank.ie" class="govuk-link">hello@hourtimebank.ie</a>
                    </p>

                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Response times</h3>
                    <p class="govuk-body">
                        We aim to respond to all enquiries within 2 working days.
                    </p>

                    <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">

                    <h2 class="govuk-heading-s">Related links</h2>
                    <ul class="govuk-list">
                        <li>
                            <a href="<?= $basePath ?>/help" class="govuk-link">Help centre</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/faq" class="govuk-link">Frequently asked questions</a>
                        </li>
                    </ul>

                </aside>
            </div>
        </div>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
