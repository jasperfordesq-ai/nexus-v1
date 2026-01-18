<?php
/**
 * Database Seed Generator - Admin Interface
 * FDS Gold Standard Design
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Seed Generator | Nexus Admin</title>
    <link rel="stylesheet" href="/assets/css/admin-gold-standard.min.css">
    <link rel="stylesheet" href="/assets/css/nexus-phoenix.min.css">
    <style>
        .seed-generator-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 32px;
            color: white;
            margin-bottom: 32px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

        .page-header h1 {
            margin: 0 0 8px 0;
            font-size: 32px;
            font-weight: 700;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .stat-card .icon {
            font-size: 32px;
            margin-bottom: 12px;
            display: block;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 4px;
        }

        .stat-card .label {
            color: #64748b;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .action-section {
            background: white;
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .action-section h2 {
            margin: 0 0 16px 0;
            font-size: 24px;
            color: #1e293b;
        }

        .action-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }

        .action-card {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 32px;
            background: #f8fafc;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            opacity: 0;
            transition: opacity 0.2s;
        }

        .action-card:hover {
            border-color: #667eea;
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
        }

        .action-card:hover::before {
            opacity: 1;
        }

        .action-card h3 {
            margin: 0 0 12px 0;
            font-size: 20px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-card .badge {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .action-card .badge.demo {
            background: #f59e0b;
        }

        .action-card p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .action-card ul {
            list-style: none;
            padding: 0;
            margin: 0 0 24px 0;
        }

        .action-card ul li {
            padding: 8px 0;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-card ul li::before {
            content: '✓';
            color: #10b981;
            font-weight: bold;
            font-size: 16px;
        }

        .btn-group {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        .table-section {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .table-section h2 {
            margin: 0 0 24px 0;
            font-size: 24px;
            color: #1e293b;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table thead {
            background: #f8fafc;
        }

        .data-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .data-table td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            color: #64748b;
        }

        .data-table tr:hover {
            background: #f8fafc;
        }

        .table-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: #e0e7ff;
            color: #667eea;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        .warning-section {
            background: #fff7ed;
            border: 2px solid #fed7aa;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
        }

        .warning-section h3 {
            color: #c2410c;
            margin: 0 0 12px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .warning-section ul {
            margin: 0;
            padding-left: 24px;
            color: #9a3412;
        }

        .warning-section li {
            margin-bottom: 8px;
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../modern/admin/partials/admin-header.php'; ?>

<div class="seed-generator-container">

    <!-- Page Header -->
    <div class="page-header">
        <h1>🌱 Database Seed Generator</h1>
        <p>Intelligent script generator that analyzes your current database and creates production-ready seeding scripts</p>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success">
            <span style="font-size: 20px;">✓</span>
            <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-warning">
            <span style="font-size: 20px;">⚠</span>
            <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Database Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="icon">👥</span>
            <div class="value"><?= number_format($stats['users'] ?? 0) ?></div>
            <div class="label">Users</div>
        </div>
        <div class="stat-card">
            <span class="icon">👥</span>
            <div class="value"><?= number_format($stats['groups'] ?? 0) ?></div>
            <div class="label">Groups</div>
        </div>
        <div class="stat-card">
            <span class="icon">📝</span>
            <div class="value"><?= number_format($stats['feed_posts'] ?? 0) ?></div>
            <div class="label">Posts</div>
        </div>
        <div class="stat-card">
            <span class="icon">📅</span>
            <div class="value"><?= number_format($stats['events'] ?? 0) ?></div>
            <div class="label">Events</div>
        </div>
        <div class="stat-card">
            <span class="icon">💼</span>
            <div class="value"><?= number_format($stats['listings'] ?? 0) ?></div>
            <div class="label">Listings</div>
        </div>
        <div class="stat-card">
            <span class="icon">💰</span>
            <div class="value"><?= number_format($stats['transactions'] ?? 0) ?></div>
            <div class="label">Transactions</div>
        </div>
        <div class="stat-card">
            <span class="icon">🏆</span>
            <div class="value"><?= number_format($stats['user_badges'] ?? 0) ?></div>
            <div class="label">Badges</div>
        </div>
        <div class="stat-card">
            <span class="icon">🗄️</span>
            <div class="value"><?= number_format($stats['total_tables'] ?? 0) ?></div>
            <div class="label">Total Tables</div>
        </div>
    </div>

    <!-- Verification Notice -->
    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 12px; padding: 32px; margin-bottom: 32px; color: white; text-align: center;">
        <h2 style="color: white; margin: 0 0 16px 0; font-size: 28px;">
            🛡️ Want 100% Proof This Generator is Safe?
        </h2>
        <p style="margin: 0 0 24px 0; font-size: 16px; opacity: 0.95;">
            Run comprehensive verification checks to prove the generator can see your entire database
            and contains ZERO dangerous operations (no DELETE, UPDATE, or DROP queries).
        </p>
        <a href="/admin/seed-generator/verification"
           style="display: inline-block; background: white; color: #059669; padding: 16px 32px; border-radius: 8px;
                  font-weight: 700; font-size: 16px; text-decoration: none; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                  transition: transform 0.2s;"
           onmouseover="this.style.transform='translateY(-2px)'"
           onmouseout="this.style.transform='translateY(0)'">
            ✓ Run Safety Verification Now
        </a>
    </div>

    <!-- Security Warning -->
    <div class="warning-section">
        <h3>⚠️ Security & Best Practices</h3>
        <ul>
            <li><strong>Production Script:</strong> Creates ONLY your super admin account with secure password</li>
            <li><strong>Demo Script:</strong> Creates your admin + demo users with randomly generated secure passwords</li>
            <li><strong>No Weak Passwords:</strong> All passwords are bcrypt hashed with strong random values</li>
            <li><strong>Super Admin:</strong> Your account (jasper.ford.esq@gmail.com) is always created first</li>
            <li><strong>Tenant Isolated:</strong> Scripts only seed data for your current tenant</li>
        </ul>
    </div>

    <!-- Generator Actions -->
    <div class="action-section">
        <h2>Generate Seeding Scripts</h2>
        <p style="color: #64748b; margin-bottom: 24px;">
            Choose the type of seeding script to generate. The generator will analyze your current database and create an intelligent, production-ready PHP script.
        </p>

        <div class="action-cards">
            <!-- Production Script -->
            <div class="action-card">
                <h3>
                    <span>🔒 Production Script</span>
                    <span class="badge">Secure</span>
                </h3>
                <p>Creates a minimal, secure seeding script perfect for production environments.</p>

                <ul>
                    <li>Only super admin account created</li>
                    <li>Your email: jasper.ford.esq@gmail.com</li>
                    <li>Secure bcrypt password hash</li>
                    <li>No test data or demo users</li>
                    <li>Production-ready structure only</li>
                </ul>

                <div class="btn-group">
                    <a href="/admin/seed-generator/download?type=production&format=sql" class="btn btn-primary">
                        <span>📄</span>
                        Download SQL
                    </a>
                    <a href="/admin/seed-generator/download?type=production&format=php" class="btn btn-secondary">
                        <span>🐘</span>
                        Download PHP
                    </a>
                    <a href="/admin/seed-generator/preview?type=production" class="btn btn-secondary" target="_blank">
                        <span>👁️</span>
                        Preview
                    </a>
                </div>
            </div>

            <!-- Demo Script -->
            <div class="action-card">
                <h3>
                    <span>🎭 Demo Script</span>
                    <span class="badge demo">With Data</span>
                </h3>
                <p>Creates a comprehensive script with your current database structure and realistic demo data.</p>

                <ul>
                    <li>Super admin + demo users</li>
                    <li>All current data structure</li>
                    <li>Secure random passwords</li>
                    <li>Perfect for demonstrations</li>
                    <li>Testing and development</li>
                </ul>

                <div class="btn-group">
                    <a href="/admin/seed-generator/download?type=demo&format=sql" class="btn btn-primary">
                        <span>📄</span>
                        Download SQL
                    </a>
                    <a href="/admin/seed-generator/download?type=demo&format=php" class="btn btn-secondary">
                        <span>🐘</span>
                        Download PHP
                    </a>
                    <a href="/admin/seed-generator/preview?type=demo" class="btn btn-secondary" target="_blank">
                        <span>👁️</span>
                        Preview
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Database Tables Overview -->
    <div class="table-section">
        <h2>📊 Database Tables Overview</h2>
        <p style="color: #64748b; margin-bottom: 24px;">
            Current state of your database tables that will be analyzed for script generation.
        </p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Table Name</th>
                    <th>Records</th>
                    <th>Size (KB)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tables as $table): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($table['name']) ?></strong></td>
                        <td><?= number_format($table['rows']) ?></td>
                        <td><?= number_format($table['size'], 2) ?> KB</td>
                        <td>
                            <?php if ($table['rows'] > 0): ?>
                                <span class="table-badge" style="background: #d1fae5; color: #065f46;">✓ Has Data</span>
                            <?php else: ?>
                                <span class="table-badge" style="background: #fee2e2; color: #991b1b;">Empty</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
// Add confirmation for production script generation
document.querySelector('form[action*="generate-production"]')?.addEventListener('submit', function(e) {
    if (!confirm('Generate PRODUCTION seeding script?\n\nThis will create a script with ONLY your super admin account.\nNo demo data or test users will be included.\n\nContinue?')) {
        e.preventDefault();
    }
});

// Add confirmation for demo script generation
document.querySelector('form[action*="generate-demo"]')?.addEventListener('submit', function(e) {
    if (!confirm('Generate DEMO seeding script?\n\nThis will create a script with your current database structure and demo data.\nAll passwords will be securely hashed.\n\nContinue?')) {
        e.preventDefault();
    }
});
</script>

</body>
</html>
