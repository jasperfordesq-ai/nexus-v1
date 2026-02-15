/**
 * Login Page with 2FA Support and Tenant Selection
 * Theme-aware styling for light and dark modes
 */

import { useState, useEffect, type FormEvent } from 'react';
import { Link, useNavigate, useLocation, useSearchParams } from 'react-router-dom';
import { Button, Input, Checkbox, Divider, Select, SelectItem } from '@heroui/react';
import { motion, AnimatePresence } from 'framer-motion';
import { Mail, Lock, Eye, EyeOff, Shield, ArrowLeft, Loader2, Building2 } from 'lucide-react';
import { useAuth, useTenant } from '@/contexts';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { usePageTitle } from '@/hooks';
import { api, tokenManager } from '@/lib/api';

interface Tenant {
  id: number;
  name: string;
  slug: string;
  domain?: string;
  tagline?: string;
  logo_url?: string;
}

export function LoginPage() {
  usePageTitle('Sign In');
  const navigate = useNavigate();
  const location = useLocation();
  const {
    status,
    error,
    isAuthenticated,
    login,
    verify2FA,
    clearError,
    cancel2FA,
    twoFactorMethods,
  } = useAuth();
  const { branding, tenantSlug, tenantPath } = useTenant();
  const [searchParams] = useSearchParams();

  // Tenant state
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [selectedTenantId, setSelectedTenantId] = useState<string>('');
  const [tenantsLoading, setTenantsLoading] = useState(true);

  // Form state
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  // 2FA state
  const [twoFactorCode, setTwoFactorCode] = useState('');
  const [useBackupCode, setUseBackupCode] = useState(false);
  const [trustDevice, setTrustDevice] = useState(false);

  // Redirect after successful login (preserve tenant slug prefix)
  const from = (location.state as { from?: string })?.from || tenantPath('/dashboard');

  // Clear stale auth tokens on mount — login page should always start clean
  // This prevents tenant mismatch errors when switching between tenants
  useEffect(() => {
    tokenManager.clearTokens();
  }, []);

  // Fetch available tenants on mount, with ?tenant= hint support (TRS-001 Phase 0)
  useEffect(() => {
    const fetchTenants = async () => {
      try {
        const response = await api.get<Tenant[]>('/v2/tenants', { skipAuth: true, skipTenant: true });
        if (response.success && response.data) {
          setTenants(response.data);

          // Priority: URL slug prefix > ?tenant= query param > auto-select single
          const tenantHint = tenantSlug || searchParams.get('tenant');
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
      } catch {
        // Silently fail - tenants will be empty
      } finally {
        setTenantsLoading(false);
      }
    };
    fetchTenants();
  }, [tenantSlug, searchParams]);

  // Handle tenant selection
  const handleTenantChange = (keys: unknown) => {
    // HeroUI Select returns a Set
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

    if (!email.trim() || !password.trim()) {
      return;
    }

    // Ensure tenant is selected before login
    if (tenants.length > 0 && !selectedTenantId) {
      return;
    }

    // Clear any stale tokens from a previous session before attempting login
    // This prevents X-Tenant-ID mismatch errors when switching between tenants
    tokenManager.clearTokens();

    // Ensure the selected tenant ID is set for the login request
    if (selectedTenantId) {
      tokenManager.setTenantId(selectedTenantId);
    }

    await login({ email, password });
  };

  const handleVerify2FA = async (e: FormEvent) => {
    e.preventDefault();

    const code = twoFactorCode.trim();
    const expectedLength = useBackupCode ? 8 : 6;

    // Validate 2FA code format before submission
    if (!code || code.length !== expectedLength || !/^\d+$/.test(code)) {
      return;
    }

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
  const canSubmit = email.trim() && password.trim() && (tenants.length === 0 || selectedTenantId);

  return (
    <>
      <PageMeta title="Log In" description="Sign in to your account" noIndex />
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
        <GlassCard className="p-8">
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
                    className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4"
                  >
                    <Mail className="w-8 h-8 text-indigo-500 dark:text-indigo-400" />
                  </motion.div>
                  <h1 className="text-2xl font-bold text-theme-primary">Welcome Back</h1>
                  <p className="text-theme-muted mt-2">
                    Sign in to continue to {branding.name}
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
                <form onSubmit={handleLogin} className="space-y-5">
                  {/* Tenant Selector - Only show if multiple tenants */}
                  {!tenantsLoading && tenants.length > 1 && (
                    <Select
                      label="Community"
                      placeholder="Select your community"
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
                      {tenants.map((tenant) => (
                        <SelectItem
                          key={String(tenant.id)}
                          textValue={tenant.name}
                          classNames={{
                            base: 'text-gray-900 dark:text-white data-[hover=true]:bg-gray-100 dark:data-[hover=true]:bg-white/10',
                          }}
                        >
                          <div className="flex flex-col">
                            <span className="text-gray-900 dark:text-white">{tenant.name}</span>
                            {tenant.tagline && (
                              <span className="text-gray-500 dark:text-gray-400 text-xs">{tenant.tagline}</span>
                            )}
                          </div>
                        </SelectItem>
                      ))}
                    </Select>
                  )}

                  {/* Show selected tenant if only one */}
                  {!tenantsLoading && tenants.length === 1 && (
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
                    label="Email"
                    placeholder="you@example.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    startContent={<Mail className="w-4 h-4 text-theme-subtle" />}
                    isRequired
                    autoComplete="email"
                    classNames={{
                      inputWrapper: 'glass-card',
                      label: 'text-theme-muted',
                      input: 'text-theme-primary placeholder:text-theme-subtle',
                    }}
                  />

                  <Input
                    type={showPassword ? 'text' : 'password'}
                    label="Password"
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
                        aria-label={showPassword ? 'Hide password' : 'Show password'}
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
                      inputWrapper: 'glass-card',
                      label: 'text-theme-muted',
                      input: 'text-theme-primary placeholder:text-theme-subtle',
                    }}
                  />

                  <div className="flex items-center justify-between">
                    <Checkbox
                      isSelected={remember}
                      onValueChange={setRemember}
                      size="sm"
                      classNames={{
                        label: 'text-theme-muted text-sm',
                      }}
                    >
                      Remember me
                    </Checkbox>

                    <Link
                      to={tenantPath('/password/forgot')}
                      className="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 transition-colors"
                    >
                      Forgot password?
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
                    Sign In
                  </Button>
                </form>

                {/* Divider */}
                <Divider className="my-6 bg-[var(--border-default)]" />

                {/* Register Link */}
                <p className="text-center text-theme-muted text-sm">
                  Don&apos;t have an account?{' '}
                  <Link
                    to={tenantPath('/register')}
                    className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 font-medium transition-colors"
                  >
                    Create one
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
                    className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4"
                  >
                    <Shield className="w-8 h-8 text-indigo-500 dark:text-indigo-400" />
                  </motion.div>
                  <h1 className="text-2xl font-bold text-theme-primary">
                    Two-Factor Authentication
                  </h1>
                  <p className="text-theme-muted mt-2">
                    Enter the code from your authenticator app
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
                    label={useBackupCode ? 'Backup Code' : 'Authentication Code'}
                    placeholder={useBackupCode ? 'XXXX-XXXX' : '000000'}
                    value={twoFactorCode}
                    onChange={(e) => setTwoFactorCode(e.target.value.replace(/\D/g, '').slice(0, useBackupCode ? 8 : 6))}
                    startContent={<Shield className="w-4 h-4 text-theme-subtle" />}
                    isRequired
                    autoComplete="one-time-code"
                    classNames={{
                      inputWrapper: 'glass-card',
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
                        Use backup code instead
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
                      Trust this device for 30 days
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
                      Back
                    </Button>

                    <Button
                      type="submit"
                      isLoading={isLoading}
                      isDisabled={!twoFactorCode.trim()}
                      className="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
                      spinner={<Loader2 className="w-4 h-4 animate-spin" />}
                    >
                      Verify
                    </Button>
                  </div>
                </form>

                {/* Help text */}
                <p className="mt-6 text-center text-theme-subtle text-xs">
                  Lost access to your authenticator? Contact support for help recovering your account.
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
            Back to home
          </Link>
        </motion.div>
      </motion.div>
      </div>
    </>
  );
}

export default LoginPage;
