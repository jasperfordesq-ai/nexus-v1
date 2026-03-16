<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * RegistrationService — Laravel DI-based service for user registration.
 *
 * Eloquent/DI counterpart to legacy static registration logic.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class RegistrationService
{
    public function __construct(
        private readonly User $user,
    ) {}

    /**
     * Register a new user account.
     *
     * @throws ValidationException
     */
    public function register(array $data): User
    {
        $validator = validator($data, [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|max:255',
            'password'   => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $exists = $this->user->newQuery()->where('email', $data['email'])->exists();
        if ($exists) {
            throw ValidationException::withMessages(['email' => 'This email is already registered.']);
        }

        return DB::transaction(function () use ($data) {
            $user = $this->user->newInstance([
                'first_name'         => trim($data['first_name']),
                'last_name'          => trim($data['last_name']),
                'email'              => strtolower(trim($data['email'])),
                'password'           => Hash::make($data['password']),
                'status'             => 'pending',
                'verification_token' => Str::random(64),
                'balance'            => 0,
            ]);
            $user->save();

            return $user;
        });
    }

    /**
     * Verify a user's email with the verification token.
     */
    public function verify(string $token): bool
    {
        /** @var User|null $user */
        $user = $this->user->newQuery()
            ->where('verification_token', $token)
            ->where('status', 'pending')
            ->first();

        if (! $user) {
            return false;
        }

        $user->update([
            'status'             => 'active',
            'verification_token' => null,
            'email_verified_at'  => now(),
        ]);

        return true;
    }

    /**
     * Resend verification email by regenerating the token.
     *
     * @return string|null The new verification token, or null if user not found.
     */
    public function resendVerification(string $email): ?string
    {
        /** @var User|null $user */
        $user = $this->user->newQuery()
            ->where('email', strtolower(trim($email)))
            ->where('status', 'pending')
            ->first();

        if (! $user) {
            return null;
        }

        $token = Str::random(64);
        $user->update(['verification_token' => $token]);

        return $token;
    }
}
