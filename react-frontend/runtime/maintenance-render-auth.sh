#!/bin/sh
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.

set -eu

action="${1:-}"
token_path="${TOKEN_PATH:-/usr/share/nginx/html/prerendered/.maintenance-render.token}"
htpasswd_path="${HTPASSWD_PATH:-/usr/share/nginx/html/prerendered/.maintenance-render.htpasswd}"
auth_list_path="${AUTH_LIST_PATH:-/usr/share/nginx/html/prerendered/.maintenance-render-auth.list}"
nginx_bin="${NGINX_BIN:-nginx}"
auth_owner="${MAINTENANCE_AUTH_OWNER:-root:nginx}"

case "$action" in
    enable|disable) ;;
    *) echo "Usage: maintenance-render-auth.sh [enable|disable]" >&2; exit 64 ;;
esac

state_dir="$(dirname "$token_path")"
[ "$(dirname "$htpasswd_path")" = "$state_dir" ] \
    && [ "$(dirname "$auth_list_path")" = "$state_dir" ] || {
        echo "Maintenance credential files must share one state directory" >&2
        exit 64
    }
[ -d "$state_dir" ] && [ ! -L "$state_dir" ] || {
    echo "Maintenance credential state directory is unavailable or symlinked: $state_dir" >&2
    exit 66
}

for path in "$token_path" "$htpasswd_path" "$auth_list_path"; do
    [ ! -L "$path" ] || {
        echo "Refusing symlinked maintenance credential state: $path" >&2
        exit 66
    }
    [ ! -e "$path" ] || [ -f "$path" ] || {
        echo "Maintenance credential state is not a regular file: $path" >&2
        exit 66
    }
done

umask 077
transaction_dir="$(mktemp -d "$state_dir/.maintenance-auth-txn.XXXXXX")"
cleanup() {
    rm -rf "$transaction_dir"
}
trap cleanup EXIT HUP INT TERM

snapshot() {
    path="$1"
    key="$2"
    if [ -e "$path" ]; then
        cp -p "$path" "$transaction_dir/$key.old"
        : > "$transaction_dir/$key.existed"
    fi
}

restore() {
    path="$1"
    key="$2"
    if [ -f "$transaction_dir/$key.existed" ]; then
        mv -f "$transaction_dir/$key.old" "$path"
    else
        rm -f "$path"
    fi
}

secure_private_state() {
    # The cache root has a default ACL for the PHP uid. chmod only changes the
    # ACL mask; it does not remove named entries, and a 0640 auth map would
    # otherwise expose the reversible Basic credential to PHP. Strip inherited
    # ACLs before applying the root/nginx-only modes.
    [ ! -e "$token_path" ] || {
        setfacl -b "$token_path"
        chown root:root "$token_path"
        chmod 0600 "$token_path"
    }
    for path in "$htpasswd_path" "$auth_list_path"; do
        [ ! -e "$path" ] || {
            setfacl -b "$path"
            chown "$auth_owner" "$path"
            chmod 0640 "$path"
        }
    done
}

snapshot "$token_path" token
snapshot "$htpasswd_path" htpasswd
snapshot "$auth_list_path" auth

if [ "$action" = "enable" ]; then
    token="$(head -c 48 /dev/urandom | base64 | tr -d '\r\n')"
    [ "${#token}" -ge 48 ] || {
        echo "Could not generate a high-entropy maintenance render credential" >&2
        exit 70
    }
    printf '%s\n' "$token" > "$transaction_dir/token.new"
    htpasswd -bcB "$transaction_dir/htpasswd.new" prerender "$token" >/dev/null 2>&1
    authorization="$(printf 'prerender:%s' "$token" | base64 | tr -d '\r\n')"
    printf '"Basic %s" 1;\n' "$authorization" > "$transaction_dir/auth.new"
    chmod 0600 "$transaction_dir/token.new"
else
    # Both nginx-readable files are deliberately invalidated before the
    # sentinel is removed. A stale or manually recreated sentinel therefore
    # remains a public 503 and cannot reuse an earlier Basic credential.
    printf '%s\n' 'prerender:!' > "$transaction_dir/htpasswd.new"
    printf '%s\n' '# No private maintenance renderer credential is active.' > "$transaction_dir/auth.new"
fi

chown "$auth_owner" "$transaction_dir/htpasswd.new" "$transaction_dir/auth.new"
chmod 0640 "$transaction_dir/htpasswd.new" "$transaction_dir/auth.new"

if [ "$action" = "enable" ]; then
    mv -f "$transaction_dir/token.new" "$token_path"
else
    rm -f "$token_path"
fi
mv -f "$transaction_dir/htpasswd.new" "$htpasswd_path"
mv -f "$transaction_dir/auth.new" "$auth_list_path"
secure_private_state

if "$nginx_bin" -t >/dev/null 2>&1 \
    && "$nginx_bin" -s reload >/dev/null 2>&1; then
    exit 0
fi

echo "Nginx rejected the maintenance credential transition; restoring prior state" >&2
restore "$token_path" token
restore "$htpasswd_path" htpasswd
restore "$auth_list_path" auth
secure_private_state
if ! "$nginx_bin" -t >/dev/null 2>&1 \
    || ! "$nginx_bin" -s reload >/dev/null 2>&1; then
    echo "Prior maintenance credential state was restored, but nginx reload failed" >&2
fi
exit 1
