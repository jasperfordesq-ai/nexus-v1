/**
 * Registration Page
 */

import { useState, useEffect, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button, Input, Checkbox, Divider } from '@heroui/react';
import { motion } from 'framer-motion';
import { User, Mail, Lock, Eye, EyeOff, ArrowLeft, Loader2 } from 'lucide-react';
import { useAuth, useTenant } from '@/contexts';
import { GlassCard } from '@/components/ui';

export function RegisterPage() {
  const navigate = useNavigate();
  const { register, isAuthenticated, isLoading, error, clearError } = useAuth();
  const { tenant } = useTenant();

  // Form state
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [newsletterOptIn, setNewsletterOptIn] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  // Validation state
  const [passwordErrors, setPasswordErrors] = useState<string[]>([]);

  // Redirect after successful registration
  useEffect(() => {
    if (isAuthenticated) {
      navigate('/dashboard', { replace: true });
    }
  }, [isAuthenticated, navigate]);

  // Clear error when form changes
  useEffect(() => {
    if (error) {
      clearError();
    }
  }, [firstName, lastName, email, password, passwordConfirm]); // eslint-disable-line react-hooks/exhaustive-deps

  // Validate password (OWASP compliant)
  useEffect(() => {
    const errors: string[] = [];
    if (password.length > 0) {
      if (password.length < 8) {
        errors.push('At least 8 characters');
      }
      if (!/[A-Z]/.test(password)) {
        errors.push('One uppercase letter');
      }
      if (!/[a-z]/.test(password)) {
        errors.push('One lowercase letter');
      }
      if (!/[0-9]/.test(password)) {
        errors.push('One number');
      }
      if (!/[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?]/.test(password)) {
        errors.push('One special character');
      }
      if (passwordConfirm && password !== passwordConfirm) {
        errors.push('Passwords must match');
      }
    }
    setPasswordErrors(errors);
  }, [password, passwordConfirm]);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();

    if (passwordErrors.length > 0) {
      return;
    }

    const success = await register({
      first_name: firstName,
      last_name: lastName,
      email,
      password,
      password_confirmation: passwordConfirm,
      tenant_slug: tenant?.slug || import.meta.env.VITE_DEFAULT_TENANT_SLUG || 'default',
      terms_accepted: termsAccepted,
      newsletter_opt_in: newsletterOptIn,
    });

    if (success) {
      navigate('/dashboard', { replace: true });
    }
  };

  const isFormValid =
    firstName.trim() &&
    lastName.trim() &&
    email.trim() &&
    password.trim() &&
    passwordConfirm.trim() &&
    termsAccepted &&
    passwordErrors.length === 0;

  return (
    <div className="min-h-screen flex items-center justify-center p-4 py-12">
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
          {/* Header */}
          <div className="text-center mb-8">
            <motion.div
              initial={{ scale: 0.8 }}
              animate={{ scale: 1 }}
              className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4"
            >
              <User className="w-8 h-8 text-indigo-400" />
            </motion.div>
            <h1 className="text-2xl font-bold text-white">Create Account</h1>
            <p className="text-white/60 mt-2">
              Join {tenant?.name || 'NEXUS'} and start exchanging time
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
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <Input
                type="text"
                label="First Name"
                placeholder="John"
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
                isRequired
                autoComplete="given-name"
                classNames={{
                  inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
                  label: 'text-white/70',
                  input: 'text-white placeholder:text-white/30',
                }}
              />

              <Input
                type="text"
                label="Last Name"
                placeholder="Doe"
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
                isRequired
                autoComplete="family-name"
                classNames={{
                  inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
                  label: 'text-white/70',
                  input: 'text-white placeholder:text-white/30',
                }}
              />
            </div>

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
              autoComplete="new-password"
              classNames={{
                inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
                label: 'text-white/70',
                input: 'text-white placeholder:text-white/30',
              }}
            />

            {/* Password requirements */}
            {password.length > 0 && (
              <div className="space-y-1 text-xs">
                {['At least 8 characters', 'One uppercase letter', 'One number'].map((req) => {
                  const isMet =
                    (req === 'At least 8 characters' && password.length >= 8) ||
                    (req === 'One uppercase letter' && /[A-Z]/.test(password)) ||
                    (req === 'One number' && /[0-9]/.test(password));

                  return (
                    <div
                      key={req}
                      className={`flex items-center gap-2 ${
                        isMet ? 'text-green-400' : 'text-white/40'
                      }`}
                    >
                      <div
                        className={`w-1.5 h-1.5 rounded-full ${
                          isMet ? 'bg-green-400' : 'bg-white/30'
                        }`}
                      />
                      {req}
                    </div>
                  );
                })}
              </div>
            )}

            <Input
              type={showPassword ? 'text' : 'password'}
              label="Confirm Password"
              placeholder="••••••••"
              value={passwordConfirm}
              onChange={(e) => setPasswordConfirm(e.target.value)}
              startContent={<Lock className="w-4 h-4 text-white/40" />}
              isRequired
              autoComplete="new-password"
              isInvalid={passwordConfirm.length > 0 && password !== passwordConfirm}
              errorMessage={passwordConfirm.length > 0 && password !== passwordConfirm ? 'Passwords must match' : ''}
              classNames={{
                inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
                label: 'text-white/70',
                input: 'text-white placeholder:text-white/30',
              }}
            />

            <div className="space-y-3 pt-2">
              <Checkbox
                isSelected={termsAccepted}
                onValueChange={setTermsAccepted}
                size="sm"
                classNames={{
                  label: 'text-white/60 text-sm',
                }}
              >
                I agree to the{' '}
                <Link to="/terms" className="text-indigo-400 hover:underline">
                  Terms of Service
                </Link>{' '}
                and{' '}
                <Link to="/privacy" className="text-indigo-400 hover:underline">
                  Privacy Policy
                </Link>
              </Checkbox>

              <Checkbox
                isSelected={newsletterOptIn}
                onValueChange={setNewsletterOptIn}
                size="sm"
                classNames={{
                  label: 'text-white/60 text-sm',
                }}
              >
                Send me occasional updates and tips
              </Checkbox>
            </div>

            <Button
              type="submit"
              isLoading={isLoading}
              isDisabled={!isFormValid}
              className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
              size="lg"
              spinner={<Loader2 className="w-4 h-4 animate-spin" />}
            >
              Create Account
            </Button>
          </form>

          {/* Divider */}
          <Divider className="my-6 bg-white/10" />

          {/* Login Link */}
          <p className="text-center text-white/60 text-sm">
            Already have an account?{' '}
            <Link
              to="/login"
              className="text-indigo-400 hover:text-indigo-300 font-medium transition-colors"
            >
              Sign in
            </Link>
          </p>
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
  );
}

export default RegisterPage;
