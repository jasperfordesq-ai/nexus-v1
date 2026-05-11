<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\AI\Tools;

/**
 * Contract for AI chat tools.
 *
 * A tool is a server-side function the model can call during a chat turn to
 * fetch live, tenant-scoped data (listings, members, KB articles, etc.).
 * Tools must be safe to call with model-supplied arguments: they validate
 * their inputs, enforce tenant scope from TenantContext, and return a small
 * structured result the model can summarise back to the user.
 */
interface ToolInterface
{
    /**
     * Stable tool identifier exposed to the model (snake_case).
     */
    public function name(): string;

    /**
     * One-sentence description shown to the model so it can decide when to
     * call this tool. Should describe *what the tool returns* and *when to
     * use it*, not implementation details.
     */
    public function description(): string;

    /**
     * JSON schema for the tool's arguments, in OpenAI function-calling shape:
     *   ['type' => 'object', 'properties' => [...], 'required' => [...]]
     */
    public function parametersSchema(): array;

    /**
     * Whether this tool should be offered for the given user. Use this to
     * gate tools by tenant feature flags or user role.
     */
    public function isAvailable(int $userId): bool;

    /**
     * Execute the tool. Implementations MUST scope every query by the current
     * tenant (TenantContext::getId()) and MUST NOT trust caller-supplied
     * tenant IDs.
     *
     * Return shape:
     *   [
     *     'ok' => bool,
     *     'summary' => string,       // short text the model can quote
     *     'results' => array,        // structured data for UI cards
     *     'card_type' => string,     // hint for UI rendering (listing|member|...)
     *     'error' => ?string,        // human-readable error if ok=false
     *   ]
     */
    public function execute(array $arguments, int $userId): array;
}
