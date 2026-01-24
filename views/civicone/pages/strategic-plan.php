<?php
/**
 * CivicOne View: Strategic Plan
 * GOV.UK Design System (WCAG 2.1 AA)
 * Tenant-specific: Hour Timebank only
 */
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'Strategic Plan 2026-2030';
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Strategic Plan</li>
    </ol>
</nav>

<!-- Header Section -->
<div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="region" aria-labelledby="plan-title">
    <div class="govuk-notification-banner__header">
        <h2 class="govuk-notification-banner__title" id="plan-title">Strategic Plan</h2>
    </div>
    <div class="govuk-notification-banner__content govuk-!-text-align-center">
        <h1 class="govuk-notification-banner__heading">Strategic Plan 2026 - 2030</h1>
        <p class="govuk-body-l">The Power of an Hour: Building a Resilient, Connected Ireland.</p>
        <a href="/uploads/tenants/hour-timebank/Timebank-Ireland-Strategic-Plan-2026-2030.pdf" target="_blank" class="govuk-button" data-module="govuk-button">
            <i class="fa-solid fa-download govuk-!-margin-right-1" aria-hidden="true"></i>
            Download Official PDF
        </a>
    </div>
</div>

<div class="govuk-grid-row">
    <!-- Sidebar TOC -->
    <div class="govuk-grid-column-one-third">
        <nav class="govuk-!-padding-4 govuk-!-margin-bottom-6" style="background: #f3f2f1; position: sticky; top: 1rem;" aria-label="Page contents">
            <h3 class="govuk-heading-s govuk-!-margin-bottom-3">Contents</h3>
            <ul class="govuk-list">
                <li><a href="#executive-summary" class="govuk-link">1. Executive Summary</a></li>
                <li><a href="#vision" class="govuk-link">2. Vision & Mission</a></li>
                <li><a href="#analysis" class="govuk-link">3. SWOT Analysis</a></li>
                <li><a href="#pillars" class="govuk-link">4. Strategic Pillars</a></li>
                <li><a href="#roadmap" class="govuk-link">5. Roadmap (Year 1)</a></li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="govuk-grid-column-two-thirds">

            <!-- 1. Executive Summary -->
            <section id="executive-summary" class="govuk-!-margin-bottom-8">
                <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
                    <h2 class="govuk-heading-l">1. Executive Summary</h2>
                    <p class="govuk-body">This five-year strategic plan (2026-2030) outlines a clear and ambitious path for hOUR Timebank (TBI) to transition from a single, high-impact regional organisation into a resilient and scalable national network.</p>
                    <p class="govuk-body">Our strategy is built on a foundation of proven, exceptional social value. A 2023 Social Impact Study quantified our SROI at <strong>1:16</strong>—for every €1 invested, €16 in tangible social value is returned.</p>

                    <h3 class="govuk-heading-s">Two Primary Goals:</h3>
                    <ol class="govuk-list govuk-list--number">
                        <li><strong>Sustainable Growth:</strong> Scale from 245 to 2,500+ members by 2030, establishing a "Centre of Excellence" in West Cork.</li>
                        <li><strong>Maximising Social Impact:</strong> Deepen community value by investing in technology and diversifying our financial model.</li>
                    </ol>
                </div>
            </section>

            <!-- 2. Vision -->
            <section id="vision" class="govuk-!-margin-bottom-8">
                <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
                    <h2 class="govuk-heading-l">2. Our 2026 Vision</h2>

                    <div class="govuk-inset-text govuk-!-margin-bottom-4" style="border-left-color: #1d70b8;">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">Our Mission</h3>
                        <p class="govuk-body govuk-!-margin-bottom-0">To connect and empower Irish communities by facilitating the exchange of skills, talents, and support, where every hour given is an hour received.</p>
                    </div>

                    <div class="govuk-inset-text" style="border-left-color: #00703c;">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">Our Vision</h3>
                        <p class="govuk-body govuk-!-margin-bottom-0">An interconnected Ireland where every individual feels valued and supported, and where the power of shared time and talent creates strong, resilient communities.</p>
                    </div>
                </div>
            </section>

            <!-- 3. SWOT -->
            <section id="analysis" class="govuk-!-margin-bottom-8">
                <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
                    <h2 class="govuk-heading-l">3. SWOT Analysis</h2>

                    <div class="govuk-grid-row">
                        <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
                            <div class="govuk-!-padding-3" style="border-left: 4px solid #00703c; background: #f3f2f1;">
                                <h4 class="govuk-heading-s" style="color: #00703c;">Strengths</h4>
                                <ul class="govuk-list govuk-list--bullet govuk-body-s">
                                    <li><strong>Proven Impact:</strong> Independently validated 1:16 SROI.</li>
                                    <li><strong>Partnerships:</strong> WCDP and Rethink Ireland.</li>
                                    <li><strong>Lean Operations:</strong> Effective low-cost model.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
                            <div class="govuk-!-padding-3" style="border-left: 4px solid #d4351c; background: #f3f2f1;">
                                <h4 class="govuk-heading-s" style="color: #d4351c;">Weaknesses</h4>
                                <ul class="govuk-list govuk-list--bullet govuk-body-s">
                                    <li><strong>Financial Instability:</strong> Funding gap after shop closure.</li>
                                    <li><strong>Human Resources:</strong> Reliance on key individuals.</li>
                                    <li><strong>No Physical Hub:</strong> Lack of central premises.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
                            <div class="govuk-!-padding-3" style="border-left: 4px solid #1d70b8; background: #f3f2f1;">
                                <h4 class="govuk-heading-s" style="color: #1d70b8;">Opportunities</h4>
                                <ul class="govuk-list govuk-list--bullet govuk-body-s">
                                    <li><strong>Public Sector Contracts:</strong> HSE Social Prescribing.</li>
                                    <li><strong>Hybrid Models:</strong> B2B Timebanking.</li>
                                    <li><strong>"Loneliness Epidemic":</strong> Powerful narrative.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
                            <div class="govuk-!-padding-3" style="border-left: 4px solid #f47738; background: #f3f2f1;">
                                <h4 class="govuk-heading-s" style="color: #f47738;">Threats</h4>
                                <ul class="govuk-list govuk-list--bullet govuk-body-s">
                                    <li><strong>Funding Cliff:</strong> Securing long-term coordinator funding.</li>
                                    <li><strong>Volunteer Burnout:</strong> Unsustainable burden.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 4. Pillars -->
            <section id="pillars" class="govuk-!-margin-bottom-8">
                <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
                    <h2 class="govuk-heading-l">4. Strategic Pillars</h2>

                    <h3 class="govuk-heading-m" style="color: #1d70b8;">Pillar 1: Roots & Reach (Growth)</h3>
                    <table class="govuk-table govuk-!-margin-bottom-6">
                        <thead class="govuk-table__head">
                            <tr class="govuk-table__row">
                                <th scope="col" class="govuk-table__header">Key Initiatives</th>
                                <th scope="col" class="govuk-table__header">Year 1 Priorities</th>
                                <th scope="col" class="govuk-table__header">KPIs</th>
                            </tr>
                        </thead>
                        <tbody class="govuk-table__body">
                            <tr class="govuk-table__row">
                                <td class="govuk-table__cell"><strong>1.1: West Cork Centre</strong></td>
                                <td class="govuk-table__cell">Secure funding for Hub Coordinator.</td>
                                <td class="govuk-table__cell">Monthly Hours &gt; 200</td>
                            </tr>
                            <tr class="govuk-table__row">
                                <td class="govuk-table__cell"><strong>1.2: National Plan</strong></td>
                                <td class="govuk-table__cell">"Hub-in-a-Box" toolkit.</td>
                                <td class="govuk-table__cell">Toolkit completed.</td>
                            </tr>
                        </tbody>
                    </table>

                    <h3 class="govuk-heading-m" style="color: #00703c;">Pillar 2: Financial Resilience</h3>
                    <table class="govuk-table">
                        <thead class="govuk-table__head">
                            <tr class="govuk-table__row">
                                <th scope="col" class="govuk-table__header">Key Initiatives</th>
                                <th scope="col" class="govuk-table__header">Year 1 Priorities</th>
                                <th scope="col" class="govuk-table__header">KPIs</th>
                            </tr>
                        </thead>
                        <tbody class="govuk-table__body">
                            <tr class="govuk-table__row">
                                <td class="govuk-table__cell"><strong>2.1: Core Funding</strong></td>
                                <td class="govuk-table__cell">Develop "Case for Support" (SROI).</td>
                                <td class="govuk-table__cell">Core costs funded 2026-28.</td>
                            </tr>
                            <tr class="govuk-table__row">
                                <td class="govuk-table__cell"><strong>2.2: Public Contracts</strong></td>
                                <td class="govuk-table__cell">Pilot in West Cork for Social Prescribing.</td>
                                <td class="govuk-table__cell">1x Pilot Contract.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 5. Roadmap -->
            <section id="roadmap" class="govuk-!-margin-bottom-8">
                <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
                    <h2 class="govuk-heading-l">5. Roadmap: Year 1</h2>

                    <table class="govuk-table">
                        <thead class="govuk-table__head">
                            <tr class="govuk-table__row">
                                <th scope="col" class="govuk-table__header">Initiative</th>
                                <th scope="col" class="govuk-table__header govuk-!-text-align-centre">Q1</th>
                                <th scope="col" class="govuk-table__header govuk-!-text-align-centre">Q2</th>
                                <th scope="col" class="govuk-table__header govuk-!-text-align-centre">Q3</th>
                                <th scope="col" class="govuk-table__header govuk-!-text-align-centre">Q4</th>
                            </tr>
                        </thead>
                        <tbody class="govuk-table__body">
                            <tr class="govuk-table__row">
                                <td class="govuk-table__cell"><strong>Fund Coordinator</strong></td>
                                <td class="govuk-table__cell govuk-!-text-align-centre"><span class="govuk-tag govuk-tag--blue">SUBMIT</span></td>
                                <td class="govuk-table__cell govuk-!-text-align-centre"><span class="govuk-tag govuk-tag--green">SECURE</span></td>
                                <td class="govuk-table__cell"></td>
                                <td class="govuk-table__cell"></td>
                            </tr>
                            <tr class="govuk-table__row">
                                <td class="govuk-table__cell"><strong>Re-Engagement</strong></td>
                                <td class="govuk-table__cell"></td>
                                <td class="govuk-table__cell"></td>
                                <td class="govuk-table__cell govuk-!-text-align-centre"><span class="govuk-tag govuk-tag--purple">LAUNCH</span></td>
                                <td class="govuk-table__cell govuk-!-text-align-centre"><span class="govuk-tag govuk-tag--grey">ONGOING</span></td>
                            </tr>
                            <tr class="govuk-table__row">
                                <td class="govuk-table__cell"><strong>Multi-Year Grants</strong></td>
                                <td class="govuk-table__cell govuk-!-text-align-centre"><span class="govuk-tag govuk-tag--blue">SUBMIT</span></td>
                                <td class="govuk-table__cell govuk-!-text-align-centre"><span class="govuk-tag govuk-tag--yellow">PITCH</span></td>
                                <td class="govuk-table__cell govuk-!-text-align-centre"><span class="govuk-tag govuk-tag--green">SECURE</span></td>
                                <td class="govuk-table__cell"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
