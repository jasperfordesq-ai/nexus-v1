<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | Argon2id is the recommended password hashing algorithm — it is resistant
    | to both GPU and side-channel attacks. All password hashing throughout the
    | application should use Argon2id consistently.
    |
    */

    'driver' => 'argon2id',

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | Kept for backward compatibility — existing bcrypt hashes will still
    | verify correctly via password_verify() / Hash::check(), and Laravel
    | will transparently rehash them to Argon2id on the next login.
    |
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon2id Options
    |--------------------------------------------------------------------------
    |
    | These match the OWASP 2024 recommended parameters for Argon2id:
    | 64 MiB memory, 4 iterations, 1 thread.
    |
    */

    'argon' => [
        'memory' => 65536,
        'threads' => 1,
        'time' => 4,
    ],

];
