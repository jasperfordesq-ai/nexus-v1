/**
 * Home Page - NEXUS Visual Showcase Landing
 *
 * This page demonstrates the NEXUS visual identity:
 * - Glassmorphism cards with depth
 * - Holographic gradient accents
 * - Tenant-branded colors
 * - Floating elements with hover effects
 *
 * Purpose: When users land here, they should immediately feel
 * "This is the new NEXUS frontend"
 */

import { Link } from 'react-router-dom';
import { Button, Avatar, Chip } from '@heroui/react';
import { useTenant } from '../tenant';
import { useAuth } from '../auth';
import { GlassCard, GlassCardHeader, GlassCardBody } from '../components';

// Feature icons
function ListingsIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
    </svg>
  );
}

function MessagesIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
    </svg>
  );
}

function WalletIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3" />
    </svg>
  );
}

function SparklesIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
    </svg>
  );
}

export function HomePage() {
  const tenant = useTenant();
  const { isAuthenticated, user } = useAuth();

  // Quick action cards data
  const quickActions = [
    {
      title: 'Listings',
      description: 'Browse and post services',
      icon: ListingsIcon,
      href: '/listings',
      gradient: 'from-indigo-500 to-purple-500',
    },
    {
      title: 'Messages',
      description: 'Connect with members',
      icon: MessagesIcon,
      href: '/messages',
      gradient: 'from-cyan-500 to-blue-500',
    },
    {
      title: 'Wallet',
      description: 'Track your time credits',
      icon: WalletIcon,
      href: '/wallet',
      gradient: 'from-amber-500 to-orange-500',
    },
  ];

  return (
    <div className="space-y-8">
      {/* Hero Section - Full glass treatment */}
      <section className="relative overflow-hidden">
        <GlassCard variant="elevated" padding="lg" className="text-center">
          {/* Decorative gradient orbs */}
          <div className="absolute top-0 left-1/4 w-64 h-64 bg-[var(--tenant-primary)] rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse-soft" />
          <div className="absolute bottom-0 right-1/4 w-64 h-64 bg-[var(--tenant-secondary)] rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse-soft delay-200" />

          <div className="relative z-10">
            {/* Animated sparkle icon */}
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl gradient-primary mb-6 shadow-elevated">
              <SparklesIcon className="w-8 h-8 text-white" />
            </div>

            <h1 className="text-4xl sm:text-5xl font-bold mb-4">
              <span className="bg-gradient-to-r from-[var(--tenant-primary)] via-[var(--tenant-secondary)] to-[var(--tenant-accent)] bg-clip-text text-transparent">
                {tenant.seo?.h1_headline || `Welcome to ${tenant.name}`}
              </span>
            </h1>

            {tenant.tagline && (
              <p className="text-xl text-gray-600 mb-6 max-w-2xl mx-auto">
                {tenant.tagline}
              </p>
            )}

            {tenant.seo?.hero_intro && (
              <p className="text-gray-500 max-w-xl mx-auto mb-8 leading-relaxed">
                {tenant.seo.hero_intro}
              </p>
            )}

            <div className="flex flex-col sm:flex-row justify-center gap-4">
              <Button
                as={Link}
                to="/listings"
                size="lg"
                className="gradient-primary text-white font-semibold shadow-elevated hover:shadow-float transition-shadow"
              >
                Get Started
              </Button>
              {!isAuthenticated && (
                <Button
                  as={Link}
                  to="/login"
                  variant="bordered"
                  size="lg"
                  className="border-2 border-[var(--tenant-primary)]/30 hover:border-[var(--tenant-primary)] hover:bg-[var(--tenant-primary)]/10 transition-all"
                >
                  Sign In
                </Button>
              )}
            </div>
          </div>
        </GlassCard>
      </section>

      {/* Welcome back card for authenticated users */}
      {isAuthenticated && user && (
        <GlassCard variant="primary" className="animate-slide-up">
          <div className="flex items-center gap-4">
            <Avatar
              src={user.avatar_url || undefined}
              name={`${user.first_name} ${user.last_name}`}
              size="lg"
              isBordered
              color="primary"
              className="ring-2 ring-white/50"
            />
            <div>
              <p className="text-lg font-semibold">
                Welcome back, {user.first_name}!
              </p>
              <p className="text-sm text-gray-600">
                Ready to make a difference in your community?
              </p>
            </div>
          </div>
        </GlassCard>
      )}

      {/* Quick Actions Grid */}
      <section>
        <h2 className="text-xl font-semibold mb-4 text-gray-800">Quick Actions</h2>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          {quickActions.map((action, index) => (
            <Link
              key={action.title}
              to={action.href}
              className={`block animate-slide-up delay-${(index + 1) * 100}`}
            >
              <GlassCard
                variant="elevated"
                hoverable
                className="h-full group"
              >
                <div className={`inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br ${action.gradient} mb-4 shadow-medium group-hover:scale-110 transition-transform`}>
                  <action.icon className="w-6 h-6 text-white" />
                </div>
                <h3 className="font-semibold text-gray-900 mb-1 group-hover:text-[var(--tenant-primary)] transition-colors">
                  {action.title}
                </h3>
                <p className="text-sm text-gray-500">
                  {action.description}
                </p>
              </GlassCard>
            </Link>
          ))}
        </div>
      </section>

      {/* Stats / Info Section */}
      <section className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* About Card */}
        <GlassCard variant="default">
          <GlassCardHeader divider>
            <h2 className="text-lg font-semibold text-gray-800">
              About {tenant.name}
            </h2>
          </GlassCardHeader>
          <GlassCardBody>
            <dl className="space-y-3 text-sm">
              {tenant.contact?.location && (
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-lg bg-[var(--tenant-primary)]/10 flex items-center justify-center flex-shrink-0">
                    <svg className="w-4 h-4 text-[var(--tenant-primary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                      <path strokeLinecap="round" strokeLinejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                  </div>
                  <div>
                    <dt className="text-gray-500 text-xs uppercase tracking-wider">Location</dt>
                    <dd className="font-medium text-gray-800">{tenant.contact.location}</dd>
                  </div>
                </div>
              )}
              {tenant.contact?.email && (
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-lg bg-[var(--tenant-primary)]/10 flex items-center justify-center flex-shrink-0">
                    <svg className="w-4 h-4 text-[var(--tenant-primary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                  </div>
                  <div>
                    <dt className="text-gray-500 text-xs uppercase tracking-wider">Contact</dt>
                    <dd className="font-medium text-gray-800">{tenant.contact.email}</dd>
                  </div>
                </div>
              )}
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-lg bg-[var(--tenant-primary)]/10 flex items-center justify-center flex-shrink-0">
                  <svg className="w-4 h-4 text-[var(--tenant-primary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                  </svg>
                </div>
                <div>
                  <dt className="text-gray-500 text-xs uppercase tracking-wider">Theme</dt>
                  <dd className="font-medium text-gray-800 capitalize">{tenant.default_layout}</dd>
                </div>
              </div>
            </dl>
          </GlassCardBody>
        </GlassCard>

        {/* Features Card */}
        <GlassCard variant="default">
          <GlassCardHeader divider>
            <h2 className="text-lg font-semibold text-gray-800">
              Platform Features
            </h2>
          </GlassCardHeader>
          <GlassCardBody>
            <div className="flex flex-wrap gap-2">
              {tenant.features.listings && (
                <Chip
                  color="primary"
                  variant="flat"
                  className="backdrop-blur-sm"
                >
                  Listings
                </Chip>
              )}
              {tenant.features.messages && (
                <Chip
                  color="secondary"
                  variant="flat"
                  className="backdrop-blur-sm"
                >
                  Messages
                </Chip>
              )}
              {tenant.features.wallet && (
                <Chip
                  color="warning"
                  variant="flat"
                  className="backdrop-blur-sm"
                >
                  Wallet
                </Chip>
              )}
              {tenant.features.events && (
                <Chip
                  color="success"
                  variant="flat"
                  className="backdrop-blur-sm"
                >
                  Events
                </Chip>
              )}
              {tenant.features.groups && (
                <Chip
                  color="primary"
                  variant="flat"
                  className="backdrop-blur-sm"
                >
                  Groups
                </Chip>
              )}
              {tenant.features.gamification && (
                <Chip
                  color="danger"
                  variant="flat"
                  className="backdrop-blur-sm"
                >
                  Gamification
                </Chip>
              )}
              {tenant.features.volunteering && (
                <Chip
                  color="secondary"
                  variant="flat"
                  className="backdrop-blur-sm"
                >
                  Volunteering
                </Chip>
              )}
              {tenant.features.feed && (
                <Chip
                  color="default"
                  variant="flat"
                  className="backdrop-blur-sm"
                >
                  Feed
                </Chip>
              )}
            </div>
            <p className="text-xs text-gray-400 mt-4">
              Features enabled for this tenant
            </p>
          </GlassCardBody>
        </GlassCard>
      </section>

      {/* Visual Identity Badge */}
      <div className="text-center py-8">
        <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full glass text-sm text-gray-500">
          <span className="w-2 h-2 rounded-full gradient-holo" />
          Powered by NEXUS
        </div>
      </div>
    </div>
  );
}
