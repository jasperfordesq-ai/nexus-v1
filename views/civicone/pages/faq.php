<?php
/**
 * FAQ Page - GOV.UK Design System
 * Template E: Content/Article
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;

// Tenant-specific: Hour Timebank only
$tSlug = TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$basePath = TenantContext::getBasePath();
$pageTitle = 'Frequently asked questions';

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
                Frequently asked questions
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h1 class="govuk-heading-xl">Frequently asked questions</h1>

                <p class="govuk-body-l">
                    Everything you need to know about timebanking and how it works.
                </p>

            </div>
        </div>

        <!-- FAQ Accordion -->
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <div class="govuk-accordion" data-module="govuk-accordion" id="accordion-faq">

                    <!-- What is Timebanking? -->
                    <div class="govuk-accordion__section">
                        <div class="govuk-accordion__section-header">
                            <h2 class="govuk-accordion__section-heading">
                                <span class="govuk-accordion__section-button" id="accordion-faq-heading-1">
                                    What is timebanking?
                                </span>
                            </h2>
                        </div>
                        <div id="accordion-faq-content-1" class="govuk-accordion__section-content">
                            <p class="govuk-body">
                                Timebanking is a system of mutual service exchange that uses units of time as currency.
                                The underlying principle is that everyone's time is equally valuable.
                            </p>
                            <p class="govuk-body">
                                It helps build stronger communities by fostering cooperation and support,
                                transcending traditional monetary transactions.
                            </p>
                        </div>
                    </div>

                    <!-- Who can join? -->
                    <div class="govuk-accordion__section">
                        <div class="govuk-accordion__section-header">
                            <h2 class="govuk-accordion__section-heading">
                                <span class="govuk-accordion__section-button" id="accordion-faq-heading-2">
                                    Who can join a timebank?
                                </span>
                            </h2>
                        </div>
                        <div id="accordion-faq-content-2" class="govuk-accordion__section-content">
                            <p class="govuk-body">
                                Anyone can join. Whether you possess professional expertise, everyday life skills,
                                or unique hobbies, your talents are valued.
                            </p>
                            <p class="govuk-body">
                                Timebanks embrace diversity and recognise that every member has something valuable to offer.
                            </p>
                        </div>
                    </div>

                    <!-- What can I offer? -->
                    <div class="govuk-accordion__section">
                        <div class="govuk-accordion__section-header">
                            <h2 class="govuk-accordion__section-heading">
                                <span class="govuk-accordion__section-button" id="accordion-faq-heading-3">
                                    What services can I offer?
                                </span>
                            </h2>
                        </div>
                        <div id="accordion-faq-content-3" class="govuk-accordion__section-content">
                            <p class="govuk-body">
                                The possibilities are endless. You can offer:
                            </p>
                            <ul class="govuk-list govuk-list--bullet">
                                <li>Gardening and home repairs</li>
                                <li>Cooking and meal preparation</li>
                                <li>Companionship and befriending</li>
                                <li>Mentoring and tutoring</li>
                                <li>Music lessons</li>
                                <li>IT help and computer skills</li>
                                <li>Transportation and errands</li>
                                <li>Administrative tasks</li>
                            </ul>
                            <p class="govuk-body">
                                Offer what you genuinely enjoy and excel at.
                            </p>
                        </div>
                    </div>

                    <!-- How do Credits work? -->
                    <div class="govuk-accordion__section">
                        <div class="govuk-accordion__section-header">
                            <h2 class="govuk-accordion__section-heading">
                                <span class="govuk-accordion__section-button" id="accordion-faq-heading-4">
                                    How do time credits work?
                                </span>
                            </h2>
                        </div>
                        <div id="accordion-faq-content-4" class="govuk-accordion__section-content">
                            <p class="govuk-body">
                                When you spend an hour helping another member, you earn one <strong>time credit</strong>.
                            </p>
                            <p class="govuk-body">
                                You can use this credit to receive an hour of service from another member.
                                It's a reciprocal system promoting fairness and equality.
                            </p>
                            <div class="govuk-inset-text">
                                One hour given = one time credit earned, regardless of the type of service.
                            </div>
                        </div>
                    </div>

                    <!-- How do I join? -->
                    <div class="govuk-accordion__section">
                        <div class="govuk-accordion__section-header">
                            <h2 class="govuk-accordion__section-heading">
                                <span class="govuk-accordion__section-button" id="accordion-faq-heading-5">
                                    How do I join?
                                </span>
                            </h2>
                        </div>
                        <div id="accordion-faq-content-5" class="govuk-accordion__section-content">
                            <p class="govuk-body">
                                Joining is simple:
                            </p>
                            <ol class="govuk-list govuk-list--number">
                                <li>Register on our platform</li>
                                <li>Create your profile with your skills and interests</li>
                                <li>Start listing your offers and requests</li>
                                <li>Connect with other members in your community</li>
                            </ol>
                            <p class="govuk-body">
                                <a href="<?= $basePath ?>/register" class="govuk-link">Create your account</a> to get started.
                            </p>
                        </div>
                    </div>

                    <!-- Our Philosophy -->
                    <div class="govuk-accordion__section">
                        <div class="govuk-accordion__section-header">
                            <h2 class="govuk-accordion__section-heading">
                                <span class="govuk-accordion__section-button" id="accordion-faq-heading-6">
                                    What is the timebanking philosophy?
                                </span>
                            </h2>
                        </div>
                        <div id="accordion-faq-content-6" class="govuk-accordion__section-content">
                            <p class="govuk-body">
                                <strong>"Time is the most valuable currency."</strong>
                            </p>
                            <p class="govuk-body">
                                We celebrate inclusivity and the joy of giving and receiving.
                                Timebanking recognises that everyone has assets to share and needs to be met.
                            </p>
                            <p class="govuk-body">
                                It's about building community, not just exchanging services.
                            </p>
                        </div>
                    </div>

                </div>

            </div>

            <!-- Sidebar -->
            <div class="govuk-grid-column-one-third">
                <aside class="govuk-!-margin-top-6" role="complementary">

                    <h2 class="govuk-heading-m">Ready to get started?</h2>

                    <p class="govuk-body">
                        Join the movement and start enriching lives, one shared moment at a time.
                    </p>

                    <p class="govuk-body">
                        <a href="<?= $basePath ?>/register" class="govuk-button" data-module="govuk-button">
                            Become a member
                        </a>
                    </p>

                    <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">

                    <h2 class="govuk-heading-s">Need more help?</h2>
                    <ul class="govuk-list">
                        <li>
                            <a href="<?= $basePath ?>/help" class="govuk-link">Help centre</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/contact" class="govuk-link">Contact us</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/about" class="govuk-link">About us</a>
                        </li>
                    </ul>

                </aside>
            </div>
        </div>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
