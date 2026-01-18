const fs = require('fs');
const path = require('path');

const jsDir = path.join(__dirname, '../httpdocs/assets/js');
const viewsDir = path.join(__dirname, '../views');
const classes = new Set();

// Patterns to find dynamic classes
const patterns = [
    /classList\.(?:add|remove|toggle)\(['"]([^'"]+)['"]/g,
    /className\s*[+=]\s*['"]([^'"]+)['"]/g,
    /\.addClass\(['"]([^'"]+)['"]/g,
    /\.removeClass\(['"]([^'"]+)['"]/g,
    /\.toggleClass\(['"]([^'"]+)['"]/g,
];

// Scan JS files
function scanDir(dir, ext) {
    if (!fs.existsSync(dir)) return;

    fs.readdirSync(dir, { withFileTypes: true }).forEach(entry => {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            scanDir(fullPath, ext);
        } else if (entry.name.endsWith(ext) && !entry.name.includes('.min.')) {
            const content = fs.readFileSync(fullPath, 'utf8');
            patterns.forEach(pattern => {
                let match;
                const regex = new RegExp(pattern.source, pattern.flags);
                while ((match = regex.exec(content)) !== null) {
                    match[1].split(/\s+/).forEach(cls => {
                        if (cls && cls.length > 1) classes.add(cls);
                    });
                }
            });
        }
    });
}

scanDir(jsDir, '.js');

// Common dynamic classes that may be added programmatically
const commonDynamic = [
    'active', 'open', 'closed', 'visible', 'hidden', 'show', 'hide',
    'loading', 'loaded', 'error', 'success', 'warning', 'info',
    'disabled', 'enabled', 'selected', 'checked', 'focused',
    'expanded', 'collapsed', 'animating', 'animated',
    'is-active', 'is-open', 'is-visible', 'is-hidden', 'is-loading',
    'has-error', 'has-success', 'has-warning',
    'fade-in', 'fade-out', 'slide-in', 'slide-out',
    'btn-success', 'btn-error', 'btn-loading',
    'dark', 'light', 'scrolled', 'sticky', 'fixed',
    'mobile', 'desktop', 'tablet',
    'verified-offline', 'hydrated', 'content-loaded',
    'no-ptr', 'chat-page', 'messages-fullscreen',
    'logged-in', 'user-is-admin',
    'nexus-home-page', 'nexus-skin-modern',
];

commonDynamic.forEach(cls => classes.add(cls));

console.log(JSON.stringify(Array.from(classes).sort(), null, 2));
