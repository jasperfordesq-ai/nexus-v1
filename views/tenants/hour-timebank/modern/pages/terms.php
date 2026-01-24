<?php
/**
 * Terms of Service - Ireland Timebank
 * Theme Color: Blue (#3b82f6)
 * Tenant: Hour Timebank Ireland
 * Legal Entity: hOUR Timebank CLG (RCN 20162023)
 *
 * Updated: January 2026 - Insurance feedback incorporated
 */
$pageTitle = 'Terms of Service';
$hideHero = true;

require __DIR__ . '/../../../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';

// Get tenant info
$tenantName = 'hOUR Timebank Ireland';
if (class_exists('Nexus\Core\TenantContext')) {
    $t = Nexus\Core\TenantContext::get();
    $tenantName = $t['name'] ?? 'hOUR Timebank Ireland';
}
?>

<!-- Terms Page Styles - Loaded via page-specific CSS loader in header.php -->

<div id="terms-glass-wrapper">
    <div class="terms-inner">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/legal" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Legal Hub
        </a>

        <!-- Page Header -->
        <div class="terms-page-header">
            <h1>
                <span class="header-icon"><i class="fa-solid fa-file-contract"></i></span>
                Terms of Service
            </h1>
            <p>Terms and conditions for hOUR Timebank Ireland members and partner organisations</p>
            <span class="last-updated">
                <i class="fa-solid fa-calendar"></i>
                Last Updated: <?= date('F j, Y') ?>
            </span>
        </div>

        <!-- Quick Navigation -->
        <div class="terms-quick-nav">
            <a href="#platform-role" class="terms-nav-btn">
                <i class="fa-solid fa-diagram-project"></i> Platform Role
            </a>
            <a href="#who-we-are" class="terms-nav-btn">
                <i class="fa-solid fa-building"></i> Who We Are
            </a>
            <a href="#safeguarding" class="terms-nav-btn">
                <i class="fa-solid fa-shield-halved"></i> Safeguarding
            </a>
            <a href="#relationship" class="terms-nav-btn">
                <i class="fa-solid fa-handshake-simple"></i> Relationship
            </a>
            <a href="#assumption-of-risk" class="terms-nav-btn">
                <i class="fa-solid fa-person-falling"></i> Risk
            </a>
            <a href="#liability" class="terms-nav-btn">
                <i class="fa-solid fa-scale-balanced"></i> Liability
            </a>
            <a href="#disputes" class="terms-nav-btn">
                <i class="fa-solid fa-gavel"></i> Disputes
            </a>
        </div>

        <!-- Introduction -->
        <div class="terms-section highlight">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-gavel"></i></div>
                <h2>Agreement to Terms</h2>
            </div>
            <p>By accessing or using hOUR Timebank Ireland (accessible at <strong>hour-timebank.ie</strong>), you agree to be bound by these Terms of Service. Please read them carefully before participating in our community.</p>
            <p>These terms establish a framework for <strong>fair, respectful, and meaningful exchanges</strong> between community members across Ireland. Our goal is to create a trusted environment where everyone's time is valued equally.</p>
            <p>If you are using the platform on behalf of an organisation, you represent that you have authority to bind that organisation to these terms.</p>
        </div>

        <!-- Platform Role & Disclaimer -->
        <div class="terms-section highlight" id="platform-role">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-diagram-project"></i></div>
                <h2>Platform Role & Responsibility</h2>
            </div>
            <p><strong>hOUR Timebank Ireland is a connection platform only.</strong> We provide technology and tools that enable community members to find and arrange service exchanges with each other.</p>

            <div class="terms-notice" style="border-left: 4px solid #3b82f6;">
                <span class="notice-icon" style="color: #3b82f6;"><i class="fa-solid fa-circle-info"></i></span>
                <div class="notice-content">
                    <h4>Critical Distinction</h4>
                    <p><strong>We are NOT a service provider.</strong> We do not perform, supervise, direct, or control any services exchanged between members. We are similar to online platforms like Yelp, Facebook Marketplace, or community notice boards — we facilitate connections, but the actual exchanges are entirely between independent members.</p>
                </div>
            </div>

            <p><strong>What We Do:</strong></p>
            <ul>
                <li>Provide a platform for members to create profiles and list services they can offer or need</li>
                <li>Facilitate communication between members through messaging features</li>
                <li>Track Time Credits earned and spent by members</li>
                <li>Offer tools for members to review and provide feedback on exchanges</li>
                <li>Moderate content to ensure compliance with community guidelines</li>
                <li>Provide support and guidance on how to use the platform</li>
            </ul>

            <p><strong>What We Do NOT Do:</strong></p>
            <ul>
                <li>Perform any services on behalf of members</li>
                <li>Employ, hire, or contract with members to provide services</li>
                <li>Supervise, direct, or control how services are performed</li>
                <li>Verify the quality, safety, or suitability of services</li>
                <li>Act as an agent, intermediary, or representative in exchanges</li>
                <li>Guarantee outcomes or resolve service quality disputes</li>
            </ul>

            <p>By using this platform, you acknowledge that <strong>all service exchanges are direct arrangements between independent members</strong>, and hOUR Timebank CLG bears no responsibility for the quality, safety, or outcome of those exchanges.</p>
        </div>

        <!-- Who We Are -->
        <div class="terms-section" id="who-we-are">
            <div class="section-header">
                <div class="section-number">1</div>
                <h2>Who We Are</h2>
            </div>
            <p>hOUR Timebank Ireland is operated by <strong>hOUR Timebank CLG</strong>, an Irish registered charity dedicated to building community through time-based exchange.</p>

            <div class="entity-info">
                <dl>
                    <dt>Legal Name</dt>
                    <dd>hOUR Timebank CLG (Company Limited by Guarantee)</dd>

                    <dt>Registered Business Name</dt>
                    <dd>Timebank Ireland</dd>

                    <dt>Charity Registration</dt>
                    <dd>RCN 20162023 (Charities Regulator)</dd>

                    <dt>Registered Address</dt>
                    <dd>21 Páirc Goodman, Skibbereen, Co. Cork, P81 AK26, Ireland</dd>

                    <dt>Contact</dt>
                    <dd><a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a></dd>
                </dl>
            </div>

            <p>As a registered charity, we operate on a not-for-profit basis. All platform activities support our charitable mission of fostering community connections and mutual aid throughout Ireland.</p>
        </div>

        <!-- Time Credit System -->
        <div class="terms-section" id="time-credits">
            <div class="section-header">
                <div class="section-number">2</div>
                <h2>Time Credit System</h2>
            </div>
            <p>Our platform operates on a simple but powerful principle: <strong>everyone's time is equal</strong>.</p>

            <div class="time-credit-visual">
                <div class="credit-box">
                    <div class="credit-icon"><i class="fa-solid fa-clock"></i></div>
                    <span class="credit-label">1 Hour of Service</span>
                </div>
                <span class="equals-sign">=</span>
                <div class="credit-box">
                    <div class="credit-icon"><i class="fa-solid fa-gem"></i></div>
                    <span class="credit-label">1 Time Credit</span>
                </div>
            </div>

            <div class="terms-notice">
                <span class="notice-icon"><i class="fa-solid fa-info-circle"></i></span>
                <div class="notice-content">
                    <h4>Important</h4>
                    <p>Time Credits have no monetary value and cannot be exchanged for cash. They exist solely to facilitate community exchanges and are not considered payment for services under Irish law.</p>
                </div>
            </div>

            <ul>
                <li>One hour of service provided equals one Time Credit earned</li>
                <li>Credits can be used to receive services from other members</li>
                <li>The type of service does not affect the credit value — all time is equal</li>
                <li>Credits are tracked automatically through the platform</li>
                <li>Credits cannot be transferred, sold, or inherited</li>
            </ul>
        </div>

        <!-- Transaction Recording -->
        <div class="terms-section" id="transactions">
            <div class="section-header">
                <div class="section-number">3</div>
                <h2>Transaction Recording & Verification</h2>
            </div>
            <p>When members complete a service exchange, Time Credits must be recorded through the platform. Here's how the process works:</p>

            <p><strong>Recording an Exchange:</strong></p>
            <ol>
                <li><strong>Service Provider Initiates:</strong> After completing a service, the provider records the exchange through the platform, specifying the recipient and hours provided</li>
                <li><strong>Recipient Confirms:</strong> The recipient receives a notification and must confirm or dispute the exchange within 7 days</li>
                <li><strong>Credits Transfer:</strong> Upon confirmation (or after 7 days if no dispute is raised), Time Credits are automatically transferred</li>
                <li><strong>Both Parties Notified:</strong> Both members receive confirmation of the completed transaction</li>
            </ol>

            <p><strong>Transaction Disputes:</strong></p>
            <p>If a recipient believes a transaction record is inaccurate (wrong hours, service not completed, etc.), they may dispute it within 7 days:</p>

            <ul>
                <li><strong>Dispute Window:</strong> 7 days from receipt of transaction notification</li>
                <li><strong>Dispute Process:</strong> The recipient can reject the transaction and provide a reason</li>
                <li><strong>Member Resolution:</strong> Members are encouraged to communicate directly to resolve the discrepancy</li>
                <li><strong>Platform Mediation:</strong> If members cannot agree, either party may request platform mediation (see Section 14: Dispute Resolution)</li>
                <li><strong>Final Decision:</strong> hOUR Timebank CLG reserves the right to make a final determination based on available evidence, including messages, previous transaction history, and member statements</li>
            </ul>

            <div class="terms-notice">
                <span class="notice-icon"><i class="fa-solid fa-file-invoice"></i></span>
                <div class="notice-content">
                    <h4>Keep Your Own Records</h4>
                    <p>We recommend members maintain their own records of exchanges, including dates, services provided, hours worked, and any agreements made. This helps resolve disputes quickly and fairly.</p>
                </div>
            </div>

            <p><strong>Fraudulent Transactions:</strong></p>
            <p>Recording transactions that did not occur, inflating hours, or otherwise misrepresenting exchanges is strictly prohibited and will result in:</p>
            <ul>
                <li>Immediate reversal of fraudulent credits</li>
                <li>Formal warning or account suspension</li>
                <li>Permanent account termination for repeated violations</li>
                <li>Potential referral to An Garda Síochána for fraud</li>
            </ul>

            <p><strong>Platform Fees:</strong></p>
            <p>hOUR Timebank Ireland operates on a <strong>not-for-profit basis</strong>. There are currently:</p>
            <ul>
                <li><strong>No transaction fees</strong> — Members do not pay to record exchanges</li>
                <li><strong>No membership fees</strong> — Platform use is free for individual members</li>
                <li><strong>Partner Organisation Fees:</strong> Partner organisations may be charged a modest annual fee to cover platform hosting and support costs</li>
            </ul>
            <p>Should we ever introduce fees for individual members, you will be notified at least 60 days in advance and given the option to close your account before any fees apply.</p>
        </div>

        <!-- Safeguarding - Prohibited Services -->
        <div class="terms-section highlight" id="safeguarding">
            <div class="section-header">
                <div class="section-number">4</div>
                <h2>Safeguarding & Prohibited Services</h2>
            </div>
            <p>hOUR Timebank CLG takes safeguarding seriously. Under the <strong>National Vetting Bureau (Children and Vulnerable Persons) Acts 2012-2016</strong>, certain activities involving children or vulnerable persons require Garda Vetting by a Registered Organisation.</p>

            <div class="terms-notice" style="border-left: 4px solid #ef4444;">
                <span class="notice-icon" style="color: #ef4444;"><i class="fa-solid fa-exclamation-triangle"></i></span>
                <div class="notice-content">
                    <h4>Absolute Prohibition</h4>
                    <p><strong>hOUR Timebank CLG does NOT provide Garda Vetting services. Therefore, ALL services involving children or vulnerable persons are strictly prohibited on this platform.</strong> No exceptions.</p>
                </div>
            </div>

            <p><strong>Definitions Under Irish Law:</strong></p>

            <p><strong>"Child"</strong> means a person under the age of 18 years other than a person who is or has been married.</p>

            <p><strong>"Vulnerable Person"</strong> means a person, other than a child, who:</p>
            <ul>
                <li>Is suffering from a disorder of the mind, whether as a result of mental illness, dementia, or intellectual disability</li>
                <li>Has a physical impairment, whether as a result of injury, illness, or age</li>
                <li>Has a physical disability</li>
                <li>Is receiving health or personal social services from a healthcare provider</li>
            </ul>

            <p><strong>Absolutely Prohibited Services (No Exceptions):</strong></p>
            <div class="prohibited-grid">
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-child"></i></span>
                    <span>Childminding or babysitting</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-book-open"></i></span>
                    <span>Tutoring or teaching children</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-car"></i></span>
                    <span>Transport for children/vulnerable persons</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-user-nurse"></i></span>
                    <span>Care assistance for vulnerable adults</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-house-user"></i></span>
                    <span>Home visits to vulnerable persons</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-hands-holding-child"></i></span>
                    <span>Youth group or children's activities</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-user-group"></i></span>
                    <span>Befriending vulnerable adults</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-universal-access"></i></span>
                    <span>Any contact with protected groups</span>
                </div>
            </div>

            <p><strong>Why This Prohibition Exists:</strong></p>
            <p>Irish law requires that persons undertaking "Relevant Work" (work involving access to children or vulnerable persons) must be vetted by the National Vetting Bureau through a Registered Organisation. hOUR Timebank CLG is <strong>not a Registered Organisation</strong> for the purposes of Garda Vetting. Therefore, we cannot facilitate any services that would constitute Relevant Work under the Acts.</p>

            <p><strong>Compliance & Enforcement:</strong></p>
            <ul>
                <li>Offering or requesting prohibited services will result in <strong>immediate account termination</strong></li>
                <li>This is a <strong>legal requirement</strong> under Irish law, not a policy choice</li>
                <li>Violations may be reported to <strong>An Garda Síochána</strong></li>
                <li>Members who need to provide such services should seek vetting through an appropriate Registered Organisation (e.g., their employer, a sports club, or another charity)</li>
            </ul>

            <div class="terms-notice">
                <span class="notice-icon"><i class="fa-solid fa-circle-info"></i></span>
                <div class="notice-content">
                    <h4>Adults-Only Platform</h4>
                    <p>All timebank exchanges on this platform must be between adults (18+) for services that do not involve children or vulnerable persons. Examples of permitted services include: gardening, DIY, cooking lessons for adults, language exchange between adults, pet sitting, admin help, IT support, etc.</p>
                </div>
            </div>
        </div>

        <!-- Partner Organisations -->
        <div class="terms-section highlight" id="partners">
            <div class="section-header">
                <div class="section-number">5</div>
                <h2>Partner Organisations</h2>
            </div>
            <p>hOUR Timebank Ireland provides platform services to Irish community groups, voluntary organisations, charities, and other relevant bodies ("Partner Organisations") who wish to operate timebanking within their communities.</p>

            <div class="partner-features">
                <div class="partner-feature">
                    <span class="feature-icon"><i class="fa-solid fa-users-gear"></i></span>
                    <div class="feature-text">
                        <strong>Branded Communities</strong>
                        <span>Partners can operate their own branded timebank community</span>
                    </div>
                </div>
                <div class="partner-feature">
                    <span class="feature-icon"><i class="fa-solid fa-sliders"></i></span>
                    <div class="feature-text">
                        <strong>Local Management</strong>
                        <span>Manage members and exchanges within your community</span>
                    </div>
                </div>
                <div class="partner-feature">
                    <span class="feature-icon"><i class="fa-solid fa-chart-line"></i></span>
                    <div class="feature-text">
                        <strong>Reporting Tools</strong>
                        <span>Track community impact and social value created</span>
                    </div>
                </div>
                <div class="partner-feature">
                    <span class="feature-icon"><i class="fa-solid fa-headset"></i></span>
                    <div class="feature-text">
                        <strong>Support & Training</strong>
                        <span>Access resources and guidance for successful operation</span>
                    </div>
                </div>
            </div>

            <p><strong>Partner Responsibilities:</strong></p>
            <ul>
                <li>Ensure compliance with these Terms within their community</li>
                <li>Maintain accurate records of members and exchanges</li>
                <li>Handle member data in accordance with GDPR and our Privacy Policy</li>
                <li>Report any issues or concerns to hOUR Timebank CLG promptly</li>
                <li>Uphold the values and mission of timebanking in Ireland</li>
            </ul>

            <p>Partner Organisations operate as independent entities. hOUR Timebank CLG provides the platform but does not control or supervise individual community operations.</p>
        </div>

        <!-- Account Responsibilities -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">6</div>
                <h2>Account Responsibilities</h2>
            </div>
            <p>When you create an account, you agree to:</p>
            <ul>
                <li><strong>Provide accurate information:</strong> Your profile must reflect your true identity and skills</li>
                <li><strong>Maintain security:</strong> Keep your login credentials confidential and secure</li>
                <li><strong>Use one account:</strong> Each person may only maintain one active account</li>
                <li><strong>Stay current:</strong> Update your profile when your skills or availability change</li>
                <li><strong>Be reachable:</strong> Respond to messages and requests in a timely manner</li>
                <li><strong>Be 18 or older:</strong> You must be at least 18 years old to create an account</li>
            </ul>

            <div class="terms-notice">
                <span class="notice-icon"><i class="fa-solid fa-sliders"></i></span>
                <div class="notice-content">
                    <h4>Privacy Settings Are Your Responsibility</h4>
                    <p>Your account includes privacy and visibility settings that control how your information is shared. <strong>You are solely responsible for reviewing, understanding, and maintaining these settings.</strong> This includes settings for profile visibility, federation opt-in, notifications, and data sharing preferences. We recommend reviewing your privacy settings regularly. hOUR Timebank CLG is not liable for any disclosure of information that occurs because you enabled sharing features or failed to configure your privacy settings appropriately.</p>
                </div>
            </div>
        </div>

        <!-- Privacy & Data Protection -->
        <div class="terms-section highlight" id="privacy">
            <div class="section-header">
                <div class="section-number">7</div>
                <h2>Privacy & Data Protection</h2>
            </div>
            <p>Your privacy is important to us. hOUR Timebank CLG is committed to protecting your personal data in accordance with the <strong>General Data Protection Regulation (GDPR)</strong> and Irish data protection law.</p>

            <p><strong>Key Privacy Points:</strong></p>
            <ul>
                <li>We collect only the personal information necessary to operate the platform</li>
                <li>Your data is stored securely and never sold to third parties</li>
                <li>You have the right to access, correct, or delete your personal data</li>
                <li>You control your privacy settings and what information is visible to other members</li>
                <li>We use cookies and similar technologies to improve your experience</li>
            </ul>

            <div class="terms-notice">
                <span class="notice-icon"><i class="fa-solid fa-file-shield"></i></span>
                <div class="notice-content">
                    <h4>Full Privacy Policy</h4>
                    <p>For complete details on how we collect, use, store, and protect your personal data, please read our full <strong><a href="<?= $basePath ?>/privacy" style="color: var(--terms-theme); text-decoration: underline;">Privacy Policy</a></strong>. The Privacy Policy forms part of these Terms of Service.</p>
                </div>
            </div>

            <p><strong>Your Privacy Responsibilities:</strong></p>
            <p>As mentioned in Section 6 (Account Responsibilities), you are solely responsible for:</p>
            <ul>
                <li>Reviewing and configuring your privacy settings appropriately</li>
                <li>Understanding what information you choose to share publicly or with other members</li>
                <li>Opting in or out of optional features like federation, email notifications, or data sharing</li>
                <li>Keeping your account credentials secure</li>
            </ul>

            <p>hOUR Timebank CLG is not liable for disclosure of information that occurs because you enabled public sharing features or failed to configure your privacy settings.</p>

            <p><strong>Data Controller:</strong></p>
            <p>For the purposes of GDPR, the data controller is:</p>
            <div class="entity-info" style="margin-top: 1rem;">
                <dl>
                    <dt>Entity</dt>
                    <dd>hOUR Timebank CLG</dd>

                    <dt>Address</dt>
                    <dd>21 Páirc Goodman, Skibbereen, Co. Cork, P81 AK26, Ireland</dd>

                    <dt>Data Protection Contact</dt>
                    <dd><a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a></dd>
                </dl>
            </div>
        </div>

        <!-- Community Guidelines -->
        <div class="terms-section" id="community">
            <div class="section-header">
                <div class="section-number">8</div>
                <h2>Community Guidelines</h2>
            </div>
            <p>Our community is built on <strong>trust, respect, and mutual support</strong>. All members must:</p>
            <ol>
                <li><strong>Treat everyone with respect</strong> — Be kind and courteous in all interactions</li>
                <li><strong>Honor your commitments</strong> — If you agree to an exchange, follow through</li>
                <li><strong>Communicate clearly</strong> — Keep other members informed about your availability</li>
                <li><strong>Be inclusive</strong> — Welcome members of all backgrounds and abilities</li>
                <li><strong>Give honest feedback</strong> — Help the community by providing fair reviews</li>
                <li><strong>Respect boundaries</strong> — Only contact members through the platform unless invited otherwise</li>
            </ol>
        </div>

        <!-- Reviews and Feedback -->
        <div class="terms-section" id="reviews">
            <div class="section-header">
                <div class="section-number">9</div>
                <h2>Reviews and Feedback System</h2>
            </div>
            <p>Members can leave reviews for each other after completing exchanges. Reviews help build trust and accountability in the community.</p>

            <p><strong>Review Guidelines:</strong></p>
            <ul>
                <li><strong>Honesty:</strong> Reviews should be truthful and based on your actual experience</li>
                <li><strong>Relevance:</strong> Focus on the service exchange, not personal characteristics unrelated to the service</li>
                <li><strong>Respect:</strong> Avoid offensive language, personal attacks, or discriminatory remarks</li>
                <li><strong>Fairness:</strong> Consider the context — was the member a beginner? Were there circumstances beyond their control?</li>
                <li><strong>Timeliness:</strong> Reviews should be submitted within 14 days of the exchange</li>
            </ul>

            <p><strong>What You May Include in Reviews:</strong></p>
            <ul>
                <li>Description of the service provided</li>
                <li>Quality and professionalism of the work</li>
                <li>Timeliness and reliability</li>
                <li>Communication and friendliness</li>
                <li>Whether you would exchange with this member again</li>
            </ul>

            <p><strong>Prohibited Review Content:</strong></p>
            <div class="prohibited-grid">
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Defamatory statements</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Discriminatory remarks</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Personal information disclosure</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Profanity or hate speech</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Irrelevant personal attacks</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>False or malicious statements</span>
                </div>
            </div>

            <p><strong>Review Moderation:</strong></p>
            <p>hOUR Timebank CLG reserves the right to:</p>
            <ul>
                <li>Review and moderate all feedback submitted on the platform</li>
                <li>Remove reviews that violate these guidelines</li>
                <li>Edit reviews to remove prohibited content (with notification to the author)</li>
                <li>Suspend or terminate accounts that repeatedly post inappropriate reviews</li>
                <li>Investigate reports of fake, coerced, or fraudulent reviews</li>
            </ul>

            <div class="terms-notice">
                <span class="notice-icon"><i class="fa-solid fa-shield-halved"></i></span>
                <div class="notice-content">
                    <h4>Defamation Protection</h4>
                    <p><strong>Reviews reflect the personal opinions of members.</strong> Under the Defamation Act 2009, hOUR Timebank CLG operates as a platform host and is protected under Section 27 (innocent publication defence) provided we take reasonable care. We actively moderate content and respond to complaints, but we are not liable for the content of member reviews unless we fail to act on legitimate complaints.</p>
                </div>
            </div>

            <p><strong>Disputing a Review:</strong></p>
            <p>If you believe a review about you is false, defamatory, or violates our guidelines:</p>
            <ol>
                <li><strong>Report the Review:</strong> Use the "Report" button on the review or contact us at <a href="mailto:jasper@hour-timebank.ie" style="color: var(--terms-theme);">jasper@hour-timebank.ie</a></li>
                <li><strong>Provide Evidence:</strong> Explain why the review violates guidelines and provide any supporting evidence</li>
                <li><strong>Investigation:</strong> We will review the complaint within 5 working days</li>
                <li><strong>Decision:</strong> We will remove or edit reviews that clearly violate guidelines; we will not remove honest negative reviews simply because you disagree with them</li>
                <li><strong>Right to Reply:</strong> You may post a public response to any review (subject to the same guidelines)</li>
            </ol>

            <p><strong>Review Authenticity:</strong></p>
            <ul>
                <li>Only members who have actually exchanged services may leave reviews for each other</li>
                <li>Members cannot review themselves</li>
                <li>Soliciting positive reviews in exchange for incentives is prohibited</li>
                <li>Posting fake reviews or asking others to post reviews on your behalf is prohibited</li>
            </ul>

            <p><strong>Your Responsibility:</strong></p>
            <p>By posting a review, you confirm that:</p>
            <ul>
                <li>The review is based on your genuine experience</li>
                <li>The content is accurate to the best of your knowledge</li>
                <li>You are not posting at the direction of a third party</li>
                <li>You grant hOUR Timebank CLG a licence to display the review on the platform</li>
                <li>You accept responsibility for any legal consequences if your review is found to be defamatory or false</li>
            </ul>
        </div>

        <!-- Prohibited Activities -->
        <div class="terms-section" id="prohibited">
            <div class="section-header">
                <div class="section-number">10</div>
                <h2>Prohibited Activities</h2>
            </div>
            <p>The following activities are strictly prohibited and may result in account termination:</p>

            <div class="prohibited-grid">
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Harassment or discrimination</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Fraudulent exchanges</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Illegal services or activities</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Commercial exploitation</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Impersonation</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Sharing others' private info</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Requesting cash payments</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Multi-level marketing</span>
                </div>
            </div>
        </div>

        <!-- Safety -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">11</div>
                <h2>Safety & Meetings</h2>
            </div>
            <p>Your safety is important. We recommend following these guidelines:</p>
            <ul>
                <li><strong>First meetings:</strong> Meet in public places for initial exchanges</li>
                <li><strong>Verify identity:</strong> Confirm the member's profile before meeting</li>
                <li><strong>Trust your instincts:</strong> If something feels wrong, don't proceed</li>
                <li><strong>Report concerns:</strong> Let us know about any suspicious behaviour</li>
                <li><strong>Keep records:</strong> Document exchanges through the platform</li>
                <li><strong>Inform someone:</strong> Let a friend or family member know about your plans</li>
            </ul>
        </div>

        <!-- Nature of Relationship -->
        <div class="terms-section highlight" id="relationship">
            <div class="section-header">
                <div class="section-number">12</div>
                <h2>Nature of Relationship</h2>
            </div>
            <p>It is essential that all members understand the legal nature of their relationship with hOUR Timebank CLG and with other members:</p>

            <div class="terms-notice" style="border-left: 4px solid #3b82f6;">
                <span class="notice-icon" style="color: #3b82f6;"><i class="fa-solid fa-handshake-simple"></i></span>
                <div class="notice-content">
                    <h4>No Employment Relationship</h4>
                    <p>Members are <strong>NOT</strong> employees, workers, agents, or contractors of hOUR Timebank CLG. No employment relationship, contract of service, or agency relationship exists or is created by participation in the timebank.</p>
                </div>
            </div>

            <p><strong>Key Legal Points:</strong></p>
            <ul>
                <li>hOUR Timebank CLG is <strong>not an employer</strong> — we do not direct, control, or supervise your activities</li>
                <li>You are <strong>not entitled</strong> to any employment benefits, workers' compensation, or employment protections from hOUR Timebank CLG</li>
                <li>Exchanges between members are <strong>personal arrangements</strong> between independent individuals</li>
                <li>You are responsible for your own <strong>tax obligations</strong>, if any arise (note: Time Credits are not monetary income)</li>
                <li>You must provide your own <strong>tools, equipment, and materials</strong> for any services you offer</li>
                <li>You are free to accept or decline any exchange request at your sole discretion</li>
            </ul>

            <p>This clarification is made pursuant to Irish employment law to ensure there is no ambiguity regarding the independent status of members.</p>
        </div>

        <!-- Assumption of Risk -->
        <div class="terms-section" id="assumption-of-risk">
            <div class="section-header">
                <div class="section-number">13</div>
                <h2>Assumption of Risk & Personal Responsibility</h2>
            </div>
            <p>By participating in the timebank, you acknowledge and accept the following:</p>

            <p><strong>Voluntary Participation:</strong></p>
            <p>In accordance with the principle of <em>volenti non fit injuria</em> (voluntary assumption of risk) as recognised under Irish law and the Occupiers' Liability Act 1995 (as amended by the Courts & Civil Law (Miscellaneous Provisions) Act 2023):</p>
            <ul>
                <li>You <strong>voluntarily choose</strong> to participate in timebank exchanges</li>
                <li>You acknowledge that exchanges carry <strong>inherent risks</strong> that cannot be entirely eliminated</li>
                <li>You accept responsibility for <strong>assessing the suitability</strong> of any exchange before agreeing to participate</li>
                <li>You are capable of <strong>comprehending the nature and extent</strong> of risks involved in exchanges</li>
            </ul>

            <p><strong>Personal Responsibility (Civil Liability Act 1961, Section 34):</strong></p>
            <ul>
                <li>You are responsible for taking <strong>reasonable care for your own safety</strong></li>
                <li>Any damages may be reduced in proportion to your own <strong>contributory negligence</strong></li>
                <li>You must disclose any <strong>health conditions, limitations, or circumstances</strong> that may affect your ability to safely participate in an exchange</li>
                <li>You should <strong>not proceed</strong> with any exchange that you believe may be unsafe</li>
            </ul>

            <p><strong>No Skill or Qualification Verification:</strong></p>
            <p>hOUR Timebank CLG does <strong>NOT</strong>:</p>
            <ul>
                <li>Verify the qualifications, certifications, or professional credentials of any member</li>
                <li>Assess the competence or skill level of members offering services</li>
                <li>Guarantee the quality or standard of any service provided</li>
                <li>Inspect, test, or certify any work performed between members</li>
            </ul>
            <p>You are solely responsible for assessing whether a member is suitable for your needs. For services requiring professional qualifications (e.g., electrical work, gas fitting, legal advice), you should always verify appropriate certifications independently.</p>
        </div>

        <!-- Insurance Recommendations -->
        <div class="terms-section" id="insurance">
            <div class="section-header">
                <div class="section-number">14</div>
                <h2>Insurance Recommendations</h2>
            </div>
            <p>While not legally required, we <strong>strongly recommend</strong> the following insurance considerations:</p>

            <p><strong>For All Members:</strong></p>
            <ul>
                <li><strong>Personal Accident Insurance:</strong> Consider coverage for injuries sustained during exchanges</li>
                <li><strong>Home Insurance:</strong> Check that your policy covers visitors to your property and activities you undertake</li>
                <li><strong>Motor Insurance:</strong> If providing transport services, ensure your policy covers carrying non-paying passengers</li>
            </ul>

            <p><strong>For Members Offering Professional-Type Services:</strong></p>
            <ul>
                <li><strong>Public Liability Insurance:</strong> Recommended if providing services that could cause injury to others or damage to property</li>
                <li><strong>Professional Indemnity Insurance:</strong> Consider if offering advice or consultancy-type services</li>
            </ul>

            <div class="terms-notice">
                <span class="notice-icon"><i class="fa-solid fa-shield-halved"></i></span>
                <div class="notice-content">
                    <h4>hOUR Timebank CLG Insurance</h4>
                    <p>hOUR Timebank CLG maintains public liability insurance for organised timebank events and activities. This does <strong>NOT</strong> extend to individual exchanges between members, which are personal arrangements conducted at your own risk.</p>
                </div>
            </div>
        </div>

        <!-- Liability -->
        <div class="terms-section highlight" id="liability">
            <div class="section-header">
                <div class="section-number">15</div>
                <h2>Limitation of Liability</h2>
            </div>
            <p>hOUR Timebank CLG provides a platform for community members to connect and exchange services. To the maximum extent permitted by Irish law:</p>

            <p><strong>Platform Limitations:</strong></p>
            <ul>
                <li>We do not guarantee the quality, safety, legality, or suitability of any services exchanged</li>
                <li>We are not responsible for disputes, disagreements, or conflicts between members</li>
                <li>We do not supervise, direct, or control exchanges between members</li>
                <li>We do not verify the identity, background, qualifications, or skills of members (except where Garda Vetting is undertaken)</li>
                <li>We are not liable for any loss, damage, or injury arising from exchanges</li>
            </ul>

            <p><strong>Indemnification:</strong></p>
            <p>You agree to <strong>indemnify, defend, and hold harmless</strong> hOUR Timebank CLG, its charity trustees, directors, officers, employees, volunteers, and agents from and against any claims, liabilities, damages, losses, costs, or expenses (including reasonable legal fees) arising from:</p>
            <ul>
                <li>Your breach of these Terms of Service</li>
                <li>Your violation of any applicable law or regulation</li>
                <li>Your exchanges or interactions with other members</li>
                <li>Any content you submit, post, or transmit through the platform</li>
                <li>Your negligent or wrongful conduct</li>
            </ul>

            <p><strong>Statutory Protections (Cannot Be Excluded):</strong></p>
            <p>In accordance with Irish consumer protection law, including the Consumer Rights Act 2022 and the European Communities (Unfair Terms in Consumer Contracts) Regulations 1995, nothing in these terms shall exclude or limit liability for:</p>
            <ul>
                <li>Death or personal injury caused by negligence</li>
                <li>Fraud or fraudulent misrepresentation</li>
                <li>Any liability that cannot lawfully be excluded or limited under Irish law</li>
            </ul>
        </div>

        <!-- Dispute Resolution -->
        <div class="terms-section" id="disputes">
            <div class="section-header">
                <div class="section-number">16</div>
                <h2>Dispute Resolution</h2>
            </div>
            <p>We encourage members to resolve disputes amicably. If a dispute arises:</p>

            <p><strong>Step 1: Direct Communication</strong></p>
            <p>First, attempt to resolve the matter directly with the other member. Many misunderstandings can be resolved through open dialogue.</p>

            <p><strong>Step 2: Platform Mediation</strong></p>
            <p>If direct communication fails, you may request informal mediation assistance from hOUR Timebank CLG. We can facilitate communication but are <strong>not arbitrators</strong> and cannot impose binding decisions.</p>

            <p><strong>Step 3: External Mediation</strong></p>
            <p>For unresolved disputes, we recommend the <strong>Mediators' Institute of Ireland (MII)</strong> or another accredited mediation service before pursuing legal action.</p>

            <p><strong>Step 4: Legal Proceedings</strong></p>
            <p>If mediation fails, disputes shall be subject to the exclusive jurisdiction of the Irish courts under Section 18 (Governing Law).</p>

            <div class="terms-notice">
                <span class="notice-icon"><i class="fa-solid fa-circle-info"></i></span>
                <div class="notice-content">
                    <h4>Small Claims Procedure</h4>
                    <p>For claims under €2,000, you may use the <strong>Small Claims Court</strong> procedure, which is a low-cost, informal way to resolve disputes without needing a solicitor.</p>
                </div>
            </div>
        </div>

        <!-- Intellectual Property -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">17</div>
                <h2>Intellectual Property</h2>
            </div>
            <p>The hOUR Timebank platform, including its design, features, and content (excluding user-generated content), is owned by hOUR Timebank CLG and protected by intellectual property laws.</p>
            <ul>
                <li>You may not copy, modify, or distribute platform content without permission</li>
                <li>The hOUR Timebank name and logo are trademarks of hOUR Timebank CLG</li>
                <li>Content you create (profiles, messages, reviews) remains yours, but you grant us a licence to display it on the platform</li>
            </ul>
        </div>

        <!-- Termination -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">18</div>
                <h2>Account Termination</h2>
            </div>
            <p>We reserve the right to suspend or terminate accounts that violate these terms. Reasons for termination include:</p>
            <ul>
                <li>Repeated violation of community guidelines</li>
                <li>Fraudulent or deceptive behaviour</li>
                <li>Harassment of other members</li>
                <li>Extended inactivity (over 12 months without notice)</li>
                <li>Providing false information</li>
            </ul>
            <p>You may also close your account at any time through your account settings or by contacting us. Upon termination, your Time Credits will be forfeited as they have no monetary value.</p>
        </div>

        <!-- Governing Law -->
        <div class="terms-section highlight">
            <div class="section-header">
                <div class="section-number">19</div>
                <h2>Governing Law</h2>
            </div>
            <p>These Terms of Service are governed by and construed in accordance with the <strong>laws of the Republic of Ireland</strong>.</p>
            <p>Any disputes arising from these terms or your use of the platform shall be subject to the exclusive jurisdiction of the <strong>Irish courts</strong>.</p>
            <p>If any provision of these terms is found to be unenforceable, the remaining provisions will continue in full force and effect.</p>
        </div>

        <!-- Changes to Terms -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">20</div>
                <h2>Changes to These Terms</h2>
            </div>
            <p>We may update these terms from time to time to reflect changes in our practices, legal requirements, or platform features. When we make significant changes:</p>
            <ul>
                <li>We will notify you via email or platform notification</li>
                <li>The updated date will be shown at the top of this page</li>
                <li>Continued use of the platform after changes constitutes acceptance of the new terms</li>
                <li>If you disagree with changes, you may close your account</li>
            </ul>
        </div>

        <!-- Contact CTA -->
        <div class="terms-cta">
            <h2><i class="fa-solid fa-question-circle"></i> Have Questions?</h2>
            <p>If you have any questions about these Terms of Service, need clarification, or are interested in becoming a Partner Organisation, please get in touch.</p>
            <a href="mailto:jasper@hour-timebank.ie" class="terms-cta-btn">
                <i class="fa-solid fa-envelope"></i>
                jasper@hour-timebank.ie
            </a>
        </div>

    </div>
</div>

<!-- Terms Page JavaScript -->
<?php
// Use deployment version for cache busting (same pattern as footer.php)
$deploymentVersion = file_exists(__DIR__ . '/../../../../config/deployment-version.php')
    ? require __DIR__ . '/../../../../config/deployment-version.php'
    : ['version' => time()];
$jsVersion = $deploymentVersion['version'] ?? time();
?>
<script src="/assets/js/terms-page.js?v=<?= $jsVersion ?>" defer></script>

<?php require __DIR__ . '/../../../../layouts/modern/footer.php'; ?>
