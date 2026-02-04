/**
 * Forgot Password Page - Request password reset
 */

import { useState } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input } from '@heroui/react';
import { Mail, ArrowLeft, CheckCircle } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { api } from '@/lib/api';

export function ForgotPasswordPage() {
  const { branding } = useTenant();
  const [email, setEmail] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [error, setError] = useState('');

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!email.trim()) return;

    try {
      setIsLoading(true);
      setError('');
      await api.post('/v2/auth/password/forgot', { email });
      setIsSubmitted(true);
    } catch (err) {
      // Don't reveal if email exists or not for security
      setIsSubmitted(true);
    } finally {
      setIsLoading(false);
    }
  }

  if (isSubmitted) {
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
            <h1 className="text-2xl font-bold text-white mb-2">Check your email</h1>
            <p className="text-white/60 mb-6">
              If an account exists with that email address,
              we've sent instructions to reset your password.
            </p>
            <p className="text-white/40 text-sm mb-6">
              Didn't receive the email? Check your spam folder or try again.
            </p>
            <div className="flex flex-col gap-3">
              <Button
                onClick={() => setIsSubmitted(false)}
                variant="flat"
                className="bg-white/5 text-white"
              >
                Try another email
              </Button>
              <Link to="/login">
                <Button
                  variant="flat"
                  className="w-full bg-white/5 text-white"
                  startContent={<ArrowLeft className="w-4 h-4" />}
                >
                  Back to login
                </Button>
              </Link>
            </div>
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
          to="/login"
          className="flex items-center gap-2 text-white/60 hover:text-white transition-colors mb-6"
        >
          <ArrowLeft className="w-4 h-4" />
          Back to login
        </Link>

        <GlassCard className="p-8">
          {/* Header */}
          <div className="text-center mb-8">
            <h1 className="text-2xl font-bold text-white mb-2">Reset your password</h1>
            <p className="text-white/60">
              Enter your email and we'll send you instructions to reset your password.
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
            <Input
              type="email"
              label="Email address"
              placeholder="you@example.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              startContent={<Mail className="w-4 h-4 text-white/40" />}
              classNames={{
                input: 'bg-transparent text-white',
                inputWrapper: 'bg-white/5 border-white/10',
                label: 'text-white/80',
              }}
              isRequired
            />

            <Button
              type="submit"
              className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              isLoading={isLoading}
              isDisabled={!email.trim()}
            >
              Send reset instructions
            </Button>
          </form>

          {/* Footer */}
          <div className="mt-6 text-center text-sm text-white/50">
            Remember your password?{' '}
            <Link to="/login" className="text-indigo-400 hover:underline">
              Sign in
            </Link>
          </div>
        </GlassCard>

        {/* Branding */}
        <p className="text-center text-white/30 text-sm mt-6">
          {branding.name}
        </p>
      </motion.div>
    </div>
  );
}

export default ForgotPasswordPage;
