<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Support;

/**
 * EmojiConstants — Shared emoji allowlists for non-reaction contexts.
 *
 * NOTE: For polymorphic post/comment REACTIONS, use named types from
 * ReactionService::VALID_TYPES (love, like, laugh, wow, sad, celebrate,
 * clap, time_credit) — NOT raw emojis. These constants below are for
 * decorative emoji attachment on posts and emoji reactions on direct
 * messages, which are distinct features.
 */
final class EmojiConstants
{
    /**
     * Decorative emoji that may be attached to a feed post body.
     * Used by FeedService::createPost.
     */
    public const POST_DECORATIVE = [
        '👍', '❤️', '😂', '😮', '😢', '🔥',
        '👏', '🎉', '✨', '💡', '🙌', '😍',
    ];

    /**
     * Allowed emoji reactions on direct messages.
     * Used by MessagesController::toggleReaction.
     */
    public const MESSAGE_REACTIONS = [
        '👍', '❤️', '😂', '😮', '😢', '🙏',
    ];
}
