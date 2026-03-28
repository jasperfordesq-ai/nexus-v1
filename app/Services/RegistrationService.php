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
use Illuminate\Validation\Rules\Password;
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
     * @return array Registration result with user data and status flags
     */
    public function register(array $data, int $tenantId): array
    {
        $validator = validator($data, [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|max:255',
            'password'   => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->first();
            return ['error' => $errors];
        }

        $exists = $this->user->newQuery()
            ->where('email', strtolower(trim($data['email'])))
            ->where('tenant_id', $tenantId)
            ->exists();
        if ($exists) {
            return ['error' => 'Registration could not be completed. Please try again or contact support.'];
        }

        $user = DB::transaction(function () use ($data, $tenantId) {
            $user = $this->user->newInstance();
            $user->tenant_id = $tenantId;
            $user->first_name = trim($data['first_name']);
            $user->last_name = trim($data['last_name']);
            $user->email = strtolower(trim($data['email']));
            $user->password_hash = Hash::make($data['password']);
            $user->status = 'pending';
            $user->verification_token = Str::random(64);
            $user->balance = 0;

            // Optional fields from frontend
            if (!empty($data['phone'])) {
                $user->phone = $data['phone'];
            }
            if (!empty($data['location'])) {
                $user->location = $data['location'];
            }
            if (!empty($data['latitude'])) {
                $user->latitude = (float) $data['latitude'];
            }
            if (!empty($data['longitude'])) {
                $user->longitude = (float) $data['longitude'];
            }
            if (!empty($data['profile_type'])) {
                $user->profile_type = $data['profile_type'];
            }
            if (!empty($data['organization_name'])) {
                $user->organization_name = $data['organization_name'];
            }

            $user->save();

            return $user;
        });

        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ],
            'requires_verification' => true,
            'message' => 'Registration successful. Please check your email to verify your account.',
        ];
    }

    /**
     * Verify a user's email with the verification token.
     */
    public function verifyEmail(string $token): bool
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
    public function resendVerification(string $email, int $tenantId = 0): ?string
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
