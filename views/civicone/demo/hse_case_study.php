<?php
$pageTitle = "HSE Social Prescribing Case Study - Project NEXUS";
$hSubtitle = "Digital Pathways for Social Prescribing in Irish Healthcare";
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-demo-pages.css">

<main class="demo-page">

    <!-- HSE Branding Header -->
    <div class="demo-hero">
        <span class="demo-hero__label">Case Study: Healthcare</span>
        <h1 class="demo-hero__title">Reducing Healthcare Pressure via Community Action</h1>
        <p class="demo-hero__desc">A Community Healthcare Organisation (CHO) uses NEXUS to manage a network of "Wellness Volunteers."</p>
    </div>

    <div class="demo-two-col">

        <!-- Scenario -->
        <div>
            <h2 class="demo-section-heading demo-section-heading--green">The Scenario</h2>
            <p class="demo-text-body">
                GPs and Public Health Nurses often see patients whose primary complaints are rooted in isolation or lack of activity, rather than acute medical issues. The "Social Prescribing" model works, but tracking referrals and ensuring patient safety has historically been manual and paper-based.
            </p>
            <p class="demo-text-body">
                <strong>The Challenge:</strong> Connecting patients to trusted, vetted community groups without adding administrative burden to clinical staff.
            </p>
        </div>

        <!-- Solution -->
        <div class="demo-solution-box">
            <h3 class="demo-solution-box__title">The Result</h3>
            <ul class="demo-result-list" role="list">
                <li class="demo-result-list__item">
                    <span class="demo-result-list__icon" aria-hidden="true">🏥</span>
                    <span class="demo-result-list__text"><strong>Direct Referral:</strong> Patients are referred directly to community garden projects or walking groups via the NEXUS portal.</span>
                </li>
                <li class="demo-result-list__item">
                    <span class="demo-result-list__icon" aria-hidden="true">📊</span>
                    <span class="demo-result-list__text"><strong>Real-Time Data:</strong> The HSE can track engagement levels and "hours of support" generated.</span>
                </li>
                <li class="demo-result-list__item">
                    <span class="demo-result-list__icon" aria-hidden="true">💰</span>
                    <span class="demo-result-list__text"><strong>ROI Calculator:</strong> Clear visualisation of social value versus clinical hours saved.</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="demo-cta">
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="demo-cta__btn demo-cta__btn--green">View Live Volunteer Opportunities</a>
        <p class="demo-cta__note">Experience the user journey for a potential volunteer.</p>
    </div>

</main>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>