<?php
$pageTitle = "HSE Social Prescribing Case Study - Project NEXUS";
$hSubtitle = "Digital Pathways for Social Prescribing in Irish Healthcare";
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<main class="civic-container" style="padding: 40px 20px;">

    <!-- HSE Branding Header -->
    <div style="background: linear-gradient(135deg, #007b5f 0%, #045d48 100%); padding: 50px; border-radius: 12px; color: white; margin-bottom: 40px;">
        <span style="background: rgba(255,255,255,0.2); text-transform: uppercase; letter-spacing: 1px; font-size: 0.8rem; padding: 4px 10px; border-radius: 4px;">Case Study: Healthcare</span>
        <h1 style="color: white; margin: 15px 0; font-size: 2.5rem;">Reducing Healthcare Pressure via Community Action</h1>
        <p style="font-size: 1.2rem; opacity: 0.9;">A Community Healthcare Organisation (CHO) uses NEXUS to manage a network of "Wellness Volunteers."</p>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 50px;">

        <!-- Scenario -->
        <div>
            <h2 style="color: #007b5f; border-bottom: 2px solid #007b5f; padding-bottom: 10px; display: inline-block;">The Scenario</h2>
            <p style="font-size: 1.1rem; line-height: 1.7; color: #334155;">
                GPs and Public Health Nurses often see patients whose primary complaints are rooted in isolation or lack of activity, rather than acute medical issues. The "Social Prescribing" model works, but tracking referrals and ensuring patient safety has historically been manual and paper-based.
            </p>
            <p style="font-size: 1.1rem; line-height: 1.7; color: #334155;">
                <strong>The Challenge:</strong> Connecting patients to trusted, vetted community groups without adding administrative burden to clinical staff.
            </p>
        </div>

        <!-- Solution -->
        <div style="background: #f0fdf4; padding: 30px; border-radius: 12px; border: 1px solid #bbf7d0;">
            <h3 style="color: #166534; margin-top: 0;">The Result</h3>
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 15px; display: flex; align-items: start; gap: 10px;">
                    <span style="font-size: 1.2rem;">🏥</span>
                    <span style="color: #14532d;"><strong>Direct Referral:</strong> Patients are referred directly to community garden projects or walking groups via the NEXUS portal.</span>
                </li>
                <li style="margin-bottom: 15px; display: flex; align-items: start; gap: 10px;">
                    <span style="font-size: 1.2rem;">📊</span>
                    <span style="color: #14532d;"><strong>Real-Time Data:</strong> The HSE can track engagement levels and "hours of support" generated.</span>
                </li>
                <li style="margin-bottom: 15px; display: flex; align-items: start; gap: 10px;">
                    <span style="font-size: 1.2rem;">💰</span>
                    <span style="color: #14532d;"><strong>ROI Calculator:</strong> Clear visualisation of social value versus clinical hours saved.</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Call to Action -->
    <div style="margin-top: 50px; text-align: center;">
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="civic-btn" style="background-color: #007b5f; color: white; padding: 12px 25px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 1.1rem;">View Live Volunteer Opportunities</a>
        <p style="margin-top: 15px; color: #64748b; font-size: 0.9rem;">Experience the user journey for a potential volunteer.</p>
    </div>

</main>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>