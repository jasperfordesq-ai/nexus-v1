#!/bin/sh
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.

set -eu

prerender_dir=/usr/share/nginx/html/prerendered
php_uid="${PRERENDER_PHP_UID:-33}"
status_list="$prerender_dir/.status-overrides.list"
maintenance_htpasswd="$prerender_dir/.maintenance-render.htpasswd"
maintenance_auth_list="$prerender_dir/.maintenance-render-auth.list"

mkdir -p "$prerender_dir"
case "$php_uid" in
    ''|*[!0-9]*) echo "Invalid PRERENDER_PHP_UID: $php_uid" >&2; exit 1 ;;
esac
chmod 0770 "$prerender_dir"
setfacl -m "u:${php_uid}:rwx,u:nginx:rwx,m:rwx,d:u:${php_uid}:rwx,d:u:nginx:rwx,d:m:rwx" "$prerender_dir"

# Serialize startup normalization with Laravel invalidation and host
# publication. A second blue/green frontend may start while the active color
# is publishing into the same external volume.
mutation_lock="$prerender_dir/.mutation.lock"
if [ -L "$mutation_lock" ]; then
    echo "Refusing symlinked prerender mutation lock: $mutation_lock" >&2
    exit 1
fi
: >> "$mutation_lock"
chmod 0660 "$mutation_lock"
setfacl -m "u:${php_uid}:rw,u:nginx:rw,m:rw" "$mutation_lock"
exec 9>"$mutation_lock"
flock -w 120 9 || {
    echo "Timed out acquiring prerender mutation lock during startup" >&2
    exit 75
}

# A newly-added default ACL only affects future files. Normalize legacy host
# trees from before the shared-volume ACL rollout so Laravel can immediately
# invalidate or quarantine them. Root-level dotfiles are operational state
# (including the private maintenance token) and are deliberately excluded.
for host_tree in "$prerender_dir"/*; do
    [ -e "$host_tree" ] || continue
    [ -d "$host_tree" ] && [ ! -L "$host_tree" ] || continue
    find "$host_tree" -type d -exec chmod 0770 {} +
    find "$host_tree" -type d -exec setfacl -m \
        "u:${php_uid}:rwx,u:nginx:rwx,m:rwx,d:u:${php_uid}:rwx,d:u:nginx:rwx,d:m:rwx" {} +
    find "$host_tree" -type f -exec chmod 0660 {} +
    find "$host_tree" -type f -exec setfacl -m \
        "u:${php_uid}:rw,u:nginx:rw,m:rw" {} +
done

if [ -L "$status_list" ]; then
    echo "Refusing symlinked prerender status include: $status_list" >&2
    exit 1
fi

if [ ! -e "$status_list" ]; then
    umask 002
    printf '%s\n' '# Authoritative prerender status map — empty until first publication.' > "$status_list"
fi

[ -f "$status_list" ] || {
    echo "Prerender status include is not a regular file: $status_list" >&2
    exit 1
}

# Keep the maintenance authentication file present even before the first
# operator transition. `!` is deliberately not a valid password hash, so a
# sentinel created without the canonical maintenance script fails closed with
# HTTP 503 instead of turning an absent auth file into an nginx 500.
if [ -L "$maintenance_htpasswd" ]; then
    echo "Refusing symlinked maintenance credential file: $maintenance_htpasswd" >&2
    exit 1
fi
if [ ! -e "$maintenance_htpasswd" ]; then
    umask 027
    printf '%s\n' 'prerender:!' > "$maintenance_htpasswd"
fi
[ -f "$maintenance_htpasswd" ] || {
    echo "Maintenance credential file is not regular: $maintenance_htpasswd" >&2
    exit 1
}
setfacl -b "$maintenance_htpasswd"
chown root:nginx "$maintenance_htpasswd"
chmod 0640 "$maintenance_htpasswd"

if [ -L "$maintenance_auth_list" ]; then
    echo "Refusing symlinked maintenance auth map: $maintenance_auth_list" >&2
    exit 1
fi
if [ ! -e "$maintenance_auth_list" ]; then
    umask 027
    printf '%s\n' '# No private maintenance renderer credential has been activated.' > "$maintenance_auth_list"
fi
[ -f "$maintenance_auth_list" ] || {
    echo "Maintenance auth map is not regular: $maintenance_auth_list" >&2
    exit 1
}
setfacl -b "$maintenance_auth_list"
chown root:nginx "$maintenance_auth_list"
chmod 0640 "$maintenance_auth_list"

# Advisory lock contents carry no secrets. Both nginx (root publisher) and
# PHP/Apache (www-data invalidation) can open the same inode. The descriptor
# closes on script exit, releasing the startup normalization lock.
