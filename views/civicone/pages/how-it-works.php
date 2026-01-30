<?php
/**
 * How It Works Page - GOV.UK Design System
 * Template E: Content/Article
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = 'How it works';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
?>

<div class="govuk-width-container">

    <?= civicone_govuk_breadcrumbs([
        'items' => [
            ['text' => 'Home', 'href' => $basePath],
            ['text' => 'How it works']
        ],
        'class' => 'govuk-!-margin-bottom-6'
    ]) ?>

    <main class="govuk-main-wrapper" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h1 class="govuk-heading-xl">How timebanking works</h1>

                <p class="govuk-body-l">
                    Hour Timebank is a community currency system where time is the money.
                    Everyone's hour is worth the same, regardless of the service provided.
                </p>

            </div>
        </div>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <!-- The Three Steps -->
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-third">
                <h2 class="govuk-heading-l">1. Give time</h2>
                <p class="govuk-body">
                    Offer your skills or help to a neighbour. Whether it's gardening, teaching,
                    or tech support, your contribution matters.
                </p>
                <p class="govuk-body">
                    Everyone has something valuable to offer. Share what you know and enjoy doing.
                </p>
            </div>

            <div class="govuk-grid-column-one-third">
                <h2 class="govuk-heading-l">2. Earn credits</h2>
                <p class="govuk-body">
                    For every hour you give, you earn 1 time credit. It's banked automatically
                    in your digital wallet.
                </p>
                <p class="govuk-body">
                    Track your balance, view your transaction history, and see your impact on the community.
                </p>
            </div>

            <div class="govuk-grid-column-one-third">
                <h2 class="govuk-heading-l">3. Get help</h2>
                <p class="govuk-body">
                    Spend your credits to receive help from others. Learn a new language,
                    get a ride, or find a pet sitter.
                </p>
                <p class="govuk-body">
                    Browse listings, connect with members, and request the help you need.
                </p>
            </div>
        </div>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <!-- Key Principle -->
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h2 class="govuk-heading-l">The key principle</h2>

                <div class="govuk-inset-text">
                    <p class="govuk-body govuk-!-font-weight-bold">
                        One hour = one time credit, regardless of what service is provided.
                    </p>
                    <p class="govuk-body">
                        A professional lawyer's hour is worth the same as a teenager's hour of dog walking.
                        This is what makes timebanking different from traditional markets.
                    </p>
                </div>

                <h2 class="govuk-heading-l">What can you exchange?</h2>

                <p class="govuk-body">
                    Members exchange all kinds of services:
                </p>

                <ul class="govuk-list govuk-list--bullet">
                    <li>Home and garden help (repairs, gardening, cleaning)</li>
                    <li>Skills and teaching (languages, music, IT help)</li>
                    <li>Transport (lifts, shopping trips, errands)</li>
                    <li>Companionship and care (visiting, befriending, pet sitting)</li>
                    <li>Professional services (advice, admin, crafts)</li>
                    <li>And much more...</li>
                </ul>

            </div>

            <!-- Sidebar -->
            <div class="govuk-grid-column-one-third">
                <aside class="govuk-!-margin-top-6" role="complementary">

                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <h2 class="govuk-heading-m">Ready to join?</h2>
                        <p class="govuk-body">
                            Start sharing your time and skills with your community today.
                        </p>
                        <p class="govuk-body">
                            <a href="<?= $basePath ?>/register" class="govuk-button" data-module="govuk-button">
                                Join the movement
                            </a>
                        </p>
                        <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">
                    <?php endif; ?>

                    <h2 class="govuk-heading-s">Learn more</h2>
                    <ul class="govuk-list">
                        <li>
                            <a href="<?= $basePath ?>/faq" class="govuk-link">Frequently asked questions</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/about" class="govuk-link">About us</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/help" class="govuk-link">Help centre</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/contact" class="govuk-link">Contact us</a>
                        </li>
                    </ul>

                </aside>
            </div>
        </div>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
