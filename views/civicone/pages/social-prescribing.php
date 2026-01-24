<?php
// CivicOne View: Social Prescribing
// Tenant-specific: Hour Timebank only
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'Social Prescribing';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-demo-pages.css">

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card civic-mb-8 civic-text-center civic-p-8">
        <h1 class="civic-text-primary civic-mt-0 civic-mb-4">Social Prescribing Partner</h1>
        <p class="civic-text-xl civic-text-medium civic-max-w-lg civic-mx-auto civic-leading-relaxed">
            Evidence-Based, Community-Led, and 100% Effective for Wellbeing.
        </p>
    </div>

    <!-- Outcomes Section -->
    <div class="civic-card civic-p-10 civic-mb-10">
        <div class="demo-two-col demo-two-col--align-center">
            <div>
                <h2 class="civic-text-primary civic-mt-0 civic-mb-5">Validated Outcomes</h2>
                <ul class="demo-outcomes-list" role="list">
                    <li class="demo-outcomes-list__item">
                        <span class="demo-outcomes-list__icon" aria-hidden="true">✔</span>
                        <span><strong>100% Improved Wellbeing:</strong> Every member surveyed reported an improvement in emotional, physical, or mental wellbeing.</span>
                    </li>
                    <li class="demo-outcomes-list__item">
                        <span class="demo-outcomes-list__icon" aria-hidden="true">✔</span>
                        <span><strong>95% Increased Connection:</strong> We are successfully tackling loneliness.</span>
                    </li>
                    <li class="demo-outcomes-list__item">
                        <span class="demo-outcomes-list__icon" aria-hidden="true">✔</span>
                        <span><strong>Strategic Fit:</strong> "Could become part of a social prescribing offering for early intervention".</span>
                    </li>
                </ul>
            </div>
            <blockquote class="demo-testimonial">
                <p class="demo-testimonial__quote">
                    "Monica found out about TBI through the outreach mental health team... Since joining TBI, Monica 'feels much more connected to the community which has had a positive mental health impact'."
                </p>
                <footer class="demo-testimonial__footer">
                    <cite class="demo-testimonial__author">— Monica (Member)</cite>
                    <span class="demo-testimonial__source">Source: 2023 Social Impact Study</span>
                </footer>
            </blockquote>
        </div>
    </div>

    <!-- Referral Pathway -->
    <section class="civic-mb-10" aria-label="Referral pathway steps">
        <h2 class="civic-text-center civic-mb-8 civic-text-dark">The Managed Referral Pathway</h2>

        <div class="demo-pathway-grid">

            <article class="demo-pathway-step">
                <span class="demo-pathway-step__number" aria-hidden="true">1</span>
                <h3 class="demo-pathway-step__title">Formal Referral</h3>
                <p class="demo-pathway-step__desc">Warm handover from Link Worker to our TBI Hub Coordinator.</p>
            </article>

            <article class="demo-pathway-step">
                <span class="demo-pathway-step__number" aria-hidden="true">2</span>
                <h3 class="demo-pathway-step__title">Onboarding</h3>
                <p class="demo-pathway-step__desc">1-to-1 welcome to explain the model and identify skills.</p>
            </article>

            <article class="demo-pathway-step">
                <span class="demo-pathway-step__number" aria-hidden="true">3</span>
                <h3 class="demo-pathway-step__title">Connection</h3>
                <p class="demo-pathway-step__desc">Active facilitation of first exchanges and group activities.</p>
            </article>

            <article class="demo-pathway-step">
                <span class="demo-pathway-step__number" aria-hidden="true">4</span>
                <h3 class="demo-pathway-step__title">Follow-up</h3>
                <p class="demo-pathway-step__desc">Feedback to Link Worker on engagement and outcomes.</p>
            </article>

        </div>
    </section>

    <!-- CTA -->
    <div class="civic-card civic-text-center civic-p-12">
        <h2 class="civic-text-dark civic-mt-0 civic-mb-4">We are Seeking a Partner to Launch a Formal Pilot</h2>
        <p class="civic-text-lg civic-text-medium civic-max-w-md civic-mx-auto civic-mb-6 civic-leading-relaxed">
            We are seeking a public sector contract to secure the essential Hub Coordinator role, which is the <strong>lynchpin of the entire service</strong>.
        </p>
        <a href="/uploads/tenants/hour-timebank/THE-RECIPROCITY-PATHWAY_A-Pilot-Proposal.pdf" target="_blank" class="civic-btn">Download Pilot Proposal</a>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>