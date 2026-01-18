<?php
// Phoenix View: About Page
$hTitle = 'About Us';
$hSubtitle = 'Our Mission & Vision';
$hGradient = 'htb-hero-gradient-profile';
$hType = 'Information';

require __DIR__ . '/../..' . '/layouts/civicone/header.php';
?>

<div class="civic-container" style="margin-top: -80px; position: relative; z-index: 20; display: block; max-width: 900px; margin-left: auto; margin-right: auto;">

    <div class="civic-card">
        <div class="civic-card-body" style="padding: 40px; text-align: center; max-width: 800px; margin: 0 auto;">

            <h2 style="font-size: 2.5rem; margin-bottom: 20px;">Empowering Community Exchange</h2>
            <p style="font-size: 1.2rem; color: var(--civic-text-muted); line-height: 1.6;">
                Project NEXUS is a community platform dedicated to the exchange of time and skills.
                We believe that everyone has something valuable to contribute.
            </p>

            <hr style="margin: 40px 0; border: 0; border-top: 1px solid #eee;">

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 30px; text-align: left;">
                <div>
                    <h3>🤝 Connect</h3>
                    <p>Find neighbors who share your interests and needs.</p>
                </div>
                <div>
                    <h3>🔄 Exchange</h3>
                    <p>Trade 1 hour of help for 1 time credit. Everyone's time is equal.</p>
                </div>
                <div>
                    <h3>🌱 Grow</h3>
                    <p>Build a stronger, more resilient local community together.</p>
                </div>
            </div>

        </div>
    </div>

</div>

<?php require __DIR__ . '/../..' . '/layouts/civicone/footer.php'; ?>