/**
 * Privacy Policy Page
 */

import { motion } from 'framer-motion';
import { useTenant } from '@/contexts';
import { GlassCard } from '@/components/ui';

export function PrivacyPage() {
  const { branding } = useTenant();

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <GlassCard className="p-8 sm:p-12">
          <h1 className="text-3xl font-bold text-white mb-2">Privacy Policy</h1>
          <p className="text-white/50 text-sm mb-8">Last updated: January 2026</p>

          <div className="prose prose-invert max-w-none space-y-6 text-white/70">
            <p>
              Your privacy is important to us. This policy explains how {branding.name}
              collects, uses, and protects your personal information.
            </p>

            <h2 className="text-xl font-semibold text-white mt-8">Information We Collect</h2>
            <ul className="list-disc list-inside space-y-2">
              <li>Account information (name, email, profile details)</li>
              <li>Listings and transaction history</li>
              <li>Messages between users</li>
              <li>Usage data and preferences</li>
            </ul>

            <h2 className="text-xl font-semibold text-white mt-8">How We Use Your Information</h2>
            <ul className="list-disc list-inside space-y-2">
              <li>To provide and improve our services</li>
              <li>To facilitate connections between users</li>
              <li>To send important platform notifications</li>
              <li>To ensure platform security and prevent abuse</li>
            </ul>

            <h2 className="text-xl font-semibold text-white mt-8">Data Sharing</h2>
            <p>
              We do not sell your personal data. We only share information with:
            </p>
            <ul className="list-disc list-inside space-y-2">
              <li>Other users as necessary for service exchanges</li>
              <li>Service providers who help operate the platform</li>
              <li>Law enforcement when legally required</li>
            </ul>

            <h2 className="text-xl font-semibold text-white mt-8">Your Rights</h2>
            <p>
              Under GDPR, you have the right to access, correct, or delete your
              personal data. Contact us to exercise these rights.
            </p>

            <h2 className="text-xl font-semibold text-white mt-8">Cookies</h2>
            <p>
              We use essential cookies to provide our services and analytics cookies
              to improve user experience. You can manage your cookie preferences in settings.
            </p>

            <h2 className="text-xl font-semibold text-white mt-8">Contact</h2>
            <p>
              For privacy-related questions, please contact our Data Protection Officer
              through the contact form or at privacy@{branding.name.toLowerCase().replace(' ', '')}.ie
            </p>
          </div>
        </GlassCard>
      </motion.div>
    </div>
  );
}

export default PrivacyPage;
