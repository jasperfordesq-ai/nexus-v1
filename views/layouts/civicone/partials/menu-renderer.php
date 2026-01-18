<?php
/**
 * Menu Renderer Helper Functions
 * Provides helper functions to render menus from MenuManager
 */

/**
 * Render a navigation link
 */
function renderMenuNavLink($item, $basePath = '')
{
    $url = $item['url'];
    $label = htmlspecialchars($item['label']);
    $icon = $item['icon'] ?? '';
    $cssClass = $item['css_class'] ?? 'nav-link';
    $target = $item['target'] ?? '_self';

    // Build data-nav-match attribute for active state highlighting
    $navMatch = '';
    if (preg_match('#^' . preg_quote($basePath, '#') . '/([^/]+)#', $url, $matches)) {
        $navMatch = ' data-nav-match="' . htmlspecialchars($matches[1]) . '"';
    } elseif ($url === $basePath || $url === $basePath . '/' || $url === '/') {
        $navMatch = ' data-nav-match="/"';
    }

    echo '<a href="' . htmlspecialchars($url) . '" class="' . htmlspecialchars($cssClass) . '" target="' . htmlspecialchars($target) . '"' . $navMatch . '>';
    if ($icon) {
        echo '<i class="' . htmlspecialchars($icon) . '"></i> ';
    }
    echo $label;
    echo '</a>';
}

/**
 * Render a dropdown menu with children
 */
function renderMenuDropdown($item, $basePath = '')
{
    $label = htmlspecialchars($item['label']);
    $icon = $item['icon'] ?? '';
    $children = $item['children'] ?? [];

    if (empty($children)) {
        return;
    }

    echo '<div class="htb-dropdown premium-dropdown">';
    echo '<a href="#" class="nav-link">';
    if ($icon) {
        echo '<i class="' . htmlspecialchars($icon) . '"></i> ';
    }
    echo $label . ' ▾';
    echo '</a>';
    echo '<div class="htb-dropdown-content" style="min-width: 220px;">';

    foreach ($children as $child) {
        $childUrl = $child['url'];
        $childLabel = htmlspecialchars($child['label']);
        $childIcon = $child['icon'] ?? '';
        $childTarget = $child['target'] ?? '_self';
        $separator = $child['separator_before'] ?? false;
        $highlight = $child['highlight'] ?? false;

        // Render separator if specified
        if ($separator) {
            echo '<div style="border-top: 1px solid #e5e7eb; margin: 5px 0;"></div>';
        }

        // Use color from menu item if provided, otherwise fallback to label-based mapping
        $iconColor = $child['color'] ?? '';

        // Fallback to label-based colors if not provided in menu data
        if (!$iconColor) {
            switch (strtolower($child['label'])) {
                case 'events':
                    $iconColor = '#8b5cf6';
                    break;
                case 'polls':
                    $iconColor = '#06b6d4';
                    break;
                case 'goals':
                    $iconColor = '#f59e0b';
                    break;
                case 'resources':
                    $iconColor = '#10b981';
                    break;
                case 'smart matches':
                case 'smart matching':
                    $iconColor = '#ec4899';
                    break;
                case 'leaderboards':
                    $iconColor = '#f59e0b';
                    break;
                case 'achievements':
                    $iconColor = '#a855f7';
                    break;
                case 'ai assistant':
                    $iconColor = '#6366f1';
                    break;
            }
        }

        // Build link style
        $linkStyle = '';
        if ($highlight) {
            $linkStyle = ' style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1)); font-weight:600;"';
        } elseif (strtolower($child['label']) === 'get app') {
            $linkStyle = ' style="color:var(--civic-brand-primary); font-weight:700;"';
        }

        echo '<a href="' . htmlspecialchars($childUrl) . '" target="' . htmlspecialchars($childTarget) . '"' . $linkStyle . '>';
        if ($childIcon) {
            $iconStyle = 'margin-right:10px; width:16px; text-align:center;';
            if ($iconColor) {
                $iconStyle .= ' color:' . $iconColor . ';';
            }
            echo '<i class="' . htmlspecialchars($childIcon) . '" style="' . $iconStyle . '"></i>';
        }
        echo $childLabel;
        echo '</a>';
    }

    echo '</div>';
    echo '</div>';
}

/**
 * Render menu items (auto-detects type)
 */
function renderMenuItems($items, $basePath = '')
{
    foreach ($items as $item) {
        if ($item['type'] === 'dropdown' && !empty($item['children'])) {
            renderMenuDropdown($item, $basePath);
        } elseif ($item['type'] === 'divider') {
            echo '<hr class="menu-divider">';
        } else {
            renderMenuNavLink($item, $basePath);
        }
    }
}

/**
 * Render utility bar item (special styling for admin/sign out)
 */
function renderUtilityItem($item, $basePath = '')
{
    $url = $item['url'];
    $label = htmlspecialchars($item['label']);
    $icon = $item['icon'] ?? '';
    $target = $item['target'] ?? '_self';

    // Resolve {user_id} placeholder
    if (strpos($url, '{user_id}') !== false) {
        $url = str_replace('{user_id}', $_SESSION['user_id'] ?? '', $url);
    }

    // Determine color based on label/type
    $color = 'white';
    if (stripos($label, 'admin') !== false) {
        $color = 'orange';
    } elseif (stripos($label, 'sign out') !== false || stripos($label, 'logout') !== false) {
        $color = '#ff6b6b';
    }

    echo '<a href="' . htmlspecialchars($url) . '" target="' . htmlspecialchars($target) . '" style="color: ' . htmlspecialchars($color) . '; text-decoration: none; font-weight: 500; font-size: 0.95rem;">';
    if ($icon) {
        echo '<i class="' . htmlspecialchars($icon) . '"></i> ';
    }
    echo $label;
    echo '</a>';
}
