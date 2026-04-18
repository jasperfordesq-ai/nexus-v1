#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Fix #4: Protect compose.yml from git overwrites.

# Marks compose.yml as skip-worktree so 'git reset --hard' never
# replaces it with the dev version from the repository.
protect_compose_yml() {
    if git ls-files --error-unmatch compose.yml > /dev/null 2>&1; then
        git update-index --skip-worktree compose.yml 2>/dev/null && \
            log_ok "compose.yml protected from git overwrites (skip-worktree)" || \
            log_warn "Could not set skip-worktree on compose.yml"
    else
        log_info "compose.yml is not tracked by git — no protection needed"
    fi
}

# Temporarily remove skip-worktree, reset the file to tracked HEAD state,
# then re-apply skip-worktree. This allows 'git reset --hard origin/main'
# to succeed even when compose.yml differs from the repo version.
pre_reset_compose_yml() {
    if git ls-files --error-unmatch compose.yml > /dev/null 2>&1; then
        git update-index --no-skip-worktree compose.yml 2>/dev/null || true
        git checkout HEAD -- compose.yml 2>/dev/null || true
        git update-index --skip-worktree compose.yml 2>/dev/null || true
    fi
}
