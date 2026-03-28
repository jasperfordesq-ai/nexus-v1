-- Fix transactions missing `status` column value
-- These were created by CreditDonationService, ExchangeWorkflowService, and
-- CommunityFundService before the status column was consistently set.
-- Without status='completed', these transactions are invisible in the wallet
-- because WalletService::getTransactions() uses a ->completed() scope.
--
-- Safe to re-run (idempotent: only updates rows where status IS NULL).

UPDATE transactions
SET status = 'completed', updated_at = NOW()
WHERE status IS NULL
  AND (transaction_type IN ('donation', 'exchange', 'community_fund', 'starting_balance', 'admin_grant')
       OR transaction_type IS NULL);

-- Also fix any transactions where sender_id = receiver_id (old community fund bug)
-- These show the user as both sender and receiver which is confusing.
-- Set receiver_id to NULL for donations (credits left the user's wallet)
UPDATE transactions
SET receiver_id = NULL, updated_at = NOW()
WHERE transaction_type = 'donation'
  AND sender_id = receiver_id
  AND sender_id IS NOT NULL;

-- Set sender_id to NULL for community fund grants (credits came from the fund)
UPDATE transactions
SET sender_id = NULL, updated_at = NOW()
WHERE transaction_type = 'community_fund'
  AND sender_id = receiver_id
  AND receiver_id IS NOT NULL;
