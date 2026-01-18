<?php
// Phoenix View: Terms Page
$hTitle = 'Terms of Service';
$hSubtitle = 'Legal Information';
$hGradient = 'civic-hero-gradient-hub';
$hType = 'Legal';

require __DIR__ . '/../..' . '/layouts/civicone/header.php';
?>

<div class="civic-container" style="margin-top: -80px; position: relative; z-index: 20; display: block; max-width: 900px; margin-left: auto; margin-right: auto;">
    <div class="civic-card">
        <div class="civic-card-body" style="padding: 40px; max-width: 800px; margin: 0 auto; line-height: 1.6;">
            <p><strong>Last Updated:</strong> <?= date('F j, Y') ?></p>

            <h3>1. Introduction</h3>
            <p>Welcome to Project NEXUS. By accessing or using our platform, you agree to be bound by these Terms of Service.</p>

            <h3>2. Time Credit System</h3>
            <p>One hour of service equals one Time Credit, regardless of the nature of the service performed. Time Credits have no monetary value.</p>

            <h3>3. Community Guidelines</h3>
            <p>We rely on trust and respect. Users found violating our community standards may be banned.</p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../..' . '/layouts/civicone/footer.php'; ?>