// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Login Page with 2FA Support and Tenant Selection
 * Theme-aware styling for light and dark modes
 *
 * Tenant resolution priority:
 * 1. TenantContext already resolved a tenant from URL slug or custom domain → use it, no dropdown
 * 2. No URL tenant, but bootstrap resolved tenant 1 (platform) → use it, no dropdown
 * 3. No URL tenant and no bootstrap tenant → show community dropdown (multi-tenant chooser)
 */

import { useState, useEffect, useRef, useCallback, type FormEvent } from 'react';
import { Link, useNavigate, useLocation, useSearchParams } from 'react-router-dom';
import { Button, Input, Checkbox, Divider, Select, SelectItem } from '@heroui/react';
import { motion, AnimatePresence } from 'framer-motion';
import { Mail, Lock, Eye, EyeOff, Shield, ArrowLeft, Loader2, Building2, Fingerprint, ShieldAlert, ShieldX } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant, useToast } from '@/contexts';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { usePageTitle } from '@/hooks';
import { api, tokenManager } from '@/lib/api';
import {
  isBiometricAvailable,
  isConditionalMediationAvailable,
  startConditionalAuthentication,
} from '@/lib/webauthn';

interface Tenant {
  id: number;
  name: string;
  slug: string;
  domain?: string;
  tagline?: string;
  logo_url?: string;
}

export function LoginPage() {
  const { t } = useTranslation('auth');
  usePageTitle('Sign In');
  const navigate = useNavigate();
  const location = useLocation();
  const {
    status,
    error,
    isAuthenticated,
    login,
    loginWithBiometric,
    verify2FA,
    clearError,
    cancel2FA,
    twoFactorMethods,
  } = useAuth();
  const { tenant, branding, tenantSlug, tenantPath, isLoading: tenantLoading } = useTenant();
  const toast = useToast();
  const [searchParams] = useSearchParams();

  // Tenant state — only used when no tenant is resolved from URL/domain
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [selectedTenantId, setSelectedTenantId] = useState<string>('');
  const [tenantsLoading, setTenantsLoading] = useState(false);

  // Form state
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  // 2FA state
  const [twoFactorCode, setTwoFactorCode] = useState('');
  const [useBackupCode, setUseBackupCode] = useState(false);
  const [trustDevice, setTrustDevice] = useState(false);

  // Verification resend state
  const [loginErrorCode, setLoginErrorCode] = useState<string | undefined>();
  const [isResendingVerification, setIsResendingVerification] = useState(false);
  const [resendVerificationSent, setResendVerificationSent] = useState(false);

  // Passkey login state
  const [biometricAvailable, setBiometricAvailable] = useState(false);
  const [biometricLoading, setBiometricLoading] = useState(false);
  const conditionalAbortRef = useRef<AbortController | null>(null);

  // Redirect after successful login (preserve tenant slug prefix)
  const from = (location.state as { from?: string })?.from || tenantPath('/dashboard');

  // Clear stale auth tokens on mount — login page should always start clean
  useEffect(() => {
    tokenManager.clearTokens();
  }, []);

  // Check if passkey login is available on this device
  useEffect(() => {
    isBiometricAvailable().then(setBiometricAvailable).catch(() => setBiometricAvailable(false));
  }, []);

  // Start conditional mediation (passkey autofill) when a tenant is selected
  const startConditionalAuth = useCallback(async () => {
    if (!selectedTenantId) return;
    const supported = await isConditionalMediationAvailable();
    if (!supported) return;

    // Abort any previous conditional auth
    conditionalAbortRef.current?.abort();
    const controller = new AbortController();
    conditionalAbortRef.current = controller;

    // Set tenant for API calls
    tokenManager.setTenantId(selectedTenantId);

    const result = await startConditionalAuthentication(controller.signal);
    if (result?.success && result.data) {
      // Passkey autofill succeeded — store tokens and reload to bootstrap auth
      tokenManager.setAccessToken(result.data.access_token);
      tokenManager.setRefreshToken(result.data.refresh_token);
      window.location.href = from;
    }
  }, [selectedTenantId, from]);

  useEffect(() => {
    startConditionalAuth();
    return () => {
      conditionalAbortRef.current?.abort();
    };
  }, [startConditionalAuth]);

  // Tenant is "resolved from URL" only when there's an explicit URL slug or
  // the hostname is a custom tenant domain (not the generic app.* domain).
  // If bootstrap just fell back to tenant 1 with no URL hint, we still need
  // the dropdown so regular users can pick their community.
  const hostname = typeof window !== 'undefined' ? window.location.hostname : '';
  const isGenericAppDomain = hostname === 'app.project-nexus.ie' || hostname === 'localhost' || hostname === '127.0.0.1';
  const tenantResolvedFromUrl = !tenantLoading && tenant !== null && (!!tenantSlug || !isGenericAppDomain);

  // When tenant is resolved from URL/domain, set it directly — no dropdown needed.
  useEffect(() => {
    if (tenantResolvedFromUrl && tenant) {
      setSelectedTenantId(String(tenant.id));
      tokenManager.setTenantId(tenant.id);
    }
  }, [tenantResolvedFromUrl, tenant]);

  // Only fetch the tenant list when URL/domain gives us no tenant hint.
  // This is the fallback for app.project-nexus.ie/login with no slug.
  useEffect(() => {
    if (tenantLoading) return; // Wait for context to settle first
    if (tenantResolvedFromUrl) return; // Already have a tenant — no list needed

    const fetchTenants = async () => {
      setTenantsLoading(true);
      try {
        // Fetch ALL tenants including tenant 1 for super admin access
        const response = await api.get<Tenant[]>('/v2/tenants?include_master=1', { skipAuth: true, skipTenant: true });
        if (response.success && response.data) {
          setTenants(response.data);

          // Pre-select from ?tenant= query param
          const tenantHint = searchParams.get('tenant');
          const hintMatch = tenantHint
            ? response.data.find((t) => t.slug === tenantHint)
            : null;

          if (hintMatch) {
            setSelectedTenantId(String(hintMatch.id));
            tokenManager.setTenantId(hintMatch.id);
          } else if (response.data.length === 1) {
            setSelectedTenantId(String(response.data[0].id));
            tokenManager.setTenantId(response.data[0].id);
          }
        }
      } catch (err) {
        console.error('[LoginPage] Failed to fetch tenants:', err);
      } finally {
        setTenantsLoading(false);
      }
    };
    fetchTenants();
  }, [tenantLoading, tenantResolvedFromUrl, tenantSlug, searchParams]);

  // Handle tenant selection from dropdown
  const handleTenantChange = (keys: unknown) => {
    if (!(keys instanceof Set)) return;
    const selectedKeys = keys as Set<string>;
    const tenantId = Array.from(selectedKeys)[0] || '';
    setSelectedTenantId(tenantId);
    if (tenantId) {
      tokenManager.setTenantId(tenantId);
    }
  };

  useEffect(() => {
    if (isAuthenticated) {
      navigate(from, { replace: true });
    }
  }, [isAuthenticated, navigate, from]);

  // Clear error when form changes
  useEffect(() => {
    if (error) {
      clearError();
    }
  }, [email, password, twoFactorCode, selectedTenantId]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleLogin = async (e: FormEvent) => {
    e.preventDefault();

    if (!email.trim() || !password.trim()) return;
    if (!selectedTenantId) return;

    setLoginErrorCode(undefined);
    setResendVerificationSent(false);
    tokenManager.clearTokens();
    tokenManager.setTenantId(selectedTenantId);

    const result = await login({ email, password });
    if (!result.success && result.errorCode) {
      setLoginErrorCode(result.errorCode);
    }
  };

  const handleResendVerification = async () => {
    if (!email.trim()) return;
    setIsResendingVerification(true);
    try {
      await api.post('/auth/resend-verification-by-email', { email }, { skipAuth: true });
      setResendVerificationSent(true);
    } catch {
      // Silently handle — the endpoint always returns success for security
    } finally {
      setIsResendingVerification(false);
    }
  };

  const handleBiometricLogin = async () => {
    if (!selectedTenantId) return;
    setBiometricLoading(true);
    tokenManager.clearTokens();
    tokenManager.setTenantId(selectedTenantId);

    const result = await loginWithBiometric(email || undefined);
    setBiometricLoading(false);

    if (result.success) {
      navigate(from, { replace: true });
    } else if (result.error?.includes('cancelled')) {
      // User cancelled — do nothing
    } else if (result.error?.includes('not found') || result.error?.includes('Credential not found')) {
      // No passkey registered for this account
      toast.error(t('passkey_not_found'));
    }
  };

  const handleVerify2FA = async (e: FormEvent) => {
    e.preventDefault();

    const code = twoFactorCode.trim();
    const expectedLength = useBackupCode ? 8 : 6;

    if (!code || code.length !== expectedLength || !/^\d+$/.test(code)) return;

    const success = await verify2FA({
      code,
      use_backup_code: useBackupCode,
      trust_device: trustDevice,
    });

    if (success) {
      navigate(from, { replace: true });
    }
  };

  const handleBack2FA = () => {
    setTwoFactorCode('');
    setUseBackupCode(false);
    setTrustDevice(false);
    cancel2FA();
  };

  const isLoading = status === 'loading';
  const requires2FA = status === 'requires_2fa';

  // Show dropdown only when no URL/domain tenant is resolved AND multiple tenants exist
  const showTenantDropdown = !tenantResolvedFromUrl && !tenantsLoading && tenants.length > 1;
  const showTenantCard = !tenantResolvedFromUrl && !tenantsLoading && tenants.length === 1;
  // Show resolved tenant info (from URL/domain) when known
  const showResolvedTenant = tenantResolvedFromUrl && tenant;

  const canSubmit = email.trim() && password.trim() && !!selectedTenantId;

  return (
    <>
      <PageMeta title={t("login_meta_title")} description={t("login_meta_description")} noIndex />
      <div className="min-h-screen flex items-center justify-center p-4">
        {/* Background blobs */}
        <div className="fixed inset-0 overflow-hidden pointer-events-none">
          <div className="blob blob-indigo" />
          <div className="blob blob-purple" />
          <div className="blob blob-cyan" />
        </div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="w-full max-w-md relative z-10"
        >
          <GlassCard className="p-5 sm:p-8">
            <AnimatePresence mode="wait">
              {!requires2FA ? (
                // Login Form
                <motion.div
                  key="login"
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  exit={{ opacity: 0, x: 20 }}
                >
                  {/* Header */}
                  <div className="text-center mb-8">
                    <motion.div
                      initial={{ scale: 0.8 }}
                      animate={{ scale: 1 }}
                      className="inline-flex items-center justify-center w-12 h-12 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4"
                    >
                      <Mail className="w-8 h-8 text-indigo-500 dark:text-indigo-400" />
                    </motion.div>
                    <h1 className="text-xl sm:text-2xl font-bold text-theme-primary">{t('login.title')}</h1>
                    <p className="text-theme-muted mt-2">
                      {t('login.subtitle_community', { name: branding.name })}
                    </p>
                  </div>

                  {/* Error Alert */}
                  {error && (
                    <motion.div
                      initial={{ opacity: 0, y: -10 }}
                      animate={{ opacity: 1, y: 0 }}
                      className="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-sm"
                    >
                      <p className="text-red-600 dark:text-red-400">{error}</p>
                      {/* Resend verification email button */}
                      {loginErrorCode === 'AUTH_EMAIL_NOT_VERIFIED' && (
                        <div className="mt-3">
                          {resendVerificationSent ? (
                            <p className="text-emerald-600 dark:text-emerald-400 text-xs">
                              {t('login.verification_email_sent', { defaultValue: 'Verification email sent! Check your inbox.' })}
                            </p>
                          ) : (
                            <Button
                              size="sm"
                              variant="flat"
                              onPress={handleResendVerification}
                              isLoading={isResendingVerification}
                              className="bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-500/20"
                              startContent={!isResendingVerification ? <Mail className="w-3 h-3" /> : undefined}
                            >
                              {t('login.resend_verification', { defaultValue: 'Resend verification email' })}
                            </Button>
                          )}
                        </div>
                      )}
                      {loginErrorCode === 'AUTH_PENDING_VERIFICATION' && (
                        <div className="mt-3 flex items-start gap-2">
                          <ShieldAlert className="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" />
                          <div>
                            <p className="text-amber-600 dark:text-amber-400 text-xs">
                              {t('login.pending_verification', {
                                defaultValue: 'Your identity verification is still in progress. Please complete the verification process or wait for it to finish.',
                              })}
                            </p>
                            <Button
                              size="sm"
                              variant="flat"
                              as={Link}
                              to={tenantPath('/verify-identity')}
                              className="mt-2 bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20"
                              startContent={<ShieldAlert className="w-3 h-3" />}
                            >
                              {t('login.continue_verification', { defaultValue: 'Continue verification' })}
                            </Button>
                          </div>
                        </div>
                      )}
                      {loginErrorCode === 'AUTH_VERIFICATION_FAILED' && (
                        <div className="mt-3 flex items-start gap-2">
                          <ShieldX className="w-4 h-4 text-red-500 mt-0.5 flex-shrink-0" />
                          <div>
                            <p className="text-red-600 dark:text-red-400 text-xs">
                              {t('login.verification_failed', {
                                defaultValue: 'Your identity verification was unsuccessful. You may retry the process or contact support for assistance.',
                              })}
                            </p>
                            <Button
                              size="sm"
                              variant="flat"
                              as={Link}
                              to={tenantPath('/verify-identity')}
                              className="mt-2 bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-500/20"
                              startContent={<ShieldX className="w-3 h-3" />}
                            >
                              {t('login.retry_verification', { defaultValue: 'Retry verification' })}
                            </Button>
                          </div>
                        </div>
                      )}
                    </motion.div>
                  )}

                  {/* Form */}
                  <form onSubmit={handleLogin} className="space-y-5">
                    {/* Tenant resolved from URL/domain — show as read-only info */}
                    {showResolvedTenant && (
                      <div className="p-3 rounded-xl bg-white/90 dark:bg-white/10 backdrop-blur-xl border border-gray-200 dark:border-white/10">
                        <div className="flex items-center gap-3">
                          <Building2 className="w-5 h-5 text-indigo-500 dark:text-indigo-400 flex-shrink-0" />
                          <div>
                            <p className="text-gray-900 dark:text-white font-medium">{tenant.name}</p>
                            {tenant.tagline && (
                              <p className="text-gray-500 dark:text-gray-400 text-xs">{tenant.tagline}</p>
                            )}
                          </div>
                        </div>
                      </div>
                    )}

                    {/* Multi-tenant dropdown — only when tenant not resolved from URL */}
                    {showTenantDropdown && (
                      <Select
                        label={t('login.community_label')}
                        placeholder={t('login.community_placeholder')}
                        selectedKeys={selectedTenantId ? new Set([selectedTenantId]) : new Set()}
                        onSelectionChange={handleTenantChange}
                        startContent={<Building2 className="w-4 h-4 text-theme-subtle" />}
                        isRequired
                        classNames={{
                          trigger: 'bg-white/90 dark:bg-white/10 backdrop-blur-xl border border-gray-200 dark:border-white/10',
                          label: 'text-theme-muted',
                          value: 'text-theme-primary',
                          popoverContent: 'bg-content1 border border-theme-default',
                        }}
                      >
                        {tenants.map((t) => (
                          <SelectItem
                            key={String(t.id)}
                            textValue={t.name}
                            classNames={{
                              base: 'text-gray-900 dark:text-white data-[hover=true]:bg-gray-100 dark:data-[hover=true]:bg-white/10',
                            }}
                          >
                            <div className="flex flex-col">
                              <span className="text-gray-900 dark:text-white">{t.name}</span>
                              {t.tagline && (
                                <span className="text-gray-500 dark:text-gray-400 text-xs">{t.tagline}</span>
                              )}
                            </div>
                          </SelectItem>
                        ))}
                      </Select>
                    )}

                    {/* Single tenant card */}
                    {showTenantCard && (
                      <div className="p-3 rounded-xl bg-white/90 dark:bg-white/10 backdrop-blur-xl border border-gray-200 dark:border-white/10">
                        <div className="flex items-center gap-3">
                          <Building2 className="w-5 h-5 text-indigo-500 dark:text-indigo-400" />
                          <div>
                            <p className="text-gray-900 dark:text-white font-medium">{tenants[0].name}</p>
                            {tenants[0].tagline && (
                              <p className="text-gray-500 dark:text-gray-400 text-xs">{tenants[0].tagline}</p>
                            )}
                          </div>
                        </div>
                      </div>
                    )}

                    <Input
                      type="email"
                      label={t('login.email_label')}
                      placeholder={t('login.email_placeholder')}
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      startContent={<Mail className="w-4 h-4 text-theme-subtle" />}
                      isRequired
                      autoComplete="username webauthn"
                      classNames={{
                        inputWrapper: 'glass-card backdrop-blur-lg',
                        label: 'text-theme-muted',
                        input: 'text-theme-primary placeholder:text-theme-subtle',
                      }}
                    />

                    <Input
                      type={showPassword ? 'text' : 'password'}
                      label={t('login.password_label')}
                      placeholder="••••••••"
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
                          aria-label={showPassword ? t('login.hide_password', { defaultValue: 'Hide password' }) : t('login.show_password', { defaultValue: 'Show password' })}
                        >
                          {showPassword ? (
                            <EyeOff className="w-4 h-4" aria-hidden="true" />
                          ) : (
                            <Eye className="w-4 h-4" aria-hidden="true" />
                          )}
                        </Button>
                      }
                      isRequired
                      autoComplete="current-password"
                      classNames={{
                        inputWrapper: 'glass-card backdrop-blur-lg',
                        label: 'text-theme-muted',
                        input: 'text-theme-primary placeholder:text-theme-subtle',
                      }}
                    />

                    <div className="flex items-center justify-end">
                      <Link
                        to={tenantPath('/password/forgot')}
                        className="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 transition-colors"
                      >
                        {t('login.forgot_password')}
                      </Link>
                    </div>

                    <Button
                      type="submit"
                      isLoading={isLoading}
                      isDisabled={!canSubmit}
                      className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
                      size="lg"
                      spinner={<Loader2 className="w-4 h-4 animate-spin" />}
                    >
                      {t('login.submit')}
                    </Button>
                  </form>

                  {/* Passkey Login */}
                  {biometricAvailable && selectedTenantId && (
                    <>
                      <div className="relative flex items-center my-5">
                        <div className="flex-grow border-t border-[var(--border-default)]" />
                        <span className="flex-shrink mx-3 text-xs text-theme-subtle">
                          {t('login.or', { defaultValue: 'or' })}
                        </span>
                        <div className="flex-grow border-t border-[var(--border-default)]" />
                      </div>

                      <Button
                        type="button"
                        variant="bordered"
                        onPress={handleBiometricLogin}
                        isLoading={biometricLoading}
                        isDisabled={isLoading}
                        className="w-full border-indigo-500/30 text-theme-primary hover:bg-indigo-500/10"
                        size="lg"
                        startContent={
                          !biometricLoading ? (
                            <Fingerprint className="w-5 h-5 text-indigo-500" />
                          ) : undefined
                        }
                        spinner={<Loader2 className="w-4 h-4 animate-spin" />}
                      >
                        {t('login.passkey_login', { defaultValue: 'Sign in with a passkey' })}
                      </Button>
                      <p className="text-xs text-theme-muted text-center mt-1.5">
                        {t('login.passkey_hint', {
                          defaultValue: 'Use a passkey from this device or another device. No passkey yet? Log in with your password, then set one up in Settings.',
                        })}
                      </p>
                    </>
                  )}

                  {/* Divider */}
                  <Divider className="my-6 bg-[var(--border-default)]" />

                  {/* Register Link */}
                  <p className="text-center text-theme-muted text-sm">
                    {t('login.no_account')}{' '}
                    <Link
                      to={tenantPath('/register')}
                      className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 font-medium transition-colors"
                    >
                      {t('login.create_account_link')}
                    </Link>
                  </p>
                </motion.div>
              ) : (
                // 2FA Form
                <motion.div
                  key="2fa"
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  exit={{ opacity: 0, x: -20 }}
                >
                  {/* Header */}
                  <div className="text-center mb-8">
                    <motion.div
                      initial={{ scale: 0.8 }}
                      animate={{ scale: 1 }}
                      className="inline-flex items-center justify-center w-12 h-12 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4"
                    >
                      <Shield className="w-8 h-8 text-indigo-500 dark:text-indigo-400" />
                    </motion.div>
                    <h1 className="text-xl sm:text-2xl font-bold text-theme-primary">
                      {t('login.twofa_title')}
                    </h1>
                    <p className="text-theme-muted mt-2">
                      {t('login.twofa_subtitle')}
                    </p>
                  </div>

                  {/* Error Alert */}
                  {error && (
                    <motion.div
                      initial={{ opacity: 0, y: -10 }}
                      animate={{ opacity: 1, y: 0 }}
                      className="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 text-sm"
                    >
                      {error}
                    </motion.div>
                  )}

                  {/* Form */}
                  <form onSubmit={handleVerify2FA} className="space-y-5">
                    <Input
                      type="text"
                      label={useBackupCode ? t('login.twofa_backup_code_label') : t('login.twofa_code_label')}
                      placeholder={useBackupCode ? t('login.twofa_backup_placeholder') : t('login.twofa_code_placeholder')}
                      value={twoFactorCode}
                      onChange={(e) => setTwoFactorCode(e.target.value.replace(/\D/g, '').slice(0, useBackupCode ? 8 : 6))}
                      startContent={<Shield className="w-4 h-4 text-theme-subtle" />}
                      isRequired
                      autoComplete="one-time-code"
                      classNames={{
                        inputWrapper: 'glass-card backdrop-blur-lg',
                        label: 'text-theme-muted',
                        input: 'text-theme-primary placeholder:text-theme-subtle text-center text-xl tracking-widest',
                      }}
                    />

                    <div className="space-y-3">
                      {twoFactorMethods.includes('backup_code') && (
                        <Checkbox
                          isSelected={useBackupCode}
                          onValueChange={(checked) => {
                            setUseBackupCode(checked);
                            setTwoFactorCode('');
                          }}
                          size="sm"
                          classNames={{
                            label: 'text-theme-muted text-sm',
                          }}
                        >
                          {t('login.twofa_use_backup')}
                        </Checkbox>
                      )}

                      <Checkbox
                        isSelected={trustDevice}
                        onValueChange={setTrustDevice}
                        size="sm"
                        classNames={{
                          label: 'text-theme-muted text-sm',
                        }}
                      >
                        {t('login.twofa_trust_device')}
                      </Checkbox>
                    </div>

                    <div className="flex gap-3">
                      <Button
                        type="button"
                        variant="flat"
                        onPress={handleBack2FA}
                        className="flex-1 bg-theme-elevated text-theme-muted hover:bg-theme-hover"
                        startContent={<ArrowLeft className="w-4 h-4" />}
                      >
                        {t('login.back')}
                      </Button>

                      <Button
                        type="submit"
                        isLoading={isLoading}
                        isDisabled={!twoFactorCode.trim()}
                        className="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
                        spinner={<Loader2 className="w-4 h-4 animate-spin" />}
                      >
                        {t('login.twofa_verify')}
                      </Button>
                    </div>
                  </form>

                  {/* Help text */}
                  <p className="mt-6 text-center text-theme-subtle text-xs">
                    {t('login.twofa_help')}
                  </p>
                </motion.div>
              )}
            </AnimatePresence>
          </GlassCard>

          {/* Back to home link */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 0.3 }}
            className="mt-6 text-center"
          >
            <Link
              to={tenantPath('/')}
              className="inline-flex items-center gap-2 text-theme-subtle hover:text-theme-secondary text-sm transition-colors"
            >
              <ArrowLeft className="w-4 h-4" />
              {t('login.back_to_home')}
            </Link>
          </motion.div>
        </motion.div>
      </div>
    </>
  );
}

export default LoginPage;
