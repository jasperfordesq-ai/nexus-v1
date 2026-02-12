/**
 * Registration Page
 *
 * Step-by-step registration form for better mobile UX:
 * - Step 1: Community & Profile Type
 * - Step 2: Personal Details (name, location, phone)
 * - Step 3: Account Setup (email, password)
 * - Step 4: Terms & Consent
 *
 * Desktop shows all fields, mobile shows one step at a time
 */

import { useState, useEffect, useRef, type FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Button, Input, Checkbox, Divider, Select, SelectItem, Progress } from '@heroui/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  User,
  Mail,
  Lock,
  Eye,
  EyeOff,
  ArrowLeft,
  ArrowRight,
  Loader2,
  Building2,
  MapPin,
  Phone,
  Check,
  X,
  ChevronLeft,
} from 'lucide-react';
import { useAuth, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { GlassCard } from '@/components/ui';
import { api, tokenManager } from '@/lib/api';
import { PASSWORD_REQUIREMENTS, isPasswordValid, getPasswordStrength } from '@/lib/validation';

interface Tenant {
  id: number;
  name: string;
  slug: string;
  domain?: string;
  tagline?: string;
  logo_url?: string;
}

type ProfileType = 'individual' | 'organisation';

const STEPS = [
  { id: 1, title: 'Community', shortTitle: 'Community' },
  { id: 2, title: 'Your Details', shortTitle: 'Details' },
  { id: 3, title: 'Account', shortTitle: 'Account' },
  { id: 4, title: 'Terms', shortTitle: 'Terms' },
];

export function RegisterPage() {
  usePageTitle('Create Account');
  const navigate = useNavigate();
  const { register, isAuthenticated, isLoading, error, clearError } = useAuth();
  const { tenant, tenantSlug, tenantPath } = useTenant();
  const [searchParams] = useSearchParams();

  // Step state (1-4)
  const [currentStep, setCurrentStep] = useState(1);
  const [isMobile, setIsMobile] = useState(false);

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

  // Detect mobile viewport
  useEffect(() => {
    const checkMobile = () => setIsMobile(window.innerWidth < 640);
    checkMobile();
    window.addEventListener('resize', checkMobile);
    return () => window.removeEventListener('resize', checkMobile);
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
    const type = (Array.from(selectedKeys)[0] as ProfileType) || 'individual';
    setProfileType(type);
    if (type === 'individual') {
      setOrganizationName('');
    }
  };

  // Redirect after successful registration
  useEffect(() => {
    if (isAuthenticated) {
      navigate(tenantPath('/dashboard'), { replace: true });
    }
  }, [isAuthenticated, navigate]);

  // Clear error when form changes
  useEffect(() => {
    if (error) {
      clearError();
    }
  }, [firstName, lastName, email, password, passwordConfirm, location, phone]); // eslint-disable-line react-hooks/exhaustive-deps

  // Validation for each step
  const isStep1Valid = tenants.length === 0 || tenants.length === 1 || selectedTenantId;
  const isStep2Valid =
    firstName.trim() &&
    lastName.trim() &&
    (profileType === 'individual' || organizationName.trim());
  const isStep3Valid =
    email.trim() &&
    password.trim() &&
    passwordConfirm.trim() &&
    isPasswordValid(password) &&
    password === passwordConfirm;
  const isStep4Valid = termsAccepted;

  const canProceed = () => {
    switch (currentStep) {
      case 1:
        return isStep1Valid;
      case 2:
        return isStep2Valid;
      case 3:
        return isStep3Valid;
      case 4:
        return isStep4Valid;
      default:
        return false;
    }
  };

  const handleNext = () => {
    if (currentStep < 4 && canProceed()) {
      setCurrentStep(currentStep + 1);
    }
  };

  const handleBack = () => {
    if (currentStep > 1) {
      setCurrentStep(currentStep - 1);
    }
  };

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
      clearError();
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
      navigate(tenantPath('/dashboard'), { replace: true });
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

  // Step progress percentage
  const progressPercent = (currentStep / STEPS.length) * 100;

  // Render step content
  const renderStepContent = (step: number) => {
    switch (step) {
      case 1:
        return (
          <div className="space-y-4">
            {/* Tenant Selector - Only show if multiple tenants */}
            {!tenantsLoading && tenants.length > 1 && (
              <Select
                label="Community"
                placeholder="Select your community"
                selectedKeys={selectedTenantId ? new Set([selectedTenantId]) : new Set()}
                onSelectionChange={handleTenantChange}
                startContent={<Building2 className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
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
                        <span className="text-gray-500 dark:text-gray-400 text-xs">
                          {t.tagline}
                        </span>
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
                  <Building2 className="w-5 h-5 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                  <div>
                    <p className="text-gray-900 dark:text-white font-medium">{tenants[0].name}</p>
                    {tenants[0].tagline && (
                      <p className="text-gray-500 dark:text-gray-400 text-xs">
                        {tenants[0].tagline}
                      </p>
                    )}
                  </div>
                </div>
              </div>
            )}

            {/* No tenants message */}
            {!tenantsLoading && tenants.length === 0 && (
              <div className="p-3 rounded-xl bg-white/90 dark:bg-white/10 backdrop-blur-xl border border-gray-200 dark:border-white/10 text-center">
                <p className="text-theme-muted text-sm">
                  Joining {tenant?.name || 'NEXUS'}
                </p>
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
                startContent={<Building2 className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                isRequired
                classNames={{
                  inputWrapper:
                    'glass-card border-glass-border hover:border-glass-border-hover',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                }}
              />
            )}
          </div>
        );

      case 2:
        return (
          <div className="space-y-4">
            {/* Name Fields */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                type="text"
                label="First Name"
                placeholder="John"
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
                isRequired
                autoComplete="given-name"
                classNames={{
                  inputWrapper:
                    'glass-card border-glass-border hover:border-glass-border-hover',
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
                description={
                  profileType === 'organisation' ? 'Only visible to admins' : undefined
                }
                classNames={{
                  inputWrapper:
                    'glass-card border-glass-border hover:border-glass-border-hover',
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
              startContent={<MapPin className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              autoComplete="address-level2"
              classNames={{
                inputWrapper:
                  'glass-card border-glass-border hover:border-glass-border-hover',
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
              startContent={<Phone className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              autoComplete="tel"
              description="Only visible to administrators"
              classNames={{
                inputWrapper:
                  'glass-card border-glass-border hover:border-glass-border-hover',
                label: 'text-theme-muted',
                input: 'text-theme-primary placeholder:text-theme-subtle',
                description: 'text-theme-subtle text-xs',
              }}
            />
          </div>
        );

      case 3:
        return (
          <div className="space-y-4">
            {/* Email */}
            <Input
              type="email"
              label="Email"
              placeholder="you@example.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              startContent={<Mail className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              isRequired
              autoComplete="email"
              classNames={{
                inputWrapper:
                  'glass-card border-glass-border hover:border-glass-border-hover',
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
                startContent={<Lock className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
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
                autoComplete="new-password"
                classNames={{
                  inputWrapper:
                    'glass-card border-glass-border hover:border-glass-border-hover',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                }}
              />

              {/* Password strength indicator */}
              {password && (
                <div className="mt-2 space-y-2">
                  <Progress
                    value={getPasswordStrength(password)}
                    color={
                      getPasswordStrength(password) < 40
                        ? 'danger'
                        : getPasswordStrength(password) < 80
                          ? 'warning'
                          : 'success'
                    }
                    size="sm"
                    aria-label="Password strength"
                  />
                  <ul className="space-y-1 text-xs">
                    {PASSWORD_REQUIREMENTS.map((req) => {
                      const passed = req.test(password);
                      return (
                        <li
                          key={req.id}
                          className={`flex items-center gap-1.5 ${
                            passed
                              ? 'text-emerald-500 dark:text-emerald-400'
                              : 'text-theme-subtle'
                          }`}
                        >
                          {passed ? (
                            <Check className="w-3 h-3" aria-hidden="true" />
                          ) : (
                            <X className="w-3 h-3" aria-hidden="true" />
                          )}
                          {req.label}
                        </li>
                      );
                    })}
                  </ul>
                </div>
              )}
            </div>

            {/* Confirm Password */}
            <Input
              type={showPassword ? 'text' : 'password'}
              label="Confirm Password"
              placeholder="Re-enter your password"
              value={passwordConfirm}
              onChange={(e) => setPasswordConfirm(e.target.value)}
              startContent={<Lock className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              isRequired
              autoComplete="new-password"
              isInvalid={passwordConfirm.length > 0 && !passwordsMatch}
              errorMessage={
                passwordConfirm.length > 0 && !passwordsMatch ? 'Passwords must match' : ''
              }
              classNames={{
                inputWrapper:
                  'glass-card border-glass-border hover:border-glass-border-hover',
                label: 'text-theme-muted',
                input: 'text-theme-primary placeholder:text-theme-subtle',
              }}
            />
          </div>
        );

      case 4:
        return (
          <div className="space-y-4">
            {/* Consents */}
            <div className="space-y-3">
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
                    to={tenantPath('/terms')}
                    className="text-indigo-600 dark:text-indigo-400 hover:underline"
                  >
                    Terms of Service
                  </Link>{' '}
                  and{' '}
                  <Link
                    to={tenantPath('/privacy')}
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
                <Link
                  to={tenantPath('/privacy')}
                  className="text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                  View our full Privacy Policy
                </Link>
              </p>
            </div>
          </div>
        );

      default:
        return null;
    }
  };

  // Desktop: Show all fields at once
  // Mobile: Show step-by-step
  const renderForm = () => {
    if (!isMobile) {
      // Desktop view - all fields visible
      return (
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

          {/* All steps content */}
          {renderStepContent(1)}
          {renderStepContent(2)}
          {renderStepContent(3)}
          {renderStepContent(4)}

          <Button
            type="submit"
            isLoading={isLoading}
            isDisabled={!isFormValid}
            className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
            size="lg"
            spinner={<Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" />}
          >
            Create Account
          </Button>
        </form>
      );
    }

    // Mobile view - step by step
    return (
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

        {/* Step indicator */}
        <div className="mb-6">
          <div className="flex justify-between items-center mb-2">
            <span className="text-sm font-medium text-theme-primary">
              Step {currentStep} of {STEPS.length}
            </span>
            <span className="text-sm text-theme-muted">{STEPS[currentStep - 1].title}</span>
          </div>
          <Progress
            value={progressPercent}
            color="primary"
            size="sm"
            aria-label={`Registration progress: step ${currentStep} of ${STEPS.length}`}
          />
          {/* Step dots */}
          <div className="flex justify-between mt-2">
            {STEPS.map((step) => (
              <Button
                key={step.id}
                variant="light"
                size="sm"
                className={`flex flex-col items-center min-w-0 h-auto p-1 gap-0 ${
                  step.id === currentStep
                    ? 'text-indigo-600 dark:text-indigo-400'
                    : step.id < currentStep
                      ? 'text-theme-muted'
                      : 'text-theme-subtle'
                }`}
                onPress={() => step.id < currentStep && setCurrentStep(step.id)}
                isDisabled={step.id > currentStep}
                aria-label={`Go to step ${step.id}: ${step.title}`}
              >
                <div
                  className={`w-2.5 h-2.5 rounded-full ${
                    step.id === currentStep
                      ? 'bg-indigo-600 dark:bg-indigo-400'
                      : step.id < currentStep
                        ? 'bg-emerald-500'
                        : 'bg-theme-elevated'
                  }`}
                />
                <span className="text-[10px] mt-1 hidden xs:block">{step.shortTitle}</span>
              </Button>
            ))}
          </div>
        </div>

        {/* Animated step content */}
        <AnimatePresence mode="wait">
          <motion.div
            key={currentStep}
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -20 }}
            transition={{ duration: 0.2 }}
          >
            {renderStepContent(currentStep)}
          </motion.div>
        </AnimatePresence>

        {/* Navigation buttons */}
        <div className="flex gap-3 pt-4">
          {currentStep > 1 && (
            <Button
              type="button"
              variant="flat"
              onPress={handleBack}
              className="flex-1 bg-theme-elevated text-theme-secondary"
              startContent={<ChevronLeft className="w-4 h-4" aria-hidden="true" />}
            >
              Back
            </Button>
          )}

          {currentStep < 4 ? (
            <Button
              type="button"
              onPress={handleNext}
              isDisabled={!canProceed()}
              className={`${currentStep === 1 ? 'w-full' : 'flex-1'} bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium`}
              endContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
            >
              Continue
            </Button>
          ) : (
            <Button
              type="submit"
              isLoading={isLoading}
              isDisabled={!isFormValid}
              className="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
              spinner={<Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" />}
            >
              Create Account
            </Button>
          )}
        </div>
      </form>
    );
  };

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
        <GlassCard className="p-6 sm:p-8">
          {/* Header */}
          <div className="text-center mb-6 sm:mb-8">
            <motion.div
              initial={{ scale: 0.8 }}
              animate={{ scale: 1 }}
              className="inline-flex items-center justify-center w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4"
            >
              <User className="w-7 h-7 sm:w-8 sm:h-8 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
            </motion.div>
            <h1 className="text-xl sm:text-2xl font-bold text-theme-primary">Create Account</h1>
            <p className="text-theme-muted mt-2 text-sm sm:text-base">
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
          {renderForm()}

          {/* Divider */}
          <Divider className="my-6 bg-theme-elevated" />

          {/* Login Link */}
          <p className="text-center text-theme-muted text-sm">
            Already have an account?{' '}
            <Link
              to={tenantPath('/login')}
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
            to={tenantPath('/')}
            className="inline-flex items-center gap-2 text-theme-subtle hover:text-theme-muted text-sm transition-colors"
          >
            <ArrowLeft className="w-4 h-4" aria-hidden="true" />
            Back to home
          </Link>
        </motion.div>
      </motion.div>
    </div>
  );
}

export default RegisterPage;
