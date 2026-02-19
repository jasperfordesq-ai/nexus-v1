<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CivicOne GOV.UK Pattern Test Page
 * Demonstrates all GOV.UK Frontend components extracted for CivicOne
 *
 * URL: http://localhost/civicone-govuk-test.php
 */

// Simulate basic variables that would normally come from your app
$basePath = '';
$pageTitle = 'GOV.UK Pattern Test - CivicOne';
$layout = 'civicone';
?>
<!DOCTYPE html>
<html lang="en" class="govuk-template">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Design Tokens -->
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/design-tokens.css">

    <!-- GOV.UK Components -->
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/civicone-govuk-focus.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/civicone-govuk-typography.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/civicone-govuk-spacing.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/civicone-govuk-buttons.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/civicone-govuk-forms.css">

    <!-- Hero Component - REMOVED (using pure GOV.UK typography now) -->

    <!-- Additional CivicOne styles -->
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/civicone-header.css">

    <style>
        /* Test page specific styles */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f3f2f1;
        }

        .civicone-width-container {
            max-width: 1020px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .civicone-main-wrapper {
            padding-top: 20px;
            padding-bottom: 20px;
        }

        .test-section {
            background: white;
            padding: 40px 30px;
            margin-bottom: 30px;
            border: 2px solid #b1b4b6;
            border-left: 5px solid #1d70b8;
        }

        .test-section h2 {
            margin-top: 0;
            color: #1d70b8;
            font-size: 1.5rem;
            border-bottom: 3px solid #1d70b8;
            padding-bottom: 10px;
        }

        .test-info {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #00703c;
        }

        .test-info strong {
            color: #0b0c0c;
        }

        .civicone-skip-link {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: 0;
            overflow: hidden;
            clip: rect(0 0 0 0);
            clip-path: inset(50%);
            white-space: nowrap;
        }

        .civicone-skip-link:focus {
            position: static;
            width: auto;
            height: auto;
            margin: inherit;
            overflow: visible;
            clip: auto;
            clip-path: none;
            white-space: inherit;
            background-color: #ffdd00;
            color: #0b0c0c;
            padding: 15px;
            text-align: center;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: underline;
            display: block;
        }

        .civicone-heading-xl {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.09375;
            margin: 0 0 20px 0;
        }

        @media (min-width: 641px) {
            .civicone-heading-xl {
                font-size: 3rem;
                line-height: 1.04167;
            }
        }

        .civicone-heading-l {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.04167;
        }

        .civicone-body-l {
            font-size: 1.125rem;
            line-height: 1.31579;
        }

        @media (min-width: 641px) {
            .civicone-body-l {
                font-size: 1.5rem;
                line-height: 1.25;
            }
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            background: #00703c;
            color: white;
            font-size: 0.875rem;
            font-weight: 700;
            border-radius: 3px;
            margin-left: 10px;
        }

        .top-banner {
            background: #0b0c0c;
            color: white;
            text-align: center;
            padding: 30px 20px;
        }

        .top-banner h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
        }

        code {
            background: #f3f2f1;
            padding: 2px 6px;
            font-family: monospace;
            color: #d4351c;
        }

        .grid-demo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .grid-item {
            padding: 20px;
            background: #f3f2f1;
            border: 2px solid #b1b4b6;
        }
    </style>
</head>
<body class="civicone">

<!-- Skip Link (WCAG requirement) -->
<a href="#main-content" class="civicone-skip-link">Skip to main content</a>

<!-- Top Banner -->
<div class="top-banner">
    <h1>CivicOne GOV.UK Pattern Test Page</h1>
    <p>Testing all components extracted from GOV.UK Frontend v5.14.0</p>
    <span class="status-badge">✓ WCAG 2.2 AA</span>
</div>

<!-- Main Content -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

        <!-- Section 1: Page Header (GOV.UK Typography) -->
        <div class="test-section">
            <h2>1. Page Header (Standard Pages)</h2>
            <div class="test-info">
                <p><strong>Source:</strong> GOV.UK Typography (govuk-heading-xl + govuk-body-l)</p>
                <p><strong>Use for:</strong> All pages - Members, Groups, Events, Volunteering directories</p>
                <p><strong>Note:</strong> No custom hero component needed - just use standard GOV.UK classes</p>
            </div>

            <h1 class="govuk-heading-xl">Members Directory</h1>
            <p class="govuk-body-l">
                Connect with community members who can help with your projects or who share your interests and skills.
            </p>
        </div>

        <!-- Section 2: Page Header with Start Button -->
        <div class="test-section">
            <h2>2. Page Header with CTA (Landing Pages)</h2>
            <div class="test-info">
                <p><strong>Source:</strong> GOV.UK Typography + Start Button</p>
                <p><strong>Use for:</strong> Homepage, onboarding, service hubs</p>
                <p><strong>File:</strong> <code>civicone-govuk-buttons.css</code></p>
            </div>

            <h1 class="govuk-heading-xl">Welcome to CivicOne</h1>
            <p class="govuk-body-l">
                A community-powered platform where neighbors help each other, share skills, and build stronger communities together.
            </p>
            <a href="#" role="button" draggable="false" class="civicone-button civicone-button--start" data-module="civicone-button">
                Get started
                <svg class="civicone-button__start-icon" xmlns="http://www.w3.org/2000/svg" width="17.5" height="19" viewBox="0 0 33 40" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M0 0h13l20 20-20 20H0l20-20z"/>
                </svg>
            </a>
        </div>

        <!-- Section 3: Confirmation Panel -->
        <div class="test-section">
            <h2>3. Confirmation Panel (Success Pages)</h2>
            <div class="test-info">
                <p><strong>Source:</strong> GOV.UK Panel Component (EXACT)</p>
                <p><strong>Use for:</strong> Form submissions, task completions</p>
                <p><strong>GOV.UK Source:</strong> <code>govuk-frontend/src/govuk/components/panel/_index.scss</code></p>
            </div>

            <div class="civicone-panel civicone-panel--confirmation">
                <h1 class="civicone-panel__title">Application complete</h1>
                <div class="civicone-panel__body">
                    Your reference number<br>
                    <strong>HDJ2123F</strong>
                </div>
            </div>

            <p style="text-align: center; margin-top: 30px;">
                <a href="#main-content" style="color: #1d70b8; text-decoration: underline; font-weight: 700;">Return to dashboard</a>
            </p>
        </div>

        <!-- Section 4: Article Header -->
        <div class="test-section">
            <h2>4. Article Header (Articles/Blog)</h2>
            <div class="test-info">
                <p><strong>Source:</strong> GOV.UK Typography</p>
                <p><strong>Use for:</strong> Blog posts, help articles, content pages</p>
            </div>

            <p class="govuk-body-s govuk-!-margin-bottom-1">
                Published: <time datetime="2026-01-22">22 January 2026</time> | Category: Community Stories
            </p>
            <h1 class="govuk-heading-xl">How Our Members Transformed the Community Garden</h1>
            <p class="govuk-body-l">
                A story of collaboration, dedication, and the power of community working together to create lasting change.
            </p>
        </div>

        <!-- Section 5: GOV.UK Buttons -->
        <div class="test-section">
            <h2>5. GOV.UK Button Components</h2>
            <div class="test-info">
                <p><strong>Source:</strong> GOV.UK Button Component</p>
                <p><strong>File:</strong> <code>civicone-govuk-buttons.css</code></p>
            </div>

            <div class="grid-demo">
                <div class="grid-item">
                    <h3 style="margin-top: 0;">Primary (Green)</h3>
                    <button type="button" class="civicone-button">Save and continue</button>
                </div>

                <div class="grid-item">
                    <h3 style="margin-top: 0;">Secondary (Grey)</h3>
                    <button type="button" class="civicone-button civicone-button--secondary">Cancel</button>
                </div>

                <div class="grid-item">
                    <h3 style="margin-top: 0;">Warning (Red)</h3>
                    <button type="button" class="civicone-button civicone-button--warning">Delete account</button>
                </div>

                <div class="grid-item">
                    <h3 style="margin-top: 0;">Start Button (Link)</h3>
                    <a href="#" role="button" draggable="false" class="civicone-button civicone-button--start">
                        Start now
                        <svg class="civicone-button__start-icon" xmlns="http://www.w3.org/2000/svg" width="17.5" height="19" viewBox="0 0 33 40" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M0 0h13l20 20-20 20H0l20-20z"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Section 6: GOV.UK Form Components -->
        <div class="test-section">
            <h2>6. GOV.UK Form Components</h2>
            <div class="test-info">
                <p><strong>Source:</strong> GOV.UK Form Components</p>
                <p><strong>File:</strong> <code>civicone-govuk-forms.css</code></p>
            </div>

            <form>
                <!-- Text Input -->
                <div class="civicone-form-group">
                    <label class="civicone-label" for="name-input">
                        Full name
                    </label>
                    <div id="name-hint" class="civicone-hint">
                        Enter your full name as it appears on official documents
                    </div>
                    <input class="civicone-input" id="name-input" name="name" type="text" aria-describedby="name-hint">
                </div>

                <!-- Text Input with Error -->
                <div class="civicone-form-group civicone-form-group--error">
                    <label class="civicone-label" for="email-input">
                        Email address
                    </label>
                    <div id="email-hint" class="civicone-hint">
                        We'll only use this to send you notifications
                    </div>
                    <p id="email-error" class="civicone-error-message">
                        <span class="civicone-visually-hidden">Error:</span> Enter a valid email address
                    </p>
                    <input class="civicone-input civicone-input--error" id="email-input" name="email" type="email"
                           aria-describedby="email-hint email-error" aria-invalid="true" value="invalid-email">
                </div>

                <!-- Textarea -->
                <div class="civicone-form-group">
                    <label class="civicone-label" for="description">
                        Description
                    </label>
                    <div id="description-hint" class="civicone-hint">
                        Tell us about your skills and interests (optional)
                    </div>
                    <textarea class="civicone-textarea" id="description" name="description" rows="5" aria-describedby="description-hint"></textarea>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="civicone-button">
                    Submit
                </button>
            </form>
        </div>

        <!-- Section 7: GOV.UK Focus States -->
        <div class="test-section">
            <h2>7. GOV.UK Focus States (Keyboard Navigation)</h2>
            <div class="test-info">
                <p><strong>Source:</strong> GOV.UK Focus Pattern</p>
                <p><strong>File:</strong> <code>civicone-govuk-focus.css</code></p>
                <p><strong>Test:</strong> Press Tab key to see yellow focus indicators (#ffdd00)</p>
            </div>

            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <a href="#" style="color: #1d70b8; text-decoration: underline; font-weight: 700;">Link with focus</a>
                <button type="button" class="civicone-button">Button with focus</button>
                <input type="text" class="civicone-input" placeholder="Input with focus" style="max-width: 200px;">
            </div>

            <p style="margin-top: 20px; color: #484949;">
                <strong>Accessibility Test:</strong> Tab through the elements above. You should see:
            </p>
            <ul style="color: #484949;">
                <li>Yellow background (#ffdd00)</li>
                <li>Black text (#0b0c0c)</li>
                <li>3px solid outline</li>
                <li>Visible on all focusable elements</li>
            </ul>
        </div>

        <!-- Section 8: Typography Scale -->
        <div class="test-section">
            <h2>8. GOV.UK Typography Scale</h2>
            <div class="test-info">
                <p><strong>Source:</strong> GOV.UK Typography</p>
                <p><strong>File:</strong> <code>civicone-govuk-typography.css</code></p>
            </div>

            <h1 class="civicone-heading-xl" style="margin-bottom: 15px;">Heading XL (48px desktop)</h1>
            <h2 class="civicone-heading-l" style="margin-bottom: 15px;">Heading L (36px desktop)</h2>
            <p class="civicone-body-l" style="margin-bottom: 15px;">Body Large (24px desktop) - Used for lead paragraphs</p>
            <p style="margin-bottom: 15px; font-size: 1.1875rem;">Body (19px) - Default text size for content</p>
            <p style="font-size: 0.875rem; color: #484949;">Small (14px) - Used for hints and secondary information</p>
        </div>

        <!-- Section 9: Design Tokens -->
        <div class="test-section">
            <h2>9. Design Tokens in Use</h2>
            <div class="test-info">
                <p><strong>Source:</strong> GOV.UK Design Tokens</p>
                <p><strong>File:</strong> <code>design-tokens.css</code></p>
            </div>

            <div class="grid-demo">
                <div class="grid-item" style="background: #1d70b8; color: white;">
                    <strong>Primary Blue</strong><br>
                    <code style="background: rgba(0,0,0,0.2); color: white;">#1d70b8</code><br>
                    Links, accents
                </div>
                <div class="grid-item" style="background: #00703c; color: white;">
                    <strong>Success Green</strong><br>
                    <code style="background: rgba(0,0,0,0.2); color: white;">#00703c</code><br>
                    Success states
                </div>
                <div class="grid-item" style="background: #d4351c; color: white;">
                    <strong>Error Red</strong><br>
                    <code style="background: rgba(0,0,0,0.2); color: white;">#d4351c</code><br>
                    Error states
                </div>
                <div class="grid-item" style="background: #ffdd00; color: #0b0c0c;">
                    <strong>Focus Yellow</strong><br>
                    <code style="background: rgba(255,255,255,0.8);">#ffdd00</code><br>
                    Focus indicators
                </div>
            </div>
        </div>

        <!-- Documentation Links -->
        <div class="test-section">
            <h2>10. Documentation & Resources</h2>

            <h3 style="margin-top: 20px;">CivicOne Documentation:</h3>
            <ul>
                <li><a href="/docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md" style="color: #1d70b8;">WCAG 2.1 AA Source of Truth</a></li>
                <li><a href="/docs/GOVUK-ONLY-COMPONENTS.md" style="color: #1d70b8;">GOV.UK Components Documentation</a></li>
                <li><a href="/docs/hero-govuk-examples.html" style="color: #1d70b8;">Hero Component Examples</a></li>
            </ul>

            <h3 style="margin-top: 20px;">GOV.UK Frontend References:</h3>
            <ul>
                <li><a href="https://design-system.service.gov.uk/" target="_blank" style="color: #1d70b8;">GOV.UK Design System</a></li>
                <li><a href="https://design-system.service.gov.uk/components/panel/" target="_blank" style="color: #1d70b8;">Panel Component</a></li>
                <li><a href="https://design-system.service.gov.uk/components/button/" target="_blank" style="color: #1d70b8;">Button Component</a></li>
                <li><a href="https://design-system.service.gov.uk/styles/page-template/" target="_blank" style="color: #1d70b8;">Page Template</a></li>
            </ul>
        </div>

    </main>
</div>

<!-- Footer -->
<footer style="background: #0b0c0c; color: white; padding: 40px 20px; margin-top: 60px; text-align: center;">
    <div class="civicone-width-container">
        <h3 style="margin-top: 0; color: #ffdd00;">GOV.UK Frontend v5.14.0</h3>
        <p>All components on this page are extracted from GOV.UK Frontend and adapted for CivicOne.</p>
        <p style="margin-top: 20px; font-size: 0.875rem; color: #b1b4b6;">
            ✓ WCAG 2.2 AA Compliant | Test Page Created: 22 January 2026
        </p>
    </div>
</footer>

</body>
</html>
