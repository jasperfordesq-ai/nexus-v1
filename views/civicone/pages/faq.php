<?php
// CivicOne View: FAQ
// Tenant-specific: Hour Timebank only
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'FAQ';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card" style="margin-bottom: 40px; text-align: center; padding: 40px;">
        <h1 style="margin-top: 0; margin-bottom: 15px; color: var(--skin-primary);">Timebanking Made Simple</h1>
        <p style="font-size: 1.2rem; color: #555; max-width: 800px; margin: 0 auto; line-height: 1.6;">
            Everything you need to know about using time as currency.
        </p>
    </div>

    <!-- FAQ Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 50px;">

        <div class="civic-card" style="padding: 30px;">
            <h2 style="color: var(--skin-primary); font-size: 1.3rem; margin-top: 0; margin-bottom: 15px;">What is Timebanking?</h2>
            <p style="color: #555; line-height: 1.6;">
                Timebanking is a system of mutual service exchange that uses units of time as currency. The underlying principle is that everyone's time is equally valuable. It helps build stronger communities by fostering cooperation and support, transcending traditional monetary transactions.
            </p>
        </div>

        <div class="civic-card" style="padding: 30px;">
            <h2 style="color: var(--skin-primary); font-size: 1.3rem; margin-top: 0; margin-bottom: 15px;">Who can join?</h2>
            <p style="color: #555; line-height: 1.6;">
                Anyone! Whether you possess professional expertise, everyday life skills, or unique hobbies, your talents are valued. Timebanks embrace diversity and recognize that every member has something valuable to offer.
            </p>
        </div>

        <div class="civic-card" style="padding: 30px;">
            <h2 style="color: var(--skin-primary); font-size: 1.3rem; margin-top: 0; margin-bottom: 15px;">What can I offer?</h2>
            <p style="color: #555; line-height: 1.6;">
                Possibilities are endless: gardening, home repairs, cooking, companionship, mentoring, music lessons, IT help, and more. Offer what you genuinely enjoy and excel at.
            </p>
        </div>

        <div class="civic-card" style="padding: 30px;">
            <h2 style="color: var(--skin-primary); font-size: 1.3rem; margin-top: 0; margin-bottom: 15px;">How do Credits work?</h2>
            <p style="color: #555; line-height: 1.6;">
                When you spend an hour helping another member, you earn one <strong>Time Credit</strong>. You can use this credit to "buy" an hour of service you need. It's a reciprocal system promoting fairness.
            </p>
        </div>

        <div class="civic-card" style="padding: 30px;">
            <h2 style="color: var(--skin-primary); font-size: 1.3rem; margin-top: 0; margin-bottom: 15px;">How do I join?</h2>
            <p style="color: #555; line-height: 1.6;">
                It's simple. Register on our platform, create your profile, and start listing your offers and requests.
            </p>
        </div>

        <div class="civic-card" style="padding: 30px;">
            <h2 style="color: var(--skin-primary); font-size: 1.3rem; margin-top: 0; margin-bottom: 15px;">Our Philosophy</h2>
            <p style="color: #555; line-height: 1.6;">
                <strong>"Time is the most valuable currency."</strong> We celebrate inclusivity and the joy of giving and receiving.
            </p>
        </div>

    </div>

    <!-- CTA -->
    <div class="civic-card" style="text-align: center; padding: 60px; background: #fdfdfd;">
        <h2 style="color: #333; margin-top: 0; margin-bottom: 20px;">Join the Movement Today</h2>
        <p style="font-size: 1.1rem; color: #555; margin-bottom: 30px;">Embark on a journey of enriching lives, one shared moment at a time.</p>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" class="civic-btn">Become a Member</a>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>