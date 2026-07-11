#!/bin/bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.

# Resolve the active blue/green container without relying on docker ps order.
# Source this file and call resolve_active_nexus_container <php-app|react>, or
# execute it directly with that role. Tests may override
# nexus_running_container_names() after sourcing.

nexus_running_container_names() {
    docker ps --format '{{.Names}}'
}

resolve_active_nexus_container() {
    local role="${1:-}"
    local suffix legacy
    case "$role" in
        php-app)
            suffix="php-app"
            legacy="nexus-php-app"
            ;;
        react)
            suffix="react"
            legacy="nexus-react-prod"
            ;;
        *)
            echo "resolve-active-container: expected role php-app or react" >&2
            return 64
            ;;
    esac

    local deploy_dir="${DEPLOY_DIR:-/opt/nexus-php}"
    local state_file="${NEXUS_BLUEGREEN_STATE_FILE:-$deploy_dir/.bluegreen-active}"
    local names color="" expected
    if ! names="$(nexus_running_container_names 2>/dev/null)"; then
        echo "resolve-active-container: docker ps failed" >&2
        return 69
    fi

    if [ -f "$state_file" ]; then
        color="$(tr -d '[:space:]' < "$state_file" 2>/dev/null || true)"
        case "$color" in
            blue|green)
                expected="nexus-${color}-${suffix}"
                if printf '%s\n' "$names" | grep -Fqx -- "$expected"; then
                    printf '%s\n' "$expected"
                    return 0
                fi
                echo "resolve-active-container: state selects $expected, but it is not running" >&2
                return 69
                ;;
            *) color="" ;;
        esac
    fi

    local -a matches=()
    while IFS= read -r candidate; do
        [ -n "$candidate" ] && matches+=("$candidate")
    done < <(printf '%s\n' "$names" | grep -E "^nexus-(blue|green)-${suffix}$" || true)
    if printf '%s\n' "$names" | grep -Fqx -- "$legacy"; then
        matches+=("$legacy")
    fi

    if [ "${#matches[@]}" -eq 1 ]; then
        printf '%s\n' "${matches[0]}"
        return 0
    fi
    if [ "${#matches[@]}" -gt 1 ]; then
        echo "resolve-active-container: active state is missing/invalid and multiple ${suffix} containers are running" >&2
        return 69
    fi

    echo "resolve-active-container: no running container found for role $role" >&2
    return 69
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    set -euo pipefail
    resolve_active_nexus_container "${1:-}"
fi
