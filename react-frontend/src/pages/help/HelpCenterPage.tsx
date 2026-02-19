// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Help Center Page - FAQ and support resources
 *
 * Displays common questions, guides, and links to contact support.
 * Uses HeroUI Accordion component for expand/collapse.
 */

import { useState } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Accordion, AccordionItem, Button, Input } from '@heroui/react';
import {
  HelpCircle,
  Search,
  MessageSquare,
  BookOpen,
  Wallet,
  Users,
  Shield,
  Settings,
  Calendar,
  ArrowRightLeft,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';

/* ───────────────────────── Types ───────────────────────── */

interface FaqItem {
  question: string;
  answer: string;
}

interface FaqCategory {
  title: string;
  icon: React.ReactNode;
  items: FaqItem[];
}

/* ───────────────────────── FAQ Data ───────────────────────── */

const faqCategories: FaqCategory[] = [
  {
    title: 'Getting Started',
    icon: <BookOpen className="w-5 h-5" aria-hidden="true" />,
    items: [
      {
        question: 'What is timebanking?',
        answer:
          'Timebanking is a community-based exchange system where everyone\'s time is valued equally. You earn time credits by helping others and spend them to receive help. One hour of service equals one time credit, regardless of the type of service.',
      },
      {
        question: 'How do I create an account?',
        answer:
          'Click "Sign Up" on the home page, select your community, fill in your details, and verify your email. Your community coordinator may need to approve your account before you can start exchanging.',
      },
      {
        question: 'How do I get started after signing up?',
        answer:
          'Start by completing your profile with your skills and interests. Then browse listings to find services you need, or create your own listings to offer your skills. You can also join groups and events to meet other members.',
      },
    ],
  },
  {
    title: 'Listings & Exchanges',
    icon: <ArrowRightLeft className="w-5 h-5" aria-hidden="true" />,
    items: [
      {
        question: 'How do I create a listing?',
        answer:
          'Go to the Listings page and click "Create Listing". Choose whether you\'re offering a service or requesting one, add a title, description, category, and estimated time. Your listing will be visible to other community members.',
      },
      {
        question: 'How does an exchange work?',
        answer:
          'When you find a listing you\'re interested in, you can request an exchange. The other member accepts or declines. Once accepted, you coordinate the service, and when complete, time credits are transferred automatically.',
      },
      {
        question: 'What happens if a service takes longer than estimated?',
        answer:
          'The actual time spent is what gets recorded. Before confirming the exchange, both parties agree on the actual hours. The estimate is just a guide to help members plan.',
      },
    ],
  },
  {
    title: 'Wallet & Credits',
    icon: <Wallet className="w-5 h-5" aria-hidden="true" />,
    items: [
      {
        question: 'How do I earn time credits?',
        answer:
          'You earn credits by providing services to other members. When an exchange is completed and confirmed, the agreed-upon hours are added to your wallet balance.',
      },
      {
        question: 'Can I transfer credits directly?',
        answer:
          'Yes! Go to your Wallet page and use the "Transfer" option. You can send credits to any member in your community. This is useful for gifting or adjusting balances.',
      },
      {
        question: 'What if my balance is zero?',
        answer:
          'You can still request services even with a zero balance. Timebanking is built on trust and reciprocity. Most communities allow negative balances to encourage participation.',
      },
    ],
  },
  {
    title: 'Community Features',
    icon: <Users className="w-5 h-5" aria-hidden="true" />,
    items: [
      {
        question: 'How do groups work?',
        answer:
          'Groups are community spaces around shared interests. You can join existing groups, participate in discussions, and share resources. Some groups are open for anyone, while others require approval.',
      },
      {
        question: 'How do I RSVP to events?',
        answer:
          'Browse the Events page, find an event you\'re interested in, and click "RSVP". You\'ll receive reminders before the event. You can cancel your RSVP at any time.',
      },
      {
        question: 'How do I connect with other members?',
        answer:
          'Visit a member\'s profile and click "Connect". They\'ll receive a notification and can accept or decline. Once connected, you can message each other directly.',
      },
    ],
  },
  {
    title: 'Account & Privacy',
    icon: <Shield className="w-5 h-5" aria-hidden="true" />,
    items: [
      {
        question: 'How do I update my profile?',
        answer:
          'Go to Settings from the menu. You can update your name, bio, skills, location, and avatar. Your profile helps other members find and connect with you.',
      },
      {
        question: 'Is my personal information safe?',
        answer:
          'We take privacy seriously. Your email and personal details are only visible to community members. We comply with GDPR and provide tools for you to manage your data, including account deletion.',
      },
      {
        question: 'How do I change my password?',
        answer:
          'Go to Settings and find the Password section. Enter your current password and your new password. For extra security, consider enabling two-factor authentication.',
      },
    ],
  },
  {
    title: 'Settings & Preferences',
    icon: <Settings className="w-5 h-5" aria-hidden="true" />,
    items: [
      {
        question: 'How do I enable dark mode?',
        answer:
          'Click the sun/moon icon in the top navigation bar to toggle between light and dark themes. Your preference is saved automatically.',
      },
      {
        question: 'How do I manage notifications?',
        answer:
          'Go to Settings and find the Notifications section. You can control email notifications, push notifications, and in-app alerts for different types of activity.',
      },
      {
        question: 'Can I delete my account?',
        answer:
          'Yes, you can delete your account from Settings. This action is permanent and will remove your profile, listings, and transaction history. Contact your community coordinator if you need help.',
      },
    ],
  },
];

/* ───────────────────────── Main Component ───────────────────────── */

export function HelpCenterPage() {
  const { branding, tenantPath } = useTenant();
  usePageTitle('Help Center');
  const [searchQuery, setSearchQuery] = useState('');

  // Filter FAQ items by search
  const filteredCategories = searchQuery.trim()
    ? faqCategories
        .map((cat) => ({
          ...cat,
          items: cat.items.filter(
            (item) =>
              item.question.toLowerCase().includes(searchQuery.toLowerCase()) ||
              item.answer.toLowerCase().includes(searchQuery.toLowerCase())
          ),
        }))
        .filter((cat) => cat.items.length > 0)
    : faqCategories;

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      {/* Header */}
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }}>
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4">
            <HelpCircle className="w-8 h-8 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
          </div>
          <h1 className="text-3xl font-bold text-theme-primary">Help Center</h1>
          <p className="text-theme-muted mt-2 max-w-lg mx-auto">
            Find answers to common questions about {branding.name}
          </p>
        </div>

        {/* Search */}
        <div className="max-w-md mx-auto mb-8">
          <Input
            placeholder="Search for help..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            startContent={<Search className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
            size="lg"
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default',
            }}
          />
        </div>
      </motion.div>

      {/* Quick Links */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="grid grid-cols-2 sm:grid-cols-4 gap-3"
      >
        <QuickLink to={tenantPath("/listings")} icon={<BookOpen />} label="Browse Listings" />
        <QuickLink to={tenantPath("/wallet")} icon={<Wallet />} label="My Wallet" />
        <QuickLink to={tenantPath("/events")} icon={<Calendar />} label="Events" />
        <QuickLink to={tenantPath("/contact")} icon={<MessageSquare />} label="Contact Us" />
      </motion.div>

      {/* FAQ Categories */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
        className="space-y-4"
      >
        {filteredCategories.length === 0 ? (
          <GlassCard className="p-8 text-center">
            <Search className="w-12 h-12 text-theme-subtle mx-auto mb-4 opacity-50" aria-hidden="true" />
            <h2 className="text-lg font-semibold text-theme-primary mb-2">No results found</h2>
            <p className="text-theme-muted mb-4">
              Try different search terms or{' '}
              <Link to={tenantPath("/contact")} className="text-indigo-500 hover:underline">
                contact us
              </Link>{' '}
              for help.
            </p>
          </GlassCard>
        ) : (
          <Accordion
            selectionMode="multiple"
            variant="splitted"
            defaultExpandedKeys={["0"]}
            itemClasses={{
              base: 'bg-theme-elevated/50 backdrop-blur-md border border-theme-default/30 shadow-sm',
              title: 'font-semibold text-theme-primary',
              subtitle: 'text-xs text-theme-subtle',
              trigger: 'p-5 hover:bg-theme-hover/30 data-[hover=true]:bg-theme-hover/30',
              indicator: 'text-theme-muted',
              content: 'px-5 pb-2',
            }}
          >
            {filteredCategories.map((category, catIdx) => (
              <AccordionItem
                key={String(catIdx)}
                aria-label={category.title}
                title={category.title}
                subtitle={`${category.items.length} articles`}
                startContent={
                  <div className="p-2 rounded-lg bg-gradient-to-br from-indigo-500/20 to-purple-500/20 text-indigo-600 dark:text-indigo-400">
                    {category.icon}
                  </div>
                }
              >
                <Accordion
                  selectionMode="multiple"
                  variant="light"
                  itemClasses={{
                    base: 'border-b border-theme-default/50 last:border-b-0',
                    title: 'text-sm font-medium text-theme-primary',
                    trigger: 'px-2 py-3 hover:bg-theme-hover/20 data-[hover=true]:bg-theme-hover/20',
                    content: 'px-2 pb-3 text-sm text-theme-muted leading-relaxed',
                    indicator: 'text-theme-muted',
                  }}
                >
                  {category.items.map((item, itemIdx) => (
                    <AccordionItem
                      key={`${catIdx}-${itemIdx}`}
                      aria-label={item.question}
                      title={item.question}
                    >
                      {item.answer}
                    </AccordionItem>
                  ))}
                </Accordion>
              </AccordionItem>
            ))}
          </Accordion>
        )}
      </motion.div>

      {/* Still Need Help */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.3 }}
      >
        <GlassCard className="p-6 text-center">
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Still need help?</h2>
          <p className="text-sm text-theme-muted mb-4">
            Can&apos;t find what you&apos;re looking for? Our team is here to help.
          </p>
          <Link to={tenantPath("/contact")}>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
            >
              Contact Support
            </Button>
          </Link>
        </GlassCard>
      </motion.div>
    </div>
  );
}

/* ───────────────────────── Quick Link ───────────────────────── */

interface QuickLinkProps {
  to: string;
  icon: React.ReactNode;
  label: string;
}

function QuickLink({ to, icon, label }: QuickLinkProps) {
  return (
    <Link to={to}>
      <GlassCard className="p-4 text-center hover:scale-[1.02] transition-transform">
        <div className="inline-flex p-2 rounded-lg bg-gradient-to-br from-indigo-500/20 to-purple-500/20 text-indigo-600 dark:text-indigo-400 mb-2">
          {icon}
        </div>
        <p className="text-sm font-medium text-theme-primary">{label}</p>
      </GlassCard>
    </Link>
  );
}

export default HelpCenterPage;
