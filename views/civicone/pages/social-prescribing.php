<?php
// CivicOne View: Social Prescribing
$pageTitle = 'Social Prescribing';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card" style="margin-bottom: 40px; text-align: center; padding: 40px;">
        <h1 style="margin-top: 0; margin-bottom: 15px; color: var(--skin-primary);">Social Prescribing Partner</h1>
        <p style="font-size: 1.2rem; color: #555; max-width: 800px; margin: 0 auto; line-height: 1.6;">
            Evidence-Based, Community-Led, and 100% Effective for Wellbeing.
        </p>
    </div>

    <!-- Outcomes Section -->
    <div class="civic-card" style="padding: 50px; margin-bottom: 50px;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: center;">
            <div>
                <h2 style="color: var(--skin-primary); margin-top: 0; margin-bottom: 20px;">Validated Outcomes</h2>
                <ul style="list-style: none; padding: 0; font-size: 1.05rem; color: #555;">
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
            <div style="background: rgba(37, 99, 235, 0.05); padding: 30px; border-radius: 8px; border-left: 4px solid var(--skin-primary);">
                <p style="font-style: italic; color: #555; font-size: 1.1rem; line-height: 1.6; margin-bottom: 15px;">
                    "Monica found out about TBI through the outreach mental health team... Since joining TBI, Monica 'feels much more connected to the community which has had a positive mental health impact'."
                </p>
                <div style="text-align: right; font-weight: bold; color: var(--skin-primary);">— Monica (Member)</div>
                <div style="text-align: right; font-size: 0.8rem; color: #888;">Source: 2023 Social Impact Study</div>
            </div>
        </div>
    </div>

    <!-- Referral Pathway -->
    <div style="margin-bottom: 50px;">
        <h2 style="text-align: center; margin-bottom: 40px; color: #333;">The Managed Referral Pathway</h2>

        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">

            <div class="civic-card" style="text-align: center; padding: 30px; position: relative; padding-top: 50px;">
                <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); width: 40px; height: 40px; background: var(--skin-primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">1</div>
                <h3 style="color: #333; margin-bottom: 15px;">Formal Referral</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); font-size: 0.95rem; line-height: 1.5;">Warm handover from Link Worker to our TBI Hub Coordinator.</p>
            </div>

            <div class="civic-card" style="text-align: center; padding: 30px; position: relative; padding-top: 50px;">
                <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); width: 40px; height: 40px; background: var(--skin-primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">2</div>
                <h3 style="color: #333; margin-bottom: 15px;">Onboarding</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); font-size: 0.95rem; line-height: 1.5;">1-to-1 welcome to explain the model and identify skills.</p>
            </div>

            <div class="civic-card" style="text-align: center; padding: 30px; position: relative; padding-top: 50px;">
                <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); width: 40px; height: 40px; background: var(--skin-primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">3</div>
                <h3 style="color: #333; margin-bottom: 15px;">Connection</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); font-size: 0.95rem; line-height: 1.5;">Active facilitation of first exchanges and group activities.</p>
            </div>

            <div class="civic-card" style="text-align: center; padding: 30px; position: relative; padding-top: 50px;">
                <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); width: 40px; height: 40px; background: var(--skin-primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">4</div>
                <h3 style="color: #333; margin-bottom: 15px;">Follow-up</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); font-size: 0.95rem; line-height: 1.5;">Feedback to Link Worker on engagement and outcomes.</p>
            </div>

        </div>
    </div>

    <!-- CTA -->
    <div class="civic-card" style="text-align: center; padding: 60px;">
        <h2 style="color: #333; margin-top: 0; margin-bottom: 20px;">We are Seeking a Partner to Launch a Formal Pilot</h2>
        <p style="font-size: 1.1rem; color: #555; max-width: 700px; margin: 0 auto 30px auto; line-height: 1.6;">
            We are seeking a public sector contract to secure the essential Hub Coordinator role, which is the <strong>lynchpin of the entire service</strong>.
        </p>
        <a href="/uploads/tenants/hour-timebank/THE-RECIPROCITY-PATHWAY_A-Pilot-Proposal.pdf" target="_blank" class="civic-btn">Download Pilot Proposal</a>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>