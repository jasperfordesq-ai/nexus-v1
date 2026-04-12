<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [

    /*
    |--------------------------------------------------------------------------
    | Federation JWT signing
    |--------------------------------------------------------------------------
    |
    | Secret used by FederationJwtService to sign and verify cross-tenant /
    | cross-platform federation tokens. Must be set in production; the service
    | refuses to fall back to APP_KEY on purpose. Supports "base64:..." prefix
    | for base64-encoded keys.
    |
    | Issuer claim (iss) for tokens we mint. Defaults to APP_URL if unset.
    */

    'jwt_secret' => env('FEDERATION_JWT_SECRET'),

    'jwt_issuer' => env('FEDERATION_JWT_ISSUER', env('APP_URL')),

];
