<?php
// views/modern/volunteering/certificate_print.php
if (!isset($_SESSION['user_id'])) die("Access Denied");

// Fetch User & Stats
$user = $currentUser;
$totalHours = \Nexus\Models\VolLog::getTotalVerifiedHours($user['id']);
$logs = \Nexus\Models\VolLog::getForUser($user['id']);
$date = date('F j, Y');

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Certificate of Service - <?= htmlspecialchars($user['first_name']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Pinyon+Script&family=Roboto:wght@300;400;700&display=swap');

        body {
            font-family: 'Roboto', sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        .certificate {
            width: 1000px;
            height: 700px;
            /* Landscape A4 approx */
            background: #fff;
            padding: 50px;
            border: 20px solid #166534;
            position: relative;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .inner-border {
            border: 2px solid #bbf7d0;
            height: 100%;
            padding: 40px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        h1 {
            font-family: 'Cinzel', serif;
            color: #166534;
            font-size: 3.5rem;
            margin: 0 0 20px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .subtitle {
            font-size: 1.2rem;
            color: #555;
            margin-bottom: 40px;
        }

        .recipient-name {
            font-family: 'Pinyon Script', cursive;
            font-size: 4rem;
            color: #000;
            margin: 20px 0;
            border-bottom: 1px solid #ddd;
            display: inline-block;
            padding: 0 40px;
        }

        .details {
            font-size: 1.4rem;
            color: #444;
            line-height: 1.6;
            margin: 30px 0;
        }

        .hours {
            font-weight: bold;
            color: #166534;
            font-size: 1.6rem;
        }

        .footer {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
            padding: 0 50px;
        }

        .signature-line {
            width: 300px;
            border-top: 1px solid #333;
            margin-top: 50px;
            font-size: 1rem;
            color: #555;
            padding-top: 10px;
        }

        .logo {
            width: 100px;
            opacity: 0.8;
        }

        @media print {
            body {
                background: none;
                padding: 0;
            }

            .certificate {
                box-shadow: none;
                width: 100%;
                height: 100vh;
                border: 10px solid #166534;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>

    <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()" style="background: #166534; color: white; border: none; padding: 10px 20px; font-size: 1rem; cursor: pointer; border-radius: 6px;">
            🖨️ Print to PDF
        </button>
    </div>

    <div class="certificate">
        <div class="inner-border">
            <!-- <img src="/assets/img/logo.png" class="logo" alt="Logo"> -->
            <h1>Certificate of Service</h1>
            <p class="subtitle">This certifies that</p>

            <div class="recipient-name">
                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
            </div>

            <div class="details">
                Has generously dedicated their time and effort to support the community,<br>
                contributing a verified total of
                <div class="hours"><?= number_format($totalHours, 1) ?> Hours</div>
                of voluntary service.
            </div>

            <div class="footer">
                <div class="signature">
                    <div class="signature-line">Date: <?= $date ?></div>
                </div>
                <div class="signature">
                    <!-- Placeholder signature -->
                    <div style="font-family: 'Pinyon Script', cursive; font-size: 2rem; color: #166534; height: 40px;">Nexus Timebank</div>
                    <div class="signature-line">Authorized Signature</div>
                </div>
            </div>
        </div>
    </div>

</body>

</html>