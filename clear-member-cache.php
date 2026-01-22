<?php
// Clear member count cache
session_start();
unset($_SESSION['user_count_2']);
unset($_SESSION['user_count_2_time']);
echo "Cache cleared! Refresh the members page now.\n";
echo "Visit: http://staging.timebank.local/hour-timebank/members\n";
