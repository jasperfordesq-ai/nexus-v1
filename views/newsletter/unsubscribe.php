<?php
$layout = 'default';
$basePath = \Nexus\Core\TenantContext::getBasePath();
$tenant = \Nexus\Core\TenantContext::get();
$tenantName = $tenant['name'] ?? 'Newsletter';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe - <?= htmlspecialchars($tenantName) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .unsubscribe-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            max-width: 450px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }
        .icon {
            width: 64px;
            height: 64px;
            background: #fef3c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
        }
        h1 {
            font-size: 1.5rem;
            color: #111827;
            margin-bottom: 10px;
        }
        .email-display {
            color: #6b7280;
            margin-bottom: 25px;
        }
        .email-display strong {
            color: #374151;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            color: #374151;
            font-size: 0.9rem;
        }
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.9rem;
            resize: vertical;
            min-height: 80px;
        }
        .form-group textarea:focus {
            outline: none;
            border-color: #6366f1;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn {
            flex: 1;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.2s;
        }
        .btn-cancel {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .btn-cancel:hover {
            background: #e5e7eb;
        }
        .btn-unsubscribe {
            background: #dc2626;
            color: white;
            border: none;
        }
        .btn-unsubscribe:hover {
            background: #b91c1c;
        }
        .note {
            margin-top: 25px;
            font-size: 0.85rem;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="unsubscribe-container">
        <div class="icon">&#128532;</div>

        <h1>Unsubscribe from Newsletter</h1>

        <p class="email-display">
            You are unsubscribing: <strong><?= htmlspecialchars($subscriber['email']) ?></strong>
        </p>

        <form action="<?= $basePath ?>/newsletter/unsubscribe" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="form-group">
                <label for="reason">Why are you leaving? (Optional)</label>
                <textarea id="reason" name="reason" placeholder="Help us improve by sharing your feedback..."></textarea>
            </div>

            <div class="btn-group">
                <a href="<?= $basePath ?>/" class="btn btn-cancel">Cancel</a>
                <button type="submit" class="btn btn-unsubscribe">Unsubscribe</button>
            </div>
        </form>

        <p class="note">
            You can always re-subscribe later if you change your mind.
        </p>
    </div>
</body>
</html>
