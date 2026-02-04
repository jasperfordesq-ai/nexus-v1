/**
 * Login Page with 2FA Support
 */

import { useState, useEffect, type FormEvent } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { Button, Input, Checkbox, Divider } from '@heroui/react';
import { motion, AnimatePresence } from 'framer-motion';
import { Mail, Lock, Eye, EyeOff, Shield, ArrowLeft, Loader2 } from 'lucide-react';
import { useAuth } from '@/contexts';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';

export function LoginPage() {
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

  // Form state
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  // 2FA state
  const [twoFactorCode, setTwoFactorCode] = useState('');
  const [useBackupCode, setUseBackupCode] = useState(false);
  const [trustDevice, setTrustDevice] = useState(false);

  // Redirect after successful login
  const from = (location.state as { from?: string })?.from || '/dashboard';

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
  }, [email, password, twoFactorCode]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleLogin = async (e: FormEvent) => {
    e.preventDefault();

    if (!email.trim() || !password.trim()) {
      return;
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
                    <Mail className="w-8 h-8 text-indigo-400" />
                  </motion.div>
                  <h1 className="text-2xl font-bold text-white">Welcome Back</h1>
                  <p className="text-white/60 mt-2">
                    Sign in to continue to NEXUS
                  </p>
                </div>

                {/* Error Alert */}
                {error && (
                  <motion.div
                    initial={{ opacity: 0, y: -10 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm"
                  >
                    {error}
                  </motion.div>
                )}

                {/* Form */}
                <form onSubmit={handleLogin} className="space-y-5">
                  <Input
                    type="email"
                    label="Email"
                    placeholder="you@example.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    startContent={<Mail className="w-4 h-4 text-white/40" />}
                    isRequired
                    autoComplete="email"
                    classNames={{
                      inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
                      label: 'text-white/70',
                      input: 'text-white placeholder:text-white/30',
                    }}
                  />

                  <Input
                    type={showPassword ? 'text' : 'password'}
                    label="Password"
                    placeholder="••••••••"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    startContent={<Lock className="w-4 h-4 text-white/40" />}
                    endContent={
                      <button
                        type="button"
                        onClick={() => setShowPassword(!showPassword)}
                        className="text-white/40 hover:text-white/70 transition-colors"
                      >
                        {showPassword ? (
                          <EyeOff className="w-4 h-4" />
                        ) : (
                          <Eye className="w-4 h-4" />
                        )}
                      </button>
                    }
                    isRequired
                    autoComplete="current-password"
                    classNames={{
                      inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
                      label: 'text-white/70',
                      input: 'text-white placeholder:text-white/30',
                    }}
                  />

                  <div className="flex items-center justify-between">
                    <Checkbox
                      isSelected={remember}
                      onValueChange={setRemember}
                      size="sm"
                      classNames={{
                        label: 'text-white/60 text-sm',
                      }}
                    >
                      Remember me
                    </Checkbox>

                    <Link
                      to="/password/forgot"
                      className="text-sm text-indigo-400 hover:text-indigo-300 transition-colors"
                    >
                      Forgot password?
                    </Link>
                  </div>

                  <Button
                    type="submit"
                    isLoading={isLoading}
                    isDisabled={!email.trim() || !password.trim()}
                    className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
                    size="lg"
                    spinner={<Loader2 className="w-4 h-4 animate-spin" />}
                  >
                    Sign In
                  </Button>
                </form>

                {/* Divider */}
                <Divider className="my-6 bg-white/10" />

                {/* Register Link */}
                <p className="text-center text-white/60 text-sm">
                  Don&apos;t have an account?{' '}
                  <Link
                    to="/register"
                    className="text-indigo-400 hover:text-indigo-300 font-medium transition-colors"
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
                    <Shield className="w-8 h-8 text-indigo-400" />
                  </motion.div>
                  <h1 className="text-2xl font-bold text-white">
                    Two-Factor Authentication
                  </h1>
                  <p className="text-white/60 mt-2">
                    Enter the code from your authenticator app
                  </p>
                </div>

                {/* Error Alert */}
                {error && (
                  <motion.div
                    initial={{ opacity: 0, y: -10 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm"
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
                    startContent={<Shield className="w-4 h-4 text-white/40" />}
                    isRequired
                    autoComplete="one-time-code"
                    classNames={{
                      inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
                      label: 'text-white/70',
                      input: 'text-white placeholder:text-white/30 text-center text-xl tracking-widest',
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
                          label: 'text-white/60 text-sm',
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
                        label: 'text-white/60 text-sm',
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
                      className="flex-1 bg-white/5 text-white/70 hover:bg-white/10"
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
                <p className="mt-6 text-center text-white/40 text-xs">
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
            to="/"
            className="inline-flex items-center gap-2 text-white/50 hover:text-white/80 text-sm transition-colors"
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
