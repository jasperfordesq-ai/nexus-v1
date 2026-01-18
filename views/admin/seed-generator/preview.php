<?php
/**
 * Seed Generator - Preview & Validation
 * Shows exactly what will be generated before you download it
 */

$type = $_GET['type'] ?? 'production';
$preview = $_GET['preview'] ?? 'analysis';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seed Generator Preview | Nexus Admin</title>
    <link rel="stylesheet" href="/assets/css/admin-gold-standard.min.css">
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
        }

        .preview-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .preview-header {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .preview-header h1 {
            margin: 0 0 8px 0;
            color: #1e293b;
        }

        .preview-tabs {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        .preview-tab {
            padding: 12px 24px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 600;
            color: #64748b;
            transition: all 0.2s;
        }

        .preview-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .preview-content {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .code-preview {
            background: #1e293b;
            color: #e2e8f0;
            padding: 24px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            max-height: 600px;
            overflow-y: auto;
        }

        .analysis-section {
            margin-bottom: 32px;
        }

        .analysis-section h3 {
            color: #1e293b;
            margin: 0 0 16px 0;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        .safety-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-right: 8px;
            margin-bottom: 8px;
        }

        .safety-badge.safe {
            background: #d1fae5;
            color: #065f46;
        }

        .safety-badge.warning {
            background: #fef3c7;
            color: #92400e;
        }

        .safety-badge.info {
            background: #dbeafe;
            color: #1e40af;
        }

        .check-list {
            list-style: none;
            padding: 0;
        }

        .check-list li {
            padding: 12px;
            margin-bottom: 8px;
            background: #f8fafc;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .check-list li .icon {
            font-size: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .stat-box {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-box .value {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
        }

        .stat-box .label {
            color: #64748b;
            font-size: 13px;
            margin-top: 4px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 32px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 12px;
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            padding: 12px 32px;
            border-radius: 8px;
            border: 2px solid #667eea;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
    </style>
</head>
<body>

<div class="preview-container">
    <div class="preview-header">
        <h1>🔍 Seed Generator Preview - <?= ucfirst(htmlspecialchars($type)) ?> Mode</h1>
        <p style="color: #64748b; margin: 8px 0 0 0;">Review what will be generated before downloading</p>

        <div class="preview-tabs">
            <button class="preview-tab <?= $preview === 'analysis' ? 'active' : '' ?>"
                    onclick="location.href='?type=<?= $type ?>&preview=analysis'">
                📊 Safety Analysis
            </button>
            <button class="preview-tab <?= $preview === 'tables' ? 'active' : '' ?>"
                    onclick="location.href='?type=<?= $type ?>&preview=tables'">
                📋 Tables Included
            </button>
            <button class="preview-tab <?= $preview === 'code' ? 'active' : '' ?>"
                    onclick="location.href='?type=<?= $type ?>&preview=code'">
                💻 Generated Code
            </button>
        </div>
    </div>

    <div class="preview-content">
        <?php if ($preview === 'analysis'): ?>
            <!-- Safety Analysis -->
            <div class="analysis-section">
                <h3>🛡️ Safety Guarantees</h3>

                <ul class="check-list">
                    <li>
                        <span class="icon">✓</span>
                        <div>
                            <strong>READ-ONLY OPERATION</strong><br>
                            <small>This generator ONLY READS your database. It never writes, updates, or deletes anything.</small>
                        </div>
                    </li>
                    <li>
                        <span class="icon">✓</span>
                        <div>
                            <strong>CREATES NEW SCRIPT FILE</strong><br>
                            <small>Generated script is saved to /scripts/generated/ - your database is untouched.</small>
                        </div>
                    </li>
                    <li>
                        <span class="icon">✓</span>
                        <div>
                            <strong>NO AUTOMATIC EXECUTION</strong><br>
                            <small>Generated script requires manual execution. Nothing happens automatically.</small>
                        </div>
                    </li>
                    <li>
                        <span class="icon">✓</span>
                        <div>
                            <strong>CONFIRMATION REQUIRED</strong><br>
                            <small>Generated script prompts for "y/n" confirmation before making any changes.</small>
                        </div>
                    </li>
                    <li>
                        <span class="icon">✓</span>
                        <div>
                            <strong>ONLY INSERTS DATA</strong><br>
                            <small>Generated script only uses INSERT statements. Never DELETE, UPDATE, or DROP.</small>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="analysis-section">
                <h3>📋 What This Generator Does</h3>

                <div style="background: #f0f9ff; border-left: 4px solid #0284c7; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                    <strong>Step 1: Analyze Database</strong><br>
                    <small>Reads table structure and counts records. No modifications.</small>
                </div>

                <div style="background: #f0f9ff; border-left: 4px solid #0284c7; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                    <strong>Step 2: Generate PHP Script</strong><br>
                    <small>Creates a .php file with INSERT statements based on your data.</small>
                </div>

                <div style="background: #f0f9ff; border-left: 4px solid #0284c7; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                    <strong>Step 3: Save to File</strong><br>
                    <small>Script saved to /scripts/generated/ - completely separate from your live database.</small>
                </div>

                <div style="background: #dcfce7; border-left: 4px solid #16a34a; padding: 16px; border-radius: 8px;">
                    <strong>✓ Your Live Database is NEVER Modified</strong><br>
                    <small>The generator is completely safe. It's like taking a photo - it looks but never touches.</small>
                </div>
            </div>

            <?php if ($type === 'production'): ?>
            <div class="analysis-section">
                <h3>🔒 Production Mode Safety</h3>

                <ul class="check-list">
                    <li>
                        <span class="icon">✓</span>
                        <div>
                            <strong>MINIMAL DATA</strong><br>
                            <small>Only creates your super admin account. No demo data.</small>
                        </div>
                    </li>
                    <li>
                        <span class="icon">✓</span>
                        <div>
                            <strong>SECURE CREDENTIALS</strong><br>
                            <small>Your password (DruryLane66350!) is bcrypt hashed in generated script.</small>
                        </div>
                    </li>
                    <li>
                        <span class="icon">✓</span>
                        <div>
                            <strong>NO TEST USERS</strong><br>
                            <small>Zero demo accounts or weak passwords.</small>
                        </div>
                    </li>
                </ul>
            </div>
            <?php else: ?>
            <div class="analysis-section">
                <h3>🎭 Demo Mode Information</h3>

                <ul class="check-list">
                    <li>
                        <span class="icon">✓</span>
                        <div>
                            <strong>CURRENT DATABASE SNAPSHOT</strong><br>
                            <small>Reads your current data structure and content.</small>
                        </div>
                    </li>
                    <li>
                        <span class="icon">✓</span>
                        <div>
                            <strong>SECURE PASSWORDS</strong><br>
                            <small>Demo users get randomly generated secure passwords, all bcrypt hashed.</small>
                        </div>
                    </li>
                    <li>
                        <span class="icon">✓</span>
                        <div>
                            <strong>YOUR ADMIN ALWAYS FIRST</strong><br>
                            <small>Your account (jasper.ford.esq@gmail.com) is created before anything else.</small>
                        </div>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <div class="analysis-section">
                <h3>🧪 How to Test Safely</h3>

                <ol style="padding-left: 24px; color: #475569;">
                    <li style="margin-bottom: 12px;"><strong>Generate the script</strong> (safe - only reads database)</li>
                    <li style="margin-bottom: 12px;"><strong>Review the code</strong> (use "Generated Code" tab above)</li>
                    <li style="margin-bottom: 12px;"><strong>Test on local/staging first</strong> (never test on production)</li>
                    <li style="margin-bottom: 12px;"><strong>Verify it works correctly</strong> (check all data loaded)</li>
                    <li style="margin-bottom: 12px;"><strong>Only then use on production</strong> (with database backup first)</li>
                </ol>
            </div>

            <div style="background: #fff7ed; border: 2px solid #fed7aa; border-radius: 12px; padding: 24px; margin-top: 32px;">
                <h3 style="color: #c2410c; margin: 0 0 12px 0;">⚠️ Best Practice</h3>
                <p style="color: #9a3412; margin: 0;"><strong>Always backup your database before running ANY seeding script</strong>, even though this generator is completely safe during the generation phase.</p>
            </div>

            <div style="margin-top: 32px; padding-top: 24px; border-top: 2px solid #e2e8f0;">
                <a href="/admin/seed-generator/download?type=<?= $type ?>" class="btn-primary">
                    ⬇️ Download Script (Safe)
                </a>
                <a href="/admin/seed-generator" class="btn-secondary">
                    ← Back to Generator
                </a>
            </div>

        <?php elseif ($preview === 'tables'): ?>
            <!-- Tables Preview -->
            <div class="analysis-section">
                <h3>📋 Database Tables Analysis</h3>
                <p style="color: #64748b;">This shows which tables the generator can see and will include in the script.</p>

                <div style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 16px; border-radius: 8px; margin: 16px 0;">
                    <strong>✓ Generator Can See All Tables</strong><br>
                    <small>Complete read access to your database structure.</small>
                </div>

                <iframe src="/admin/seed-generator/preview?type=<?= $type ?>&format=tables-only"
                        style="width: 100%; height: 600px; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 16px;">
                </iframe>
            </div>

        <?php elseif ($preview === 'code'): ?>
            <!-- Code Preview -->
            <div class="analysis-section">
                <h3>💻 Generated Script Preview</h3>
                <p style="color: #64748b;">This is the actual PHP code that will be generated. Review it to understand exactly what it does.</p>

                <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 8px; margin: 16px 0;">
                    <strong>⚠️ Large Output</strong><br>
                    <small>The generated script may be very long. Scroll to see all code.</small>
                </div>

                <iframe src="/admin/seed-generator/preview?type=<?= $type ?>&format=code-only"
                        style="width: 100%; height: 600px; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 16px; background: #1e293b;">
                </iframe>
            </div>

            <div style="margin-top: 24px;">
                <a href="/admin/seed-generator/download?type=<?= $type ?>" class="btn-primary">
                    ⬇️ Download This Script
                </a>
                <a href="/admin/seed-generator" class="btn-secondary">
                    ← Back to Generator
                </a>
            </div>

        <?php endif; ?>
    </div>
</div>

</body>
</html>
