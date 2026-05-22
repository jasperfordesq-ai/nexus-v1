-- Community fund donations and grants are system-side wallet movements:
-- donations have no member receiver, and grants have no member sender.
-- The service layer already writes NULL for those sides, so keep the
-- transactions ledger schema aligned with that contract.

ALTER TABLE transactions
  MODIFY sender_id INT NULL,
  MODIFY receiver_id INT NULL,
  MODIFY transaction_type VARCHAR(30) NOT NULL DEFAULT 'transfer'
    COMMENT 'transfer, exchange, donation, starting_balance, admin_grant, community_fund';
