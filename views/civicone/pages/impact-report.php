<?php
// CivicOne View: Impact Report
// Tenant-specific: Hour Timebank only
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'Social Impact Report';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-report-pages.min.css?v=<?= time() ?>">

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card report-header">
        <h1 class="report-title">Social Impact Study</h1>
        <div class="report-divider"></div>
        <p class="report-subtitle">
            hOUR Timebank (Full Report)
        </p>
    </div>

    <div class="report-grid">

        <!-- Sidebar TOC -->
        <div class="civic-card report-toc">
            <h3 class="report-toc-title">Table of Contents</h3>
            <ul class="report-toc-list">
                <li><a href="#introduction" class="report-toc-link">1. Introduction & Context</a></li>
                <li><a href="#literature" class="report-toc-link">2. Literature Review</a></li>
                <li><a href="#activity" class="report-toc-link">3. TBI 2021-22 Activity</a></li>
                <li><a href="#impact" class="report-toc-link">4. Impact & Demographics</a></li>
                <li><a href="#sroi" class="report-toc-link">5. SROI Calculation</a></li>
                <li><a href="#discussion" class="report-toc-link">6. Discussion & Learning</a></li>
                <li><a href="#recommendations" class="report-toc-link">7. Recommendations</a></li>
                <li><a href="#references" class="report-toc-link">8. Bibliography</a></li>
            </ul>

            <div class="report-toc-actions">
                <a href="/uploads/tenants/hour-timebank/TBI-Social-Impact-Study-Executive-Summary-Design-Version-Final.pdf" target="_blank" class="civic-btn report-download-btn report-download-btn--primary">
                    Download Executive Summary
                </a>
                <a href="/uploads/tenants/hour-timebank/TBI-Social-Impact-Study-Final-Full-Report-May-23.pdf" target="_blank" class="civic-btn report-download-btn report-download-btn--secondary">
                    Download Full PDF
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div>

            <!-- 1. Introduction -->
            <div id="introduction" class="civic-card report-section">
                <h2 class="report-section-title">1. Introduction and Context</h2>

                <h3 class="report-section-subtitle">1.1 Introduction</h3>
                <p>As part of their ongoing Social Inclusion & Community Activation Programme (SICAP) support for hOUR Timebank (TBI), West Cork Development Partnership (WCDP) commissioned this social impact study for the twelve-month period, November 1st, 2021, to October 31st, 2022.</p>

                <h3 class="report-section-subtitle">1.2 Objectives of the Study</h3>
                <p>The objectives of the study were to:</p>
                <ul class="report-list">
                    <li>Demonstrate the social impact of TBI through measuring the outcomes for its members including many vulnerable and hard to reach groups.</li>
                    <li>Capture how TBI engages with its participants in an effective and tangible way.</li>
                    <li>Tell the story of the journey of change experienced by TBI members.</li>
                    <li>Enhance the value proposition for TBI to strengthen applications to statutory and philanthropic funders.</li>
                    <li>Provide structure, direction and give confidence to those considering expanding and replicating the initiative in other areas.</li>
                    <li>Identify key learning and recommendations.</li>
                </ul>

                <h3 class="report-section-subtitle">1.4 hOUR Timebank (TBI)</h3>
                <p>TBI is a group of people who help and support each other by sharing services, skills, talents, and knowledge. Its vision is of an interconnected community where meaningful relationships strengthen resilience, solidarity, and prosperity. Members provide services voluntarily enabling them to give and receive time and no money is exchanged. Through this exchange, TBI appreciates the value of every member and recognises all have needs as well as gifts to share. It supports basic needs to be met that mitigates deprivation and stress resulting in a better quality of life and stronger connections among citizens.</p>

                <div class="report-case-study">
                    <h4 class="report-case-study-title">Case Study 1: Monica</h4>
                    <p class="report-case-study-text">Monica found out about TBI through the outreach mental health team. She lives alone in a rural area with no family support... Since getting involved in TBI, Monica feels much more connected to the community.</p>
                    <p class="report-case-study-quote">"Contact is important for both giver and receiver... Giving is good for the soul and being in contact with new people is lovely."</p>
                </div>

                <h3 class="report-section-subtitle">1.5 Methodology</h3>
                <p>A mixed method approach was adopted for data collection, capturing both quantitative and qualitative data. This included:</p>
                <ul class="report-list">
                    <li>Web-based survey eliciting 30 responses from TBI members.</li>
                    <li>Semi-structured 1-1 interviews with a further ten TBI members.</li>
                    <li>Semi structured interviews with external stakeholders.</li>
                </ul>

                <div class="report-case-study">
                    <h4 class="report-case-study-title">Case Study 2: John</h4>
                    <p class="report-case-study-text">John lives in a rural and remote area in West Cork. John receives support with transport through TBI... Through a Meitheal, John's cottage was painted, and some handyman jobs were completed.</p>
                    <p class="report-case-study-quote">"I think belonging to timebank has helped change my life... I recommend anyone should join timebank and offer a service."</p>
                </div>
            </div>

            <!-- 2. Literature -->
            <div id="literature" class="civic-card report-section">
                <h2 class="report-section-title">2. Literature Review</h2>

                <h3 class="report-section-subtitle">2.2 Timebanking in Ireland</h3>
                <p>In a 2020 study, Isaac Hurley found that out of fifty-seven Community Currencies (CC) established in Ireland between 2000 and 2020, only three were still operational. Hurley concludes that TBI has great potential and represented a "clear step up in evolution of Irish CCs towards more broadly appreciated and professional systems."</p>

                <h3 class="report-section-subtitle">2.3 Timebanking in the UK</h3>
                <p>Under the New Labour government (1997-2010), Timebanks (TBs) were viewed as a tool to address social exclusion. A 2014 Cambridge University evaluation found TBs were successful in investing in community capacity and supporting social capital.</p>

                <div class="report-case-study">
                    <h4 class="report-case-study-title">Case Study 3: Brenda</h4>
                    <p class="report-case-study-text">Brenda summed up the impact of TBI as "crucial for settling back into life in West Cork after being away for 25 years."</p>
                </div>
            </div>

            <!-- 3. Activity -->
            <div id="activity" class="civic-card report-section">
                <h2 class="report-section-title">3. hOUR Timebank (TBI) 2021-22</h2>

                <h3 class="report-section-subtitle">3.2 User Group Information</h3>
                <p>There was a significant increase in enabled users from 219 to 391 over the year. 95% of enabled users are resident in County Cork.</p>

                <h3 class="report-section-subtitle">3.3 Activity</h3>
                <p>The currency used is time credits in units of one hour. 2868 hours were exchanged via 797 transactions. By the end of October 2022, there was more than one million hours of time credits in the Community Treasure Chest.</p>

                <table class="report-table" aria-label="Activity metrics">
                    <thead>
                        <tr>
                            <th scope="col">Metric</th>
                            <th scope="col">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Gross Income</td>
                            <td>1,941.10 Time Credits</td>
                        </tr>
                        <tr>
                            <td>Number of Incoming Transfers</td>
                            <td>559</td>
                        </tr>
                        <tr>
                            <td>Number of Logins</td>
                            <td>1,560</td>
                        </tr>
                        <tr>
                            <td>Balance of Community Account</td>
                            <td>1,007,748.95</td>
                        </tr>
                    </tbody>
                </table>

                <div class="report-case-study">
                    <h4 class="report-case-study-title">Case Study 4: Delores</h4>
                    <p class="report-case-study-text">Delores had an accident and felt very isolated during Covid... The home clean enabled her to get a council grant for home improvements, and the whole experience helped improve her mental well-being.</p>
                </div>
            </div>

            <!-- 4. Impact -->
            <div id="impact" class="civic-card report-section">
                <h2 class="report-section-title">4. Impact</h2>
                <p>This section explores the impact for members, the primary TBI stakeholder. 40 members were consulted.</p>

                <h3 class="report-section-subtitle">Profile</h3>
                <p>Nearly 60% of respondents were aged 56 or older. This reflects the wider TBI membership, though a younger profile is emerging.</p>

                <div class="report-progress-container" role="figure" aria-label="Members age distribution">
                    <h4 class="report-progress-title">Members Age Bands</h4>

                    <div class="report-progress-item">
                        <div class="report-progress-label">
                            <span>56 - 65</span>
                            <span class="report-progress-value">37.5%</span>
                        </div>
                        <div class="report-progress-bar" role="progressbar" aria-valuenow="37.5" aria-valuemin="0" aria-valuemax="100">
                            <div class="report-progress-fill report-progress-fill--primary" style="width: 37.5%;"></div>
                        </div>
                    </div>

                    <div class="report-progress-item">
                        <div class="report-progress-label">
                            <span>66+</span>
                            <span class="report-progress-value">20.0%</span>
                        </div>
                        <div class="report-progress-bar" role="progressbar" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100">
                            <div class="report-progress-fill report-progress-fill--cyan" style="width: 20%;"></div>
                        </div>
                    </div>

                    <div class="report-progress-item">
                        <div class="report-progress-label">
                            <span>36 - 45</span>
                            <span class="report-progress-value">17.5%</span>
                        </div>
                        <div class="report-progress-bar" role="progressbar" aria-valuenow="17.5" aria-valuemin="0" aria-valuemax="100">
                            <div class="report-progress-fill report-progress-fill--purple" style="width: 17.5%;"></div>
                        </div>
                    </div>
                </div>

                <h3 class="report-section-subtitle">Outcomes</h3>
                <p>95% indicated they felt more socially connected due to TBI.</p>
                <p>100% of respondents felt their well-being had improved.</p>

                <div class="report-case-study">
                    <h4 class="report-case-study-title">Case Study 5: Elaine</h4>
                    <p class="report-case-study-text">Elaine is single with no family, lives remotely, and struggles with mental health issues and chronic pain. She describes TBI as "the most important support in life."</p>
                </div>
            </div>

            <!-- 5. SROI -->
            <section id="sroi" class="report-sroi-hero report-section" aria-label="SROI calculation">
                <h2>5. Calculating the SROI</h2>
                <p>We estimated proxy costs for outcomes (e.g., improved health = cost of community counselling). The total input was €50,000.</p>

                <h3>5.4 SROI Results</h3>
                <div class="report-sroi-result">
                    <p class="report-sroi-result__label">Social Return on Investment</p>
                    <p class="report-sroi-result__value">€16</p>
                    <p class="report-sroi-result__desc">generated for every €1 invested.</p>
                    <p class="report-sroi-result__note">Based on a Total Present Value of €803,184 created against the input of €50,000.</p>
                </div>
            </section>

            <!-- 6. Discussion -->
            <div id="discussion" class="civic-card report-section">
                <h2 class="report-section-title">6. Discussion & Learning</h2>
                <p>Members with little disposable income get services they could not afford. We estimate that for every €1 invested, €16 is generated in social value. We believe this is a conservative valuation.</p>
                <p>TBI has shown a commitment to compliance, evidenced by its charitable status (CRA) and CLG registration.</p>
            </div>

            <!-- 7. Recommendations -->
            <div id="recommendations" class="civic-card report-section">
                <h2 class="report-section-title">7. Recommendations</h2>

                <h3 class="report-section-subtitle">Sustainable Funding</h3>
                <p>We recommend TBI use the SROI findings (1:16) to approach funders. The initial ask should be to increase the Broker role.</p>

                <h3 class="report-section-subtitle">Increasing membership</h3>
                <p>The community account has over 1 million time credits; this is a great opportunity to increase membership. TBI should work with WCDP to identify impactful projects to use these credits.</p>
            </div>

            <!-- 8. References -->
            <div id="references" class="civic-card report-section">
                <h2 class="report-section-title">8. Bibliography</h2>
                <ol class="report-bibliography-list">
                    <li>Bretherton, Joanne and Pleace, Nicholas (2014) An evaluation of the Broadway Skills Exchange Time Bank.</li>
                    <li>Burgess, G. (2014) Evaluation of the Cambridgeshire Timebanks.</li>
                    <li>Hurley, Isaac (2020) Uncovering Ireland's Monetary Ecology.</li>
                </ol>
            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
