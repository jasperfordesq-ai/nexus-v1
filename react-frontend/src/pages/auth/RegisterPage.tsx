/**
 * Registration Page
 *
 * Full registration form matching PHP version with:
 * - Profile type (individual/organisation)
 * - Location with autocomplete
 * - Phone number
 * - Password validation (12 chars + complexity)
 * - Terms acceptance
 * - Newsletter opt-in
 */

import { useState, useEffect, useRef, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button, Input, Checkbox, Divider, Select, SelectItem } from '@heroui/react';
import { motion } from 'framer-motion';
import {
  User,
  Mail,
  Lock,
  Eye,
  EyeOff,
  ArrowLeft,
  Loader2,
  Building2,
  MapPin,
  Phone,
  Check,
  X,
} from 'lucide-react';
import { useAuth, useTenant } from '@/contexts';
import { GlassCard } from '@/components/ui';
import { api, tokenManager } from '@/lib/api';
import { PASSWORD_REQUIREMENTS, isPasswordValid } from '@/lib/validation';

interface Tenant {
  id: number;
  name: string;
  slug: string;
  domain?: string;
  tagline?: string;
  logo_url?: string;
}

type ProfileType = 'individual' | 'organisation';

export function RegisterPage() {
  const navigate = useNavigate();
  const { register, isAuthenticated, isLoading, error, clearError } = useAuth();
  const { tenant } = useTenant();

  // Bot protection
  const [formStartTime] = useState(() => Date.now());
  const honeypotRef = useRef<HTMLInputElement>(null);

  // Tenant state
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [selectedTenantId, setSelectedTenantId] = useState<string>('');
  const [tenantsLoading, setTenantsLoading] = useState(true);

  // Form state - Basic
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  // Form state - Profile
  const [profileType, setProfileType] = useState<ProfileType>('individual');
  const [organizationName, setOrganizationName] = useState('');

  // Form state - Contact
  const [location, setLocation] = useState('');
  const [phone, setPhone] = useState('');

  // Form state - Consents
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [newsletterOptIn, setNewsletterOptIn] = useState(false);

  // Fetch available tenants on mount
  useEffect(() => {
    const fetchTenants = async () => {
      try {
        const response = await api.get<Tenant[]>('/v2/tenants', { skipAuth: true, skipTenant: true });
        if (response.success && response.data) {
          setTenants(response.data);
          // If only one tenant, auto-select it
          if (response.data.length === 1) {
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
  }, []);

  // Handle tenant selection
  const handleTenantChange = (keys: unknown) => {
    const selectedKeys = keys as Set<string>;
    const tenantId = Array.from(selectedKeys)[0] || '';
    setSelectedTenantId(tenantId);
    if (tenantId) {
      tokenManager.setTenantId(tenantId);
    }
  };

  // Handle profile type selection
  const handleProfileTypeChange = (keys: unknown) => {
    const selectedKeys = keys as Set<string>;
    const type = Array.from(selectedKeys)[0] as ProfileType || 'individual';
    setProfileType(type);
    if (type === 'individual') {
      setOrganizationName('');
    }
  };

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
  }, [firstName, lastName, email, password, passwordConfirm, location, phone]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();

    // Bot protection checks
    const honeypotValue = honeypotRef.current?.value;
    if (honeypotValue) {
      // Bot detected - silently fail
      return;
    }

    const timeTaken = Date.now() - formStartTime;
    if (timeTaken < 3000) {
      // Form submitted too fast (< 3 seconds) - likely a bot
      return;
    }

    // Password validation
    if (!isPasswordValid(password)) {
      return;
    }

    if (password !== passwordConfirm) {
      return;
    }

    // Get selected tenant
    const selectedTenant = tenants.find((t) => String(t.id) === selectedTenantId);
    const tenantId = selectedTenant?.id || parseInt(selectedTenantId) || undefined;

    const success = await register({
      first_name: firstName,
      last_name: lastName,
      email,
      password,
      password_confirmation: passwordConfirm,
      tenant_id: tenantId,
      profile_type: profileType,
      organization_name: profileType === 'organisation' ? organizationName : undefined,
      location: location || undefined,
      phone: phone || undefined,
      terms_accepted: termsAccepted,
      newsletter_opt_in: newsletterOptIn,
    });

    if (success) {
      navigate('/dashboard', { replace: true });
    }
  };

  const passwordValid = isPasswordValid(password);
  const passwordsMatch = password === passwordConfirm;

  const isFormValid =
    firstName.trim() &&
    lastName.trim() &&
    email.trim() &&
    password.trim() &&
    passwordConfirm.trim() &&
    termsAccepted &&
    passwordValid &&
    passwordsMatch &&
    (profileType === 'individual' || organizationName.trim()) &&
    (tenants.length === 0 || selectedTenantId);

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
              <User className="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
            </motion.div>
            <h1 className="text-2xl font-bold text-theme-primary">Create Account</h1>
            <p className="text-theme-muted mt-2">
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
            {/* Honeypot - hidden from users, visible to bots */}
            <div className="hidden" aria-hidden="true">
              <label htmlFor="website">Website</label>
              <input
                ref={honeypotRef}
                type="text"
                name="website"
                id="website"
                tabIndex={-1}
                autoComplete="off"
              />
            </div>

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
                  trigger:
                    'bg-white/90 dark:bg-white/10 backdrop-blur-xl border border-gray-200 dark:border-white/10',
                  label: 'text-theme-muted',
                  value: 'text-theme-primary',
                  popoverContent:
                    'bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10',
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

            {/* Profile Type */}
            <Select
              label="Profile Type"
              placeholder="Select profile type"
              selectedKeys={new Set([profileType])}
              onSelectionChange={handleProfileTypeChange}
              isRequired
              classNames={{
                trigger:
                  'bg-white/90 dark:bg-white/10 backdrop-blur-xl border border-gray-200 dark:border-white/10',
                label: 'text-theme-muted',
                value: 'text-theme-primary',
                popoverContent:
                  'bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10',
              }}
            >
              <SelectItem
                key="individual"
                textValue="Individual"
                classNames={{
                  base: 'text-gray-900 dark:text-white data-[hover=true]:bg-gray-100 dark:data-[hover=true]:bg-white/10',
                }}
              >
                Individual
              </SelectItem>
              <SelectItem
                key="organisation"
                textValue="Organisation"
                classNames={{
                  base: 'text-gray-900 dark:text-white data-[hover=true]:bg-gray-100 dark:data-[hover=true]:bg-white/10',
                }}
              >
                Organisation
              </SelectItem>
            </Select>

            {/* Organisation Name - Only show for organisations */}
            {profileType === 'organisation' && (
              <Input
                type="text"
                label="Organisation Name"
                placeholder="e.g. Acme Corp"
                value={organizationName}
                onChange={(e) => setOrganizationName(e.target.value)}
                startContent={<Building2 className="w-4 h-4 text-theme-subtle" />}
                isRequired
                classNames={{
                  inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                }}
              />
            )}

            {/* Name Fields */}
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
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
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
                description={profileType === 'organisation' ? 'Only visible to admins' : undefined}
                classNames={{
                  inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                  description: 'text-theme-subtle text-xs',
                }}
              />
            </div>

            {/* Location */}
            <Input
              type="text"
              label="Location"
              placeholder="Your town or city"
              value={location}
              onChange={(e) => setLocation(e.target.value)}
              startContent={<MapPin className="w-4 h-4 text-theme-subtle" />}
              autoComplete="address-level2"
              classNames={{
                inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
                label: 'text-theme-muted',
                input: 'text-theme-primary placeholder:text-theme-subtle',
              }}
            />

            {/* Phone */}
            <Input
              type="tel"
              label="Phone Number"
              placeholder="e.g. 087 123 4567"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              startContent={<Phone className="w-4 h-4 text-theme-subtle" />}
              autoComplete="tel"
              description="Only visible to administrators"
              classNames={{
                inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
                label: 'text-theme-muted',
                input: 'text-theme-primary placeholder:text-theme-subtle',
                description: 'text-theme-subtle text-xs',
              }}
            />

            {/* Email */}
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
                inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
                label: 'text-theme-muted',
                input: 'text-theme-primary placeholder:text-theme-subtle',
              }}
            />

            {/* Password */}
            <div>
              <Input
                type={showPassword ? 'text' : 'password'}
                label="Password"
                placeholder="Create a strong password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                startContent={<Lock className="w-4 h-4 text-theme-subtle" />}
                endContent={
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="text-theme-subtle hover:text-theme-muted transition-colors"
                  >
                    {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                  </button>
                }
                isRequired
                autoComplete="new-password"
                classNames={{
                  inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                }}
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
                          passed ? 'text-emerald-500 dark:text-emerald-400' : 'text-theme-subtle'
                        }`}
                      >
                        {passed ? <Check className="w-3 h-3" /> : <X className="w-3 h-3" />}
                        {req.label}
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>

            {/* Confirm Password */}
            <Input
              type={showPassword ? 'text' : 'password'}
              label="Confirm Password"
              placeholder="Re-enter your password"
              value={passwordConfirm}
              onChange={(e) => setPasswordConfirm(e.target.value)}
              startContent={<Lock className="w-4 h-4 text-theme-subtle" />}
              isRequired
              autoComplete="new-password"
              isInvalid={passwordConfirm.length > 0 && !passwordsMatch}
              errorMessage={passwordConfirm.length > 0 && !passwordsMatch ? 'Passwords must match' : ''}
              classNames={{
                inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
                label: 'text-theme-muted',
                input: 'text-theme-primary placeholder:text-theme-subtle',
              }}
            />

            {/* Consents */}
            <div className="space-y-3 pt-2">
              <Checkbox
                isSelected={termsAccepted}
                onValueChange={setTermsAccepted}
                size="sm"
                classNames={{
                  label: 'text-theme-muted text-sm',
                }}
              >
                <span>
                  I agree to the{' '}
                  <Link
                    to="/terms"
                    className="text-indigo-600 dark:text-indigo-400 hover:underline"
                  >
                    Terms of Service
                  </Link>{' '}
                  and{' '}
                  <Link
                    to="/privacy"
                    className="text-indigo-600 dark:text-indigo-400 hover:underline"
                  >
                    Privacy Policy
                  </Link>
                  , and I am 18 years of age or older.
                </span>
              </Checkbox>

              <Checkbox
                isSelected={newsletterOptIn}
                onValueChange={setNewsletterOptIn}
                size="sm"
                classNames={{
                  label: 'text-theme-muted text-sm',
                }}
              >
                Send me occasional updates and tips
              </Checkbox>
            </div>

            {/* Data Protection Notice */}
            <div className="p-3 rounded-lg bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-xs text-theme-muted space-y-2">
              <p className="font-medium text-theme-primary">Data Protection Notice</p>
              <p>
                By clicking "Create Account," you are entering into a membership agreement. We
                collect your personal data solely to administer your account, facilitate safe
                exchanges between members, and send you essential community updates.
              </p>
              <p>
                <Link to="/privacy" className="text-indigo-600 dark:text-indigo-400 hover:underline">
                  View our full Privacy Policy
                </Link>
              </p>
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
          <Divider className="my-6 bg-theme-elevated" />

          {/* Login Link */}
          <p className="text-center text-theme-muted text-sm">
            Already have an account?{' '}
            <Link
              to="/login"
              className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 font-medium transition-colors"
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
            className="inline-flex items-center gap-2 text-theme-subtle hover:text-theme-muted text-sm transition-colors"
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
