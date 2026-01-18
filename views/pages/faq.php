<?php
$hTitle = 'Frequently Asked Questions';
$hSubtitle = 'Find answers to common questions';

?>

<style>
/* ========== FAQ CSS Variables for Dark Mode ========== */
:root {
    --faq-card-bg: #ffffff;
    --faq-border: #e5e7eb;
    --faq-text: #374151;
    --faq-heading: #111827;
    --faq-summary-bg: transparent;
    --faq-summary-hover: #f9fafb;
    --faq-category-bg: #f3f4f6;
    --faq-category-text: #6b7280;
    --faq-link: #1877f2;
    --faq-icon: #9ca3af;
}

[data-theme="dark"] {
    --faq-card-bg: var(--bg-card, #242526);
    --faq-border: #3a3b3c;
    --faq-text: #b0b3b8;
    --faq-heading: #e4e6eb;
    --faq-summary-bg: transparent;
    --faq-summary-hover: #3a3b3c;
    --faq-category-bg: #3a3b3c;
    --faq-category-text: #b0b3b8;
    --faq-link: #4dabf7;
    --faq-icon: #606770;
}

@media (prefers-color-scheme: dark) {
    body:not([data-theme="light"]) {
        --faq-card-bg: var(--bg-card, #242526);
        --faq-border: #3a3b3c;
        --faq-text: #b0b3b8;
        --faq-heading: #e4e6eb;
        --faq-summary-bg: transparent;
        --faq-summary-hover: #3a3b3c;
        --faq-category-bg: #3a3b3c;
        --faq-category-text: #b0b3b8;
        --faq-link: #4dabf7;
        --faq-icon: #606770;
    }
}

.faq-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
}

.faq-intro {
    text-align: center;
    margin-bottom: 40px;
    color: var(--faq-text);
}

.faq-intro p {
    font-size: 1.1rem;
    margin-bottom: 15px;
}

.faq-intro a {
    color: var(--faq-link);
    text-decoration: none;
    font-weight: 600;
}

.faq-intro a:hover {
    text-decoration: underline;
}

.faq-category {
    margin-bottom: 30px;
}

.faq-category-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: var(--faq-category-bg);
    border-radius: 8px;
    margin-bottom: 15px;
}

.faq-category-header i {
    color: var(--faq-link);
    font-size: 1.1rem;
}

.faq-category-header h2 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--faq-heading);
}

.faq-item {
    margin-bottom: 12px;
    border: 1px solid var(--faq-border);
    border-radius: 8px;
    background: var(--faq-card-bg);
    overflow: hidden;
    transition: border-color 0.2s;
}

.faq-item:hover {
    border-color: var(--faq-link);
}

.faq-item summary {
    padding: 16px 20px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    color: var(--faq-heading);
    display: flex;
    align-items: center;
    justify-content: space-between;
    list-style: none;
    background: var(--faq-summary-bg);
    transition: background 0.2s;
}

.faq-item summary::-webkit-details-marker {
    display: none;
}

.faq-item summary::after {
    content: '\f107';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    color: var(--faq-icon);
    transition: transform 0.2s;
}

.faq-item[open] summary::after {
    transform: rotate(180deg);
}

.faq-item summary:hover {
    background: var(--faq-summary-hover);
}

.faq-item .faq-answer {
    padding: 0 20px 20px 20px;
    color: var(--faq-text);
    line-height: 1.7;
    font-size: 0.95rem;
}

.faq-item .faq-answer p {
    margin: 0 0 12px 0;
}

.faq-item .faq-answer p:last-child {
    margin-bottom: 0;
}

.faq-item .faq-answer ul {
    margin: 10px 0;
    padding-left: 20px;
}

.faq-item .faq-answer li {
    margin-bottom: 6px;
}

.faq-item .faq-answer a {
    color: var(--faq-link);
    text-decoration: none;
}

.faq-item .faq-answer a:hover {
    text-decoration: underline;
}

.faq-contact {
    text-align: center;
    padding: 30px;
    background: var(--faq-category-bg);
    border-radius: 12px;
    margin-top: 40px;
}

.faq-contact h3 {
    margin: 0 0 10px 0;
    color: var(--faq-heading);
    font-size: 1.2rem;
}

.faq-contact p {
    margin: 0 0 15px 0;
    color: var(--faq-text);
}

.faq-contact a {
    display: inline-block;
    padding: 12px 24px;
    background: var(--faq-link);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: opacity 0.2s;
}

.faq-contact a:hover {
    opacity: 0.9;
}

/* Touch-friendly on mobile */
@media (max-width: 768px) {
    .faq-item summary {
        padding: 18px 16px;
        min-height: 48px;
    }
}
</style>

<div class="faq-container">

    <div class="faq-intro">
        <p>Can't find what you're looking for? Visit our <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/help">Help Center</a> for detailed guides.</p>
    </div>

    <!-- Getting Started -->
    <div class="faq-category">
        <div class="faq-category-header">
            <i class="fa-solid fa-rocket"></i>
            <h2>Getting Started</h2>
        </div>

        <details class="faq-item">
            <summary>What is time banking?</summary>
            <div class="faq-answer">
                <p>Time banking is a community exchange system where <strong>one hour of help equals one time credit</strong>, regardless of the service provided. Whether you're teaching a language, helping with gardening, or providing tech support, your time is valued equally.</p>
                <p>It's a way to build community connections while exchanging skills and services without traditional money.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary>Is it free to join?</summary>
            <div class="faq-answer">
                <p>Yes! Membership is completely free. The only currency used is time credits, which you earn by helping others in the community.</p>
                <p>New members often receive a small number of starter credits to help them get started.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary>How do I get started?</summary>
            <div class="faq-answer">
                <p>Here's how to begin:</p>
                <ul>
                    <li><strong>Complete your profile</strong> - Add a photo, bio, and your skills</li>
                    <li><strong>Browse the marketplace</strong> - See what services are offered and needed</li>
                    <li><strong>Post an offer</strong> - Share what you can help others with</li>
                    <li><strong>Make a request</strong> - Ask for something you need</li>
                    <li><strong>Connect with members</strong> - Build your network</li>
                </ul>
            </div>
        </details>

        <details class="faq-item">
            <summary>What skills can I offer?</summary>
            <div class="faq-answer">
                <p>Almost anything! Common offerings include:</p>
                <ul>
                    <li>Home help (gardening, cleaning, minor repairs)</li>
                    <li>Tech support (computer help, phone setup)</li>
                    <li>Teaching (languages, music, academic subjects)</li>
                    <li>Creative services (design, photography, writing)</li>
                    <li>Transportation (rides, errands)</li>
                    <li>Companionship (walks, visits, conversation)</li>
                    <li>Professional skills (accounting, legal advice, etc.)</li>
                </ul>
                <p>Everyone has something valuable to offer!</p>
            </div>
        </details>
    </div>

    <!-- Time Credits -->
    <div class="faq-category">
        <div class="faq-category-header">
            <i class="fa-solid fa-wallet"></i>
            <h2>Time Credits</h2>
        </div>

        <details class="faq-item">
            <summary>What is a time credit?</summary>
            <div class="faq-answer">
                <p>One time credit equals one hour of service. It's the currency of our community.</p>
                <p>You can also exchange partial hours: 30 minutes = 0.5 credits, 15 minutes = 0.25 credits.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary>How do I earn time credits?</summary>
            <div class="faq-answer">
                <p>You earn credits by:</p>
                <ul>
                    <li><strong>Providing services</strong> to other members</li>
                    <li><strong>Logging volunteer hours</strong> with approved organizations</li>
                    <li><strong>Receiving donations</strong> from other members</li>
                    <li><strong>Starter credits</strong> when you first join</li>
                </ul>
            </div>
        </details>

        <details class="faq-item">
            <summary>Can I donate my credits?</summary>
            <div class="faq-answer">
                <p>Yes! You can:</p>
                <ul>
                    <li>Transfer credits to any other member</li>
                    <li>Donate to the community pot (helps members who need extra support)</li>
                </ul>
                <p>Go to your <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet">Wallet</a> to send credits.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary>Can my balance go negative?</summary>
            <div class="faq-answer">
                <p>Some communities allow limited negative balances to help new members get started before they've had a chance to earn credits.</p>
                <p>Check with your community coordinator for the specific rules in your timebank.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary>What if I sent credits to the wrong person?</summary>
            <div class="faq-answer">
                <p>Transactions cannot be automatically reversed. If you made a mistake:</p>
                <ul>
                    <li>Contact the recipient and ask them to send the credits back</li>
                    <li>If you can't resolve it, contact support for assistance</li>
                </ul>
                <p>Always double-check the recipient before confirming a transfer.</p>
            </div>
        </details>
    </div>

    <!-- Exchanges & Safety -->
    <div class="faq-category">
        <div class="faq-category-header">
            <i class="fa-solid fa-handshake"></i>
            <h2>Exchanges & Safety</h2>
        </div>

        <details class="faq-item">
            <summary>How do I arrange an exchange?</summary>
            <div class="faq-answer">
                <p>The typical process is:</p>
                <ul>
                    <li>Find an offer or request that interests you</li>
                    <li>Message the member to discuss details</li>
                    <li>Agree on when, where, and estimated time</li>
                    <li>Meet and complete the exchange</li>
                    <li>The person who received help sends time credits</li>
                    <li>Leave a review for each other</li>
                </ul>
            </div>
        </details>

        <details class="faq-item">
            <summary>Is it safe to meet strangers?</summary>
            <div class="faq-answer">
                <p>We recommend these safety practices:</p>
                <ul>
                    <li>Meet in public places for first exchanges</li>
                    <li>Check the member's profile and reviews</li>
                    <li>Tell someone where you're going</li>
                    <li>Trust your instincts - if something feels wrong, leave</li>
                    <li>Start with smaller, low-risk exchanges</li>
                </ul>
                <p>Report any concerning behavior to our support team.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary>What if someone doesn't show up or complete the service?</summary>
            <div class="faq-answer">
                <p>Communication is key. If there's an issue:</p>
                <ul>
                    <li>Message the member to discuss what happened</li>
                    <li>Try to find a resolution together</li>
                    <li>If you can't resolve it, contact support</li>
                </ul>
                <p>Repeated no-shows or poor behavior may result in account restrictions.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary>Are the services insured?</summary>
            <div class="faq-answer">
                <p>Exchanges are informal arrangements between community members. We don't provide insurance coverage for services.</p>
                <p>For higher-risk activities, discuss liability with the other member beforehand. Some members have their own professional insurance.</p>
            </div>
        </details>
    </div>

    <!-- Gamification & Rewards -->
    <div class="faq-category">
        <div class="faq-category-header">
            <i class="fa-solid fa-trophy"></i>
            <h2>Badges & Rewards</h2>
        </div>

        <details class="faq-item">
            <summary>What are badges and XP?</summary>
            <div class="faq-answer">
                <p>Our gamification system rewards community participation:</p>
                <ul>
                    <li><strong>XP (Experience Points)</strong> - Earned for various activities, helping you level up</li>
                    <li><strong>Badges</strong> - Achievements for reaching milestones (60+ to collect!)</li>
                    <li><strong>Levels</strong> - Progress from Newcomer to Timebank Legend</li>
                    <li><strong>Streaks</strong> - Rewards for daily engagement</li>
                </ul>
                <p>See the <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/help/gamification-overview">full gamification guide</a> for details.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary>How do I earn XP?</summary>
            <div class="faq-answer">
                <p>You earn XP for many activities:</p>
                <ul>
                    <li>Sending/receiving time credits</li>
                    <li>Creating listings</li>
                    <li>Logging volunteer hours</li>
                    <li>Attending events</li>
                    <li>Making connections</li>
                    <li>Daily logins</li>
                    <li>Earning badges</li>
                </ul>
            </div>
        </details>

        <details class="faq-item">
            <summary>Can I opt out of leaderboards?</summary>
            <div class="faq-answer">
                <p>Yes! If you prefer privacy, you can opt out of appearing on public leaderboards in your account settings.</p>
                <p>You'll still earn XP and badges, they just won't be visible to others on the rankings.</p>
            </div>
        </details>
    </div>

    <!-- Account & Privacy -->
    <div class="faq-category">
        <div class="faq-category-header">
            <i class="fa-solid fa-shield-halved"></i>
            <h2>Account & Privacy</h2>
        </div>

        <details class="faq-item">
            <summary>How do I change my password?</summary>
            <div class="faq-answer">
                <p>Go to <strong>Settings > Security</strong> to change your password. You'll need to enter your current password first.</p>
                <p>If you've forgotten your password, use the "Forgot Password" link on the login page.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary>Who can see my profile?</summary>
            <div class="faq-answer">
                <p>By default, your profile is visible to other community members. You can control:</p>
                <ul>
                    <li>What contact information is displayed</li>
                    <li>Whether you appear on leaderboards</li>
                    <li>Your general location visibility</li>
                </ul>
                <p>Adjust these in your <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/settings">Settings</a>.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary>How do I delete my account?</summary>
            <div class="faq-answer">
                <p>If you wish to leave the community:</p>
                <ul>
                    <li>Go to Settings > Account</li>
                    <li>Click "Delete Account"</li>
                    <li>Confirm your decision</li>
                </ul>
                <p>Note: Your transaction history will be retained for record-keeping, but your personal information will be removed.</p>
            </div>
        </details>

        <details class="faq-item">
            <summary>How is my data protected?</summary>
            <div class="faq-answer">
                <p>We take privacy seriously:</p>
                <ul>
                    <li>Your data is encrypted and securely stored</li>
                    <li>We never sell your information</li>
                    <li>You can request a copy of your data anytime</li>
                    <li>We comply with GDPR and data protection regulations</li>
                </ul>
            </div>
        </details>
    </div>

    <!-- Contact Support -->
    <div class="faq-contact">
        <h3>Still have questions?</h3>
        <p>Our support team is here to help.</p>
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/contact">Contact Support</a>
    </div>

</div>

<?php  ?>
