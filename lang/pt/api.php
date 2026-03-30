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
    'user_ids_required'            => 'User IDs array is required',
    'campaign_name_required'       => 'Campaign name is required',
    'feature_name_required'        => 'Feature name is required',
    'module_name_required'         => 'Module name is required',
    'enabled_required'             => 'Enabled value is required',
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
];
