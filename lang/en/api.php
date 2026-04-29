<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    // ============================================
    // AUTHENTICATION & AUTHORIZATION
    // ============================================
    'auth_required'                => 'Authentication required',
    'auth_required_detail'         => 'You must be logged in to access this resource.',
    'admin_access_required'        => 'Admin access required',
    'broker_or_admin_access_required' => 'Broker or admin access required',
    'super_admin_required'         => 'Super admin access required',
    'insufficient_permissions'     => 'Insufficient permissions',

    // ============================================
    // RATE LIMITING
    // ============================================
    'rate_limit_exceeded'          => 'Rate limit exceeded. Please try again later.',

    // ============================================
    // VALIDATION — GENERIC
    // ============================================
    'validation_failed'            => 'Validation failed',
    'missing_required_field'       => 'Missing required field: :field',
    'no_fields_to_update'          => 'No fields to update',
    'no_fields_provided'           => 'No fields provided to update',
    'request_body_empty'           => 'Request body is empty',
    'invalid_id'                   => 'Invalid :resource ID',
    'slug_already_in_use'          => 'Slug already in use',

    // ============================================
    // RESOURCE — NOT FOUND
    // ============================================
    'not_found'                    => ':model not found.',
    'user_not_found'               => 'User not found',
    'comment_not_found'            => 'Comment not found',
    'post_not_found'               => 'Post not found',
    'event_not_found'              => 'Event not found',
    'listing_not_found'            => 'Listing not found',
    'group_not_found'              => 'Group not found',
    'invalid_group_type'           => 'Invalid group type',
    'job_not_found'                => 'Job not found',
    'blog_post_not_found'          => 'Blog post not found',
    'review_not_found'             => 'Review not found',
    'article_not_found'            => 'Article not found',
    'category_not_found'           => 'Category not found',
    'attribute_not_found'          => 'Attribute not found',
    'page_not_found'               => 'Page not found',
    'menu_not_found'               => 'Menu not found',
    'menu_item_not_found'          => 'Menu item not found',
    'plan_not_found'               => 'Plan not found',
    'tenant_and_plan_required'     => 'tenant_id and pay_plan_id are required.',
    'report_not_found'             => 'Report not found',
    'poll_not_found'               => 'Poll not found',
    'newsletter_not_found'         => 'Newsletter not found',
    'partnership_not_found'        => 'Partnership not found',
    'template_not_found'           => 'Template not found',
    'role_not_found'               => 'Role not found',
    'note_not_found'               => 'Note not found',
    'task_not_found'               => 'Task not found',
    'contact_not_found'            => 'Contact not found',
    'badge_not_found'              => 'Badge not found for this user',
    'exchange_not_found'           => 'Exchange request not found',
    'log_not_found'                => 'Log not found',
    'challenge_not_found'          => 'Challenge not found',
    'segment_not_found'            => 'Segment not found',
    'webhook_not_found'            => 'Webhook not found',
    'subscriber_not_found'         => 'Subscriber not found',
    'deliverable_not_found'        => 'Deliverable not found',
    'insurance_cert_not_found'     => 'Insurance certificate not found',
    'version_not_found'            => 'Version not found',
    'member_not_found'             => 'Member not found',
    'campaign_not_found'           => 'Campaign not found',
    'cron_job_not_found'           => 'Cron job not found',
    'tenant_not_found'             => 'Tenant not found',
    'tenant_context_error'         => 'Unable to resolve tenant context',
    'legal_doc_not_found'          => 'Legal document not found',
    'document_not_found'           => 'Document not found',
    'api_key_not_found'            => 'API key not found',
    'credit_agreement_not_found'   => 'Credit agreement not found',
    'pending_request_not_found'    => 'Pending membership request not found',

    // ============================================
    // VALIDATION — USERS
    // ============================================
    'first_name_required'          => 'First name is required',
    'last_name_required'           => 'Last name is required',
    'valid_email_required'         => 'Valid email is required',
    'invalid_email'                => 'Invalid email',
    'email_already_exists'         => 'A user with this email already exists',
    'password_min_length'          => 'Password must be at least 8 characters',
    'password_uppercase'           => 'Password must contain at least one uppercase letter',
    'password_lowercase'           => 'Password must contain at least one lowercase letter',
    'password_number'              => 'Password must contain at least one number',
    'password_special_char'        => 'Password must contain at least one special character',
    'invalid_role'                 => 'Invalid role value',
    'invalid_role_allowed'         => 'Invalid role. Allowed: :roles',

    // ============================================
    // VALIDATION — CONTENT
    // ============================================
    'title_required'               => 'Title is required',
    'name_required'                => 'Name is required',
    'label_required'               => 'Label is required',
    'location_required'            => 'Location is required',
    'subject_required'             => 'Subject is required',
    'status_required'              => 'Status is required',
    'reason_required'              => 'A reason is required',
    'comment_text_required'        => 'Comment text is required',
    'valid_email_address_required' => 'Valid email address required',
    'category_name_required'       => 'Category name is required',
    'attribute_name_required'      => 'Attribute name is required',
    'badge_name_required'          => 'Badge name is required',
    'badge_slug_required'          => 'Badge slug is required',
    'badge_slug_invalid'           => 'Badge slug does not exist or is not enabled for this tenant',
    'user_ids_required'            => 'User IDs array is required',
    'campaign_name_required'       => 'Campaign name is required',
    'feature_name_required'        => 'Feature name is required',
    'module_name_required'         => 'Module name is required',
    'enabled_required'             => 'Enabled value is required',
    'field_required'               => 'This field is required.',
    'role_name_required'           => 'Role name is required',
    'breach_type_required'         => 'Breach type is required',
    'policy_key_required'          => 'Policy key is required',

    // ============================================
    // VALIDATION — FORMATTING / VALUES
    // ============================================
    'status_must_be_draft_or_published'    => 'Status must be draft or published',
    'status_must_be_active_or_inactive'    => 'Status must be "active" or "inactive"',
    'category_duplicate'                   => 'A category with this name already exists',
    'slug_reserved'                        => 'The slug ":slug" is reserved and cannot be used for a page.',
    'slug_reserved_short'                  => 'The slug ":slug" is reserved.',
    'only_draft_can_be_edited'             => 'Only draft versions can be edited',
    'only_draft_can_be_deleted'            => 'Only draft versions can be deleted',
    'version_does_not_belong'              => 'Version does not belong to this document',
    'items_array_required'                 => 'Items array is required',
    'parent_menu_item_not_found'           => 'Parent menu item not found in this menu',
    'page_not_found_validation'            => 'Page not found',
    'match_type_must_be_all_or_any'        => 'match_type must be "all" or "any"',

    // ============================================
    // ADMIN — USERS
    // ============================================
    'only_super_admins_assign_roles'       => 'Only super admins can assign elevated roles',
    'use_super_admin_endpoints'            => 'Use the dedicated super-admin endpoints to manage super admin privileges',
    'cannot_suspend_own_account'           => 'Cannot suspend your own account',
    'cannot_suspend_super_admin'           => 'Cannot suspend a super admin',
    'cannot_ban_own_account'               => 'Cannot ban your own account',
    'cannot_ban_super_admin'               => 'Cannot ban a super admin',
    'cannot_delete_own_account'            => 'Cannot delete your own account',
    'cannot_delete_super_admin'            => 'Cannot delete a super admin',
    'cannot_impersonate_super_admin'       => 'Cannot impersonate a super admin',
    'cannot_impersonate_self'              => 'Cannot impersonate yourself',
    'cannot_modify_own_super_admin'        => 'You cannot modify your own super admin status',
    'only_super_admins_manage_status'      => 'Only super admins can manage super admin status',
    'only_god_manage_global_super_admin'   => 'Only god users can manage global super admin status',
    'user_created_failed'                  => 'Failed to create user — email may already exist',
    'csv_no_file'                          => 'No CSV file uploaded or upload error',
    'csv_invalid_type'                     => 'Invalid file type. Please upload a CSV file.',
    'csv_empty'                            => 'Empty CSV file',
    'csv_missing_columns'                  => 'Missing required columns: :columns',
    'csv_could_not_read'                   => 'Could not read file',

    // ============================================
    // ADMIN — JOBS
    // ============================================
    'job_approved'                         => 'Job approved and published successfully',
    'job_rejected'                         => 'Job rejected successfully',
    'job_flagged'                          => 'Job flagged for further review',
    'reason_required_reject_job'           => 'A reason is required when rejecting a job',
    'reason_required_flag_job'             => 'A reason is required when flagging a job',

    // ============================================
    // ADMIN — COMMENTS / FEED
    // ============================================
    'comment_hidden'                       => 'Comment hidden',
    'comment_deleted'                      => 'Comment deleted',
    'item_hidden'                          => 'Item hidden',
    'item_deleted'                         => 'Item deleted',

    // ============================================
    // ADMIN — FEDERATION
    // ============================================
    'partnership_approved'                 => 'Partnership approved',
    'partnership_rejected'                 => 'Partnership rejected',
    'partnership_terminated'               => 'Partnership terminated',
    'only_pending_can_be_approved'         => 'Only pending partnerships can be approved (current: :status)',
    'only_pending_can_be_rejected'         => 'Only pending partnerships can be rejected (current: :status)',
    'only_active_can_be_terminated'        => 'Only active or suspended partnerships can be terminated (current: :status)',
    'target_community_required'            => 'Target community ID is required',
    'cannot_partner_with_self'             => 'Cannot partner with your own community',
    'level_must_be_1_to_4'                 => 'Level must be between 1 and 4',

    // ============================================
    // ADMIN — BILLING
    // ============================================
    'invalid_plan_id'                      => 'Invalid plan_id',

    // ============================================
    // SERVER / OPERATIONAL
    // ============================================
    'server_error'                         => 'An internal error occurred',
    'failed_to_save_config'                => 'Failed to save configuration',
    'failed_to_clear_cache'                => 'Failed to clear cache',
    'service_unavailable'                  => 'Service unavailable',
    'daily_rewards_unavailable'            => 'Daily rewards not available',
    'failed_to_calculate_score'            => 'Failed to calculate score',
    'forbidden'                            => 'Forbidden',

    // ============================================
    // OPERATIONS — GENERIC FAILURE
    // ============================================
    'delete_failed'                        => 'Failed to delete :resource',
    'create_failed'                        => 'Failed to create :resource',
    'update_failed'                        => 'Failed to update :resource',
    'fetch_failed'                         => 'Failed to load :resource',
    'approve_failed'                       => 'Failed to approve :resource',
    'reject_failed'                        => 'Failed to reject :resource',

    // ============================================
    // ADMIN — ANALYTICS / REPORTS
    // ============================================
    'hour_value_range'                     => 'Hour value must be between 0 and 10,000',
    'social_multiplier_range'              => 'Social multiplier must be between 0 and 100',
    'invalid_reporting_period'             => 'Invalid reporting period',
    'social_value_config_updated'          => 'Social value configuration updated',
    'moderation_settings_updated'          => 'Moderation settings updated',
    'decision_required'                    => 'Decision is required (approved or rejected)',
    'invalid_decision'                     => 'Invalid decision value. Allowed: approved, rejected.',
    'failed_update_moderation'             => 'Failed to update moderation settings',

    // ============================================
    // ADMIN — CONFIG
    // ============================================
    'unknown_feature'                      => 'Unknown feature: :feature',
    'unknown_module'                       => 'Unknown module: :module',
    'cannot_run_disabled_job'              => 'Cannot run disabled job. Enable it first.',
    'welcome_credits_range'                => 'welcome_credits must be between 0 and 100',
    'max_upload_size_range'                => 'max_upload_size_mb must be between 1 and 50',
    'items_per_page_range'                 => 'items_per_page must be between 5 and 100',
    'maintenance_mode_boolean'             => 'maintenance_mode must be a boolean value',
    'registration_mode_invalid'            => 'registration_mode must be one of: open, closed, invite_only',
    'default_currency_invalid'             => 'default_currency must be a 3-letter ISO 4217 currency code (e.g. eur, usd, gbp)',
    'no_recognized_settings'               => 'No recognized settings provided. Unknown keys: :keys',
    'no_recognized_ai_settings'            => 'No recognized AI settings provided',
    'invalid_ai_provider'                  => 'Invalid AI provider. Must be one of: :providers',
    'weight_range'                         => 'Weight :key must be between 0 and 1',
    'no_recognized_feed_settings'          => 'No recognized feed algorithm settings provided',
    'max_file_size_range'                  => 'Max file size must be between 1 and 50 MB',
    'webp_quality_range'                   => 'WebP quality must be between 50 and 100',
    'dimension_range'                      => 'Dimension :key must be between 50 and 10000',
    'no_recognized_image_settings'         => 'No recognized image settings provided',
    'no_recognized_seo_settings'           => 'No recognized SEO settings provided',
    'no_recognized_native_app_settings'    => 'No recognized native app settings provided',
    'invalid_display_mode'                 => 'Invalid display mode. Must be one of: :modes',
    'invalid_orientation'                  => 'Invalid orientation. Must be one of: :orientations',
    'algorithm_area_invalid'               => 'Area must be: feed, listings, or members',
    'algorithm_value_range'                => ':key must be between 0 and 10',
    'no_recognized_algorithm_settings'     => 'No recognized algorithm settings provided',
    'invalid_default_language'             => 'Invalid default language',
    'supported_languages_must_be_array'    => 'supported_languages must be an array',
    'invalid_language'                     => 'Invalid language: :lang',

    // ============================================
    // ADMIN — REVIEWS / REPORTS
    // ============================================
    'review_flagged'                       => 'Review flagged for attention',
    'review_hidden'                        => 'Review hidden',
    'review_deleted'                       => 'Review deleted',
    'report_resolved'                      => 'Report resolved',
    'report_dismissed'                     => 'Report dismissed',

    // ============================================
    // ADMIN — NEWSLETTER
    // ============================================
    'newsletter_not_configured'            => 'Newsletter functionality is not yet configured',
    'subscriber_not_configured'            => 'Newsletter subscriber functionality is not yet configured',
    'segment_not_configured'               => 'Segment functionality is not yet configured',
    'template_not_configured'              => 'Template functionality is not yet configured',
    'newsletter_tables_not_available'      => 'Newsletter tables not available',
    'suppression_list_not_available'       => 'Suppression list not available',
    'subscriber_already_exists'            => 'A subscriber with this email already exists',
    'rows_array_required'                  => 'An array of subscriber rows is required',
    'newsletter_already_sent'              => 'Newsletter has already been sent',
    'newsletter_currently_sending'         => 'Newsletter is currently being sent',
    'newsletter_send_failed'               => 'Failed to send newsletter. Please try again or check logs.',
    'newsletter_duplicated'                => 'Newsletter duplicated successfully',
    'ab_winner_must_be_a_or_b'             => 'Winner must be "a" or "b"',
    'no_ab_testing'                        => 'Newsletter does not have A/B testing enabled',
    'invalid_target'                       => 'Invalid target',
    'no_recipients_for_resend'             => 'No recipients found for resend target',
    'admin_no_email'                       => 'Admin user has no email address',

    // ============================================
    // ADMIN — WEBHOOK
    // ============================================
    'valid_url_required'                   => 'A valid URL is required',
    'url_must_use_https'                   => 'URL must use HTTPS',
    'url_no_private_ip'                    => 'URL must not target private or internal IP addresses',
    'at_least_one_event_type'              => 'At least one event type is required',
    'no_valid_fields_to_update'            => 'No valid fields to update',
    'webhook_private_ip'                   => 'Webhook URL resolves to a private or internal IP address',
    'delivery_already_succeeded'           => 'This delivery already succeeded',
    'parent_webhook_not_found'             => 'Parent webhook not found',
    'api_key_already_revoked'              => 'API key is already revoked',

    // ============================================
    // ADMIN — ENTERPRISE
    // ============================================
    'role_not_found_or_unavailable'        => 'Role not found or roles table not available',
    'no_configuration_data'                => 'No configuration data provided',

    // ============================================
    // ADMIN — INSURANCE
    // ============================================
    'invalid_insurance_type'               => 'Invalid insurance type',
    'invalid_status'                       => 'Invalid status',
    'certificate_already_verified'         => 'Certificate is already verified',
    'reason_required_reject_cert'          => 'A reason is required to reject an insurance certificate',

    // ============================================
    // ADMIN — GROUPS
    // ============================================
    'group_no_location'                    => 'Group has no location to geocode',
    'geocoding_failed'                     => 'Failed to geocode location',
    'group_type_in_use'                    => 'Cannot delete group type: :count groups are using it',

    // ============================================
    // ADMIN — BROKER
    // ============================================
    'exchange_not_pending'                 => 'Exchange is not pending broker approval',
    'reason_required_reject_exchange'      => 'A reason is required to reject an exchange',
    'cannot_broker_own_exchange'           => 'You cannot approve or reject an exchange you are a party to.',
    'invalid_flag_severity'                => 'Invalid severity. Must be one of: info, warning, concern, urgent.',
    'risk_notes_max_length'                => 'Risk notes must be 2000 characters or less.',
    'member_visible_notes_max_length'      => 'Member-visible notes must be 500 characters or less.',
    'broker_config_admin_only_keys'        => 'These configuration keys can only be changed by an admin: :keys',
    'invalid_risk_level'                   => 'Invalid risk level',
    'risk_category_required'               => 'Risk category is required',
    'broker_message_not_found'             => 'Broker message copy not found',
    'already_archived'                     => 'This message copy has already been archived',
    'reason_required_flag_message'         => 'A reason is required to flag a message',
    'reason_required_monitoring'           => 'A reason is required to set monitoring',

    // ============================================
    // ADMIN — SAFEGUARDING
    // ============================================
    'ward_guardian_emails_required'        => 'Both ward and guardian emails are required',
    'no_active_member_found'               => 'No active member found with email: :email',
    'ward_not_found_in_tenant'             => 'Ward user not found in this tenant',

    // ============================================
    // ADMIN — LEGAL DOCS
    // ============================================
    'both_versions_required'               => 'Both v1 and v2 parameters are required',
    'versions_not_found'                   => 'One or both versions not found',
    'version_number_required'              => 'Version number is required',
    'content_required'                     => 'Content is required',
    'effective_date_required'              => 'Effective date is required',

    // ============================================
    // ADMIN — CRM
    // ============================================
    'no_valid_fields'                      => 'No valid fields to update',
    'user_id_content_required'             => 'user_id and content are required',
    'title_is_required'                    => 'title is required',
    'user_id_tag_required'                 => 'user_id and tag are required',
    'tag_max_length'                       => 'Tag must be 50 characters or less',
    'tag_already_assigned'                 => 'Tag already assigned',
    'tag_not_found'                        => 'Tag not found',
    'tag_param_required'                   => 'tag parameter is required',

    // ============================================
    // ADMIN — EMAIL
    // ============================================
    'invalid_email_provider'               => 'Invalid email provider',
    'no_valid_settings'                    => 'No valid settings provided',
    'failed_send_test_email'               => 'Failed to send test email. Check server logs.',
    'failed_send_test_via'                 => 'Failed to send test email via :provider. Check server logs.',

    // ============================================
    // ADMIN — ONBOARDING
    // ============================================
    'preset_required'                      => 'preset is required',
    'invalid_preset'                       => 'Invalid preset: :preset',
    'onboarding_settings_updated'          => 'Onboarding settings updated',

    // ============================================
    // ADMIN — DELIVERABILITY
    // ============================================
    'title_cannot_be_empty'                => 'Title cannot be empty',
    'invalid_status_value'                 => 'Invalid status value',
    'invalid_priority_value'               => 'Invalid priority value',

    // ============================================
    // ADMIN — FEDERATION CREDIT AGREEMENTS
    // ============================================
    'partner_tenant_required'              => 'Partner tenant ID is required',
    'cannot_agree_with_self'               => 'Cannot create agreement with your own community',
    'exchange_rate_gt_zero'                => 'Exchange rate must be greater than zero',
    'monthly_limit_gt_zero'               => 'Monthly limit must be greater than zero',
    'invalid_action'                       => 'Invalid action. Must be one of: :actions',
    'federation_credit_unavailable'        => 'FederationCreditService not available',

    // ============================================
    // ADMIN — FEDERATION NEIGHBORHOODS
    // ============================================
    'neighborhood_not_found'               => 'Neighborhood not found',
    'tenant_not_in_neighborhood'           => 'Tenant not found in neighborhood',
    'tenant_id_required'                   => 'Tenant ID is required',
    'federation_neighborhood_unavailable'  => 'FederationNeighborhoodService not available',

    // ============================================
    // ADMIN — GAMIFICATION
    // ============================================
    'badge_recheck_failed'                 => 'Badge recheck failed',
    'badge_keys_must_be_array'             => 'badge_keys must be an array',
    'max_5_badges'                         => 'Maximum 5 badges allowed',
    'item_id_required'                     => 'Item ID required',
    'user_id_required'                     => 'User ID required',
    'showcase_updated'                     => 'Showcase updated',
    'invalid_challenge_status'             => 'Invalid status. Valid: open, closed, reviewing, archived',

    // ============================================
    // USER-FACING — AUTH
    // ============================================
    'two_factor_required'                  => 'Two-factor authentication required',
    'logged_out'                           => 'Logged out successfully',
    'session_refreshed'                    => 'Session refreshed',
    'session_restored'                     => 'Session restored from token',
    'session_sync_deprecated'              => 'This endpoint is deprecated. Mobile apps should use Bearer tokens directly without session sync.',
    'all_tokens_revoked'                   => 'All refresh tokens have been revoked. You will need to log in again on all devices.',
    'two_factor_disabled'                  => 'Two-factor authentication has been disabled',

    // ============================================
    // USER-FACING — PROFILE / SETTINGS
    // ============================================
    'theme_preference_updated'             => 'Theme preference updated',
    'theme_preferences_updated'            => 'Theme preferences updated',
    'language_preference_updated'          => 'Language preference updated',
    'password_updated'                     => 'Password updated successfully',
    'account_deleted'                      => 'Account deleted successfully',
    'notification_preferences_updated'     => 'Notification preferences updated',
    'data_request_submitted'               => 'Your request has been submitted and will be processed within 30 days.',

    // ============================================
    // USER-FACING — NOTIFICATIONS
    // ============================================
    'invalid_context_type'                 => 'Invalid context type',
    'invalid_frequency'                    => 'Invalid frequency',
    'context_id_required'                  => 'Context ID required',
    'invalid_group_id'                     => 'Invalid group ID',
    'invalid_thread_id'                    => 'Invalid thread ID',
    'failed_update_notification_settings'  => 'Failed to update notification settings',
    'notification_deleted'                 => 'Notification deleted',

    // ============================================
    // USER-FACING — MESSAGES
    // ============================================
    'conversation_deleted'                 => 'Conversation deleted',
    'conversation_restored'                => 'Conversation restored',
    'message_deleted'                      => 'Message deleted',
    'failed_delete_conversation'           => 'Failed to delete conversation',
    'failed_fetch_reactions'               => 'Failed to fetch reactions',

    // ============================================
    // USER-FACING — EMAIL VERIFICATION
    // ============================================
    'email_already_verified'               => 'Email address is already verified',
    'email_verified'                       => 'Email address verified successfully',
    'verification_email_sent'              => 'Verification email sent',
    'verification_email_sent_if_exists'    => 'If an account with that email exists and is unverified, a new verification email has been sent.',

    // ============================================
    // USER-FACING — PASSWORD RESET
    // ============================================
    'reset_link_sent'                      => 'If an account exists with that email address, a password reset link has been sent.',
    'reset_token_required'                 => 'Reset token is required',
    'password_required'                    => 'Password is required',
    'password_confirmation_required'       => 'Password confirmation is required',
    'password_reset_success'               => 'Password updated successfully. Please log in with your new password.',
    'password_min_length_generic'          => 'Password must be at least :length characters long',

    // ============================================
    // USER-FACING — REGISTRATION
    // ============================================
    'email_verified_success'               => 'Email verified successfully',
    'verification_sent_if_exists'          => 'Verification email sent if account exists',

    // ============================================
    // USER-FACING — WALLET / EXCHANGES
    // ============================================
    'amount_gt_zero'                       => 'Amount must be greater than 0',
    'recipient_required'                   => 'recipient_id is required',
    'amount_required'                      => 'Amount is required',
    'rating_required'                      => 'Rating is required (1-5)',
    'category_name_wallet_required'        => 'Name is required',
    'failed_create_category'               => 'Failed to create category',
    'failed_update_category'               => 'Failed to update category',
    'cannot_delete_system_category'        => 'Cannot delete this category (may be a system category)',
    'failed_update_starting_balance'       => 'Failed to update starting balance',
    'donation_successful'                  => 'Donation successful. Thank you!',
    'category_deleted'                     => 'Category deleted',
    'starting_balance_updated'             => 'Starting balance updated',
    'statement_exported'                   => 'Statement exported',
    'exchange_declined'                    => 'Exchange request declined',
    'exchange_cancelled'                   => 'Exchange cancelled',

    // ============================================
    // USER-FACING — EVENTS
    // ============================================
    'event_full_waitlisted'                => 'Event is full. You have been added to the waitlist.',

    // ============================================
    // USER-FACING — FEDERATION
    // ============================================
    'already_opted_in'                     => 'Already opted in to federation.',
    'federation_enabled'                   => 'Federation enabled successfully.',
    'federation_disabled'                  => 'Federation disabled successfully.',
    'settings_updated'                     => 'Settings updated successfully.',

    // ============================================
    // USER-FACING — GDPR
    // ============================================
    'gdpr_request_submitted'               => 'Your request has been submitted and will be processed within 30 days.',
    'gdpr_deletion_submitted'              => 'Your account deletion request has been submitted. You will receive confirmation via email.',

    // ============================================
    // USER-FACING — COOKIES
    // ============================================
    'consent_updated'                      => 'Consent preferences updated successfully',
    'consent_withdrawn'                    => 'Consent withdrawn successfully',

    // ============================================
    // USER-FACING — LEGAL
    // ============================================
    'no_documents_require_acceptance'      => 'No documents require acceptance',
    'all_documents_accepted'               => 'All documents accepted',
    'acceptance_recorded'                  => 'Acceptance recorded',
    'all_legal_documents_accepted'         => 'All legal documents accepted',

    // ============================================
    // USER-FACING — ONBOARDING
    // ============================================
    'onboarding_already_completed'         => 'Onboarding already completed',
    'onboarding_complete'                  => 'Onboarding complete!',
    'safeguarding_preferences_saved'       => 'Safeguarding preferences saved',

    // ============================================
    // USER-FACING — SOCIAL / FEED
    // ============================================
    'post_unshared'                        => 'Post unshared',
    'shared_successfully'                  => 'Shared successfully',
    'reply_posted'                         => 'Reply posted successfully',
    'comment_deleted_user'                 => 'Comment deleted successfully',

    // ============================================
    // USER-FACING — HELP
    // ============================================
    'missing_article_slug'                 => 'Missing article_slug',
    'help_article_not_found'               => 'Article not found',
    'feedback_recorded'                    => 'Feedback recorded',

    // ============================================
    // USER-FACING — GOALS
    // ============================================
    'buddy_added'                          => 'You are now a buddy for this goal',

    // ============================================
    // USER-FACING — ENDORSEMENTS
    // ============================================
    'endorsement_added'                    => 'Endorsement added',
    'endorsement_removed'                  => 'Endorsement removed',

    // ============================================
    // USER-FACING — JOBS
    // ============================================
    'job_saved'                            => 'Job saved successfully',
    'job_removed_from_saved'               => 'Job removed from saved',
    'job_alert_created'                    => 'Job alert created successfully',
    'alert_unsubscribed'                   => 'Alert unsubscribed successfully',
    'alert_resubscribed'                   => 'Alert resubscribed successfully',
    'application_updated'                  => 'Application updated successfully',
    'interview_accepted'                   => 'Interview accepted successfully',
    'interview_declined'                   => 'Interview declined successfully',
    'offer_accepted'                       => 'Offer accepted successfully',
    'offer_rejected'                       => 'Offer rejected successfully',
    'application_data_anonymised'          => 'Your job application data has been anonymised.',
    'booking_cancelled'                    => 'Booking cancelled',

    // ============================================
    // USER-FACING — VOLUNTEER
    // ============================================
    'signed_up_for_shift'                  => 'Successfully signed up for shift',
    'hours_logged'                         => 'Hours logged successfully, pending verification',
    'review_submitted'                     => 'Review submitted successfully',
    'checked_out'                          => 'Successfully checked out',
    'joined_waitlist'                      => 'Successfully joined the waitlist',
    'claimed_shift_spot'                   => 'Successfully claimed the shift spot',
    'swap_request_sent'                    => 'Swap request sent',
    'swap_needs_admin_approval'            => 'Swap accepted but requires admin approval',
    'member_added_to_group_reservation'    => 'Member added to group reservation',
    'recurring_pattern_deactivated'        => 'Recurring pattern deactivated',
    'guardian_consent_granted'             => 'Guardian consent has been granted successfully.',
    'emergency_alert_sent'                 => 'Emergency alert sent',
    'application_approved'                 => 'Application approved',
    'application_declined'                 => 'Application declined',

    // ============================================
    // USER-FACING — WEBAUTHN / PUSH
    // ============================================
    'passkey_registered'                   => 'Passkey registered successfully',
    'auth_successful'                      => 'Authentication successful',
    'credentials_removed'                  => 'Credential(s) removed',
    'unsubscribed'                         => 'Unsubscribed successfully',
    'no_subscribers_found'                 => 'No subscribers found',
    'token_received'                       => 'Token received - will be associated on login',
    'device_registered'                    => 'Device registered for push notifications',

    // ============================================
    // USER-FACING — MISC
    // ============================================
    'log_recorded'                         => 'Log recorded',
    'menu_cache_cleared'                   => 'Menu cache cleared successfully',
    'resources_reordered'                  => 'Resources reordered successfully',
    'skill_removed'                        => 'Skill removed',
    'slot_deleted'                         => 'Slot deleted',
    'relationship_revoked'                 => 'Relationship revoked',
    'peer_endorsement_recorded'            => 'Peer endorsement recorded',
    'feedback_submitted'                   => 'Feedback submitted successfully',
    'already_unsubscribed'                 => 'You are already unsubscribed.',
    'score_recalculation_initiated'        => 'Score recalculation initiated',
    'exchange_completed'                   => 'Exchange completed successfully',
    'credits_granted'                      => 'Credits granted successfully',
    'configuration_updated'                => 'Configuration updated',
    'matching_config_updated'              => 'Matching configuration updated successfully',
    'match_cache_cleared'                  => 'Match cache cleared successfully',
    'password_reset_email_sent'            => 'Password reset email sent',
    'welcome_email_sent'                   => 'Welcome email sent',

    // ============================================
    // ADMIN — ANALYTICS / REPORTS (additional)
    // ============================================
    'unknown_report_type'                  => 'Unknown report type: :type. Valid types: active, registrations, retention, engagement, top_contributors, least_active',
    'unknown_group_by'                     => 'Unknown group_by: :value. Valid values: category, member, period, summary',
    'supported_formats_csv_pdf'            => 'Supported formats: csv, pdf',
    'unknown_export_type'                  => 'Unknown report type: :type. Valid types: :valid',
    'no_data_for_export'                   => 'No data found for export',

    // ============================================
    // ADMIN — CATEGORIES (additional)
    // ============================================
    'invalid_category_type'                => 'Invalid category type. Allowed: :types',

    // ============================================
    // ADMIN — VOLUNTEER (additional)
    // ============================================
    'only_pending_can_be_verified'         => 'Only pending hours can be verified',

    // ============================================
    // ADMIN — SUPER ADMIN
    // ============================================
    'god_level_access_required'            => 'God-level access required for this action',
    'super_panel_access_denied'            => 'Super Panel access denied: :reason',
    'unknown_reason'                       => 'Unknown reason',
    'parent_id_required'                   => 'parent_id is required',
    'super_no_access_parent_tenant'        => 'You do not have access to the parent tenant',
    'name_is_required'                     => 'name is required',
    'slug_format_invalid'                  => 'Slug must contain only lowercase letters, numbers, and hyphens',
    'domain_format_invalid'                => 'Domain must be a valid domain name',
    'contact_email_invalid'                => 'contact_email must be a valid email address',
    'contact_phone_invalid'                => 'contact_phone must be a valid international phone number',
    'latitude_range'                       => 'Latitude must be between -90 and 90',
    'longitude_range'                      => 'Longitude must be between -180 and 180',
    'super_no_access_tenant'               => 'You do not have access to this tenant',
    'super_no_access_destination'          => 'You do not have access to the destination tenant',
    'super_no_access_target'               => 'You do not have access to the target tenant',
    'super_no_access_user_tenant'          => "You do not have access to this user's tenant",
    'super_no_access_user_source_tenant'   => "You do not have access to this user's source tenant",
    'tenant_id_is_required'                => 'tenant_id is required',
    'super_user_create_required_fields'    => 'first_name, email, and password are required',
    'email_already_exists_system'          => 'Email already exists in the system',
    'super_user_edit_required_fields'      => 'first_name and email are required',
    'super_grant_denied_not_hub'           => 'Cannot grant super admin: tenant does not allow sub-tenants',
    'super_grant_denied_not_hub_target'    => 'Cannot grant Super Admin: target tenant is not a Hub',
    'super_revoke_denied_global'           => 'Cannot revoke privileges from a global super admin',
    'super_cannot_revoke_self'             => 'Cannot revoke your own global super admin status',
    'new_tenant_id_required'               => 'new_tenant_id is required',
    'super_move_user_failed'               => 'Failed to move user',
    'target_tenant_id_required'            => 'target_tenant_id is required',
    'target_tenant_not_found'              => 'Target tenant not found',
    'target_must_be_hub'                   => 'Target tenant must be a Hub tenant (allows sub-tenants)',
    'user_ids_required'                    => 'User IDs array is required',
    'tenant_ids_array_required'            => 'tenant_ids array is required',
    'super_bulk_invalid_action'            => 'action must be one of: activate, deactivate, enable_hub, disable_hub',
    'new_parent_id_required'               => 'new_parent_id is required',
    'invalid_partnership_id'               => 'Invalid partnership ID',

    // ============================================
    // ADMIN — CONTENT (additional)
    // ============================================
    'invalid_page_id'                      => 'Invalid page ID',
    'invalid_menu_id'                      => 'Invalid menu ID',
    'invalid_menu_item_id'                 => 'Invalid menu item ID',
    'invalid_tenant_id'                    => 'Invalid tenant ID',
    'plan_has_active_assignments'           => 'Cannot delete plan with active tenant assignments (:count active)',

    // ============================================
    // ADMIN — VETTING
    // ============================================
    'vetting_record_not_found'             => 'Vetting record not found',
    'invalid_vetting_type'                 => 'Invalid vetting type',
    'user_not_found_in_tenant'             => 'User not found in this tenant',
    'invalid_issue_date_format'            => 'Invalid issue date format',
    'invalid_expiry_date_format'           => 'Invalid expiry date format',
    'expiry_after_issue_date'              => 'Expiry date must be after issue date',
    'vetting_fetch_failed'                 => 'Failed to fetch vetting record',
    'vetting_create_failed'                => 'Failed to create vetting record',
    'vetting_update_failed'                => 'Failed to update vetting record',
    'vetting_already_verified'             => 'Record is already verified',
    'vetting_reference_required'           => 'A reference number is required before a vetting record can be verified',
    'vetting_verify_failed'                => 'Failed to verify vetting record',
    'vetting_reject_reason_required'       => 'A reason is required to reject a vetting record',
    'vetting_reject_failed'                => 'Failed to reject vetting record',
    'vetting_delete_failed'                => 'Failed to delete vetting record',
    'ids_non_empty_array_required'         => 'ids must be a non-empty array',
    'bulk_max_100'                         => 'Maximum 100 records per bulk action',
    'bulk_ids_required'                    => 'A non-empty list of IDs is required for bulk actions.',
    'bulk_too_many'                        => 'Too many IDs. Maximum :max per bulk action.',
    'vetting_bulk_invalid_action'          => 'Invalid action. Must be: verify, reject, or delete',
    'vetting_bulk_reject_reason_required'  => 'A reason is required for bulk rejection',
    'file_upload_failed'                   => 'No file was uploaded or upload failed',
    'file_type_pdf_jpeg_png_webp'          => 'Only PDF, JPEG, PNG, and WebP files are allowed',
    'file_size_limit_10mb'                 => 'File size must be under 10 MB',
    'document_upload_failed'               => 'Failed to upload document',

    // ============================================
    // ADMIN — WEBHOOKS (additional)
    // ============================================
    'invalid_event_types'                  => 'Invalid event types: :types',
    'webhooks_fetch_failed'                => 'Failed to load webhooks',
    'webhook_create_failed'                => 'Failed to create webhook',
    'webhook_update_failed'                => 'Failed to update webhook',
    'webhook_delete_failed'                => 'Failed to delete webhook',
    'webhook_logs_fetch_failed'            => 'Failed to load webhook logs',
    'webhook_test_failed'                  => 'Webhook test failed',

    // ============================================
    // ADMIN — ENTERPRISE (additional)
    // ============================================
    'role_create_failed'                   => 'Failed to create role',
    'role_not_found_in_tenant'             => 'Role not found for this tenant',
    'role_update_failed'                   => 'Failed to update role',
    'role_delete_failed'                   => 'Failed to delete role',
    'gdpr_request_update_failed'           => 'Failed to update GDPR request',
    'breach_report_failed'                 => 'Failed to report breach',
    'config_update_failed'                 => 'Failed to update configuration',
    'config_load_failed'                   => 'Failed to load configuration',
    'legal_doc_create_failed'              => 'Failed to create legal document',
    'legal_doc_update_failed'              => 'Failed to update legal document',
    'legal_doc_delete_failed'              => 'Failed to delete legal document',

    // ============================================
    // ADMIN — INSURANCE (additional)
    // ============================================
    'insurance_cert_fetch_failed'          => 'Failed to fetch insurance certificate',
    'insurance_cert_create_failed'         => 'Failed to create insurance certificate',
    'insurance_cert_update_failed'         => 'Failed to update insurance certificate',
    'insurance_cert_verify_failed'         => 'Failed to verify insurance certificate',
    'insurance_cert_reject_failed'         => 'Failed to reject insurance certificate',
    'insurance_cert_delete_failed'         => 'Failed to delete insurance certificate',

    // ============================================
    // ADMIN — SAFEGUARDING (additional)
    // ============================================
    'option_key_required'                  => 'option_key is required',
    'label_is_required'                    => 'label is required',
    'safeguarding_invalid_option_type'     => 'option_type must be one of: checkbox, info, select',
    'safeguarding_triggers_json_required'  => 'triggers must be a JSON object',
    'safeguarding_trigger_key_invalid'     => "Unknown trigger key ':key'",
    'safeguarding_trigger_type_invalid'    => "Trigger ':key' has the wrong type — boolean fields must be true/false, vetting_type_required must be a string or null",
    'safeguarding_select_options_required' => 'select_options must be a non-empty array for select type',
    'safeguarding_select_option_invalid'   => "select_options[:idx] must have 'value' and 'label' keys",
    'safeguarding_max_50_options'          => 'Maximum 50 safeguarding options per tenant',
    'no_updateable_fields'                 => 'No updateable fields provided',
    'safeguarding_option_not_found'        => 'Option not found',
    'safeguarding_order_required'          => 'order must be a non-empty object of {id: sort_order}',
    'safeguarding_invalid_option_ids'      => 'One or more option IDs are invalid',
    'safeguarding_duplicate_option_key'    => "An option with key ':key' already exists",
    'safeguarding_tables_not_created'      => 'Safeguarding tables have not been created yet.',
    'safeguarding_message_not_found_or_reviewed' => 'Message not found or already reviewed.',
    'guardian_not_found_in_tenant'         => 'Guardian user not found in this tenant',
    'ward_guardian_same_person'            => 'Ward and guardian cannot be the same person',
    'safeguarding_assignment_not_found_or_revoked' => 'Assignment not found or already revoked.',
    'no_active_member_found'               => 'No active member found with email: :email',

    // ============================================
    // ADMIN — FEDERATION (additional)
    // ============================================
    'credit_agreements_fetch_failed'       => 'Failed to load credit agreements',
    'credit_agreement_create_failed'       => 'Failed to create credit agreement',
    'credit_agreement_update_failed'       => 'Failed to update credit agreement',
    'transactions_fetch_failed'            => 'Failed to load transactions',
    'external_user_fallback'               => 'External User',
    'external_partner_fallback'            => 'External Partner',
    'invalid_external_partner_id'          => 'Invalid external partner ID',
    'invalid_external_receiver_id'         => 'Invalid external receiver ID',
    'external_partner_not_found'           => 'External partner not found or inactive',
    'external_partner_messaging_disabled'  => 'This partner does not allow messaging',
    'external_partner_api_failed'          => 'Failed to reach external partner',
    'federation_no_subject'                => '(no subject)',
    'federation_new_message_notif'         => 'New federated message from :sender (:community): :subject',
    'external_partners_fetch_failed'       => 'Failed to load external partners',
    'external_partner_create_failed'       => 'Failed to create external partner',
    'external_partner_update_failed'       => 'Failed to update external partner',
    'external_partner_delete_failed'       => 'Failed to delete external partner',
    'partner_logs_fetch_failed'            => 'Failed to load partner logs',
    'health_check_failed'                  => 'Health check failed',
    'neighborhoods_fetch_failed'           => 'Failed to load neighborhoods',
    'neighborhood_create_failed'           => 'Failed to create neighborhood',
    'neighborhood_delete_failed'           => 'Failed to delete neighborhood',
    'neighborhood_add_tenant_failed'       => 'Failed to add tenant to neighborhood',
    'neighborhood_remove_tenant_failed'    => 'Failed to remove tenant from neighborhood',
    'available_tenants_fetch_failed'       => 'Failed to load available tenants',
    'federation_lockdown_activate_failed'  => 'Failed to activate lockdown',
    'federation_lockdown_lift_failed'      => 'Failed to lift lockdown',
    'federation_whitelist_add_failed'      => 'Failed to add tenant to whitelist',
    'federation_whitelist_remove_failed'   => 'Failed to remove tenant from whitelist',
    'feature_update_failed'                => 'Failed to update feature',

    // ============================================
    // ADMIN — SETTINGS (additional)
    // ============================================
    'no_settings_provided'                 => 'No settings provided',
    'unknown_feature_with_valid'           => 'Unknown feature: :feature. Valid features: :valid',

    // ============================================
    // ADMIN — TOOLS (additional)
    // ============================================
    'from_url_required'                    => 'from_url is required',
    'to_url_required'                      => 'to_url is required',
    'redirect_create_failed'               => 'Failed to create redirect',
    'redirect_not_found'                   => 'Redirect not found',
    'redirect_not_found_or_missing_table'  => 'Redirect not found or table does not exist',
    'error_404_not_found'                  => '404 error entry not found',
    'error_404_not_found_or_missing_table' => '404 error entry not found or table does not exist',
    'seed_type_required'                   => 'At least one seed type is required',
    'invalid_seed_types'                   => 'Invalid seed types: :invalid. Valid types: :valid',
    'invalid_backup_id'                    => 'Invalid backup ID',

    // ============================================
    // ADMIN — TIMEBANKING (additional)
    // ============================================
    'status_must_be_one_of'                => 'Status must be one of: :statuses',
    'alert_not_found'                      => 'Alert not found',
    'alert_update_failed'                  => 'Failed to update alert status',
    'amount_must_be_nonzero'               => 'amount must be non-zero',
    'balance_adjust_failed'                => 'Failed to adjust balance',
    'balance_would_go_negative'            => 'Adjustment would result in negative balance',

    // ============================================
    // ADMIN — WALLET GRANT (additional)
    // ============================================
    'user_id_positive_integer_required'    => 'user_id is required and must be a positive integer',
    'amount_gt_zero_required'              => 'amount is required and must be greater than zero',
    'grant_amount_max_10000'               => 'Grant amount cannot exceed 10,000 hours',

    // ============================================
    // ADMIN — GOALS / IDEATION / POLLS (additional)
    // ============================================
    'goal_not_found'                       => 'Goal not found',
    'goal_delete_failed'                   => 'Failed to delete goal',
    'challenge_delete_failed'              => 'Failed to delete challenge',
    'challenge_status_update_failed'       => 'Failed to update challenge status',
    'poll_delete_failed'                   => 'Failed to delete poll',

    // ============================================
    // ADMIN — REPORTS / REVIEWS (additional)
    // ============================================
    'report_already_status'                => 'Report is already :status',

    // ============================================
    // ADMIN — FEED (additional)
    // ============================================
    'feed_item_not_found'                  => 'Feed item not found',

    // ============================================
    // USER-FACING — STORIES
    // ============================================
    'stories_fetch_failed'                 => 'Failed to load stories',
    'user_stories_fetch_failed'            => 'Failed to load user stories',
    'story_image_too_large'                => 'Image must be less than 10MB',
    'story_invalid_image_type'             => 'Only JPEG, PNG, GIF, and WebP images are allowed',
    'story_upload_failed'                  => 'Failed to upload image',
    'story_video_too_large'                => 'Video must be less than 50MB',
    'story_invalid_video_type'             => 'Only MP4, WebM, OGG, and MOV videos are allowed',
    'story_video_upload_failed'            => 'Failed to upload video',
    'story_media_required'                 => ':type stories require a media file',
    'story_text_required'                  => 'Text stories require content',
    'story_create_failed'                  => 'Failed to create story',
    'story_view_failed'                    => 'Failed to record view',
    'story_viewers_failed'                 => 'Failed to load viewers',
    'story_reaction_required'              => 'Reaction type is required',
    'story_react_failed'                   => 'Failed to add reaction',
    'story_delete_failed'                  => 'Failed to delete story',
    'story_poll_option_required'           => 'Option index is required',
    'story_vote_failed'                    => 'Failed to submit vote',
    'story_highlights_fetch_failed'        => 'Failed to load highlights',
    'story_highlight_stories_failed'       => 'Failed to load highlight stories',
    'story_highlight_title_required'       => 'Highlight title is required',
    'story_highlight_create_failed'        => 'Failed to create highlight',
    'story_id_required'                    => 'Story ID is required',
    'story_highlight_add_failed'           => 'Failed to add story to highlight',
    'story_reply_required'                 => 'Reply message is required',
    'story_reply_failed'                   => 'Failed to send reply',
    'story_highlight_delete_failed'        => 'Failed to delete highlight',
    'story_archive_fetch_failed'           => 'Failed to load archived stories',
    'story_close_friends_failed'           => 'Failed to load close friends',
    'story_friend_id_required'             => 'Friend ID is required',
    'story_add_friend_failed'              => 'Failed to add close friend',
    'story_remove_friend_failed'           => 'Failed to remove close friend',
    'story_event_type_required'            => 'Event type is required',
    'story_analytics_failed'               => 'Failed to load analytics',
    'story_highlight_update_failed'        => 'Failed to update highlight',
    'story_highlight_remove_failed'        => 'Failed to remove story from highlight',
    'story_stickers_must_be_array'         => 'Stickers must be an array',
    'story_save_stickers_failed'           => 'Failed to save stickers',
    'story_order_required'                 => 'Order array is required',
    'story_reorder_failed'                 => 'Failed to reorder highlights',

    // ============================================
    // USER-FACING — EXCHANGES
    // ============================================
    'exchange_feature_disabled'            => 'Exchange workflow is not enabled for this community',
    'exchange_create_failed'               => 'Failed to create exchange request',
    'exchange_provider_only_accept'        => 'Only the provider can accept this request',
    'exchange_accept_failed'               => 'Unable to accept this exchange request',
    'exchange_provider_only_decline'       => 'Only the provider can decline this request',
    'exchange_decline_failed'              => 'Unable to decline this exchange request',
    'exchange_start_failed'                => 'Unable to start this exchange',
    'exchange_complete_failed'             => 'Unable to complete this exchange',
    'exchange_hours_gt_zero'               => 'hours must be greater than 0',
    'exchange_confirm_failed'              => 'Unable to confirm this exchange',
    'exchange_completed_credits'           => 'Exchange completed! Credits have been transferred.',
    'exchange_disputed_broker'             => 'Hours recorded. There is a discrepancy - a broker will review.',
    'exchange_hours_confirmed'             => 'Hours confirmed',
    'exchange_cancel_failed'               => 'Unable to cancel this exchange',

    // ============================================
    // USER-FACING — EVENTS (additional)
    // ============================================
    'event_lat_lon_required'               => 'Latitude and longitude are required',
    'event_lat_range'                      => 'Latitude must be between -90 and 90',
    'event_lon_range'                      => 'Longitude must be between -180 and 180',
    'event_rsvp_status_required'           => 'RSVP status is required',
    'event_full_waitlisted_msg'            => 'Event is full. You have been added to the waitlist.',
    'event_too_early_checkin'              => 'Check-in is not available until 30 minutes before the event starts',
    'event_ended_checkin'                  => 'Check-in is no longer available — event ended more than 24 hours ago',
    'event_organizer_only_checkin'         => 'Only the event organizer can check in attendees',
    'event_not_rsvped'                     => 'This user has not RSVPed to this event',
    'event_already_checked_in'             => 'This attendee has already been checked in',
    'event_checkin_failed'                 => 'Failed to check in attendee',
    'event_waitlist_failed'                => 'Failed to join waitlist',
    'event_reminders_must_be_array'        => 'reminders must be an array',
    'event_reminders_update_failed'        => 'Failed to update reminders',
    'event_user_id_required'               => 'user_id is required',
    'event_user_ids_array_required'        => 'user_ids must be a non-empty array',
    'event_series_title_required'          => 'Series title is required',
    'event_series_not_found'               => 'Series not found',
    'event_series_id_required'             => 'series_id is required',
    'event_scope_must_be_single_or_all'    => 'scope must be "single" or "all"',
    'event_no_image_uploaded'              => 'No image file uploaded or upload error',
    'event_image_upload_failed'            => 'Failed to upload image',

    // ============================================
    // USER-FACING — AI CHAT (additional)
    // ============================================
    'ai_streaming_not_available'           => 'Streaming chat is not yet available. Use the standard chat endpoint.',
    'ai_conversation_not_found'            => 'Conversation not found',
    'ai_feature_disabled'                  => 'Content generation is not enabled',
    'ai_rate_limit'                        => 'Usage limit reached',
    'ai_original_message_required'         => 'Original message is required',
    'ai_error_generic'                     => 'AI service encountered an error. Please try again.',

    // ============================================
    // USER-FACING — MESSAGES (additional)
    // ============================================
    'message_recipient_required'           => 'recipient_id is required',
    'message_body_or_voice_required'       => 'Message body or voice message is required',
    'message_too_long'                     => 'Message is too long (max 10000 characters)',
    'message_body_required'                => 'Message body is required',
    'message_not_found'                    => 'Message not found',
    'message_no_archived_found'            => 'No archived conversation found',
    'message_emoji_required'               => 'Emoji is required',
    'message_invalid_emoji'                => 'Invalid emoji',
    'message_other_user_required'          => 'Other user ID required',
    'message_no_audio'                     => 'No audio data provided',
    'message_voice_upload_failed'          => 'Failed to upload audio file',
    'message_recipient_id_required'        => 'Recipient ID is required',
    'message_voice_file_required'          => 'Voice message file is required',
    'message_voice_send_failed'            => 'Failed to send voice message',
    'message_target_language_required'     => 'Target language is required',
    'message_no_transcript'                => 'This message has no transcript to translate',
    'message_no_translatable_content'     => 'This message has no content to translate',
    'message_translation_failed'           => 'Translation failed. Please try again.',
    'conversation_not_found'               => 'Conversation not found',

    // ============================================
    // USER-FACING — LISTINGS (additional)
    // ============================================
    'listing_edit_own_only'                => 'You can only edit your own listings',
    'listing_delete_own_only'              => 'You can only delete your own listings',
    'listing_delete_failed'                => 'Failed to delete listing',
    'listing_modify_forbidden'             => 'You do not have permission to modify this listing',
    'listing_analytics_forbidden'          => 'You do not have permission to view analytics',
    'listing_tags_must_be_array'           => 'Tags must be an array of strings',
    'listing_no_image_uploaded'            => 'No image file uploaded or upload error',
    'listing_image_upload_failed'          => 'Failed to upload image',
    'listing_renewal_failed'               => 'Failed to renew listing',

    // ============================================
    // USER-FACING — GAMIFICATION (additional)
    // ============================================
    'gamification_challenges_failed'       => 'Failed to load challenges',
    'gamification_challenge_not_started'   => 'You have not started this challenge',
    'gamification_challenge_already_claimed' => 'You have already claimed this reward',
    'gamification_challenge_not_completed' => 'You have not completed this challenge yet',
    'gamification_claim_failed'            => 'Failed to claim challenge reward',
    'gamification_collections_failed'      => 'Failed to load collections',
    'gamification_daily_unavailable'       => 'Daily rewards not available',
    'gamification_daily_already_claimed'   => 'Daily reward already claimed today',
    'gamification_daily_claim_failed'      => 'Failed to claim daily reward',
    'gamification_shop_failed'             => 'Failed to load shop',
    'gamification_purchase_failed'         => 'Purchase failed',
    'gamification_badge_keys_array'        => 'badge_keys must be an array',
    'gamification_max_5_showcase'          => 'Maximum 5 badges can be showcased',
    'gamification_badges_not_owned'        => 'You do not own some of the specified badges',
    'gamification_showcase_update_failed'  => 'Failed to update showcase',
    'gamification_seasons_failed'          => 'Failed to load seasons',
    'gamification_season_current_failed'   => 'Failed to load current season',
    'gamification_nexus_score_failed'      => 'Failed to load NexusScore',
    'gamification_item_id_required'        => 'Item ID is required',

    // ============================================
    // USER-FACING — SOCIAL / FEED (additional)
    // ============================================
    'social_target_required'               => 'target_type and target_id are required',
    'social_invalid_target_type'           => 'Invalid target_type',
    'social_post_content_required'         => 'Post content or image is required',
    'social_group_membership_required'     => 'You must be a member of this group to post',
    'social_post_create_failed'            => 'Failed to create post',
    'social_post_delete_own_only'          => 'You can only delete your own posts',
    'social_invalid_target'                => 'Invalid target',
    'social_like_failed'                   => 'Failed to process like',
    'social_likers_failed'                 => 'Failed to fetch likers',
    'social_invalid_action'                => 'Invalid action',
    'social_comments_failed'               => 'Failed to fetch comments',
    'social_comment_empty'                 => 'Comment cannot be empty',
    'social_comment_post_failed'           => 'Failed to post comment',
    'social_share_content_required'        => 'Invalid content to share',
    'social_share_failed'                  => 'Failed to share',
    'social_unsupported_delete_type'       => 'Unsupported target type for deletion',
    'social_content_not_found'             => ':type not found',
    'social_delete_unauthorized'           => 'Unauthorized to delete this content',
    'social_delete_failed'                 => 'Failed to delete',
    'social_invalid_reaction'              => 'Invalid reaction data',
    'social_reaction_failed'               => 'Failed to toggle reaction',
    'social_invalid_reply'                 => 'Invalid reply data',
    'social_reply_failed'                  => 'Failed to post reply',
    'social_invalid_edit'                  => 'Invalid edit data',
    'social_edit_unauthorized'             => 'Unauthorized to edit this comment',
    'social_edit_failed'                   => 'Failed to edit comment',
    'social_invalid_comment_id'            => 'Invalid comment ID',
    'social_comment_delete_unauthorized'   => 'Unauthorized to delete this comment',
    'social_comment_delete_failed'         => 'Failed to delete comment',
    'social_search_failed'                 => 'Search failed',
    'social_feed_failed'                   => 'Failed to load feed',
    'social_question_required'             => 'Question is required',
    'social_min_2_options'                 => 'At least 2 options are required',
    'social_option_id_required'            => 'option_id is required',

    // ============================================
    // USER-FACING — JOBS (additional)
    // ============================================
    'job_feature_disabled'                 => 'Job Vacancies module is not enabled for this community',
    'job_vacancy_not_found'                => 'Job vacancy not found',
    'job_create_failed'                    => 'Failed to create job vacancy',
    'job_cv_invalid_type'                  => 'Invalid file type. Allowed: PDF, DOC, DOCX',
    'job_cv_too_large'                     => 'File too large. Maximum 5MB',
    'job_cv_type_not_allowed'              => 'This file type is not allowed',
    'job_already_applied'                  => 'You have already applied to this vacancy',
    'job_application_not_found'            => 'Application not found',
    'job_access_denied'                    => 'Access denied',
    'job_no_cv_attached'                   => 'No CV attached to this application',
    'job_cv_file_not_found'                => 'CV file not found',
    'job_status_required'                  => 'Status is required',
    'job_scheduled_at_required'            => 'scheduled_at is required',
    'job_interview_propose_failed'         => 'Unable to propose interview. Check application ownership and data.',
    'job_interview_accept_failed'          => 'Unable to accept interview. It may not exist or already been actioned.',
    'job_interview_decline_failed'         => 'Unable to decline interview. It may not exist or already been actioned.',
    'job_interview_cancel_failed'          => 'Unable to cancel interview. It may not exist or already been completed.',
    'job_vacancy_owner_only_interviews'    => 'Only the vacancy owner can view interviews',
    'job_offer_create_failed'              => 'Unable to create offer. Check application ownership or an offer may already exist.',
    'job_offer_accept_failed'              => 'Unable to accept offer. It may not exist or already been actioned.',
    'job_offer_reject_failed'              => 'Unable to reject offer. It may not exist or already been actioned.',
    'job_offer_withdraw_failed'            => 'Unable to withdraw offer. It may not exist or already been actioned.',
    'job_offer_not_found'                  => 'Offer not found',
    'job_vacancy_owner_only_referrals'     => 'Only the vacancy owner can view referral stats',
    'job_vacancy_owner_only_scoring'       => 'Only the vacancy owner can score applications',
    'job_scorecard_save_failed'            => 'Unable to save scorecard',
    'job_team_add_failed'                  => 'Unable to add team member',
    'job_team_remove_failed'               => 'Unable to remove team member',
    'job_vacancy_owner_only_team'          => 'Only the vacancy owner can view team members',
    'job_profile_save_failed'              => 'Unable to save profile',
    'job_template_create_failed'           => 'Unable to create template',
    'job_erasure_failed'                   => 'Erasure failed',
    'job_rule_create_failed'               => 'Unable to create rule',
    'job_bulk_ids_status_required'         => 'application_ids and status are required',
    'job_bulk_max_1000'                    => 'Maximum 1000 application IDs per request',
    'job_ai_generate_failed'               => 'Failed to generate description',
    'listing_ai_generate_failed'           => 'Could not generate description. Please try again.',
    'job_resume_visibility_failed'         => 'Failed to update resume visibility',
    'job_candidate_not_found'              => 'Candidate profile not found or not searchable',
    'job_referral_failed'                  => 'Unable to create referral token',
    'job_slots_required'                   => 'At least one slot is required',
    'job_date_range_required'              => 'date_from and date_to are required',
    'job_day_config_required'              => 'day_config is required',

    // ============================================
    // USER-FACING — VOLUNTEER COMMUNITY (additional)
    // ============================================
    'vol_feature_disabled'                 => 'Volunteering module is not enabled for this community',
    'vol_waitlist_not_found'               => 'Waitlist entry not found',
    'vol_waitlist_own_only'                => 'You can only claim your own waitlist spot',
    'vol_action_accept_reject'             => 'Action must be accept or reject',
    'vol_action_approve_reject'            => 'Action must be approve or reject',
    'vol_group_id_required'                => 'Group ID is required',
    'vol_user_id_required'                 => 'User ID is required',
    'vol_field_label_required'             => 'field_label is required',
    'vol_custom_field_create_failed'       => 'Failed to create custom field',
    'vol_custom_field_not_found'           => 'Custom field not found',
    'vol_project_not_found'                => 'Project not found',
    'vol_project_update_forbidden'         => 'Cannot update this project',
    'vol_giving_day_not_found'             => 'Giving day not found',
    'vol_invalid_reminder_type'            => 'Invalid reminder_type. Must be one of: :types',
    'vol_consent_invalid_token'            => 'Consent token is invalid or expired',
    'vol_consent_not_found'                => 'Consent not found',

    // ============================================
    // USER-FACING — FEDERATION (additional)
    // ============================================
    'fed_not_available'                    => 'Federation is not enabled for your community.',
    'fed_opt_in_failed'                    => 'Failed to enable federation. Please try again.',
    'fed_setup_failed'                     => 'Failed to enable federation. Please try again.',
    'fed_opt_out_failed'                   => 'Failed to disable federation. Please try again.',
    'fed_member_not_found'                 => 'Federated member not found or not accessible.',
    'fed_member_profile_failed'            => 'Failed to load member profile.',
    'fed_sender_not_opted_in'              => 'You must opt in to federation before sending messages.',
    'fed_sender_messaging_disabled'        => 'You must enable federated messaging in your settings before sending messages.',
    'fed_recipient_not_found'              => 'Recipient not found.',
    'fed_messaging_disabled'               => 'This member does not accept federated messages.',
    'fed_no_partnership'                   => 'No active partnership with the recipient\'s community.',
    'fed_messaging_not_allowed'            => 'Messaging is not enabled for this partnership.',
    'fed_send_failed'                      => 'Failed to send message. Please try again.',
    'fed_message_not_found'                => 'Message not found.',
    'fed_mark_read_failed'                 => 'Failed to mark message as read.',
    'fed_settings_update_failed'           => 'Failed to update settings. Please try again.',
    'fed_receiver_ids_required'            => 'receiver_id and receiver_tenant_id are required.',
    'fed_must_opt_in_first'                => 'You must opt in to federation first.',
    'fed_transactions_not_enabled'         => 'You have not enabled federated transactions.',
    'fed_receiver_id_required'             => 'Receiver ID is required.',
    'fed_receiver_tenant_required'         => 'Receiver tenant ID is required.',
    'fed_amount_required'                  => 'Amount is required.',
    'fed_description_required'             => 'Description is required.',
    'fed_amount_range'                     => 'Amount must be between 1 and 100 whole hours.',
    'fed_no_self_transaction'              => 'Cannot send a transaction to yourself.',
    'fed_recipient_transactions_disabled'  => 'Recipient has not enabled federated transactions.',
    'fed_partnership_no_transactions'      => 'Partnership does not allow transactions.',
    'fed_insufficient_balance'             => 'Insufficient balance.',
    'fed_transaction_failed'               => 'Transaction failed. Please try again.',
    'fed_partner_no_transactions'          => 'This partner does not allow transactions.',
    'fed_external_partner_rejected'        => 'External partner rejected the transaction.',

    // ============================================
    // USER-FACING — USERS / PROFILE (additional)
    // ============================================
    'user_profile_incomplete'              => 'This member\'s profile is not yet complete',
    'user_theme_update_failed'             => 'Failed to update theme preference',
    'user_theme_prefs_update_failed'       => 'Failed to update theme preferences',
    'user_lang_update_failed'              => 'Failed to update language preference',
    'user_current_password_required'       => 'Current password is required',
    'user_new_password_required'           => 'New password is required',
    'user_password_required'               => 'Password is required',
    'user_invalid_password'                => 'Invalid password',
    'user_no_valid_prefs'                  => 'No valid preferences provided',
    'user_prefs_update_failed'             => 'Failed to update preferences',
    'user_consent_slug_required'           => 'Missing consent slug',
    'user_consent_update_failed'           => 'Failed to update consent preferences',
    'user_duplicate_request'               => 'A similar request is already pending',
    'user_request_failed'                  => 'Failed to submit request. Please try again.',
    'user_no_avatar_uploaded'              => 'No avatar file uploaded or upload error',

    // ============================================
    // USER-FACING — WEBAUTHN (additional)
    // ============================================
    'webauthn_invalid_credential'          => 'Invalid credential data',
    'webauthn_registration_failed'         => 'Passkey registration failed. Please try again.',
    'webauthn_invalid_assertion'           => 'Invalid assertion data',
    'webauthn_credential_not_found'        => 'Credential not found',
    'webauthn_auth_failed'                 => 'Passkey authentication failed. Please try again.',
    'webauthn_name_fields_required'        => 'credential_id and device_name are required',
    'webauthn_name_empty'                  => 'device_name cannot be empty',
    'webauthn_challenge_expired'           => 'Challenge expired or invalid',
    'webauthn_challenge_user_mismatch'     => 'Challenge user mismatch',
    'webauthn_challenge_invalid_type'      => 'Invalid challenge type',
    'webauthn_challenge_tenant_mismatch'   => 'Challenge tenant mismatch',
    'webauthn_challenge_expired_simple'    => 'Challenge expired',

    // ============================================
    // USER-FACING — LEGAL (additional)
    // ============================================
    'legal_doc_type_not_found'             => 'Document type not found',
    'legal_version_not_found'              => 'Version not found',
    'legal_versions_same_doc_required'     => 'Versions must belong to the same document',
    'legal_comparison_failed'              => 'Comparison failed',
    'legal_missing_doc_or_version'         => 'Missing document_id or version_id',
    'legal_not_current_version'            => 'This is not the current version',
    'legal_acceptance_failed'              => 'Failed to record acceptance',

    // ============================================
    // USER-FACING — GROUP EXCHANGES
    // ============================================
    'group_exchange_hours_gt_zero'         => 'Total hours must be greater than 0',
    'group_exchange_create_failed'         => 'Failed to create exchange',
    'group_exchange_organizer_update_only' => 'Only the organizer can update',
    'group_exchange_cannot_update_final'   => 'Cannot update a completed or cancelled exchange',
    'group_exchange_organizer_cancel_only' => 'Only the organizer can cancel',
    'group_exchange_user_role_required'    => 'user_id and role are required',
    'group_exchange_participant_failed'    => 'Failed to add participant (may already exist)',
    'group_exchange_organizer_complete_only' => 'Only the organizer can complete',

    // ============================================
    // USER-FACING — GOALS (additional)
    // ============================================
    'goal_private'                         => 'This goal is private',
    'goal_not_found_or_not_owned'          => 'Goal not found or not owned',
    'goal_increment_required'              => 'Increment value is required',
    'goal_buddy_conflict'                  => 'Cannot become buddy for this goal',
    'goal_template_not_found'              => 'Template not found',

    // ============================================
    // USER-FACING — VOLUNTEER WELLBEING (additional)
    // ============================================
    'vol_mood_range'                       => 'Mood must be between 1 and 5',
    'vol_checkin_save_failed'              => 'Failed to save check-in',
    'vol_response_accept_decline'          => 'Response must be accepted or declined',
    'vol_incident_not_found'               => 'Incident not found',
    'vol_incident_view_forbidden'          => 'You do not have permission to view this incident',
    'vol_dlp_user_required'                => 'dlp_user_id is required and must be a positive integer',
    'vol_training_not_found'               => 'Training record not found',
    'vol_training_reject_reason'           => 'A reason is required to reject a training record',

    // ============================================
    // USER-FACING — KNOWLEDGE BASE (additional)
    // ============================================
    'kb_article_not_found'                 => 'Article not found',
    'kb_search_query_required'             => 'Search query is required',
    'kb_cannot_delete_with_children'       => 'Cannot delete article with child articles',
    'kb_is_helpful_required'               => 'is_helpful field is required',

    // ============================================
    // USER-FACING — REGISTRATION POLICY (additional)
    // ============================================
    'reg_session_id_required'              => 'Session ID required',
    'reg_unknown_provider'                 => 'Unknown provider',
    'reg_credential_required'              => 'At least one credential field is required',
    'reg_invalid_expires_at'               => 'Invalid expires_at date',
    'reg_code_id_required'                 => 'Code ID required',
    'reg_invite_code_not_found'            => 'Invite code not found',
    'reg_verification_unavailable'         => 'Verification service is temporarily unavailable',
    'reg_invite_code_required'             => 'Invite code required',

    // ============================================
    // USER-FACING — POLLS (additional)
    // ============================================
    'poll_not_found_or_not_owned'          => 'Poll not found or not owned',
    'poll_already_voted'                   => 'Already voted on this poll',
    'poll_rankings_required'               => 'Rankings array is required',
    'poll_already_ranked'                  => 'Already submitted rankings',
    'poll_not_ranked_choice'               => 'This is not a ranked-choice poll',
    'poll_not_found_or_unauthorized'       => 'Poll not found or not authorized',

    // ============================================
    // ADDITIONAL KEYS — i18n conversion (2026-03-30)
    // ============================================

    // Auth
    'email_and_password_required'          => 'Email and password required',
    'too_many_login_attempts'              => 'Too many login attempts. Please try again later.',
    'too_many_attempts'                    => 'Too many attempts. Please try again later.',
    'invalid_credentials'                  => 'Invalid credentials',
    'account_suspended'                    => 'Account suspended',
    'not_authenticated'                    => 'Not authenticated',
    'unauthorized'                         => 'Unauthorized',
    'bearer_token_required'               => 'Bearer token required',
    'invalid_or_expired_token'             => 'Invalid or expired token',
    'invalid_or_expired_refresh_token'     => 'Invalid or expired refresh token',
    'invalid_token_type'                   => 'Invalid token type',
    'invalid_token_payload'                => 'Invalid token payload',
    'invalid_refresh_token_or_revoked'     => 'Invalid refresh token or already revoked',
    'refresh_token_required'               => 'Refresh token required',
    'missing_token'                        => 'Missing token',
    'token_required'                       => 'Token required',
    'invalid_csrf_token'                   => 'Invalid CSRF token',
    'no_pending_2fa_session'               => 'No pending 2FA session',
    'session_expired'                      => 'Session expired',
    'code_required'                        => 'Code is required',
    'auth_error'                           => 'Auth error',

    // Validation — generic
    'action_required'                      => 'Action is required',
    'role_required'                        => 'Role is required',
    'question_required'                    => 'Question is required',
    'answer_required'                      => 'Answer is required',
    'skill_name_required'                  => 'skill_name is required',
    'emoji_required'                       => 'Emoji is required',
    'increment_required'                   => 'Increment value is required',
    'name_max_length'                      => 'Name must be 255 characters or less',
    'title_and_body_required'              => 'Title and body are required',
    'query_params_must_be_object'          => 'query_params must be an object',
    'schedule_required_array'              => 'schedule is required and must be an array',
    'day_range_0_6'                        => 'day must be between 0-6',
    'preferences_array_required'           => 'preferences must be a non-empty array of {option_id, value}',
    'preference_option_id_required'        => 'preferences[:index].option_id is required',
    'target_type_and_id_required'          => 'target_type and target_id are required',
    'item_type_and_id_required'            => 'item_type and item_id are required',
    'invalid_item_type'                    => 'Invalid item_type',
    'user_id_and_role_required'            => 'user_id and role are required',
    'consent_id_or_type_required'          => 'consent_id or consent_type is required',
    'alt_text_must_be_string'              => 'alt_text must be a string',
    'media_ids_required'                   => 'media_ids must be a non-empty array',
    'missing_socket_or_channel'            => 'Missing socket_id or channel_name',
    'invalid_channel_type'                 => 'Invalid channel type',
    'missing_id_or_action'                 => 'Missing ID or Action',
    'missing_signature_headers'            => 'Missing signature headers',
    'empty_payload'                        => 'Empty payload',
    'invalid_json_payload'                 => 'Invalid JSON payload',
    'recipient_id_required_for_user'       => 'recipient_id is required when donating to a user',
    'at_least_one_policy_field_required'   => 'At least one policy field is required (e.g., max_amount, requires_receipt, auto_approve_below, description, enabled)',

    // Passwords
    'passwords_do_not_match'               => 'Passwords do not match',
    'invalid_password'                     => 'Invalid password',
    'invalid_reset_token'                  => 'Invalid or expired reset token. Please request a new password reset.',
    'unable_to_reset_password'             => 'Unable to reset password. Please request a new password reset.',

    // Verification
    'verification_token_required'          => 'Verification token is required',
    'invalid_verification_token'           => 'Invalid or expired verification token. Please request a new verification email.',
    'verification_resend_cooldown'         => 'Please wait at least 1 minute before requesting another verification email',

    // Resources — not found
    'connection_not_found'                 => 'Connection not found',
    'connection_not_pending'               => 'This connection request is not pending or you are not the receiver',
    'connection_request_not_found'         => 'Connection request not found',
    'consent_record_not_found'             => 'Consent record not found',
    'notification_not_found'               => 'Notification not found',
    'transaction_not_found'                => 'Transaction not found',
    'endorsement_not_found'                => 'Endorsement not found',
    'donation_not_found'                   => 'Donation not found',
    'resource_not_found'                   => 'Resource not found',
    'file_not_found'                       => 'File not found',
    'no_file_for_resource'                 => 'No file associated with this resource',
    'share_not_found'                      => 'Share not found',
    'saved_search_not_found'               => 'Saved search not found',
    'opportunity_not_found'                => 'Opportunity not found',
    'organization_not_found'               => 'Organization not found',
    'expense_not_found'                    => 'Expense not found',
    'expense_not_found_or_invalid'         => 'Expense not found or invalid status',
    'certificate_not_found'                => 'Certificate not found',
    'credential_not_found'                 => 'Credential not found',
    'sub_account_request_not_found'        => 'Sub-account request not found',
    'idea_not_found'                       => 'Idea not found',
    'parent_category_not_found'            => 'Parent category not found',

    // Content operations — forbidden
    'cannot_edit_comment'                  => 'Cannot edit this comment',
    'cannot_delete_comment'                => 'Cannot delete this comment',
    'cannot_endorse_member'                => 'Cannot endorse this member',
    'cannot_endorse_yourself'              => 'You cannot endorse yourself',
    'cannot_share_own_post'                => 'You cannot share your own post',
    'cannot_become_buddy'                  => 'Cannot become buddy for this goal',
    'cannot_update_completed_exchange'     => 'Cannot update a completed or cancelled exchange',
    'cannot_delete_category_with_children' => 'Cannot delete category with child categories',
    'private_group_members_only'           => 'You must be a member to view members of a private group',
    'not_review_author'                    => 'You did not author this review',
    'no_permission_delete_resource'        => 'You do not have permission to delete this resource',
    'no_permission_org_transfer'           => 'You do not have permission to transfer from this organization',
    'no_permission_verify_checkin'         => 'You do not have permission to verify check-ins for this shift',
    'no_permission_checkout'               => 'You do not have permission to check out volunteers for this shift',
    'no_permission_view_checkins'          => 'You do not have permission to view check-ins for this shift',
    'own_posts_media_only'                 => 'You can only add media to your own posts',
    'own_media_only'                       => 'You can only manage your own media',
    'organizer_only_update'                => 'Only the organizer can update',
    'organizer_only_cancel'                => 'Only the organizer can cancel',
    'organizer_only_complete'              => 'Only the organizer can complete',

    // Feed / social
    'invalid_post_id'                      => 'Invalid post ID',
    'invalid_target_type'                  => 'Invalid target type',
    'invalid_user'                         => 'Invalid user',
    'database_error'                       => 'Database error',
    'already_reported'                     => 'Already reported',
    'already_shared_post'                  => 'You have already shared this post',
    'already_applied'                      => 'You have already applied to this opportunity',
    'daily_reward_already_claimed'         => 'Daily reward already claimed today',
    'feedback_already_submitted'           => 'Feedback already submitted',
    'reaction_toggle_failed'               => 'Failed to toggle reaction',
    'failed_get_reactors'                  => 'Failed to get reactors',
    'invalid_reaction_type'                => 'Invalid reaction type',
    'invalid_presence_status'              => 'Invalid status. Must be one of: online, away, dnd, offline',

    // File uploads
    'file_required'                        => 'File is required',
    'file_exceeds_limit'                   => 'File exceeds 10MB limit',
    'file_type_not_allowed'                => 'File type not allowed',
    'file_type_blocked'                    => 'This file type is not allowed (HTML/SVG/PHP)',
    'upload_temp_file_not_found'           => 'Upload failed: temporary file not found. Please try again.',
    'no_image_uploaded'                    => 'No image file uploaded or upload error',
    'failed_upload_image'                  => 'Failed to upload image',
    'no_valid_file_provided'               => 'No valid file provided',
    'no_media_files_provided'              => 'No media files provided',
    'media_upload_failed'                  => 'No files were uploaded successfully. Maximum 10 images per post.',
    'no_audio_data_provided'               => 'No audio data provided',
    'audio_upload_failed'                  => 'Failed to upload audio file',
    'credential_file_required'             => 'A credential file is required',
    'credential_file_types'                => 'Only PDF, JPEG, PNG, and WebP files are allowed',
    'insurance_file_types'                 => 'Only PDF, JPG, and PNG files are accepted',
    'insurance_upload_failed'              => 'Failed to upload insurance certificate',

    // Onboarding
    'profile_photo_required'               => 'Profile photo is required to complete onboarding',
    'bio_required'                         => 'Bio is required to complete onboarding',
    'safeguarding_step_required'           => 'The safeguarding step must be completed before finishing onboarding',

    // Cookies / consent
    'failed_retrieve_cookie_inventory'     => 'Failed to retrieve cookie inventory',
    'failed_update_consent'                => 'Failed to update consent preferences',
    'failed_withdraw_consent'              => 'Failed to withdraw consent',

    // Contact / misc
    'no_contact_email_configured'          => 'No contact email configured for this community.',

    // Payments / wallet
    'payment_processing_failed'            => 'Payment processing failed. Please try again.',
    'refund_processing_failed'             => 'Refund processing failed. Please try again.',
    'insufficient_balance'                 => 'Insufficient balance for transfer',
    'transfer_failed'                      => 'Transfer failed',
    'purchase_failed'                      => 'Purchase failed',

    // Search
    'search_query_min_length'              => 'Search query must be at least 2 characters',
    'search_query_too_long'                => 'Search query is too long (max 500 characters).',
    'invalid_search_type'                  => 'Invalid type. Must be one of: :types',

    // Federation
    'federation_not_available'             => 'Federation is not enabled for your community.',
    'federation_opt_in_failed'             => 'Failed to enable federation. Please try again.',
    'federation_opt_out_failed'            => 'Failed to disable federation. Please try again.',

    // GDPR
    'invalid_gdpr_request_type'            => 'Invalid request type. Valid types: data_export, data_portability, data_rectification, data_access',
    'gdpr_request_failed'                  => 'Failed to submit GDPR request. Please try again.',
    'account_deletion_failed'              => 'Failed to submit account deletion request. Please try again.',

    // Goals
    'goal_is_private'                      => 'This goal is private',

    // Explore / sidebar
    'failed_dismiss_item'                  => 'Failed to dismiss item',
    'failed_load_community_stats'          => 'Failed to load community stats',
    'failed_load_suggestions'              => 'Failed to load suggestions',
    'failed_save_match_preferences'        => 'Failed to save match preferences',
    'failed_reorder_resources'             => 'Failed to reorder resources',
    'failed_add_participant'               => 'Failed to add participant (may already exist)',

    // Webhooks / Stripe
    'webhook_signature_failed'             => 'Webhook signature verification failed.',
    'webhook_internal_error'               => 'Internal error processing webhook.',
    'webhook_handler_error'                => 'Error processing webhook event.',
    'invalid_webhook_signature'            => 'Invalid webhook signature',
    'invalid_webhook_secret'               => 'Invalid webhook secret',
    'webhook_auth_not_configured'          => 'Webhook authentication not configured',

    // Tenant
    'community_not_found_or_inactive'      => 'The requested community was not found or is inactive.',
    'tenant_inactive'                      => 'Tenant is inactive',

    // Feature gates
    'ideation_feature_disabled'            => 'Ideation Challenges module is not enabled for this community',
    'job_vacancies_feature_disabled'       => 'Job Vacancies module is not enabled for this community',
    'volunteering_feature_disabled'        => 'Volunteering module is not enabled for this community',

    // Volunteer check-in
    'no_checkin_available'                 => 'No check-in available for this shift',
    'invalid_checkin_code'                 => 'Invalid check-in code',
    'checkin_not_found_or_completed'       => 'Check-in not found or already completed',
    'action_must_be_approve_or_decline'    => 'Action must be approve or decline',
    'type_must_be_org_or_user'             => 'Type must be organization or user',

    // OpenAPI
    'openapi_spec_not_found'               => 'OpenAPI specification not found',
    'openapi_parse_failed'                 => 'Failed to parse OpenAPI specification',
    'api_docs_disabled_production'         => 'API documentation is disabled in production',

    // Push / realtime
    'device_registration_failed'           => 'Failed to register device',

    // Newsletter
    'unsubscribe_token_required'           => 'Unsubscribe token is required.',
    'invalid_unsubscribe_link'             => 'This unsubscribe link is invalid or has already been used.',
    'unable_to_process_request'            => 'Unable to process your request. Please try again.',

    // URL / Link preview
    'url_required'                         => 'URL is required',
    'invalid_url'                          => 'Invalid URL',
    'url_http_https_only'                  => 'Only http and https URLs are supported',
    'link_preview_failed'                  => 'Could not fetch preview for this URL',

    // Group exchanges
    'total_hours_gt_zero'                  => 'Total hours must be greater than 0',

    // Server errors (hardcoded-string audit — 2026-04-14)
    'unexpected_error'                     => 'An unexpected error occurred.',
    'access_denied'                        => 'Access denied',

    // Comment errors (hardcoded-string audit — 2026-04-14)
    'comment_cannot_be_empty'              => 'Comment cannot be empty',
    'parent_comment_not_found'             => 'Parent comment not found',
    'comment_unauthorized'                 => 'Unauthorized',

    // Community fund errors (hardcoded-string audit — 2026-04-14)
    'amount_must_be_greater_than_0'        => 'Amount must be greater than 0',
    'community_fund_not_found'             => 'Community fund not found',
    'insufficient_community_fund_balance'  => 'Insufficient community fund balance',
    'deposit_failed'                       => 'Deposit failed',
    'withdrawal_failed'                    => 'Withdrawal failed',
    'donation_failed'                      => 'Donation failed',

    // Maintenance mode (hardcoded-string audit — 2026-04-14)
    'maintenance_mode'                     => 'Platform is currently under maintenance. Please check back soon.',

    // Auth rate limit with seconds (hardcoded-string audit — 2026-04-14)
    'too_many_login_attempts_seconds'      => 'Too many login attempts. Try again in :seconds seconds.',

    // Federation API errors (hardcoded-string audit — 2026-04-13)
    'federation' => [
        'partner_not_found'          => 'Partner not found',
        'feature_disabled'           => 'Federation feature disabled for this tenant',
        'webhook_rate_limited'       => 'Too many requests',
        'webhook_empty_body'         => 'Empty request body',
        'webhook_auth_failed'        => 'Invalid API key or webhook signature',
        'webhook_invalid_nonce'      => 'Nonce must be 8-128 chars',
        'webhook_invalid_json'       => 'Invalid JSON',
        'webhook_missing_event'      => 'Missing event type',
        'webhook_partner_inactive'   => 'Partner is not active',
    ],

    // Legal acceptance (hardcoded-string audit — 2026-04-14)
    'legal' => [
        'no_documents_require_acceptance' => 'No documents require acceptance',
        'all_documents_accepted'          => 'All documents accepted',
    ],

    // Onboarding (hardcoded-string audit — 2026-04-14)
    'onboarding' => [
        'complete' => 'Onboarding complete!',
    ],

    // Users (hardcoded-string audit — 2026-04-14)
    'users' => [
        'theme_updated'          => 'Theme preferences updated',
        'language_updated'       => 'Language preference updated',
        'gdpr_request_submitted' => 'Your request has been submitted and will be processed within 30 days.',
    ],

    // Generic validation & error messages (hardcoded-string audit — 2026-04-20)
    'amount_invalid'           => 'Invalid amount',
    'amount_out_of_range'      => 'Amount is out of range',
    'failed_to_read_file'      => 'Failed to read the uploaded file',
    'feature_disabled'         => 'This feature is not enabled for your community',
    'invalid_file_upload'      => 'Invalid file upload',
    'invalid_input'            => 'Invalid input',
    'invalid_json'             => 'Invalid JSON',
    'invalid_menu_item_type'   => 'Invalid menu item type',
    'partnership_reactivated'  => 'Partnership reactivated',
    'purge_failed'             => 'Purge operation failed',
    'value_out_of_range'       => 'Value must be between :min and :max',

    // ============================================
    // SERVICE-LAYER i18n CONVERSION (2026-04-14)
    // EventService, GroupService, IdeationChallengeService
    // ============================================

    // EventService — authorization
    'event_edit_forbidden'              => 'You do not have permission to edit this event',
    'event_delete_forbidden'            => 'Only the event organizer can delete this event',
    'event_modify_forbidden'            => 'You do not have permission to modify this event',
    'event_cancel_forbidden'            => 'You do not have permission to cancel this event',
    'event_attendance_forbidden'        => 'Only the organizer or admin can mark attendance',

    // EventService — validation
    'event_title_max_255'               => 'Title must not exceed 255 characters',
    'event_invalid_start_time'          => 'Invalid start_time format',
    'event_end_after_start'             => 'End time must be after start time',
    'event_invalid_rsvp_status'         => 'Invalid RSVP status',
    'event_already_cancelled'           => 'This event is already cancelled',
    'event_recurrence_frequency_required' => 'Valid recurrence frequency is required',

    // EventService — server errors
    'event_rsvp_update_failed'          => 'Failed to update RSVP',
    'event_rsvp_remove_failed'          => 'Failed to remove RSVP',
    'event_image_update_failed'         => 'Failed to update image',
    'event_cancel_failed'               => 'Failed to cancel event',
    'event_mark_attendance_failed'      => 'Failed to mark attendance',
    'event_series_create_failed'        => 'Failed to create series',
    'event_series_link_failed'          => 'Failed to link event to series',
    'event_recurring_create_failed'     => 'Failed to create recurring event',

    // GroupService — authorization
    'group_edit_forbidden'              => 'You do not have permission to edit this group',
    'group_delete_forbidden'            => 'Only the group owner can delete this group',
    'group_manage_members_forbidden'    => 'You do not have permission to manage members',
    'group_remove_members_forbidden'    => 'You do not have permission to remove members',
    'group_cannot_change_owner_role'    => 'Cannot change the owner\'s role',
    'group_cannot_remove_owner'         => 'Cannot remove the group owner',
    'group_view_join_requests_forbidden' => 'You do not have permission to view join requests',
    'group_handle_join_requests_forbidden' => 'You do not have permission to handle join requests',
    'group_member_required_view_discussions' => 'You must be a member to view discussions',
    'group_member_required_create_discussions' => 'You must be a member to create discussions',
    'group_member_required_post'        => 'You must be a member to post',
    'group_modify_forbidden'            => 'You do not have permission to modify this group',

    // GroupService — validation
    'group_name_max_255'                => 'Name must not exceed 255 characters',
    'group_visibility_invalid'          => 'Visibility must be public or private',
    'group_user_not_member'             => 'User is not a member of this group',
    'group_use_leave_endpoint'          => 'Use leave endpoint to remove yourself',
    'group_action_accept_or_reject'     => 'Action must be accept or reject',
    'group_content_required'            => 'Content is required',
    'group_discussion_not_found'        => 'Discussion not found',
    'group_banned'                      => 'You have been banned from this group',
    'group_already_member'              => 'Already a member or request pending',

    // IdeationChallengeService — authorization
    'challenge_admin_only_update'       => 'Only admins can update challenges',
    'challenge_admin_only_delete'       => 'Only admins can delete challenges',
    'challenge_admin_only_status'       => 'Only admins can change challenge status',
    'challenge_admin_only_duplicate'    => 'Only admins can duplicate challenges',
    'idea_admin_only_status'            => 'Only admins can change idea status',
    'idea_edit_own_only'                => 'You can only edit your own ideas',
    'idea_delete_own_only'              => 'You can only delete your own ideas',
    'comment_delete_own_only'           => 'You can only delete your own comments',

    // IdeationChallengeService — validation / conflict
    'challenge_invalid_transition'      => 'Cannot transition from \':from\' to \':to\'',
    'challenge_closed_for_edits'        => 'Challenge is no longer open for edits',
    'challenge_voting_not_allowed'      => 'Voting is not currently allowed for this challenge',
    'idea_only_draft_editable'          => 'Only draft ideas can be edited',
    'idea_vote_withdrawn_or_draft'      => 'Cannot vote on a withdrawn or draft idea',
    'idea_cannot_vote_own'              => 'You cannot vote on your own idea',
    'idea_comment_withdrawn_or_draft'   => 'Cannot comment on a withdrawn or draft idea',
    'description_cannot_be_empty'       => 'Description cannot be empty',
    'description_required'              => 'Description is required',
    'comment_body_required'             => 'Comment body is required',

    // IdeationChallengeService — server errors
    'idea_draft_update_failed'          => 'Failed to update draft',
    'idea_delete_failed'                => 'Failed to delete idea',
    'idea_vote_toggle_failed'           => 'Failed to toggle vote',
    'comment_add_failed'                => 'Failed to add comment',
    'comment_delete_failed'             => 'Failed to delete comment',
    'challenge_duplicate_failed'        => 'Failed to duplicate challenge',

    // Wallet / balance
    'balance_adjustment_negative'       => 'Adjustment would result in negative balance',
    'organizer_insufficient_balance'    => 'Organizer has insufficient time credit balance to award the requested hours',

    // Internal / tenant errors
    'tenant_mismatch_error'             => 'Tenant mismatch — operation skipped',
    'user_missing_tenant_id'            => 'User has no tenant_id — cannot resolve tenant',
    'invalid_table_parameter'           => 'Invalid table parameter provided',

    // GroupsController join responses
    'group_joined'                      => 'Successfully joined the group',
    'group_join_requested'              => 'Join request submitted',

    // BlockUserService
    'cannot_block_yourself'             => 'You cannot block yourself',

    // ConnectionService
    'cannot_connect_with_yourself'      => 'You cannot connect with yourself',
    'cannot_send_request_to_user'       => 'Cannot send connection request to this user',
    'connection_already_exists'         => 'A connection with this user already exists',
    'only_receiver_can_accept'          => 'Only the receiver can accept a connection request',

    // BookmarkService
    'invalid_bookmarkable_type'         => 'Invalid bookmarkable type provided',

    // ShareService
    'invalid_shareable_type'            => 'Invalid shareable type provided',

    // PollsController / CommentsController
    'too_many_poll_options'             => 'Too many poll options (max 20).',
    'comment_too_long'                  => 'Comment is too long (max 10,000 characters).',

    // ListingService — sdg_goals validation
    'sdg_goals_must_be_array'           => 'sdg_goals must be an array.',
    'sdg_goals_max'                     => 'You can select at most 17 SDG goals.',
    'sdg_goals_invalid'                 => 'Invalid SDG goal value. Goals must be integers between 1 and 17.',

    'invalid_reaction'                            => 'Invalid reaction emoji.',

    'story_not_found'                            => 'Story not found.',

    'story_expired'                            => 'Story has expired.',

    // Billing delegates
    'invalid_delegate_scope'                     => 'Invalid delegate scope. Allowed: view_billing, edit_own_price, manage_children.',

    // Billing grace period
    'grace_period_days_invalid'                  => 'Days must be between 1 and 365.',

    // Caring Community workflow policy
    'caring_self_log_disabled'                   => 'Member self-logging is disabled for this community. A coordinator must record or approve these hours.',
    'municipal_report_template_name_required'    => 'Report template name is required.',
    'municipal_report_template_exists'           => 'A report template with this name already exists.',
    'municipal_report_template_not_found'        => 'Report template not found.',
    'municipal_report_template_created'          => 'Report template saved.',
    'municipal_report_template_updated'          => 'Report template updated.',
    'municipal_report_template_deleted'          => 'Report template deleted.',
    'caring_review_assignment_failed'            => 'Review could not be assigned.',
    'caring_review_assigned'                     => 'Review assignment updated.',
    'caring_review_escalation_failed'            => 'Review could not be escalated.',
    'caring_review_escalated'                    => 'Review escalated.',
    'caring_review_decision_failed'              => 'Review decision could not be applied.',
    'caring_review_approved'                     => 'Review approved.',
    'caring_review_declined'                     => 'Review declined.',
    'caring_review_payment_description'          => 'Auto-payment for :hours h of approved support',
    'caring_member_statement_unknown_partner'    => 'Unassigned partner',
    'caring_member_statement_csv_date'           => 'Date',
    'caring_member_statement_csv_type'           => 'Type',
    'caring_member_statement_csv_partner'        => 'Partner',
    'caring_member_statement_csv_description'    => 'Description',
    'caring_member_statement_csv_hours'          => 'Hours',
    'caring_member_statement_csv_status'         => 'Status',
    'caring_member_statement_csv_support_hours'  => 'Support hours',
    'caring_support_relationship_default_title'  => 'Recurring support relationship',
    'caring_support_relationship_create_failed'  => 'Support relationship could not be created.',
    'caring_support_relationship_not_found'      => 'Support relationship not found.',
    'caring_support_relationship_inactive'       => 'Support relationship is not active.',
    'caring_support_relationship_log_duplicate'  => 'Hours have already been logged for this relationship and date.',
    'caring_support_relationship_log_failed'     => 'Support relationship hours could not be logged.',
    'caring_support_relationship_payment_description' => 'Auto-payment for :hours h of recurring support',
    'caring_regional_points_disabled'          => 'Regional points are not enabled for this community.',
    'caring_regional_points_positive'          => 'Points must be greater than zero.',
    'caring_regional_points_nonzero'           => 'Point adjustment must not be zero.',
    'caring_regional_points_too_many'          => 'Point amount exceeds the maximum allowed value.',
    'caring_regional_points_insufficient'      => 'Not enough regional points.',
    'caring_regional_points_hours_award'       => 'Regional points earned for :hours approved support hours.',
    'caring_regional_points_transfers_disabled' => 'Regional point transfers are not enabled for this community.',
    'caring_regional_points_transfer_self'     => 'You cannot transfer regional points to yourself.',
    'caring_regional_points_member_transfer'   => 'Regional points member transfer.',
    'caring_regional_points_marketplace_disabled' => 'Regional point marketplace redemptions are not enabled for this community.',
    'caring_regional_points_marketplace_unavailable' => 'Regional point marketplace redemptions are not available for this community.',
    'caring_regional_points_marketplace_self' => 'You cannot redeem regional points at your own listing.',
    'caring_regional_points_order_total_positive' => 'Order total must be greater than zero.',
    'caring_regional_points_redemption_too_many' => 'Regional point redemption exceeds the maximum allowed value.',
    'caring_regional_points_listing_unavailable' => 'Marketplace listing is not available for this merchant.',
    'caring_regional_points_merchant_disabled' => 'This merchant is not accepting regional points.',
    'caring_regional_points_merchant_invalid' => 'Merchant regional point settings are invalid.',
    'caring_regional_points_discount_too_large' => 'Regional point discount exceeds the maximum allowed for this order.',
    'caring_regional_points_marketplace_redemption' => 'Regional points marketplace redemption.',
    'caring_regional_points_per_chf_invalid' => 'Regional points per CHF must be greater than zero.',
    'caring_regional_points_discount_pct_invalid' => 'Regional point maximum discount must be between 1 and 100 percent.',
    'verein_not_found'                         => 'Verein not found.',
    'verein_import_unavailable'                => 'Verein member import is not available for this community.',
    'verein_import_parse_failed'               => 'Could not parse the member import CSV.',
    'verein_import_missing_header'             => 'Member import CSV is missing the required :header column.',
    'verein_import_too_many_rows'              => 'Member import CSV is limited to 500 rows.',
    'verein_import_duplicate_in_file'          => 'This email appears more than once in the import file.',
    'verein_import_already_member'             => 'This member already belongs to the Verein.',
    'verein_import_has_errors'                 => 'Please fix import preview errors before importing members.',
    'verein_admin_role_unavailable'            => 'The Verein admin role is not installed.',
    'caring_nudge_notification'                => ':name could be a good neighbour-help match for you. Take a look when you have a moment.',
    'caring_project_tables_unavailable'        => 'Project announcements are not available for this community.',
    'caring_project_not_found'                 => 'Project announcement not found.',
    'caring_project_update_not_found'          => 'Project update not found.',
    'caring_project_title_required'            => 'Project title is required.',
    'caring_project_invalid_status'            => 'Project status is not valid.',
    'caring_project_update_notification'       => ':project has a new update: :update',
    'caring_cover_unavailable'                 => 'Cover-care requests are not available for this community.',
    'caring_cover_link_required'               => 'You need an active caregiver link before requesting cover.',
    'caring_cover_dates_required'              => 'Cover start and end dates are required.',
    'caring_cover_dates_invalid'               => 'Cover end date must be after the start date.',
    'caring_cover_not_found'                   => 'Cover-care request not found.',
    'caring_cover_candidate_invalid'           => 'This substitute is not available for the selected cover request.',
    'municipal_pdf_title'                        => 'Municipal / KISS Impact Pack',
    'municipal_pdf_generated'                    => 'Generated: :date',
    'municipal_pdf_audience'                     => 'Audience: :audience',
    'municipal_pdf_template'                     => 'Template: :template',
    'municipal_pdf_default_template'             => 'Workflow policy defaults',
    'municipal_pdf_period'                       => 'Reporting period: :period',
    'municipal_pdf_sections'                     => 'Sections: :sections',
    'municipal_pdf_executive_summary'            => 'Executive summary',
    'municipal_pdf_summary_line'                 => ':hours verified hours, :members participating members, :organisations trusted organisations, and :value estimated total value.',
    'municipal_pdf_core_metrics'                 => 'Core metrics',
    'municipal_pdf_metric_verified_hours'        => 'Verified hours: :value',
    'municipal_pdf_metric_volunteer_hours'       => 'Approved volunteering hours: :value',
    'municipal_pdf_metric_timebank_hours'        => 'Completed timebank hours: :value',
    'municipal_pdf_metric_pending_hours'         => 'Pending review hours: :value',
    'municipal_pdf_metric_active_members'        => 'Active approved members: :value',
    'municipal_pdf_metric_new_members'           => 'New approved members: :value',
    'municipal_pdf_metric_support_requests'      => 'Active support requests: :value',
    'municipal_pdf_metric_support_offers'        => 'Active support offers: :value',
    'municipal_pdf_metric_direct_value'          => 'Direct value estimate: :value',
    'municipal_pdf_metric_social_value'          => 'Social value estimate: :value',
    'municipal_pdf_readiness'                    => 'Procurement readiness signals',
    'municipal_pdf_readiness_line'               => ':label: :status (:value)',
    'municipal_pdf_status_ready'                 => 'ready',
    'municipal_pdf_status_needs_data'            => 'needs more live data',
    'municipal_pdf_signal_municipal_value'       => 'Municipal value evidence',
    'municipal_pdf_signal_participation'         => 'Participation evidence',
    'municipal_pdf_signal_partner_network'       => 'Partner network evidence',
    'municipal_pdf_signal_local_exchange'        => 'Local exchange evidence',
    'municipal_pdf_categories'                   => 'Support categories',
    'municipal_pdf_category_line'                => ':name: :hours hours across :count activities',
    'municipal_pdf_trends'                       => 'Recent trend',
    'municipal_pdf_trend_line'                   => ':period: :hours hours, :participants participants, :activities activities',
    'municipal_verification_unavailable'         => 'Municipal verification is not available for this community.',
    'municipal_verification_not_found'           => 'Municipal verification record not found.',
    'municipal_verification_dns_started'         => 'Municipal DNS verification has been started.',
    'municipal_verification_attested'            => 'Municipal verification has been attested.',
    'municipal_verification_revoked'             => 'Municipal verification has been revoked.',
    'municipal_sroi_formula_verified_hours'      => 'Approved volunteer hours plus completed timebank hours in the reporting period.',
    'municipal_sroi_formula_direct_value'        => 'Verified hours multiplied by :currency :hour_value per hour.',
    'municipal_sroi_formula_social_value'        => 'Direct value multiplied by the configured social multiplier (:multiplier).',
    'municipal_sroi_formula_social_value_disabled' => 'Social value estimate is disabled for this report.',
    'municipal_sroi_formula_total_value'         => 'Direct value plus social value when the estimate is enabled.',
    'municipal_sroi_input_approved_hours'        => 'Approved volunteering hours',
    'municipal_sroi_input_timebank_hours'        => 'Completed timebank hours',
    'municipal_sroi_input_hour_value'            => 'Configured hourly value',
    'municipal_sroi_input_multiplier'            => 'Configured social value multiplier',
    'municipal_sroi_assumption_verified_only'    => 'Only approved volunteer logs and completed timebank transactions are counted.',
    'municipal_sroi_assumption_no_cash_transfer' => 'The estimate is a reporting model, not proof of cash transferred or budget saved.',
    'municipal_sroi_assumption_multiplier_configurable' => 'The multiplier is tenant-configurable and should be reviewed against local methodology.',
    'municipal_sroi_caveat'                      => 'SROI figures are indicative and should be presented with the selected methodology, inputs, and reporting period.',

    // Audience-specific narrative section labels
    'municipal_pdf_audience_section_canton'       => 'Canton-level narrative',
    'municipal_pdf_audience_section_municipality' => 'Municipality narrative',
    'municipal_pdf_audience_section_cooperative'  => 'Cooperative internal narrative',
    'municipal_pdf_audience_section_foundation'   => 'Foundation oversight narrative',

    // Two-column metric labels for the executive summary block
    'municipal_pdf_metric_verified_hours_label'         => 'Verified hours',
    'municipal_pdf_metric_volunteer_hours_label'        => 'Volunteer hours',
    'municipal_pdf_metric_timebank_hours_label'         => 'Timebank hours',
    'municipal_pdf_metric_pending_hours_label'          => 'Pending hours',
    'municipal_pdf_metric_active_members_label'         => 'Active members',
    'municipal_pdf_metric_new_members_label'            => 'New members',
    'municipal_pdf_metric_participating_members_label'  => 'Participants',
    'municipal_pdf_metric_trusted_organisations_label'  => 'Partner orgs',
    'municipal_pdf_metric_direct_value_label'           => 'Direct value',
    'municipal_pdf_metric_total_value_label'            => 'Total value',

    // Canton variant lines
    'municipal_pdf_canton_municipalities'  => 'Aggregate municipalities reporting: :count',
    'municipal_pdf_canton_total_hours'     => 'Multi-node total verified hours: :value',
    'municipal_pdf_canton_cost_avoidance'  => 'Estimated professional-care cost avoidance: :value',
    'municipal_pdf_canton_yoy'             => 'Year-over-year change: :value (prior period :prior hours)',

    // Municipality variant lines
    'municipal_pdf_muni_partners'    => 'Partner organisations active in period: :count',
    'municipal_pdf_muni_recipients'  => 'Distinct recipients reached: :count',
    'municipal_pdf_muni_geographic'  => 'Top categories (geographic / activity split):',

    // Cooperative variant lines
    'municipal_pdf_coop_retention'    => 'Member retention vs prior period: :rate (:count members)',
    'municipal_pdf_coop_reciprocity'  => 'Helper/recipient reciprocity: :rate (:count members both gave and received)',
    'municipal_pdf_coop_tandems'      => 'Active tandem relationships: :count',
    'municipal_pdf_coop_load'         => 'Coordinator load: :avg pending reviews each (:pending pending / :coordinators coordinators)',
    'municipal_pdf_coop_pool'         => 'Future-care credit balance pool: :value hours',

    // Misc
    'municipal_pdf_value_na'  => 'n/a',
    'municipal_pdf_footer'    => 'Generated by Project NEXUS on :date - sections: :sections',
];
