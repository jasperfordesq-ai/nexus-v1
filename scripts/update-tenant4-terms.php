<?php
/**
 * Update tenant 4 (Timebank Global) terms with the real Platform Terms of Service.
 *
 * This replaces the placeholder content (copied from tenant 2) with the actual
 * comprehensive Platform Terms from the old sales site.
 *
 * Run inside Docker:
 *   docker exec nexus-php-app sh -c "php /tmp/update-tenant4-terms.php"
 */

$host = getenv('DB_HOST') ?: 'db';
$name = getenv('DB_NAME') ?: 'nexus';
$user = getenv('DB_USER') ?: 'nexus';
$pass = getenv('DB_PASS') ?: 'nexus_secret';

$pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// The real Platform Terms of Service content
$content = <<<'HTML'
<h2 id="introduction">Introduction</h2>
<p>Welcome to <strong>Timebank Global</strong> (accessible at <a href="https://timebank.global">timebank.global</a>), a platform that enables independent timebank communities worldwide to operate, connect, and optionally collaborate through federation.</p>
<p>These Platform Terms of Service (&ldquo;Platform Terms&rdquo;) govern the relationship between:</p>
<ul>
    <li><strong>Timebank Global (Platform Provider)</strong><br>Operated by hOUR Timebank CLG (RBN: Timebank Ireland) &mdash; RCN 20162023</li>
    <li><strong>Tenant Operators</strong><br>Independent organisations operating timebanks on our platform</li>
    <li><strong>Members</strong><br>Individual users of each tenant timebank</li>
</ul>
<div class="legal-notice">
    <h4>Understanding This Document</h4>
    <p>This document establishes Timebank Global as a technology platform provider. Each Tenant Operator is an independent organisation responsible for their own timebank community, compliance, and member relationships.</p>
</div>

<h2 id="definitions">1. Definitions</h2>
<p>Throughout these Platform Terms, the following terms have specific meanings:</p>
<ul>
    <li><strong>&ldquo;Platform&rdquo;</strong> &mdash; The Timebank Global software, infrastructure, and services accessible at timebank.global and associated domains.</li>
    <li><strong>&ldquo;Platform Provider&rdquo;</strong> &mdash; hOUR Timebank CLG (Registered Business Name: Timebank Ireland), a Company Limited by Guarantee registered in Ireland, and a Registered Charity (RCN 20162023), operating the Timebank Global platform.</li>
    <li><strong>&ldquo;Tenant&rdquo; or &ldquo;Tenant Operator&rdquo;</strong> &mdash; An independent organisation, community group, charity, or other entity that has been granted access to operate a timebank instance on the Platform.</li>
    <li><strong>&ldquo;Member&rdquo; or &ldquo;End User&rdquo;</strong> &mdash; An individual who registers for and uses a timebank operated by a Tenant Operator.</li>
    <li><strong>&ldquo;Time Credits&rdquo;</strong> &mdash; Non-monetary units used within the Platform to track and facilitate the exchange of services between Members.</li>
    <li><strong>&ldquo;Federation&rdquo;</strong> &mdash; Optional features that allow approved Tenants and their Members to interact across timebank boundaries.</li>
    <li><strong>&ldquo;Services&rdquo;</strong> &mdash; The activities, skills, or assistance that Members offer to and receive from each other through the Platform.</li>
    <li><strong>&ldquo;Tenant Agreement&rdquo;</strong> &mdash; A separate agreement between the Platform Provider and each Tenant Operator governing specific operational terms.</li>
</ul>

<h2 id="platform-role">2. Platform Provider Role &amp; Disclaimer</h2>
<div class="legal-notice">
    <h4>DIRECTORY SERVICE ONLY</h4>
    <p>Timebank Global is a <strong>DIRECTORY and SOFTWARE PLATFORM</strong>. We provide technology that enables independent timebanks to list themselves and operate. We do NOT operate any timebank (except Timebank Ireland), we do NOT participate in any service exchange, and we accept NO liability for the actions of Tenant Operators or their Members. Each timebank listed on our platform is 100% independent and solely responsible for themselves.</p>
</div>

<h3>2.1 What We Are</h3>
<p>Timebank Global is a technology platform provider and directory service, operated by hOUR Timebank CLG (RBN: Timebank Ireland), a Company Limited by Guarantee and Registered Charity (RCN 20162023) in Ireland. We provide:</p>
<ul>
    <li>Software infrastructure for timebanks to operate</li>
    <li>A directory listing independent timebanks worldwide</li>
    <li>Technical tools and hosting services</li>
</ul>
<p>Our role is analogous to:</p>
<ul>
    <li><strong>Airbnb</strong> &mdash; which does not own properties or employ hosts</li>
    <li><strong>Uber</strong> &mdash; which does not own vehicles or employ drivers</li>
    <li><strong>Amazon Marketplace</strong> &mdash; which does not sell products from third-party sellers</li>
    <li><strong>Facebook Groups</strong> &mdash; which does not operate or moderate community groups</li>
</ul>

<h3>2.2 What We Are Not</h3>
<p>The Platform Provider is expressly <strong>NOT</strong>:</p>
<ul>
    <li>An operator, manager, or controller of any Tenant timebank</li>
    <li>A party to any exchange, transaction, or agreement between Members</li>
    <li>An employer, agent, principal, or joint venturer with any Tenant Operator or Member</li>
    <li>A supervisor, verifier, or validator of Members&rsquo; identities, skills, qualifications, or backgrounds</li>
    <li>An endorser, guarantor, or insurer of any Services exchanged</li>
    <li>Responsible or liable for the conduct, acts, or omissions of Tenant Operators or their Members</li>
    <li>A financial services provider, payment processor, money transmitter, or currency issuer</li>
    <li>A provider of any services exchanged between Members (we only provide the platform software)</li>
</ul>

<h3>2.3 No Agency or Employment Relationship</h3>
<p>No agency, partnership, joint venture, employer-employee, or franchisor-franchisee relationship is intended or created by these Platform Terms between the Platform Provider and any Tenant Operator, Member, or third party.</p>
<p>Tenant Operators are independent entities that use our software under licence. They are not our agents, employees, franchisees, or representatives. We do not control their operations, policies, or conduct.</p>

<div class="legal-notice">
    <h4>Critical Disclaimer &mdash; Please Read Carefully</h4>
    <p><strong>WE DO NOT VERIFY, VET, ENDORSE, OR GUARANTEE:</strong></p>
    <ul>
        <li>The identity, background, or qualifications of any Member</li>
        <li>The quality, safety, legality, or suitability of any Services offered or exchanged</li>
        <li>The accuracy of any information provided by Tenant Operators or Members</li>
        <li>The compliance of any Tenant Operator with applicable laws</li>
        <li>The outcome of any service exchange or interaction between Members</li>
    </ul>
    <p><strong>ALL EXCHANGES ARE CONDUCTED ENTIRELY AT THE RISK OF THE PARTICIPATING MEMBERS AND UNDER THE SOLE RESPONSIBILITY OF THE RELEVANT TENANT OPERATOR.</strong></p>
</div>

<h3>2.4 Platform Services We Provide</h3>
<p>The Platform Provider offers technology services only:</p>
<ul>
    <li>Secure cloud hosting and technical infrastructure</li>
    <li>User registration and authentication systems</li>
    <li>Time credit tracking and ledger management tools</li>
    <li>Communication features (messaging, notifications)</li>
    <li>Member directory and skill-matching functionality</li>
    <li>Optional federation services for cross-tenant collaboration</li>
    <li>Administrative dashboards for Tenant Operators</li>
    <li>Technical support and platform maintenance</li>
</ul>
<p>We do not provide any of the actual services exchanged between Members. Those services are provided solely by Members to other Members, facilitated by Tenant Operators.</p>

<h3>2.5 Tenant Operators Are 100% Responsible</h3>
<p>Each Tenant Operator that uses our platform is an independent legal entity that is solely and entirely responsible for:</p>
<ul>
    <li>Their own timebank&rsquo;s operation, policies, and procedures</li>
    <li>Their Members&rsquo; conduct and the services they exchange</li>
    <li>Compliance with all applicable laws in their jurisdiction</li>
    <li>Member verification, safeguarding, and dispute resolution</li>
    <li>Insurance, liability coverage, and risk management</li>
    <li>Their own Terms of Service and Privacy Policy</li>
</ul>
<p>The Platform Provider accepts no responsibility whatsoever for any Tenant Operator&rsquo;s timebank or its Members.</p>

<h2 id="acceptance">3. Acceptance of Terms</h2>
<h3>3.1 For Tenant Operators</h3>
<p>By applying for, receiving, or using a tenant instance on the Platform, you (the Tenant Operator) agree to:</p>
<ul>
    <li>These Platform Terms of Service</li>
    <li>The separate Tenant Agreement (if applicable)</li>
    <li>Our Data Processing Agreement</li>
    <li>All applicable laws and regulations in your jurisdiction</li>
</ul>

<h3>3.2 For Members</h3>
<p>By creating an account on any timebank operated on this Platform, you (the Member) agree to:</p>
<ul>
    <li>These Platform Terms of Service</li>
    <li>The specific Terms of Service of your Tenant timebank</li>
    <li>The Privacy Policy of both the Platform and your Tenant timebank</li>
</ul>

<h3>3.3 Capacity to Accept</h3>
<p>You represent that you:</p>
<ul>
    <li>Are at least 16 years of age (or the age of digital consent in your jurisdiction)</li>
    <li>Have the legal capacity to enter into binding agreements</li>
    <li>Are not prohibited from using the Platform under any applicable law</li>
    <li>If accepting on behalf of an organisation, have authority to bind that organisation</li>
</ul>

<h2 id="tenant-responsibilities">4. Tenant Operator Responsibilities</h2>
<p>Each Tenant Operator is an independent entity and is solely responsible for:</p>

<h3>4.1 Legal &amp; Regulatory Compliance</h3>
<ul>
    <li>Compliance with all laws and regulations in their operating jurisdiction(s)</li>
    <li>Maintaining any required registrations, permits, or licences</li>
    <li>Data protection compliance (GDPR, CCPA, or equivalent local laws)</li>
    <li>Tax obligations and reporting requirements</li>
    <li>Employment law considerations where applicable</li>
    <li>Charity or non-profit regulatory requirements where applicable</li>
</ul>

<h3>4.2 Community Management</h3>
<ul>
    <li>Establishing and enforcing their own Terms of Service for Members</li>
    <li>Creating and maintaining their own Privacy Policy</li>
    <li>Member verification and vetting procedures</li>
    <li>Safeguarding policies and procedures (especially for vulnerable persons)</li>
    <li>Dispute resolution between their Members</li>
    <li>Content moderation within their timebank</li>
    <li>Training and supporting their Members</li>
</ul>

<h3>4.3 Operational Responsibilities</h3>
<ul>
    <li>Accurate representation of their organisation and its purpose</li>
    <li>Maintaining adequate insurance coverage appropriate to their activities</li>
    <li>Designating authorised administrators for their tenant instance</li>
    <li>Responding to Member inquiries and complaints</li>
    <li>Reporting serious incidents to appropriate authorities</li>
    <li>Cooperating with Platform Provider requests for information</li>
</ul>
<div class="legal-notice">
    <h4>Independent Legal Entities</h4>
    <p>Each Tenant Operator is an independent legal entity. The Platform Provider has no control over Tenant operations and accepts no liability for Tenant Operator actions, omissions, or failures to comply with applicable laws.</p>
</div>

<h2 id="member-terms">5. Member Terms</h2>
<h3>5.1 Relationship Structure</h3>
<p>As a Member, your primary relationship is with your Tenant Operator (your local timebank). The Platform Provider supplies the technology; your Tenant Operator runs your timebank community.</p>
<ul>
    <li><strong>For membership questions:</strong> Contact your Tenant Operator</li>
    <li><strong>For disputes with other Members:</strong> Contact your Tenant Operator</li>
    <li><strong>For platform technical issues:</strong> Contact the Platform Provider</li>
</ul>

<h3>5.2 Account Responsibilities</h3>
<p>All Members must:</p>
<ul>
    <li>Provide accurate and truthful information during registration</li>
    <li>Maintain the security of their account credentials</li>
    <li>Not share accounts or transfer accounts to others</li>
    <li>Promptly update information if it changes</li>
    <li>Comply with their Tenant Operator&rsquo;s Terms of Service</li>
    <li>Comply with these Platform Terms</li>
</ul>

<h3>5.3 Service Exchange Responsibilities</h3>
<p>When participating in service exchanges, Members must:</p>
<ul>
    <li>Accurately represent their skills and abilities</li>
    <li>Honour commitments made to other Members</li>
    <li>Communicate promptly and respectfully</li>
    <li>Not offer Services that are illegal in their jurisdiction</li>
    <li>Not offer Services requiring professional licences they do not hold</li>
    <li>Take reasonable precautions for personal safety</li>
    <li>Report concerns to their Tenant Operator</li>
</ul>

<h3>5.4 Safety Guidance</h3>
<p>The Platform Provider recommends that Members:</p>
<ul>
    <li>Meet in public places for initial exchanges</li>
    <li>Inform someone of their whereabouts during exchanges</li>
    <li>Trust their instincts and decline exchanges that feel unsafe</li>
    <li>Use the Platform&rsquo;s messaging for initial communications</li>
    <li>Report any concerning behaviour to their Tenant Operator</li>
</ul>

<h2 id="federation">6. Federation Services</h2>
<p>The Platform offers optional federation features that enable approved Tenant Operators and their Members to interact across timebank boundaries.</p>

<h3>6.1 Federation is Optional</h3>
<ul>
    <li>Federation features are disabled by default</li>
    <li>Tenant Operators must explicitly enable federation for their timebank</li>
    <li>Members must explicitly opt-in to federation visibility</li>
    <li>Either party may disable federation at any time</li>
</ul>

<h3>6.2 Federation Governance</h3>
<p>When federation features are enabled:</p>
<ul>
    <li>Cross-tenant interactions are governed by the policies of both participating Tenant Operators</li>
    <li>Members must comply with the rules of both their home timebank and any federated timebank they interact with</li>
    <li>Disputes involving cross-tenant exchanges should first be addressed with the relevant Tenant Operators</li>
    <li>The Platform Provider may mediate federation disputes but is not obligated to do so</li>
</ul>

<h3>6.3 Federation Controls</h3>
<p>The Platform Provider maintains controls to ensure federation safety:</p>
<ul>
    <li><strong>Global Kill Switch:</strong> Platform-wide federation can be disabled instantly if required</li>
    <li><strong>Tenant Whitelist:</strong> Only approved Tenants may participate in federation</li>
    <li><strong>Partnership Approval:</strong> Tenant-to-tenant federation requires mutual agreement</li>
    <li><strong>Rate Limits:</strong> Cross-tenant transactions are subject to rate limits</li>
    <li><strong>Audit Logging:</strong> All federation activity is logged for security purposes</li>
</ul>

<h3>6.4 Cross-Tenant Transactions</h3>
<p>When Time Credits are exchanged across tenant boundaries:</p>
<ul>
    <li>Each Tenant maintains its own Time Credit ledger</li>
    <li>Cross-tenant transfers are recorded in both ledgers</li>
    <li>Time Credit values are equal across all Tenants (1 hour = 1 credit)</li>
    <li>The Platform Provider does not guarantee cross-tenant credit redemption</li>
</ul>
<div class="legal-notice">
    <h4>Federation Data Sharing</h4>
    <p>When you opt into federation, limited profile information (name, skills, general location) may be visible to Members of partner timebanks. Your Tenant Operator&rsquo;s Privacy Policy and the Platform Privacy Policy provide more details.</p>
</div>

<h2 id="time-credits">7. Time Credits</h2>
<h3>7.1 Nature of Time Credits</h3>
<p>Time Credits are <strong>not</strong>:</p>
<ul>
    <li>Money, currency, or legal tender</li>
    <li>Cryptocurrency or digital assets</li>
    <li>Vouchers, coupons, or gift cards</li>
    <li>Transferable for cash or monetary value</li>
    <li>Property that can be bought, sold, or traded for money</li>
</ul>
<p>Time Credits <strong>are</strong>:</p>
<ul>
    <li>A record-keeping mechanism to track service exchanges</li>
    <li>Equal in value (1 hour of any service = 1 Time Credit)</li>
    <li>Administered by Tenant Operators</li>
    <li>Subject to the policies of each Tenant timebank</li>
</ul>

<h3>7.2 Time Credit Policies</h3>
<ul>
    <li>Each Tenant Operator may set policies for initial credit allocation</li>
    <li>Time Credits may expire according to Tenant policies</li>
    <li>Time Credits cannot be inherited or transferred upon death</li>
    <li>Time Credits are forfeited upon account closure or termination</li>
    <li>The Platform Provider is not responsible for lost or disputed Time Credits</li>
</ul>
<div class="legal-notice">
    <h4>Tax Considerations</h4>
    <p>Time Credits may have tax implications in some jurisdictions. Members and Tenant Operators are responsible for understanding and complying with their local tax obligations. The Platform Provider does not provide tax advice.</p>
</div>

<h2 id="prohibited-uses">8. Prohibited Uses</h2>
<p>The following activities are strictly prohibited on the Platform:</p>

<h3>8.1 Illegal Activities</h3>
<ul>
    <li>Any activity that violates applicable laws or regulations</li>
    <li>Money laundering or tax evasion schemes</li>
    <li>Offering or requesting illegal services</li>
    <li>Fraud, theft, or deception</li>
    <li>Trafficking in illegal goods or services</li>
</ul>

<h3>8.2 Harmful Conduct</h3>
<ul>
    <li>Harassment, bullying, or intimidation</li>
    <li>Discrimination based on protected characteristics</li>
    <li>Threatening or violent behaviour</li>
    <li>Sexual harassment or exploitation</li>
    <li>Endangering minors or vulnerable persons</li>
</ul>

<h3>8.3 Platform Abuse</h3>
<ul>
    <li>Creating multiple accounts for fraudulent purposes</li>
    <li>Impersonating others or misrepresenting identity</li>
    <li>Spam, unsolicited commercial messages, or advertising</li>
    <li>Attempting to circumvent security measures</li>
    <li>Reverse-engineering or copying Platform software</li>
    <li>Scraping or harvesting user data</li>
    <li>Introducing malware, viruses, or malicious code</li>
    <li>Interfering with Platform operations or other users&rsquo; access</li>
</ul>

<h3>8.4 Misrepresentation</h3>
<ul>
    <li>Falsely claiming professional qualifications or licences</li>
    <li>Misrepresenting skills, experience, or capabilities</li>
    <li>Creating false or misleading reviews</li>
    <li>Misrepresenting affiliation with the Platform Provider</li>
    <li>Using Platform branding without authorisation</li>
</ul>

<h2 id="intellectual-property">9. Intellectual Property</h2>
<h3>9.1 Platform Provider IP</h3>
<p>The Platform Provider owns or licences all intellectual property in:</p>
<ul>
    <li>The Platform software, code, and architecture</li>
    <li>Platform branding, logos, and trademarks (including &ldquo;Timebank Global&rdquo;)</li>
    <li>Proprietary algorithms (including EdgeRank, MatchRank, CommunityRank)</li>
    <li>Platform documentation and materials</li>
    <li>The structure and organisation of the Platform</li>
</ul>

<h3>9.2 Tenant Operator IP</h3>
<p>Tenant Operators retain ownership of:</p>
<ul>
    <li>Their own branding, logos, and trademarks</li>
    <li>Custom content they create for their timebank</li>
    <li>Their own policies and documentation</li>
</ul>

<h3>9.3 Member Content</h3>
<p>Members retain ownership of content they create but grant:</p>
<ul>
    <li>Their Tenant Operator a licence to display and use content within the timebank</li>
    <li>The Platform Provider a licence to host, display, and process content as necessary to provide Platform services</li>
    <li>If federation is enabled, partner Tenant Operators a licence to display federated content</li>
</ul>

<h3>9.4 Restrictions</h3>
<ul>
    <li>No right to copy, modify, or create derivative works from Platform software</li>
    <li>No right to sublicence Platform access to third parties</li>
    <li>No right to use Platform Provider trademarks without written permission</li>
    <li>No right to remove or alter copyright notices or attributions</li>
</ul>

<h2 id="data-protection">10. Data Protection</h2>
<h3>10.1 Data Controller Roles</h3>
<ul>
    <li><strong>Platform Provider:</strong> Data Controller for Platform operations and Tenant Operator data</li>
    <li><strong>Tenant Operators:</strong> Data Controllers for their Member data</li>
    <li><strong>Joint Controllers:</strong> Platform Provider and Tenant Operators are joint controllers for certain shared processing activities</li>
</ul>

<h3>10.2 Data Processing Agreement</h3>
<p>The Platform Provider acts as a Data Processor on behalf of Tenant Operators for certain processing activities. A separate Data Processing Agreement governs this relationship and addresses:</p>
<ul>
    <li>Categories of personal data processed</li>
    <li>Processing purposes and instructions</li>
    <li>Security measures and safeguards</li>
    <li>Sub-processor engagement</li>
    <li>Data subject rights assistance</li>
    <li>Data breach notification procedures</li>
    <li>Data return and deletion upon termination</li>
</ul>

<h3>10.3 International Data Transfers</h3>
<p>The Platform Provider:</p>
<ul>
    <li>Primarily stores data within the European Economic Area (EEA)</li>
    <li>Uses EU-approved transfer mechanisms for any transfers outside the EEA</li>
    <li>Maintains records of all sub-processors and their locations</li>
    <li>Will notify Tenant Operators of material changes to sub-processors</li>
</ul>

<h3>10.4 Privacy Policies</h3>
<p>Data collection and use is further described in:</p>
<ul>
    <li>The Platform Privacy Policy (governing Platform Provider processing)</li>
    <li>Each Tenant Operator&rsquo;s Privacy Policy (governing their Member data processing)</li>
</ul>

<h2 id="liability">11. Limitation of Liability</h2>
<h3>11.1 Platform Provider Liability Exclusions</h3>
<p>To the maximum extent permitted by applicable law, the Platform Provider shall not be liable for:</p>
<ul>
    <li>Acts or omissions of Tenant Operators</li>
    <li>Acts or omissions of Members</li>
    <li>The quality, safety, legality, or suitability of any Services exchanged</li>
    <li>Disputes between Members or between Members and Tenant Operators</li>
    <li>Loss or theft of Time Credits</li>
    <li>Personal injury, property damage, or other harm arising from service exchanges</li>
    <li>Tenant Operator compliance failures</li>
    <li>Third-party services or content</li>
    <li>Service interruptions, data loss, or security breaches beyond our reasonable control</li>
</ul>

<h3>11.2 Disclaimer of Warranties</h3>
<p>The Platform is provided <strong>&ldquo;as is&rdquo;</strong> and <strong>&ldquo;as available&rdquo;</strong> without warranties of any kind, whether express or implied, including but not limited to:</p>
<ul>
    <li>Implied warranties of merchantability</li>
    <li>Fitness for a particular purpose</li>
    <li>Non-infringement</li>
    <li>Accuracy, reliability, or completeness of Platform content</li>
    <li>Uninterrupted or error-free operation</li>
    <li>Security from unauthorised access</li>
</ul>

<h3>11.3 Liability Cap</h3>
<p>Where liability cannot be excluded, the Platform Provider&rsquo;s total aggregate liability to any Tenant Operator or Member shall not exceed:</p>
<ul>
    <li><strong>For Tenant Operators:</strong> The fees paid by that Tenant Operator in the 12 months preceding the claim (or &euro;100 if no fees were paid)</li>
    <li><strong>For Members:</strong> &euro;100</li>
</ul>

<h3>11.4 Exclusion of Consequential Damages</h3>
<p>The Platform Provider shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including but not limited to:</p>
<ul>
    <li>Loss of profits, revenue, or business</li>
    <li>Loss of data or goodwill</li>
    <li>Cost of substitute services</li>
    <li>Any damages arising from service exchanges</li>
</ul>
<div class="legal-notice">
    <h4>Consumer Rights</h4>
    <p>Nothing in these Platform Terms excludes or limits liability that cannot be excluded or limited under applicable law, including liability for death or personal injury caused by negligence, or for fraud or fraudulent misrepresentation.</p>
</div>

<h2 id="indemnification">12. Indemnification</h2>
<div class="legal-notice">
    <h4>100% INDEMNIFICATION REQUIRED</h4>
    <p>Tenant Operators and Members must fully indemnify the Platform Provider against ALL claims arising from their use of the Platform or the operation of their timebanks. The Platform Provider accepts NO liability for any timebank&rsquo;s operations, members, or service exchanges.</p>
</div>

<h3>12.1 Tenant Operator Full Indemnification</h3>
<p>To the fullest extent permitted by applicable law, each Tenant Operator agrees to indemnify, defend, and hold completely harmless the Platform Provider, hOUR Timebank CLG, and its officers, directors, employees, agents, successors, and assigns (collectively, the &ldquo;Indemnified Parties&rdquo;) from and against any and all claims, demands, actions, suits, proceedings, losses, damages, liabilities, settlements, judgments, fines, penalties, costs, and expenses (including reasonable attorneys&rsquo; fees, court costs, and expert witness fees) arising out of or relating to:</p>
<ul>
    <li>The Tenant Operator&rsquo;s use of the Platform or operation of their timebank</li>
    <li>Any Services provided, offered, or exchanged by the Tenant Operator&rsquo;s Members</li>
    <li>Any act or omission of the Tenant Operator or any of their Members, employees, volunteers, or agents</li>
    <li>Any injury, death, property damage, or other harm arising from any service exchange facilitated by the Tenant Operator&rsquo;s timebank</li>
    <li>The Tenant Operator&rsquo;s breach of these Platform Terms or any applicable law</li>
    <li>Any claim by a Member, former Member, or third party relating to the Tenant Operator&rsquo;s timebank</li>
    <li>Disputes between Members of the Tenant Operator&rsquo;s timebank or with Members of other timebanks</li>
    <li>The Tenant Operator&rsquo;s failure to fulfil their responsibilities under Section 4</li>
    <li>Any allegation that the Tenant Operator violated any third party&rsquo;s rights, including intellectual property rights</li>
    <li>Any regulatory investigation, inquiry, or enforcement action relating to the Tenant Operator&rsquo;s timebank</li>
    <li>Tax liabilities or claims arising from the Tenant Operator&rsquo;s activities</li>
    <li>Any claim that Time Credits constitute money, currency, or taxable income</li>
</ul>

<h3>12.2 Member Full Indemnification</h3>
<p>To the fullest extent permitted by applicable law, each Member agrees to indemnify, defend, and hold completely harmless the Indemnified Parties and their Tenant Operator from and against any and all claims, demands, actions, suits, proceedings, losses, damages, liabilities, settlements, judgments, fines, penalties, costs, and expenses (including reasonable attorneys&rsquo; fees) arising out of or relating to:</p>
<ul>
    <li>The Member&rsquo;s use of the Platform or participation in their timebank</li>
    <li>Any Services provided or received by the Member</li>
    <li>Any injury, death, property damage, or other harm arising from services the Member provided or received</li>
    <li>The Member&rsquo;s breach of these Platform Terms, their Tenant&rsquo;s Terms, or any applicable law</li>
    <li>The Member&rsquo;s content, communications, listings, or profile information</li>
    <li>Any claim by another Member, Tenant Operator, or third party relating to the Member&rsquo;s conduct</li>
    <li>Any allegation that the Member misrepresented their skills, qualifications, or identity</li>
    <li>Any claim arising from the Member&rsquo;s failure to obtain required licences, permits, or insurance</li>
</ul>

<h3>12.3 Indemnification Procedure</h3>
<p>The indemnification obligations above are subject to:</p>
<ul>
    <li><strong>Notice:</strong> The Indemnified Party shall promptly notify the indemnifying party of any claim, but failure to provide notice shall not relieve indemnification obligations except to the extent the indemnifying party is materially prejudiced.</li>
    <li><strong>Control:</strong> The indemnifying party shall have the right to control the defence of any claim at their own expense, with counsel reasonably acceptable to the Indemnified Party.</li>
    <li><strong>Cooperation:</strong> The Indemnified Party shall cooperate with the defence and may participate at their own expense.</li>
    <li><strong>Settlement:</strong> No settlement that admits liability or imposes obligations on the Indemnified Party shall be made without written consent.</li>
</ul>

<h3>12.4 Release of Claims</h3>
<p>Tenant Operators and Members hereby release the Platform Provider and all Indemnified Parties from any and all claims, demands, damages (actual and consequential), losses, and causes of action of every kind and nature, known and unknown, suspected and unsuspected, disclosed and undisclosed, arising out of or in any way connected with:</p>
<ul>
    <li>Any dispute with other Members, Tenant Operators, or third parties</li>
    <li>Any service exchange or transaction facilitated through the Platform</li>
    <li>Any act or omission of any other user of the Platform</li>
    <li>Any content posted by other users on the Platform</li>
</ul>
<p>If you are a California resident, you expressly waive California Civil Code Section 1542, which provides: &ldquo;A general release does not extend to claims that the creditor or releasing party does not know or suspect to exist in his or her favor at the time of executing the release, and that if known by him or her, would have materially affected his or her settlement with the debtor or released party.&rdquo;</p>

<h3>12.5 Survival</h3>
<p>The indemnification and release obligations in this Section 12 shall survive termination of these Platform Terms, closure of any account, or discontinuation of the Platform, and shall remain in full force and effect indefinitely.</p>

<h2 id="termination">13. Termination</h2>
<h3>13.1 Termination by Platform Provider</h3>
<p>The Platform Provider may suspend or terminate access:</p>
<ul>
    <li>Immediately for serious violations, security threats, or illegal activity</li>
    <li>With 30 days&rsquo; notice for other breaches, upon failure to cure</li>
    <li>With 90 days&rsquo; notice for convenience (discontinuation of service)</li>
</ul>

<h3>13.2 Termination by Tenant Operators</h3>
<p>Tenant Operators may terminate their tenancy:</p>
<ul>
    <li>With 30 days&rsquo; written notice at any time</li>
    <li>Subject to data export and transition provisions in the Tenant Agreement</li>
</ul>

<h3>13.3 Effect of Termination</h3>
<p>Upon termination:</p>
<ul>
    <li>Access to the Platform will be disabled</li>
    <li>Time Credits will be forfeited (cannot be converted to cash)</li>
    <li>Tenant Operators may request data export within 30 days</li>
    <li>Personal data will be retained per the Privacy Policy and retention schedule</li>
    <li>Provisions that by their nature should survive will survive (including Sections 11, 12, and 15)</li>
</ul>

<h3>13.4 Member Account Closure</h3>
<p>Members may close their accounts by:</p>
<ul>
    <li>Using the account deletion feature in settings</li>
    <li>Contacting their Tenant Operator</li>
</ul>
<p>Account closure is subject to the Tenant Operator&rsquo;s policies and applicable data retention requirements.</p>

<h2 id="modifications">14. Modifications to Terms</h2>
<h3>14.1 Right to Modify</h3>
<p>The Platform Provider reserves the right to modify these Platform Terms at any time. Modifications may be made to:</p>
<ul>
    <li>Reflect changes in law or regulatory requirements</li>
    <li>Address security or operational needs</li>
    <li>Improve clarity or correct errors</li>
    <li>Add new features or services</li>
    <li>Remove discontinued features or services</li>
</ul>

<h3>14.2 Notice of Changes</h3>
<ul>
    <li><strong>Material changes:</strong> 30 days&rsquo; advance notice via email to Tenant Operators</li>
    <li><strong>Minor changes:</strong> Posted on the Platform with updated effective date</li>
    <li><strong>Emergency changes:</strong> May be effective immediately if required for security or legal compliance</li>
</ul>

<h3>14.3 Acceptance of Changes</h3>
<p>Continued use of the Platform after the effective date of changes constitutes acceptance. Tenant Operators who do not accept material changes may terminate their tenancy within the notice period.</p>

<h2 id="governing-law">15. Governing Law, Jurisdiction &amp; Contracting Entities</h2>
<div class="legal-notice">
    <h4>Global Platform Structure</h4>
    <p>Timebank Global operates worldwide. The entity you contract with, the governing law, and where disputes are resolved depend on your country of residence. Please see Schedule 1 below for details specific to your region.</p>
</div>

<h3>15.1 Contracting Entities by Region</h3>
<p>When used in these Platform Terms, &ldquo;Timebank Global,&rdquo; &ldquo;Platform Provider,&rdquo; &ldquo;we,&rdquo; &ldquo;us,&rdquo; or &ldquo;our&rdquo; refers to the entity set out in Schedule 1 based on your country of residence or place of establishment.</p>
<p>Currently, all services worldwide are provided by hOUR Timebank CLG T/A Timebank Ireland, registered in Ireland. As the Platform grows, regional entities may be established, and this section will be updated accordingly.</p>

<h3>15.2 Governing Law by Region</h3>
<p>The laws that govern these Platform Terms depend on where you reside:</p>
<ul>
    <li><strong>European Economic Area (EEA), United Kingdom, or Switzerland:</strong> These Terms are governed by the laws of Ireland.</li>
    <li><strong>United States or Canada:</strong> These Terms are governed by the laws of the State of Delaware, USA, without regard to conflict of law principles. See Section 15.5 for US-specific provisions.</li>
    <li><strong>Australia or New Zealand:</strong> These Terms are governed by the laws of Ireland, subject to mandatory consumer protection laws of your country that cannot be excluded.</li>
    <li><strong>Brazil:</strong> These Terms are governed by the laws of Brazil. See Section 15.7 for Brazil-specific provisions.</li>
    <li><strong>All other countries:</strong> These Terms are governed by the laws of Ireland.</li>
</ul>

<h3>15.3 Dispute Resolution (General)</h3>
<p>Unless otherwise specified for your region, disputes shall be resolved as follows:</p>
<ol>
    <li><strong>Informal Resolution:</strong> The parties shall first attempt to resolve the dispute informally through good-faith negotiation for a period of 30 days.</li>
    <li><strong>Mediation:</strong> If informal resolution fails, the parties agree to attempt mediation before initiating legal proceedings.</li>
    <li><strong>Jurisdiction:</strong> Subject to regional provisions below, legal proceedings shall be brought in the courts of Ireland, and the parties consent to the jurisdiction of such courts.</li>
</ol>

<h3>15.4 European Economic Area, UK &amp; Switzerland</h3>
<p>If you reside in the EEA, United Kingdom, or Switzerland:</p>
<ul>
    <li><strong>Consumer Rights Preserved:</strong> If you are acting as a consumer, nothing in these Terms affects your statutory consumer rights under mandatory laws of your country of residence, including the right to bring proceedings in the courts of your country.</li>
    <li><strong>Online Dispute Resolution:</strong> The European Commission provides an online dispute resolution platform at <a href="https://ec.europa.eu/consumers/odr" target="_blank">https://ec.europa.eu/consumers/odr</a>. We are not obliged and do not commit to using this platform.</li>
    <li><strong>UK Post-Brexit:</strong> For UK residents, these Terms are governed by the laws of Ireland. However, UK consumer protection laws that cannot be excluded by contract will continue to apply.</li>
    <li><strong>No Mandatory Arbitration:</strong> The arbitration provisions in Section 15.5 do not apply to you.</li>
</ul>

<h3>15.5 United States &mdash; Additional Terms</h3>
<div class="legal-notice">
    <h4>PLEASE READ THIS SECTION CAREFULLY</h4>
    <p>It affects your legal rights, including your right to file a lawsuit in court.</p>
</div>
<p>If you reside in the United States, the following additional terms apply:</p>

<h4>15.5.1 Governing Law and Jurisdiction</h4>
<p>These Terms are governed by the laws of the State of Delaware, USA, without regard to conflict of law principles. For any claim not subject to arbitration, you agree to submit to the personal jurisdiction of the state and federal courts located in Wilmington, Delaware.</p>

<h4>15.5.2 Agreement to Arbitrate</h4>
<p>You and the Platform Provider agree that any dispute, claim, or controversy arising out of or relating to these Terms or the Platform (collectively, &ldquo;Disputes&rdquo;) will be resolved by binding individual arbitration rather than in court, except that:</p>
<ul>
    <li>Either party may bring an individual action in small claims court if the claim qualifies;</li>
    <li>Either party may seek injunctive relief in court for intellectual property infringement or misuse.</li>
</ul>
<p>There is no judge or jury in arbitration. Arbitration procedures are simpler and more limited than court proceedings. The arbitrator&rsquo;s decision is binding and may be entered as a judgment in any court of competent jurisdiction.</p>

<h4>15.5.3 Arbitration Rules and Procedures</h4>
<p>The arbitration will be administered by the American Arbitration Association (&ldquo;AAA&rdquo;) under its Consumer Arbitration Rules, as modified by this agreement. The AAA Rules are available at <a href="https://www.adr.org" target="_blank">www.adr.org</a>.</p>
<ul>
    <li>The arbitration will be conducted in English.</li>
    <li>The arbitration will take place in your county of residence or, at your election, by telephone or video conference.</li>
    <li>For claims under $10,000, you may choose whether arbitration proceeds in person, by telephone, or based solely on written submissions.</li>
    <li>The arbitrator may award the same damages and relief as a court (including injunctive and declaratory relief or statutory damages).</li>
</ul>

<h4>15.5.4 Class Action and Jury Trial Waiver</h4>
<p><strong>YOU AND THE PLATFORM PROVIDER AGREE THAT EACH MAY BRING CLAIMS AGAINST THE OTHER ONLY IN YOUR OR ITS INDIVIDUAL CAPACITY, AND NOT AS A PLAINTIFF OR CLASS MEMBER IN ANY PURPORTED CLASS, COLLECTIVE, OR REPRESENTATIVE PROCEEDING.</strong></p>
<p>Unless both you and we agree otherwise, the arbitrator may not consolidate more than one person&rsquo;s claims, and may not otherwise preside over any form of representative or class proceeding.</p>
<p><strong>YOU ACKNOWLEDGE AND AGREE THAT YOU AND THE PLATFORM PROVIDER ARE EACH WAIVING THE RIGHT TO A TRIAL BY JURY.</strong></p>

<h4>15.5.5 30-Day Right to Opt Out of Arbitration</h4>
<p>You have the right to opt out of the arbitration and class action waiver provisions above by sending written notice of your decision to opt out to: <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a> with the subject line &ldquo;Arbitration Opt-Out&rdquo; within 30 days of first accepting these Terms. Your notice must include your name, address, email address, and username. If you opt out, you and we will not be bound by the arbitration provisions, and disputes will be resolved in court.</p>

<h4>15.5.6 DMCA Notice</h4>
<p>If you believe that content on the Platform infringes your copyright, please send a notice complying with the Digital Millennium Copyright Act (17 U.S.C. &sect; 512) to our designated agent at: <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a>.</p>

<h4>15.5.7 California Residents</h4>
<p>If you are a California resident, you waive California Civil Code Section 1542, which says: &ldquo;A general release does not extend to claims that the creditor or releasing party does not know or suspect to exist in his or her favor at the time of executing the release, and that if known by him or her, would have materially affected his or her settlement with the debtor or released party.&rdquo;</p>
<p>Under California Civil Code Section 1789.3, California users are entitled to the following specific consumer rights notice: The Complaint Assistance Unit of the Division of Consumer Services of the California Department of Consumer Affairs may be contacted in writing at 1625 North Market Blvd., Suite N 112, Sacramento, CA 95834, or by telephone at (916) 445-1254 or (800) 952-5210.</p>

<h3>15.6 Australia &amp; New Zealand</h3>
<p>If you reside in Australia or New Zealand:</p>
<ul>
    <li><strong>Consumer Guarantees:</strong> Nothing in these Terms excludes, restricts, or modifies any consumer guarantee, right, or remedy conferred on you by the Australian Consumer Law (Schedule 2 of the Competition and Consumer Act 2010) or the New Zealand Consumer Guarantees Act 1993 that cannot be excluded.</li>
    <li><strong>Limitation of Liability:</strong> To the extent our liability cannot be excluded, our total liability to you is limited to AUD/NZD $100 or the resupply of the services, at our election.</li>
    <li><strong>Jurisdiction:</strong> Subject to your statutory rights, disputes shall be resolved in the courts of Ireland, or at your election, the courts of your state or territory of residence.</li>
</ul>

<h3>15.7 Brazil</h3>
<p>If you reside in Brazil:</p>
<ul>
    <li><strong>Governing Law:</strong> These Terms are governed by the laws of Brazil, including the Marco Civil da Internet (Law No. 12.965/2014) and the Lei Geral de Prote&ccedil;&atilde;o de Dados (LGPD, Law No. 13.709/2018).</li>
    <li><strong>Jurisdiction:</strong> Legal proceedings may only be brought in the courts of Brazil, in the jurisdiction of your domicile.</li>
    <li><strong>Consumer Rights:</strong> Your consumer rights under the Brazilian Consumer Defense Code (Law No. 8.078/1990) are preserved and cannot be waived.</li>
    <li><strong>No Arbitration:</strong> The arbitration provisions in Section 15.5 do not apply to you.</li>
</ul>

<h3>15.8 Change of Residence</h3>
<p>If you change your country of residence or place of establishment:</p>
<ul>
    <li>The contracting entity, governing law, and jurisdiction provisions that apply to your new country of residence will take effect from the date of your move;</li>
    <li>You agree to update your account information to reflect your new country of residence;</li>
    <li>Disputes arising before your change of residence will be governed by the provisions that applied at the time the dispute arose.</li>
</ul>

<h2 id="contact">16. Contact Information</h2>
<h3>16.1 Platform Provider Contact</h3>
<p><strong>Timebank Global</strong><br>Operated by hOUR Timebank CLG (RBN: Timebank Ireland)<br>Registered Charity Number: 20162023</p>
<ul>
    <li><strong>Registered Address:</strong> 21 P&aacute;irc Goodman, Skibbereen, Co. Cork, P81 AK26, Ireland</li>
    <li><strong>Email:</strong> <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a></li>
</ul>

<h3>16.2 Your Tenant Operator</h3>
<p>For questions about your specific timebank community, membership, or disputes with other Members, please contact your Tenant Operator directly. Their contact information should be available in your timebank&rsquo;s settings or &ldquo;About&rdquo; page.</p>

<h2 id="general-provisions">17. General Provisions</h2>
<h3>17.1 Entire Agreement</h3>
<p>These Platform Terms, together with the Privacy Policy, any applicable Tenant Agreement, and Data Processing Agreement, constitute the entire agreement between you and the Platform Provider regarding the Platform.</p>

<h3>17.2 Severability</h3>
<p>If any provision of these Platform Terms is found to be invalid or unenforceable, the remaining provisions shall continue in full force and effect.</p>

<h3>17.3 Waiver</h3>
<p>No failure or delay by the Platform Provider in exercising any right shall constitute a waiver of that right.</p>

<h3>17.4 Assignment</h3>
<p>You may not assign or transfer your rights under these Platform Terms. The Platform Provider may assign its rights and obligations to a successor in connection with a merger, acquisition, or sale of assets.</p>

<h3>17.5 No Third-Party Beneficiaries</h3>
<p>These Platform Terms do not create any third-party beneficiary rights, except that Tenant Operators are intended third-party beneficiaries of Member obligations.</p>

<h3>17.6 Language</h3>
<p>These Platform Terms are drafted in English. If translated into other languages, the English version shall prevail in case of any conflict.</p>

<h3>17.7 Headings</h3>
<p>Section headings are for convenience only and do not affect the interpretation of these Platform Terms.</p>

<h2 id="acknowledgment">Acknowledgment</h2>
<p>By using the Timebank Global Platform, you acknowledge that you have read, understood, and agree to be bound by these Platform Terms of Service.</p>
<p>If you are a Tenant Operator, you further acknowledge that you are an independent entity responsible for your own timebank community and compliance with applicable laws.</p>
<p>If you are a Member, you acknowledge that your primary relationship is with your Tenant Operator, and that the Platform Provider is a technology provider, not a party to your service exchanges.</p>
HTML;

$title = 'Platform Terms of Service';
$versionNumber = '1.0';
$versionLabel = 'Comprehensive Platform Terms';
$summaryOfChanges = 'Complete Platform Terms of Service covering platform provider role, tenant operator responsibilities, member terms, federation, time credits, data protection, liability, indemnification, and jurisdiction-specific provisions for EEA, US, Australia/NZ, and Brazil.';
$effectiveDate = '2026-01-16';

echo "=== Update Tenant 4 Terms with Real Platform Terms ===\n\n";

// Find the tenant 4 terms document and version
$stmt = $pdo->prepare(
    "SELECT ld.id as doc_id, ld.current_version_id, ldv.id as version_id
     FROM legal_documents ld
     LEFT JOIN legal_document_versions ldv ON ld.current_version_id = ldv.id
     WHERE ld.tenant_id = 4 AND ld.document_type = 'terms'"
);
$stmt->execute();
$row = $stmt->fetch();

if (!$row) {
    echo "ERROR: No terms document found for tenant 4!\n";
    exit(1);
}

echo "Found: doc_id={$row['doc_id']}, version_id={$row['version_id']}\n";
echo "Content length: " . strlen($content) . " chars\n\n";

$pdo->beginTransaction();
try {
    // Update the document title
    $stmt = $pdo->prepare("UPDATE legal_documents SET title = ? WHERE id = ?");
    $stmt->execute([$title, $row['doc_id']]);
    echo "Updated document title to: $title\n";

    // Update the version content
    $stmt = $pdo->prepare(
        "UPDATE legal_document_versions
         SET content = ?,
             content_plain = NULL,
             version_number = ?,
             version_label = ?,
             summary_of_changes = ?,
             effective_date = ?,
             published_at = NOW(),
             is_draft = 0,
             is_current = 1,
             updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([
        $content,
        $versionNumber,
        $versionLabel,
        $summaryOfChanges,
        $effectiveDate,
        $row['version_id']
    ]);
    echo "Updated version content: " . strlen($content) . " chars\n";

    $pdo->commit();
    echo "\n=== SUCCESS ===\n";

    // Verify
    $stmt = $pdo->prepare(
        "SELECT ld.title, ldv.version_number, LENGTH(ldv.content) as len
         FROM legal_documents ld
         JOIN legal_document_versions ldv ON ld.current_version_id = ldv.id
         WHERE ld.id = ?"
    );
    $stmt->execute([$row['doc_id']]);
    $verify = $stmt->fetch();
    echo "Verification: title={$verify['title']}, version={$verify['version_number']}, content={$verify['len']} chars\n";

} catch (\Throwable $e) {
    $pdo->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
