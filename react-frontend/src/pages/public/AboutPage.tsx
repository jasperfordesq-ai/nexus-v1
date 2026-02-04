/**
 * About Page - Static page with tenant info
 */

import { motion } from 'framer-motion';
import { useTenant } from '@/contexts';
import { GlassCard } from '@/components/ui';

export function AboutPage() {
  const { branding } = useTenant();

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <GlassCard className="p-8 sm:p-12">
          <h1 className="text-3xl sm:text-4xl font-bold text-white mb-6">
            About {branding.name}
          </h1>

          <div className="prose prose-invert max-w-none space-y-6 text-white/70">
            <p className="text-lg">
              {branding.name} is a time banking platform that helps communities
              exchange skills and services using time as currency. Every hour of
              service is valued equally, creating a fair and inclusive economy.
            </p>

            <h2 className="text-2xl font-semibold text-white mt-8">How It Works</h2>
            <ol className="list-decimal list-inside space-y-3">
              <li>Create your profile and list the skills you can offer</li>
              <li>Browse listings to find services you need</li>
              <li>Connect with other members and arrange exchanges</li>
              <li>Earn time credits for services you provide</li>
              <li>Spend credits on services from other members</li>
            </ol>

            <h2 className="text-2xl font-semibold text-white mt-8">Our Values</h2>
            <ul className="list-disc list-inside space-y-3">
              <li><strong className="text-white">Equality</strong> - Every hour is worth the same</li>
              <li><strong className="text-white">Community</strong> - Building connections that matter</li>
              <li><strong className="text-white">Trust</strong> - Transparent reviews and ratings</li>
              <li><strong className="text-white">Sustainability</strong> - Supporting local economies</li>
            </ul>
          </div>
        </GlassCard>
      </motion.div>
    </div>
  );
}

export default AboutPage;
