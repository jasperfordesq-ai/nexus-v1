<?php

?>
<!DOCTYPE html>
<html>

<head>
    <title>Leave a Review - Nexus TimeBank</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
</head>

<body class="container">
    <nav>
        <ul>
            <li><strong>Nexus TimeBank</strong></li>
        </ul>
        <ul>
            <li><a href="/wallet">Back to Wallet</a></li>
        </ul>
    </nav>
    <main>
        <article>
            <header style="text-align: center; margin-bottom: 2rem;">
                <div style="background: #e0f2fe; color: #002d72; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; margin: 0 auto 20px;">
                    ✓
                </div>
                <h1 style="color: #002d72; margin-bottom: 10px;">Payment Sent!</h1>
                <p style="color: #64748b;">Your transaction was successful. How was your experience?</p>
            </header>
            <div class="glass-panel" style="border-top: 4px solid #002d72;">
                <header>
                    <h3 style="margin:0;">Leave a Review</h3>
                </header>
                <form action="/reviews/store" method="POST">
                    <?= \Nexus\Core\Csrf::input() ?>

                    <input type="hidden" name="transaction_id" value="<?= $transactionId ?>">
                    <input type="hidden" name="receiver_id" value="<?= $_GET['receiver'] ?? 0 ?>">

                    <label for="rating">Rating</label>
                    <select name="rating" required>
                        <option value="5">5 - Excellent</option>
                        <option value="4">4 - Good</option>
                        <option value="3">3 - Average</option>
                        <option value="2">2 - Poor</option>
                        <option value="1">1 - Terrible</option>
                    </select>

                    <label for="comment">Comment</label>
                    <textarea name="comment" required></textarea>

                    <button type="submit">Submit Review</button>
                </form>
            </div>
        </article>
    </main>
</body>

</html>