<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Contracts;

interface EventFederationTransport
{
    /**
     * @param array<string,mixed> $payload
     * @return array{success:bool,receipt?:array<string,mixed>,error_code?:string,error?:string}
     */
    public function deliver(int $tenantId, int $externalPartnerId, array $payload): array;
}
