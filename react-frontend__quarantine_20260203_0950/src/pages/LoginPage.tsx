/**
 * Login Page with 2FA Support
 *
 * Two-view component:
 * 1. Email/Password form (status: idle, logging_in, error)
 * 2. 2FA code form (status: requires_2fa)
 *
 * Uses AuthContext state machine for proper state management.
 * The two_factor_token is stored in AuthContext memory, not localStorage.
 */

import { useState, useEffect, type FormEvent } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Input,
  Button,
  Divider,
  Checkbox,
} from '@heroui/react';
import { useAuth } from '../auth';
import { useTenant } from '../tenant';
import { ApiClientError, verify2FA } from '../api';
import type { User } from '../api';

export function LoginPage() {
  const tenant = useTenant();
  const {
    login,
    setUser,
    clearTwoFactorChallenge,
    isAuthenticated,
    isLoading,
    status,
    error: authError,
    twoFactorChallenge,
  } = useAuth();
  const navigate = useNavigate();

  // Login form state
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [localError, setLocalError] = useState<string | null>(null);

  // 2FA form state
  const [totpCode, setTotpCode] = useState('');
  const [useBackupCode, setUseBackupCode] = useState(false);
  const [trustDevice, setTrustDevice] = useState(false);
  const [is2FALoading, setIs2FALoading] = useState(false);
  const [twoFAError, setTwoFAError] = useState<string | null>(null);

  // Combined error from auth context or local validation
  const error = localError || authError;

  // Redirect if already authenticated
  useEffect(() => {
    if (isAuthenticated) {
      navigate('/', { replace: true });
    }
  }, [isAuthenticated, navigate]);

  // Clear stale auth errors on mount (e.g., from previous session expiry)
  useEffect(() => {
    if (status === 'error') {
      clearTwoFactorChallenge();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []); // Run only on mount

  // Return null while redirecting
  if (isAuthenticated) {
    return null;
  }

  // Clear any stale auth errors when user starts typing
  const clearErrors = () => {
    if (localError) setLocalError(null);
    // Also clear authError from context if in error state
    if (status === 'error') {
      clearTwoFactorChallenge(); // This clears error and resets to idle
    }
  };

  const handleLoginSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setLocalError(null);

    if (!email || !password) {
      setLocalError('Please enter your email and password');
      return;
    }

    try {
      const response = await login({ email, password });

      // If 2FA is required, the AuthContext state machine will
      // transition to 'requires_2fa' and store the challenge data
      if ('requires_2fa' in response && response.requires_2fa) {
        // Clear the TOTP code field for fresh input
        setTotpCode('');
        return;
      }

      // Success - redirect to home (handled by useEffect above)
    } catch (err) {
      if (err instanceof ApiClientError) {
        setLocalError(err.message);
      } else {
        setLocalError('An unexpected error occurred. Please try again.');
      }
    }
  };

  const handle2FASubmit = async (e: FormEvent) => {
    e.preventDefault();
    setTwoFAError(null);

    if (!totpCode || !twoFactorChallenge) {
      setTwoFAError('Please enter your verification code');
      return;
    }

    setIs2FALoading(true);

    try {
      const response = await verify2FA({
        two_factor_token: twoFactorChallenge.two_factor_token,
        code: totpCode,
        use_backup_code: useBackupCode,
        trust_device: trustDevice,
      });

      if (response.success && response.user) {
        // Update auth context with user (transitions to 'authenticated')
        setUser(response.user as User);
        // Navigate handled by useEffect
      }
    } catch (err) {
      if (err instanceof ApiClientError) {
        // Check if token expired or max attempts exceeded
        if (err.code === 'AUTH_2FA_TOKEN_EXPIRED' || err.code === 'AUTH_2FA_MAX_ATTEMPTS') {
          // Clear 2FA challenge and return to login form
          clearTwoFactorChallenge();
          setTotpCode('');
          setLocalError(err.message);
        } else {
          setTwoFAError(err.message);
        }
      } else {
        setTwoFAError('An unexpected error occurred. Please try again.');
      }
    } finally {
      setIs2FALoading(false);
    }
  };

  const handleBackToLogin = () => {
    clearTwoFactorChallenge();
    setTotpCode('');
    setUseBackupCode(false);
    setTrustDevice(false);
    setTwoFAError(null);
    setLocalError(null);
  };

  // =========================================
  // 2FA Verification Form
  // =========================================
  if (status === 'requires_2fa' && twoFactorChallenge) {
    return (
      <div className="min-h-[60vh] flex items-center justify-center">
        <Card className="w-full max-w-md">
          <CardHeader className="flex flex-col gap-1 px-6 pt-6">
            <div className="mx-auto mb-2">
              <svg
                className="h-12 w-12 text-primary"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                />
              </svg>
            </div>
            <h1 className="text-2xl font-bold text-center">Two-Factor Authentication</h1>
            <p className="text-sm text-gray-500 text-center">
              Hi {twoFactorChallenge.user.first_name}, enter the code from your authenticator app
            </p>
          </CardHeader>

          <Divider />

          <CardBody className="px-6 py-6">
            <form onSubmit={handle2FASubmit} className="space-y-4">
              {twoFAError && (
                <div className="bg-danger-50 border border-danger-200 text-danger-700 px-4 py-3 rounded text-sm">
                  {twoFAError}
                </div>
              )}

              <Input
                label={useBackupCode ? 'Backup Code' : 'Verification Code'}
                type="text"
                inputMode={useBackupCode ? 'text' : 'numeric'}
                pattern={useBackupCode ? '[A-Za-z0-9-]+' : '[0-9]*'}
                value={totpCode}
                onValueChange={setTotpCode}
                placeholder={useBackupCode ? 'XXXX-XXXX-XXXX' : '000000'}
                isRequired
                autoComplete="one-time-code"
                autoFocus
                maxLength={useBackupCode ? 14 : 6}
                classNames={{
                  input: 'text-center text-2xl tracking-widest font-mono',
                }}
              />

              <div className="space-y-2">
                <Checkbox
                  size="sm"
                  isSelected={trustDevice}
                  onValueChange={setTrustDevice}
                >
                  Trust this device for 30 days
                </Checkbox>

                <Checkbox
                  size="sm"
                  isSelected={useBackupCode}
                  onValueChange={(checked) => {
                    setUseBackupCode(checked);
                    setTotpCode('');
                  }}
                >
                  Use backup code instead
                </Checkbox>
              </div>

              <Button
                type="submit"
                color="primary"
                className="w-full"
                isLoading={is2FALoading}
              >
                Verify
              </Button>

              <Button
                type="button"
                variant="flat"
                className="w-full"
                onPress={handleBackToLogin}
              >
                Use Different Account
              </Button>
            </form>
          </CardBody>
        </Card>
      </div>
    );
  }

  // =========================================
  // Login Form
  // =========================================
  return (
    <div className="min-h-[60vh] flex items-center justify-center">
      <Card className="w-full max-w-md">
        <CardHeader className="flex flex-col gap-1 px-6 pt-6">
          <h1 className="text-2xl font-bold">Sign In</h1>
          <p className="text-sm text-gray-500">
            Sign in to your {tenant.name} account
          </p>
        </CardHeader>

        <Divider />

        <CardBody className="px-6 py-6">
          <form onSubmit={handleLoginSubmit} className="space-y-4">
            {error && (
              <div className="bg-danger-50 border border-danger-200 text-danger-700 px-4 py-3 rounded">
                {error}
              </div>
            )}

            <Input
              label="Email"
              type="email"
              value={email}
              onValueChange={(val) => { setEmail(val); clearErrors(); }}
              placeholder="you@example.com"
              isRequired
              autoComplete="email"
            />

            <Input
              label="Password"
              type="password"
              value={password}
              onValueChange={(val) => { setPassword(val); clearErrors(); }}
              placeholder="Enter your password"
              isRequired
              autoComplete="current-password"
            />

            <Button
              type="submit"
              color="primary"
              className="w-full"
              isLoading={isLoading || status === 'logging_in'}
            >
              Sign In
            </Button>

            <p className="text-center text-sm text-gray-500">
              Don't have an account?{' '}
              <Link to="/" className="text-primary hover:underline">
                Contact us
              </Link>
            </p>
          </form>
        </CardBody>
      </Card>
    </div>
  );
}
