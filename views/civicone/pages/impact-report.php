<?php
/**
 * CivicOne View: Impact Report (Full)
 * GOV.UK Design System (WCAG 2.1 AA)
 * Tenant-specific: Hour Timebank only
 */
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'Social Impact Report';
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/impact-summary">Impact</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Full Report</li>
    </ol>
</nav>

<!-- Header Section -->
<div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="region" aria-labelledby="report-title">
    <div class="govuk-notification-banner__header">
        <h2 class="govuk-notification-banner__title" id="report-title">Research Report</h2>
    </div>
    <div class="govuk-notification-banner__content govuk-!-text-align-center">
        <h1 class="govuk-notification-banner__heading">Social Impact Study</h1>
        <p class="govuk-body-l">hOUR Timebank - Full Report (2023)</p>
    </div>
</div>

<div class="govuk-grid-row">
    <!-- Sidebar TOC -->
    <div class="govuk-grid-column-one-third">
        <nav class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-panel-bg" style="position: sticky; top: 1rem;" aria-label="Page contents">
            <h3 class="govuk-heading-s govuk-!-margin-bottom-3">Table of Contents</h3>
            <ul class="govuk-list">
                <li><a href="#introduction" class="govuk-link">1. Introduction & Context</a></li>
                <li><a href="#literature" class="govuk-link">2. Literature Review</a></li>
                <li><a href="#activity" class="govuk-link">3. TBI 2021-22 Activity</a></li>
                <li><a href="#impact" class="govuk-link">4. Impact & Demographics</a></li>
                <li><a href="#sroi" class="govuk-link">5. SROI Calculation</a></li>
                <li><a href="#discussion" class="govuk-link">6. Discussion & Learning</a></li>
                <li><a href="#recommendations" class="govuk-link">7. Recommendations</a></li>
                <li><a href="#references" class="govuk-link">8. Bibliography</a></li>
            </ul>

            <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">

            <a href="/uploads/tenants/hour-timebank/TBI-Social-Impact-Study-Executive-Summary-Design-Version-Final.pdf" target="_blank" class="govuk-button govuk-!-margin-bottom-2" data-module="govuk-button" style="width: 100%;">
                <i class="fa-solid fa-download govuk-!-margin-right-1" aria-hidden="true"></i>
                Executive Summary
            </a>
            <a href="/uploads/tenants/hour-timebank/TBI-Social-Impact-Study-Final-Full-Report-May-23.pdf" target="_blank" class="govuk-button govuk-button--secondary" data-module="govuk-button" style="width: 100%;">
                <i class="fa-solid fa-file-pdf govuk-!-margin-right-1" aria-hidden="true"></i>
                Full PDF Report
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="govuk-grid-column-two-thirds">

        <!-- 1. Introduction -->
        <section id="introduction" class="govuk-!-margin-bottom-8">
            <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
                <h2 class="govuk-heading-l">1. Introduction and Context</h2>

                <h3 class="govuk-heading-m">1.1 Introduction</h3>
                <p class="govuk-body">As part of their ongoing Social Inclusion & Community Activation Programme (SICAP) support for hOUR Timebank (TBI), West Cork Development Partnership (WCDP) commissioned this social impact study for the twelve-month period, November 1st, 2021, to October 31st, 2022.</p>

                <h3 class="govuk-heading-m">1.2 Objectives of the Study</h3>
                <p class="govuk-body">The objectives of the study were to:</p>
                <ul class="govuk-list govuk-list--bullet">
                    <li>Demonstrate the social impact of TBI through measuring the outcomes for its members including many vulnerable and hard to reach groups.</li>
                    <li>Capture how TBI engages with its participants in an effective and tangible way.</li>
                    <li>Tell the story of the journey of change experienced by TBI members.</li>
                    <li>Enhance the value proposition for TBI to strengthen applications to statutory and philanthropic funders.</li>
                    <li>Provide structure, direction and give confidence to those considering expanding and replicating the initiative in other areas.</li>
                    <li>Identify key learning and recommendations.</li>
                </ul>

                <h3 class="govuk-heading-m">1.4 hOUR Timebank (TBI)</h3>
                <p class="govuk-body">TBI is a group of people who help and support each other by sharing services, skills, talents, and knowledge. Its vision is of an interconnected community where meaningful relationships strengthen resilience, solidarity, and prosperity. Members provide services voluntarily enabling them to give and receive time and no money is exchanged. Through this exchange, TBI appreciates the value of every member and recognises all have needs as well as gifts to share. It supports basic needs to be met that mitigates deprivation and stress resulting in a better quality of life and stronger connections among citizens.</p>

                <div class="govuk-inset-text" style="border-left-color: #00703c;">
                    <h4 class="govuk-heading-s">Case Study: Monica</h4>
                    <p class="govuk-body">Monica found out about TBI through the outreach mental health team. She lives alone in a rural area with no family support... Since getting involved in TBI, Monica feels much more connected to the community.</p>
                    <p class="govuk-body govuk-!-margin-bottom-0" style="font-style: italic;">"Contact is important for both giver and receiver... Giving is good for the soul and being in contact with new people is lovely."</p>
                </div>

                <h3 class="govuk-heading-m">1.5 Methodology</h3>
                <p class="govuk-body">A mixed method approach was adopted for data collection, capturing both quantitative and qualitative data. This included:</p>
                <ul class="govuk-list govuk-list--bullet">
                    <li>Web-based survey eliciting 30 responses from TBI members.</li>
                    <li>Semi-structured 1-1 interviews with a further ten TBI members.</li>
                    <li>Semi structured interviews with external stakeholders.</li>
                </ul>

                <div class="govuk-inset-text" style="border-left-color: #1d70b8;">
                    <h4 class="govuk-heading-s">Case Study: John</h4>
                    <p class="govuk-body">John lives in a rural and remote area in West Cork. John receives support with transport through TBI... Through a Meitheal, John's cottage was painted, and some handyman jobs were completed.</p>
                    <p class="govuk-body govuk-!-margin-bottom-0" style="font-style: italic;">"I think belonging to timebank has helped change my life... I recommend anyone should join timebank and offer a service."</p>
                </div>
            </div>
        </section>

        <!-- 2. Literature -->
        <section id="literature" class="govuk-!-margin-bottom-8">
            <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
                <h2 class="govuk-heading-l">2. Literature Review</h2>

                <h3 class="govuk-heading-m">2.2 Timebanking in Ireland</h3>
                <p class="govuk-body">In a 2020 study, Isaac Hurley found that out of fifty-seven Community Currencies (CC) established in Ireland between 2000 and 2020, only three were still operational. Hurley concludes that TBI has great potential and represented a "clear step up in evolution of Irish CCs towards more broadly appreciated and professional systems."</p>

                <h3 class="govuk-heading-m">2.3 Timebanking in the UK</h3>
                <p class="govuk-body">Under the New Labour government (1997-2010), Timebanks (TBs) were viewed as a tool to address social exclusion. A 2014 Cambridge University evaluation found TBs were successful in investing in community capacity and supporting social capital.</p>

                <div class="govuk-inset-text" style="border-left-color: #d53880;">
                    <h4 class="govuk-heading-s">Case Study: Brenda</h4>
                    <p class="govuk-body govuk-!-margin-bottom-0">Brenda summed up the impact of TBI as "crucial for settling back into life in West Cork after being away for 25 years."</p>
                </div>
            </div>
        </section>

        <!-- 3. Activity -->
        <section id="activity" class="govuk-!-margin-bottom-8">
            <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
                <h2 class="govuk-heading-l">3. hOUR Timebank (TBI) 2021-22</h2>

                <h3 class="govuk-heading-m">3.2 User Group Information</h3>
                <p class="govuk-body">There was a significant increase in enabled users from 219 to 391 over the year. 95% of enabled users are resident in County Cork.</p>

                <h3 class="govuk-heading-m">3.3 Activity</h3>
                <p class="govuk-body">The currency used is time credits in units of one hour. 2868 hours were exchanged via 797 transactions. By the end of October 2022, there was more than one million hours of time credits in the Community Treasure Chest.</p>

                <table class="govuk-table govuk-!-margin-bottom-6">
                    <caption class="govuk-table__caption govuk-table__caption--m">Activity Metrics 2021-22</caption>
                    <thead class="govuk-table__head">
                        <tr class="govuk-table__row">
                            <th scope="col" class="govuk-table__header">Metric</th>
                            <th scope="col" class="govuk-table__header govuk-!-text-align-right">Value</th>
                        </tr>
                    </thead>
                    <tbody class="govuk-table__body">
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">Gross Income</td>
                            <td class="govuk-table__cell govuk-!-text-align-right"><strong>1,941.10 Time Credits</strong></td>
                        </tr>
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">Number of Incoming Transfers</td>
                            <td class="govuk-table__cell govuk-!-text-align-right"><strong>559</strong></td>
                        </tr>
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">Number of Logins</td>
                            <td class="govuk-table__cell govuk-!-text-align-right"><strong>1,560</strong></td>
                        </tr>
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">Balance of Community Account</td>
                            <td class="govuk-table__cell govuk-!-text-align-right"><strong>1,007,748.95</strong></td>
                        </tr>
                    </tbody>
                </table>

                <div class="govuk-inset-text" style="border-left-color: #00703c;">
                    <h4 class="govuk-heading-s">Case Study: Delores</h4>
                    <p class="govuk-body govuk-!-margin-bottom-0">Delores had an accident and felt very isolated during Covid... The home clean enabled her to get a council grant for home improvements, and the whole experience helped improve her mental well-being.</p>
                </div>
            </div>
        </section>

        <!-- 4. Impact -->
        <section id="impact" class="govuk-!-margin-bottom-8">
            <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
                <h2 class="govuk-heading-l">4. Impact</h2>
                <p class="govuk-body">This section explores the impact for members, the primary TBI stakeholder. 40 members were consulted.</p>

                <h3 class="govuk-heading-m">Profile</h3>
                <p class="govuk-body">Nearly 60% of respondents were aged 56 or older. This reflects the wider TBI membership, though a younger profile is emerging.</p>

                <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-panel-bg">
                    <h4 class="govuk-heading-s govuk-!-margin-bottom-4">Members Age Bands</h4>

                    <div class="govuk-!-margin-bottom-3">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span class="govuk-body-s govuk-!-margin-bottom-0">56 - 65</span>
                            <span class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-0">37.5%</span>
                        </div>
                        <div style="background: #dee0e2; height: 8px; border-radius: 4px;">
                            <div style="background: #1d70b8; height: 100%; width: 37.5%; border-radius: 4px;" role="progressbar" aria-valuenow="37.5" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <div class="govuk-!-margin-bottom-3">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span class="govuk-body-s govuk-!-margin-bottom-0">66+</span>
                            <span class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-0">20.0%</span>
                        </div>
                        <div style="background: #dee0e2; height: 8px; border-radius: 4px;">
                            <div style="background: #00703c; height: 100%; width: 20%; border-radius: 4px;" role="progressbar" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <div class="govuk-!-margin-bottom-0">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span class="govuk-body-s govuk-!-margin-bottom-0">36 - 45</span>
                            <span class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-0">17.5%</span>
                        </div>
                        <div style="background: #dee0e2; height: 8px; border-radius: 4px;">
                            <div style="background: #d53880; height: 100%; width: 17.5%; border-radius: 4px;" role="progressbar" aria-valuenow="17.5" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>

                <h3 class="govuk-heading-m">Outcomes</h3>
                <div class="govuk-grid-row">
                    <div class="govuk-grid-column-one-half">
                        <div class="govuk-!-padding-3 govuk-!-text-align-center govuk-!-margin-bottom-4 civicone-panel-bg">
                            <span class="govuk-tag govuk-tag--green govuk-!-margin-bottom-2">95%</span>
                            <p class="govuk-body-s govuk-!-margin-bottom-0">feel more socially connected</p>
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-half">
                        <div class="govuk-!-padding-3 govuk-!-text-align-center govuk-!-margin-bottom-4 civicone-panel-bg">
                            <span class="govuk-tag govuk-tag--green govuk-!-margin-bottom-2">100%</span>
                            <p class="govuk-body-s govuk-!-margin-bottom-0">felt wellbeing improved</p>
                        </div>
                    </div>
                </div>

                <div class="govuk-inset-text" style="border-left-color: #d53880;">
                    <h4 class="govuk-heading-s">Case Study: Elaine</h4>
                    <p class="govuk-body govuk-!-margin-bottom-0">Elaine is single with no family, lives remotely, and struggles with mental health issues and chronic pain. She describes TBI as "the most important support in life."</p>
                </div>
            </div>
        </section>

        <!-- 5. SROI -->
        <section id="sroi" class="govuk-!-margin-bottom-8">
            <div class="govuk-!-padding-6" style="background: #1d70b8; color: white;">
                <h2 class="govuk-heading-l" style="color: white;">5. Calculating the SROI</h2>
                <p class="govuk-body" style="color: white;">We estimated proxy costs for outcomes (e.g., improved health = cost of community counselling). The total input was €50,000.</p>

                <h3 class="govuk-heading-m" style="color: white;">5.4 SROI Results</h3>
                <div class="govuk-!-padding-4 govuk-!-text-align-center" style="background: white; border-radius: 4px;">
                    <p class="govuk-body govuk-!-margin-bottom-1" style="color: #505a5f;">Social Return on Investment</p>
                    <p class="govuk-heading-xl govuk-!-margin-bottom-1" style="color: #00703c;">€16</p>
                    <p class="govuk-body-l govuk-!-margin-bottom-2">generated for every €1 invested</p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Based on a Total Present Value of €803,184 created against the input of €50,000.</p>
                </div>
            </div>
        </section>

        <!-- 6. Discussion -->
        <section id="discussion" class="govuk-!-margin-bottom-8">
            <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
                <h2 class="govuk-heading-l">6. Discussion & Learning</h2>
                <p class="govuk-body">Members with little disposable income get services they could not afford. We estimate that for every €1 invested, €16 is generated in social value. We believe this is a conservative valuation.</p>
                <p class="govuk-body">TBI has shown a commitment to compliance, evidenced by its charitable status (CRA) and CLG registration.</p>
            </div>
        </section>

        <!-- 7. Recommendations -->
        <section id="recommendations" class="govuk-!-margin-bottom-8">
            <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #00703c;">
                <h2 class="govuk-heading-l">7. Recommendations</h2>

                <h3 class="govuk-heading-m">Sustainable Funding</h3>
                <p class="govuk-body">We recommend TBI use the SROI findings (1:16) to approach funders. The initial ask should be to increase the Broker role.</p>

                <h3 class="govuk-heading-m">Increasing membership</h3>
                <p class="govuk-body">The community account has over 1 million time credits; this is a great opportunity to increase membership. TBI should work with WCDP to identify impactful projects to use these credits.</p>
            </div>
        </section>

        <!-- 8. References -->
        <section id="references" class="govuk-!-margin-bottom-8">
            <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
                <h2 class="govuk-heading-l">8. Bibliography</h2>
                <ol class="govuk-list govuk-list--number">
                    <li>Bretherton, Joanne and Pleace, Nicholas (2014) An evaluation of the Broadway Skills Exchange Time Bank.</li>
                    <li>Burgess, G. (2014) Evaluation of the Cambridgeshire Timebanks.</li>
                    <li>Hurley, Isaac (2020) Uncovering Ireland's Monetary Ecology.</li>
                </ol>
            </div>
        </section>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
