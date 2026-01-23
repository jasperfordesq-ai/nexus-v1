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

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card civic-mb-8 civic-text-center civic-p-8">
        <h1 style="margin-top: 0; margin-bottom: 15px; color: var(--skin-primary);">Social Prescribing Partner</h1>
        <p class="civic-text-xl civic-text-medium civic-max-w-lg civic-mx-auto civic-leading-relaxed">
            Evidence-Based, Community-Led, and 100% Effective for Wellbeing.
        </p>
    </div>

    <!-- Outcomes Section -->
    <div class="civic-card civic-p-10 civic-mb-10">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: center;">
            <div>
                <h2 style="color: var(--skin-primary); margin-top: 0; margin-bottom: 20px;">Validated Outcomes</h2>
                <ul class="civic-text-medium" style="list-style: none; padding: 0; font-size: 1.05rem;">
                    <li style="margin-bottom: 20px; position: relative; padding-left: 30px;">
                        <span style="position: absolute; left: 0; color: var(--skin-primary); font-weight: bold;">✔</span>
                        <strong>100% Improved Wellbeing:</strong> Every member surveyed reported an improvement in emotional, physical, or mental wellbeing.
                    </li>
                    <li style="margin-bottom: 20px; position: relative; padding-left: 30px;">
                        <span style="position: absolute; left: 0; color: var(--skin-primary); font-weight: bold;">✔</span>
                        <strong>95% Increased Connection:</strong> We are successfully tackling loneliness.
                    </li>
                    <li style="margin-bottom: 20px; position: relative; padding-left: 30px;">
                        <span style="position: absolute; left: 0; color: var(--skin-primary); font-weight: bold;">✔</span>
                        <strong>Strategic Fit:</strong> "Could become part of a social prescribing offering for early intervention".
                    </li>
                </ul>
            </div>
            <div class="civic-p-6 civic-rounded" style="background: rgba(37, 99, 235, 0.05); border-left: 4px solid var(--skin-primary);">
                <p class="civic-text-medium civic-text-lg civic-leading-relaxed civic-mb-3" style="font-style: italic;">
                    "Monica found out about TBI through the outreach mental health team... Since joining TBI, Monica 'feels much more connected to the community which has had a positive mental health impact'."
                </p>
                <div class="civic-text-right civic-font-bold" style="color: var(--skin-primary);">— Monica (Member)</div>
                <div class="civic-text-right civic-text-sm civic-text-light">Source: 2023 Social Impact Study</div>
            </div>
        </div>
    </div>

    <!-- Referral Pathway -->
    <div class="civic-mb-10">
        <h2 class="civic-text-center civic-mb-8 civic-text-dark">The Managed Referral Pathway</h2>

        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">

            <div class="civic-card civic-text-center civic-p-6" style="position: relative; padding-top: 50px;">
                <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); width: 40px; height: 40px; background: var(--skin-primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">1</div>
                <h3 class="civic-text-dark civic-mb-3">Formal Referral</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); font-size: 0.95rem; line-height: 1.5;">Warm handover from Link Worker to our TBI Hub Coordinator.</p>
            </div>

            <div class="civic-card civic-text-center civic-p-6" style="position: relative; padding-top: 50px;">
                <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); width: 40px; height: 40px; background: var(--skin-primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">2</div>
                <h3 class="civic-text-dark civic-mb-3">Onboarding</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); font-size: 0.95rem; line-height: 1.5;">1-to-1 welcome to explain the model and identify skills.</p>
            </div>

            <div class="civic-card civic-text-center civic-p-6" style="position: relative; padding-top: 50px;">
                <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); width: 40px; height: 40px; background: var(--skin-primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">3</div>
                <h3 class="civic-text-dark civic-mb-3">Connection</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); font-size: 0.95rem; line-height: 1.5;">Active facilitation of first exchanges and group activities.</p>
            </div>

            <div class="civic-card civic-text-center civic-p-6" style="position: relative; padding-top: 50px;">
                <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); width: 40px; height: 40px; background: var(--skin-primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">4</div>
                <h3 class="civic-text-dark civic-mb-3">Follow-up</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); font-size: 0.95rem; line-height: 1.5;">Feedback to Link Worker on engagement and outcomes.</p>
            </div>

        </div>
    </div>

    <!-- CTA -->
    <div class="civic-card civic-text-center" style="padding: 60px;">
        <h2 class="civic-text-dark civic-mt-0 civic-mb-4">We are Seeking a Partner to Launch a Formal Pilot</h2>
        <p class="civic-text-lg civic-text-medium civic-max-w-md civic-mx-auto civic-mb-6 civic-leading-relaxed">
            We are seeking a public sector contract to secure the essential Hub Coordinator role, which is the <strong>lynchpin of the entire service</strong>.
        </p>
        <a href="/uploads/tenants/hour-timebank/THE-RECIPROCITY-PATHWAY_A-Pilot-Proposal.pdf" target="_blank" class="civic-btn">Download Pilot Proposal</a>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>