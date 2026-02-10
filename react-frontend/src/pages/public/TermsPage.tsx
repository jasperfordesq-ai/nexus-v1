/**
 * Terms of Service Page
 */

import { motion } from 'framer-motion';
import { useTenant } from '@/contexts';
import { GlassCard } from '@/components/ui';

export function TermsPage() {
  const { branding } = useTenant();

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <GlassCard className="p-8 sm:p-12">
          <h1 className="text-3xl font-bold text-theme-primary mb-2">Terms of Service</h1>
          <p className="text-theme-subtle text-sm mb-8">Last updated: January 2026</p>

          <div className="prose prose-invert max-w-none space-y-6 text-theme-muted">
            <p>
              Welcome to {branding.name}. By using our platform, you agree to these terms.
            </p>

            <h2 className="text-xl font-semibold text-theme-primary mt-8">1. Service Description</h2>
            <p>
              {branding.name} is a time banking platform that facilitates the exchange
              of services between community members using time credits as currency.
            </p>

            <h2 className="text-xl font-semibold text-theme-primary mt-8">2. User Accounts</h2>
            <p>
              You must provide accurate information when creating an account. You are
              responsible for maintaining the security of your account credentials.
            </p>

            <h2 className="text-xl font-semibold text-theme-primary mt-8">3. Acceptable Use</h2>
            <p>
              You agree to use the platform in a lawful manner and not to engage in
              any activity that could harm other users or the platform.
            </p>

            <h2 className="text-xl font-semibold text-theme-primary mt-8">4. Time Credits</h2>
            <p>
              Time credits have no monetary value and cannot be exchanged for cash.
              They are intended solely for facilitating service exchanges within the community.
            </p>

            <h2 className="text-xl font-semibold text-theme-primary mt-8">5. Liability</h2>
            <p>
              {branding.name} is a platform that connects users. We are not responsible
              for the quality or outcome of services exchanged between users.
            </p>

            <h2 className="text-xl font-semibold text-theme-primary mt-8">6. Changes to Terms</h2>
            <p>
              We may update these terms from time to time. We will notify users of
              significant changes via email or platform notifications.
            </p>
          </div>
        </GlassCard>
      </motion.div>
    </div>
  );
}

export default TermsPage;
