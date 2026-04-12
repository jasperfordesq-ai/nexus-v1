# FK Inventory — Project NEXUS

Auto-generated from `database/schema/mysql-schema.sql` on 2026-04-12.

**Total FK constraints:** 319

**ON DELETE breakdown:**
- CASCADE: 277
- SET NULL: 40
- RESTRICT: 0
- NO ACTION: 2

| Child Table | Column | Parent Table | Parent Col | ON DELETE | ON UPDATE | Child Col Nullable |
|---|---|---|---|---|---|---|
| `admin_actions` | `admin_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `admin_actions` | `target_user_id` | `users` | `id` | SET NULL | NO ACTION | YES |
| `admin_actions` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | YES |
| `ai_messages` | `conversation_id` | `ai_conversations` | `id` | CASCADE | NO ACTION | NO |
| `badge_collection_items` | `collection_id` | `badge_collections` | `id` | CASCADE | NO ACTION | NO |
| `bookmarks` | `collection_id` | `bookmark_collections` | `id` | SET NULL | NO ACTION | YES |
| `broker_message_copies` | `receiver_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `broker_message_copies` | `related_exchange_id` | `exchange_requests` | `id` | SET NULL | NO ACTION | YES |
| `broker_message_copies` | `related_listing_id` | `listings` | `id` | SET NULL | NO ACTION | YES |
| `broker_message_copies` | `reviewed_by` | `users` | `id` | SET NULL | NO ACTION | YES |
| `broker_message_copies` | `sender_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `broker_message_copies` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `broker_review_archives` | `decided_by` | `users` | `id` | CASCADE | NO ACTION | NO |
| `broker_review_archives` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `campaign_challenges` | `campaign_id` | `campaigns` | `id` | CASCADE | NO ACTION | NO |
| `campaign_challenges` | `challenge_id` | `ideation_challenges` | `id` | CASCADE | NO ACTION | NO |
| `campaign_executions` | `campaign_id` | `achievement_campaigns` | `id` | CASCADE | NO ACTION | NO |
| `categories` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `challenge_favorites` | `challenge_id` | `ideation_challenges` | `id` | CASCADE | NO ACTION | NO |
| `challenge_idea_comments` | `idea_id` | `challenge_ideas` | `id` | CASCADE | NO ACTION | NO |
| `challenge_idea_votes` | `idea_id` | `challenge_ideas` | `id` | CASCADE | NO ACTION | NO |
| `challenge_ideas` | `challenge_id` | `ideation_challenges` | `id` | CASCADE | NO ACTION | NO |
| `challenge_outcomes` | `challenge_id` | `ideation_challenges` | `id` | CASCADE | NO ACTION | NO |
| `challenge_tag_links` | `challenge_id` | `ideation_challenges` | `id` | CASCADE | NO ACTION | NO |
| `challenge_tag_links` | `tag_id` | `challenge_tags` | `id` | CASCADE | NO ACTION | NO |
| `close_friends` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `comments` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `comments` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `community_fund_accounts` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `community_fund_transactions` | `admin_id` | `users` | `id` | SET NULL | NO ACTION | YES |
| `community_fund_transactions` | `fund_id` | `community_fund_accounts` | `id` | CASCADE | NO ACTION | NO |
| `community_fund_transactions` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `community_fund_transactions` | `user_id` | `users` | `id` | SET NULL | NO ACTION | YES |
| `connections` | `receiver_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `connections` | `requester_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `consent_version_history` | `consent_type_slug` | `consent_types` | `slug` | CASCADE | CASCADE | NO |
| `conversation_participants` | `conversation_id` | `conversations` | `id` | CASCADE | NO ACTION | NO |
| `credit_donations` | `donor_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `credit_donations` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `cron_jobs` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `deliverable_comments` | `deliverable_id` | `deliverables` | `id` | CASCADE | NO ACTION | NO |
| `deliverable_comments` | `parent_comment_id` | `deliverable_comments` | `id` | CASCADE | NO ACTION | YES |
| `deliverable_comments` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `deliverable_comments` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `deliverable_history` | `deliverable_id` | `deliverables` | `id` | CASCADE | NO ACTION | NO |
| `deliverable_history` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `deliverable_history` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `deliverable_milestones` | `completed_by` | `users` | `id` | SET NULL | NO ACTION | YES |
| `deliverable_milestones` | `deliverable_id` | `deliverables` | `id` | CASCADE | NO ACTION | NO |
| `deliverable_milestones` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `deliverables` | `assigned_group_id` | `groups` | `id` | SET NULL | NO ACTION | YES |
| `deliverables` | `assigned_to` | `users` | `id` | SET NULL | NO ACTION | YES |
| `deliverables` | `owner_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `deliverables` | `parent_deliverable_id` | `deliverables` | `id` | CASCADE | NO ACTION | YES |
| `deliverables` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `error_404_log` | `user_id` | `users` | `id` | SET NULL | NO ACTION | YES |
| `event_attendance` | `event_id` | `events` | `id` | CASCADE | NO ACTION | NO |
| `event_attendance` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `event_attendance` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `event_recurrence_rules` | `event_id` | `events` | `id` | CASCADE | NO ACTION | NO |
| `event_recurrence_rules` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `event_reminders` | `event_id` | `events` | `id` | CASCADE | NO ACTION | NO |
| `event_reminders` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `event_reminders` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `event_rsvps` | `event_id` | `events` | `id` | CASCADE | NO ACTION | NO |
| `event_rsvps` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `event_series` | `created_by` | `users` | `id` | CASCADE | NO ACTION | NO |
| `event_series` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `event_waitlist` | `event_id` | `events` | `id` | CASCADE | NO ACTION | NO |
| `event_waitlist` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `event_waitlist` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `events` | `category_id` | `categories` | `id` | SET NULL | NO ACTION | YES |
| `events` | `group_id` | `groups` | `id` | CASCADE | NO ACTION | YES |
| `events` | `volunteer_opportunity_id` | `vol_opportunities` | `id` | SET NULL | NO ACTION | YES |
| `exchange_history` | `actor_id` | `users` | `id` | SET NULL | NO ACTION | YES |
| `exchange_history` | `exchange_id` | `exchange_requests` | `id` | CASCADE | NO ACTION | NO |
| `exchange_ratings` | `exchange_id` | `exchange_requests` | `id` | CASCADE | NO ACTION | NO |
| `exchange_ratings` | `rated_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `exchange_ratings` | `rater_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `exchange_ratings` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `exchange_requests` | `broker_id` | `users` | `id` | SET NULL | NO ACTION | YES |
| `exchange_requests` | `cancelled_by` | `users` | `id` | SET NULL | NO ACTION | YES |
| `exchange_requests` | `listing_id` | `listings` | `id` | CASCADE | NO ACTION | NO |
| `exchange_requests` | `provider_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `exchange_requests` | `requester_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `exchange_requests` | `risk_tag_id` | `listing_risk_tags` | `id` | SET NULL | NO ACTION | YES |
| `exchange_requests` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `federation_api_keys` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `federation_api_logs` | `api_key_id` | `federation_api_keys` | `id` | CASCADE | NO ACTION | NO |
| `federation_exports` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `feed_posts` | `group_id` | `groups` | `id` | SET NULL | NO ACTION | YES |
| `feed_posts` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `feed_posts` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `group_achievement_progress` | `achievement_id` | `group_achievements` | `id` | CASCADE | NO ACTION | NO |
| `group_chatroom_messages` | `chatroom_id` | `group_chatrooms` | `id` | CASCADE | NO ACTION | NO |
| `group_chatroom_pinned_messages` | `chatroom_id` | `group_chatrooms` | `id` | CASCADE | NO ACTION | NO |
| `group_chatroom_pinned_messages` | `message_id` | `group_chatroom_messages` | `id` | CASCADE | NO ACTION | NO |
| `group_chatroom_pinned_messages` | `pinned_by` | `users` | `id` | CASCADE | NO ACTION | NO |
| `group_discussions` | `group_id` | `groups` | `id` | CASCADE | NO ACTION | NO |
| `group_discussions` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `group_feature_toggles` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `group_members` | `group_id` | `groups` | `id` | CASCADE | NO ACTION | NO |
| `group_members` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `group_policies` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `group_posts` | `discussion_id` | `group_discussions` | `id` | CASCADE | NO ACTION | NO |
| `group_posts` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `group_types` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `groups` | `owner_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `groups` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `groups` | `type_id` | `group_types` | `id` | SET NULL | NO ACTION | YES |
| `idea_media` | `idea_id` | `challenge_ideas` | `id` | CASCADE | NO ACTION | NO |
| `idea_team_links` | `challenge_id` | `ideation_challenges` | `id` | CASCADE | NO ACTION | NO |
| `idea_team_links` | `idea_id` | `challenge_ideas` | `id` | CASCADE | NO ACTION | NO |
| `identity_verification_events` | `session_id` | `identity_verification_sessions` | `id` | SET NULL | NO ACTION | YES |
| `identity_verification_sessions` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `identity_verification_sessions` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `insurance_certificates` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `insurance_certificates` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `job_application_history` | `application_id` | `job_vacancy_applications` | `id` | CASCADE | NO ACTION | NO |
| `job_vacancy_applications` | `vacancy_id` | `job_vacancies` | `id` | CASCADE | NO ACTION | NO |
| `job_vacancy_views` | `vacancy_id` | `job_vacancies` | `id` | CASCADE | NO ACTION | NO |
| `knowledge_base_attachments` | `article_id` | `knowledge_base_articles` | `id` | CASCADE | NO ACTION | NO |
| `legal_document_versions` | `document_id` | `legal_documents` | `id` | CASCADE | CASCADE | NO |
| `likes` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `likes` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `listing_attributes` | `attribute_id` | `attributes` | `id` | CASCADE | NO ACTION | NO |
| `listing_attributes` | `listing_id` | `listings` | `id` | CASCADE | NO ACTION | NO |
| `listing_contacts` | `listing_id` | `listings` | `id` | CASCADE | NO ACTION | NO |
| `listing_risk_tags` | `listing_id` | `listings` | `id` | CASCADE | NO ACTION | NO |
| `listing_risk_tags` | `tagged_by` | `users` | `id` | SET NULL | NO ACTION | YES |
| `listing_risk_tags` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `listing_views` | `listing_id` | `listings` | `id` | CASCADE | NO ACTION | NO |
| `listings` | `category_id` | `categories` | `id` | SET NULL | NO ACTION | YES |
| `listings` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `listings` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `marketplace_categories` | `parent_id` | `marketplace_categories` | `id` | SET NULL | NO ACTION | YES |
| `marketplace_category_templates` | `category_id` | `marketplace_categories` | `id` | SET NULL | NO ACTION | YES |
| `marketplace_collection_items` | `collection_id` | `marketplace_collections` | `id` | CASCADE | NO ACTION | NO |
| `marketplace_collection_items` | `marketplace_listing_id` | `marketplace_listings` | `id` | CASCADE | NO ACTION | NO |
| `marketplace_disputes` | `order_id` | `marketplace_orders` | `id` | CASCADE | NO ACTION | NO |
| `marketplace_escrow` | `order_id` | `marketplace_orders` | `id` | CASCADE | NO ACTION | NO |
| `marketplace_escrow` | `payment_id` | `marketplace_payments` | `id` | CASCADE | NO ACTION | NO |
| `marketplace_images` | `marketplace_listing_id` | `marketplace_listings` | `id` | CASCADE | NO ACTION | NO |
| `marketplace_listings` | `category_id` | `marketplace_categories` | `id` | SET NULL | NO ACTION | YES |
| `marketplace_offers` | `marketplace_listing_id` | `marketplace_listings` | `id` | CASCADE | NO ACTION | NO |
| `marketplace_orders` | `marketplace_listing_id` | `marketplace_listings` | `id` | CASCADE | NO ACTION | NO |
| `marketplace_orders` | `marketplace_offer_id` | `marketplace_offers` | `id` | SET NULL | NO ACTION | YES |
| `marketplace_payments` | `order_id` | `marketplace_orders` | `id` | CASCADE | NO ACTION | NO |
| `marketplace_promotions` | `marketplace_listing_id` | `marketplace_listings` | `id` | CASCADE | NO ACTION | NO |
| `marketplace_reports` | `marketplace_listing_id` | `marketplace_listings` | `id` | CASCADE | NO ACTION | NO |
| `marketplace_saved_listings` | `marketplace_listing_id` | `marketplace_listings` | `id` | CASCADE | NO ACTION | NO |
| `marketplace_seller_ratings` | `order_id` | `marketplace_orders` | `id` | CASCADE | NO ACTION | NO |
| `marketplace_shipping_options` | `seller_id` | `marketplace_seller_profiles` | `id` | CASCADE | NO ACTION | NO |
| `match_approvals` | `listing_id` | `listings` | `id` | CASCADE | NO ACTION | NO |
| `match_approvals` | `listing_owner_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `match_approvals` | `reviewed_by` | `users` | `id` | SET NULL | NO ACTION | YES |
| `match_approvals` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `match_approvals` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `match_cache` | `listing_id` | `listings` | `id` | CASCADE | NO ACTION | NO |
| `match_cache` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `match_cache` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `match_history` | `listing_id` | `listings` | `id` | CASCADE | NO ACTION | NO |
| `match_history` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `match_history` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `match_preferences` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `match_preferences` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `mentions` | `comment_id` | `comments` | `id` | CASCADE | NO ACTION | YES |
| `mentions` | `mentioned_user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `mentions` | `mentioning_user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `mentions` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `menu_items` | `menu_id` | `menus` | `id` | CASCADE | NO ACTION | NO |
| `menu_items` | `parent_id` | `menu_items` | `id` | CASCADE | NO ACTION | YES |
| `message_reactions` | `message_id` | `messages` | `id` | CASCADE | NO ACTION | NO |
| `message_reactions` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `messages` | `listing_id` | `listings` | `id` | SET NULL | NO ACTION | YES |
| `messages` | `receiver_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `messages` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `newsletter_ab_stats` | `newsletter_id` | `newsletters` | `id` | CASCADE | NO ACTION | NO |
| `newsletter_ab_stats` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `newsletter_bounces` | `newsletter_id` | `newsletters` | `id` | SET NULL | NO ACTION | YES |
| `newsletter_bounces` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `newsletter_clicks` | `newsletter_id` | `newsletters` | `id` | CASCADE | NO ACTION | NO |
| `newsletter_clicks` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `newsletter_engagement_patterns` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `newsletter_opens` | `newsletter_id` | `newsletters` | `id` | CASCADE | NO ACTION | NO |
| `newsletter_opens` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `newsletter_queue` | `newsletter_id` | `newsletters` | `id` | CASCADE | NO ACTION | NO |
| `newsletter_queue` | `user_id` | `users` | `id` | SET NULL | NO ACTION | YES |
| `newsletter_segments` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `newsletter_subscribers` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `newsletter_subscribers` | `user_id` | `users` | `id` | SET NULL | NO ACTION | YES |
| `newsletter_suppression_list` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `newsletter_templates` | `created_by` | `users` | `id` | SET NULL | NO ACTION | YES |
| `newsletter_templates` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `newsletters` | `created_by` | `users` | `id` | CASCADE | NO ACTION | NO |
| `newsletters` | `segment_id` | `newsletter_segments` | `id` | SET NULL | NO ACTION | YES |
| `newsletters` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `nexus_score_cache` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `nexus_score_cache` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `nexus_score_history` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `nexus_score_history` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `nexus_score_milestones` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `nexus_score_milestones` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `notification_queue` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `notification_settings` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `notifications` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `org_alert_settings` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `org_balance_alerts` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `page_blocks` | `page_id` | `pages` | `id` | CASCADE | NO ACTION | NO |
| `page_versions` | `page_id` | `pages` | `id` | CASCADE | NO ACTION | NO |
| `post_hashtags` | `hashtag_id` | `hashtags` | `id` | CASCADE | NO ACTION | NO |
| `post_hashtags` | `post_id` | `feed_posts` | `id` | CASCADE | NO ACTION | NO |
| `post_likes` | `post_id` | `feed_posts` | `id` | CASCADE | NO ACTION | NO |
| `post_likes` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `post_likes` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `posts` | `author_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `posts` | `category_id` | `categories` | `id` | SET NULL | NO ACTION | YES |
| `posts` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `proposal_votes` | `proposal_id` | `proposals` | `id` | CASCADE | NO ACTION | NO |
| `proposal_votes` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `proposals` | `group_id` | `groups` | `id` | CASCADE | NO ACTION | NO |
| `proposals` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `push_subscriptions` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `reactions` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `reactions` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `recurring_shift_patterns` | `created_by` | `users` | `id` | CASCADE | NO ACTION | NO |
| `recurring_shift_patterns` | `opportunity_id` | `vol_opportunities` | `id` | CASCADE | NO ACTION | NO |
| `recurring_shift_patterns` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `review_responses` | `review_id` | `reviews` | `id` | CASCADE | NO ACTION | NO |
| `review_votes` | `review_id` | `reviews` | `id` | CASCADE | NO ACTION | NO |
| `reviews` | `receiver_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `reviews` | `reviewer_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `revoked_tokens` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `role_permissions` | `permission_id` | `permissions` | `id` | CASCADE | NO ACTION | NO |
| `role_permissions` | `role_id` | `roles` | `id` | CASCADE | NO ACTION | NO |
| `saved_jobs` | `job_id` | `job_vacancies` | `id` | CASCADE | NO ACTION | NO |
| `search_feedback` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `search_feedback` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `search_logs` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `search_logs` | `user_id` | `users` | `id` | CASCADE | NO ACTION | YES |
| `season_rankings` | `season_id` | `leaderboard_seasons` | `id` | CASCADE | NO ACTION | NO |
| `seo_redirects` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `skill_categories` | `parent_id` | `skill_categories` | `id` | SET NULL | NO ACTION | YES |
| `social_identities` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `story_analytics` | `story_id` | `stories` | `id` | CASCADE | NO ACTION | NO |
| `story_archive` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `story_poll_votes` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `story_reactions` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `story_stickers` | `story_id` | `stories` | `id` | CASCADE | NO ACTION | NO |
| `story_views` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `tenant_consent_overrides` | `consent_type_slug` | `consent_types` | `slug` | CASCADE | CASCADE | NO |
| `tenant_consent_overrides` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `tenant_consent_version_history` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `tenant_invite_code_uses` | `invite_code_id` | `tenant_invite_codes` | `id` | CASCADE | NO ACTION | NO |
| `tenant_invite_code_uses` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `tenant_invite_codes` | `created_by` | `users` | `id` | CASCADE | NO ACTION | NO |
| `tenant_invite_codes` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `tenant_plan_assignments` | `pay_plan_id` | `pay_plans` | `id` | NO ACTION | NO ACTION | NO |
| `tenant_registration_policies` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `tenant_safeguarding_options` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `tenants` | `parent_id` | `tenants` | `id` | NO ACTION | CASCADE | YES |
| `transaction_categories` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `transactions` | `giver_id` | `users` | `id` | CASCADE | NO ACTION | YES |
| `transactions` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `user_badges` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `user_badges` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `user_categories` | `category_id` | `categories` | `id` | CASCADE | NO ACTION | NO |
| `user_categories` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `user_categories` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `user_category_affinity` | `category_id` | `categories` | `id` | CASCADE | NO ACTION | NO |
| `user_category_affinity` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `user_category_affinity` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `user_challenge_progress` | `challenge_id` | `challenges` | `id` | CASCADE | NO ACTION | NO |
| `user_collection_completions` | `collection_id` | `badge_collections` | `id` | CASCADE | NO ACTION | NO |
| `user_distance_preference` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `user_distance_preference` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `user_first_contacts` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `user_first_contacts` | `user1_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `user_first_contacts` | `user2_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `user_interests` | `category_id` | `categories` | `id` | CASCADE | NO ACTION | NO |
| `user_interests` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `user_legal_acceptances` | `document_id` | `legal_documents` | `id` | CASCADE | CASCADE | NO |
| `user_legal_acceptances` | `version_id` | `legal_document_versions` | `id` | CASCADE | CASCADE | NO |
| `user_messaging_restrictions` | `restricted_by` | `users` | `id` | SET NULL | NO ACTION | YES |
| `user_messaging_restrictions` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `user_messaging_restrictions` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `user_permissions` | `permission_id` | `permissions` | `id` | CASCADE | NO ACTION | NO |
| `user_roles` | `role_id` | `roles` | `id` | CASCADE | NO ACTION | NO |
| `user_safeguarding_preferences` | `option_id` | `tenant_safeguarding_options` | `id` | CASCADE | NO ACTION | NO |
| `user_safeguarding_preferences` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `user_safeguarding_preferences` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `user_skills` | `category_id` | `skill_categories` | `id` | SET NULL | NO ACTION | YES |
| `user_xp_purchases` | `item_id` | `xp_shop_items` | `id` | CASCADE | NO ACTION | NO |
| `users` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | YES |
| `vetting_records` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `vetting_records` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |
| `vol_applications` | `shift_id` | `vol_shifts` | `id` | SET NULL | CASCADE | YES |
| `vol_applications` | `user_id` | `users` | `id` | CASCADE | CASCADE | NO |
| `vol_certificates` | `user_id` | `users` | `id` | CASCADE | CASCADE | NO |
| `vol_emergency_alert_recipients` | `alert_id` | `vol_emergency_alerts` | `id` | CASCADE | CASCADE | NO |
| `vol_emergency_alert_recipients` | `user_id` | `users` | `id` | CASCADE | CASCADE | NO |
| `vol_logs` | `opportunity_id` | `vol_opportunities` | `id` | SET NULL | CASCADE | YES |
| `vol_logs` | `user_id` | `users` | `id` | CASCADE | CASCADE | NO |
| `vol_opportunities` | `category_id` | `categories` | `id` | SET NULL | NO ACTION | YES |
| `vol_shift_checkins` | `shift_id` | `vol_shifts` | `id` | CASCADE | CASCADE | NO |
| `vol_shift_checkins` | `user_id` | `users` | `id` | CASCADE | CASCADE | NO |
| `vol_shift_group_members` | `reservation_id` | `vol_shift_group_reservations` | `id` | CASCADE | CASCADE | NO |
| `vol_shift_group_members` | `user_id` | `users` | `id` | CASCADE | CASCADE | NO |
| `vol_shift_group_reservations` | `shift_id` | `vol_shifts` | `id` | CASCADE | CASCADE | NO |
| `vol_shift_swap_requests` | `from_shift_id` | `vol_shifts` | `id` | CASCADE | CASCADE | NO |
| `vol_shift_swap_requests` | `from_user_id` | `users` | `id` | CASCADE | CASCADE | NO |
| `vol_shift_swap_requests` | `to_shift_id` | `vol_shifts` | `id` | CASCADE | CASCADE | NO |
| `vol_shift_swap_requests` | `to_user_id` | `users` | `id` | CASCADE | CASCADE | NO |
| `vol_shift_waitlist` | `shift_id` | `vol_shifts` | `id` | CASCADE | CASCADE | NO |
| `vol_shift_waitlist` | `user_id` | `users` | `id` | CASCADE | CASCADE | NO |
| `vol_shifts` | `opportunity_id` | `vol_opportunities` | `id` | CASCADE | CASCADE | NO |
| `volunteering_organizations` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `webauthn_credentials` | `tenant_id` | `tenants` | `id` | CASCADE | NO ACTION | NO |
| `webauthn_credentials` | `user_id` | `users` | `id` | CASCADE | NO ACTION | NO |