-- Seed Mobile Tab Bar Menu for Menu Manager
-- This creates a dedicated menu for the bottom mobile tab bar (iOS/Android style)
-- Date: 2026-01-10

-- Create Mobile Tab Bar Menu
INSERT INTO `menus` (`tenant_id`, `name`, `slug`, `location`, `layout`, `is_active`, `created_at`)
VALUES (1, 'Mobile Tab Bar', 'mobile-tab-bar', 'mobile-tabs', 'modern', 1, NOW());

-- Get the menu ID
SET @mobile_tab_menu_id = LAST_INSERT_ID();

-- Tab 1: Home
INSERT INTO `menu_items` (`menu_id`, `type`, `label`, `url`, `icon`, `css_class`, `target`, `sort_order`, `visibility_rules`, `is_active`, `created_at`)
VALUES (@mobile_tab_menu_id, 'link', 'Home', '/', 'fa-solid fa-house', 'mobile-tab-item', '_self', 1, '{}', 1, NOW());

-- Tab 2: Listings
INSERT INTO `menu_items` (`menu_id`, `type`, `label`, `url`, `icon`, `css_class`, `target`, `sort_order`, `visibility_rules`, `is_active`, `created_at`)
VALUES (@mobile_tab_menu_id, 'link', 'Listings', '/listings', 'fa-solid fa-hand-holding-heart', 'mobile-tab-item', '_self', 2, '{}', 1, NOW());

-- Tab 3: Create (Center Tab)
INSERT INTO `menu_items` (`menu_id`, `type`, `label`, `url`, `icon`, `css_class`, `target`, `sort_order`, `visibility_rules`, `is_active`, `created_at`)
VALUES (@mobile_tab_menu_id, 'link', 'Create', '/compose', 'fa-solid fa-plus-circle', 'mobile-tab-item create-tab', '_self', 3, '{"requires_auth": true}', 1, NOW());

-- Tab 4: Messages
INSERT INTO `menu_items` (`menu_id`, `type`, `label`, `url`, `icon`, `css_class`, `target`, `sort_order`, `visibility_rules`, `is_active`, `created_at`)
VALUES (@mobile_tab_menu_id, 'link', 'Messages', '/messages', 'fa-solid fa-comment-dots', 'mobile-tab-item', '_self', 4, '{"requires_auth": true}', 1, NOW());

-- Tab 5: Profile
INSERT INTO `menu_items` (`menu_id`, `type`, `label`, `url`, `icon`, `css_class`, `target`, `sort_order`, `visibility_rules`, `is_active`, `created_at`)
VALUES (@mobile_tab_menu_id, 'link', 'Profile', '/dashboard', 'fa-solid fa-user', 'mobile-tab-item', '_self', 5, '{"requires_auth": true}', 1, NOW());

-- Success message
SELECT CONCAT('Mobile Tab Bar menu created with ID: ', @mobile_tab_menu_id) as 'Result';
SELECT COUNT(*) as 'Tab Items Created' FROM menu_items WHERE menu_id = @mobile_tab_menu_id;
