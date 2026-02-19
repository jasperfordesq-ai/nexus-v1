// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Reset Password Page - Set new password with token
 */

import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input } from '@heroui/react';
import { Lock, ArrowLeft, CheckCircle, Eye, EyeOff, Check, X } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { validatePassword, PASSWORD_REQUIREMENTS } from '@/lib/validation';

export function ResetPasswordPage() {
  usePageTitle('Set New Password');
  const { branding, tenantPath } = useTenant();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token');

  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
  const [error, setError] = useState('');

  // Validate token exists
  if (!token) {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="w-full max-w-md"
        >
          <GlassCard className="p-8 text-center">
            <h1 className="text-2xl font-bold text-theme-primary mb-4">Invalid reset link</h1>
            <p className="text-theme-muted mb-6">
              This password reset link is invalid or has expired. Please request a new one.
            </p>
            <Link to={tenantPath('/password/forgot')}>
              <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                Request new link
              </Button>
            </Link>
          </GlassCard>
        </motion.div>
      </div>
    );
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError('');

    // Validation - must match backend requirements (PasswordResetApiController)
    const passwordErrors = validatePassword(password);
    if (passwordErrors.length > 0) {
      setError(passwordErrors[0]);
      return;
    }
    if (password !== confirmPassword) {
      setError('Passwords do not match');
      return;
    }

    try {
      setIsLoading(true);
      await api.post('/auth/reset-password', {
        token,
        password,
        password_confirmation: confirmPassword,
      });
      setIsSuccess(true);
    } catch (err) {
      setError('Failed to reset password. The link may have expired.');
    } finally {
      setIsLoading(false);
    }
  }

  if (isSuccess) {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="w-full max-w-md"
        >
          <GlassCard className="p-8 text-center">
            <div className="w-16 h-16 mx-auto mb-6 rounded-full bg-emerald-500/20 flex items-center justify-center">
              <CheckCircle className="w-8 h-8 text-emerald-400" />
            </div>
            <h1 className="text-2xl font-bold text-theme-primary mb-2">Password reset successful</h1>
            <p className="text-theme-muted mb-6">
              Your password has been updated. You can now sign in with your new password.
            </p>
            <Link to={tenantPath('/login')}>
              <Button className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                Sign in
              </Button>
            </Link>
          </GlassCard>
        </motion.div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center p-4">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="w-full max-w-md"
      >
        {/* Back to login */}
        <Link
          to={tenantPath('/login')}
          className="flex items-center gap-2 text-theme-muted hover:text-theme-primary transition-colors mb-6"
        >
          <ArrowLeft className="w-4 h-4" />
          Back to login
        </Link>

        <GlassCard className="p-8">
          {/* Header */}
          <div className="text-center mb-8">
            <h1 className="text-2xl font-bold text-theme-primary mb-2">Set new password</h1>
            <p className="text-theme-muted">
              Enter your new password below.
            </p>
          </div>

          {/* Error */}
          {error && (
            <div className="p-3 mb-6 rounded-lg bg-red-500/20 border border-red-500/30 text-red-400 text-sm">
              {error}
            </div>
          )}

          {/* Form */}
          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <Input
                type={showPassword ? 'text' : 'password'}
                label="New password"
                placeholder="Enter a strong password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                startContent={<Lock className="w-4 h-4 text-theme-subtle" />}
                endContent={
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    className="min-w-0 w-auto h-auto p-0 text-theme-subtle"
                    onPress={() => setShowPassword(!showPassword)}
                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                  >
                    {showPassword ? <EyeOff className="w-4 h-4" aria-hidden="true" /> : <Eye className="w-4 h-4" aria-hidden="true" />}
                  </Button>
                }
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
                isRequired
              />
              {/* Password requirements checklist */}
              {password && (
                <ul className="mt-2 space-y-1 text-xs">
                  {PASSWORD_REQUIREMENTS.map((req) => {
                    const passed = req.test(password);
                    return (
                      <li
                        key={req.id}
                        className={`flex items-center gap-1.5 ${
                          passed ? 'text-emerald-400' : 'text-theme-subtle'
                        }`}
                      >
                        {passed ? (
                          <Check className="w-3 h-3" />
                        ) : (
                          <X className="w-3 h-3" />
                        )}
                        {req.label}
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>

            <Input
              type={showPassword ? 'text' : 'password'}
              label="Confirm password"
              placeholder="Re-enter your password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              startContent={<Lock className="w-4 h-4 text-theme-subtle" />}
              isInvalid={confirmPassword.length > 0 && password !== confirmPassword}
              errorMessage={confirmPassword.length > 0 && password !== confirmPassword ? 'Passwords do not match' : ''}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
                label: 'text-theme-muted',
              }}
              isRequired
            />

            <Button
              type="submit"
              className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              isLoading={isLoading}
              isDisabled={!password || !confirmPassword}
            >
              Reset password
            </Button>
          </form>
        </GlassCard>

        {/* Branding */}
        <p className="text-center text-theme-subtle text-sm mt-6">
          {branding.name}
        </p>
      </motion.div>
    </div>
  );
}

export default ResetPasswordPage;
