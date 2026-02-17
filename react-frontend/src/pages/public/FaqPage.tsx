/**
 * FAQ Page - Native React
 *
 * Frequently Asked Questions with searchable accordion categories.
 * Uses HeroUI Accordion component for expand/collapse.
 */

import { useState, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Accordion, AccordionItem, Input } from '@heroui/react';
import {
  Rocket, Wallet, Handshake, Trophy, ShieldCheck,
  Search, HelpCircle,
} from 'lucide-react';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { GlassCard } from '@/components/ui';

interface FaqItem {
  question: string;
  answer: React.ReactNode;
}

interface FaqCategory {
  title: string;
  icon: typeof Rocket;
  items: FaqItem[];
}

const containerVariants = {
  hidden: { opacity: 0 },
  visible: { opacity: 1, transition: { staggerChildren: 0.08 } },
};
const itemVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0 },
};

export function FaqPage() {
  const { tenantPath } = useTenant();
  usePageTitle('FAQ');

  const [searchQuery, setSearchQuery] = useState('');

  const categories: FaqCategory[] = useMemo(() => [
    {
      title: 'Getting Started',
      icon: Rocket,
      items: [
        {
          question: 'What is time banking?',
          answer: (
            <>
              <p>Time banking is a community exchange system where <strong>one hour of help equals one time credit</strong>, regardless of the service provided. Whether you&apos;re teaching a language, helping with gardening, or providing tech support, your time is valued equally.</p>
              <p>It&apos;s a way to build community connections while exchanging skills and services without traditional money.</p>
            </>
          ),
        },
        {
          question: 'Is it free to join?',
          answer: (
            <>
              <p>Yes! Membership is completely free. The only currency used is time credits, which you earn by helping others in the community.</p>
              <p>New members often receive a small number of starter credits to help them get started.</p>
            </>
          ),
        },
        {
          question: 'How do I get started?',
          answer: (
            <>
              <p>Here&apos;s how to begin:</p>
              <ul>
                <li><strong>Complete your profile</strong> &mdash; Add a photo, bio, and your skills</li>
                <li><strong>Browse the marketplace</strong> &mdash; See what services are offered and needed</li>
                <li><strong>Post an offer</strong> &mdash; Share what you can help others with</li>
                <li><strong>Make a request</strong> &mdash; Ask for something you need</li>
                <li><strong>Connect with members</strong> &mdash; Build your network</li>
              </ul>
            </>
          ),
        },
        {
          question: 'What skills can I offer?',
          answer: (
            <>
              <p>Almost anything! Common offerings include:</p>
              <ul>
                <li>Home help (gardening, cleaning, minor repairs)</li>
                <li>Tech support (computer help, phone setup)</li>
                <li>Teaching (languages, music, academic subjects)</li>
                <li>Creative services (design, photography, writing)</li>
                <li>Transportation (rides, errands)</li>
                <li>Companionship (walks, visits, conversation)</li>
                <li>Professional skills (accounting, legal advice, etc.)</li>
              </ul>
              <p>Everyone has something valuable to offer!</p>
            </>
          ),
        },
      ],
    },
    {
      title: 'Time Credits',
      icon: Wallet,
      items: [
        {
          question: 'What is a time credit?',
          answer: (
            <>
              <p>One time credit equals one hour of service. It&apos;s the currency of our community.</p>
              <p>You can also exchange partial hours: 30 minutes = 0.5 credits, 15 minutes = 0.25 credits.</p>
            </>
          ),
        },
        {
          question: 'How do I earn time credits?',
          answer: (
            <>
              <p>You earn credits by:</p>
              <ul>
                <li><strong>Providing services</strong> to other members</li>
                <li><strong>Logging volunteer hours</strong> with approved organisations</li>
                <li><strong>Receiving donations</strong> from other members</li>
                <li><strong>Starter credits</strong> when you first join</li>
              </ul>
            </>
          ),
        },
        {
          question: 'Can I donate my credits?',
          answer: (
            <>
              <p>Yes! You can:</p>
              <ul>
                <li>Transfer credits to any other member</li>
                <li>Donate to the community pot (helps members who need extra support)</li>
              </ul>
              <p>Go to your <Link to={tenantPath('/wallet')} className="text-indigo-500 dark:text-indigo-400 hover:underline">Wallet</Link> to send credits.</p>
            </>
          ),
        },
        {
          question: 'Can my balance go negative?',
          answer: (
            <p>Some communities allow limited negative balances to help new members get started before they&apos;ve had a chance to earn credits. Check with your community coordinator for the specific rules in your timebank.</p>
          ),
        },
        {
          question: 'What if I sent credits to the wrong person?',
          answer: (
            <>
              <p>Transactions cannot be automatically reversed. If you made a mistake:</p>
              <ul>
                <li>Contact the recipient and ask them to send the credits back</li>
                <li>If you can&apos;t resolve it, contact support for assistance</li>
              </ul>
              <p>Always double-check the recipient before confirming a transfer.</p>
            </>
          ),
        },
      ],
    },
    {
      title: 'Exchanges & Safety',
      icon: Handshake,
      items: [
        {
          question: 'How do I arrange an exchange?',
          answer: (
            <>
              <p>The typical process is:</p>
              <ul>
                <li>Find an offer or request that interests you</li>
                <li>Message the member to discuss details</li>
                <li>Agree on when, where, and estimated time</li>
                <li>Meet and complete the exchange</li>
                <li>The person who received help sends time credits</li>
                <li>Leave a review for each other</li>
              </ul>
            </>
          ),
        },
        {
          question: 'Is it safe to meet strangers?',
          answer: (
            <>
              <p>We recommend these safety practices:</p>
              <ul>
                <li>Meet in public places for first exchanges</li>
                <li>Check the member&apos;s profile and reviews</li>
                <li>Tell someone where you&apos;re going</li>
                <li>Trust your instincts &mdash; if something feels wrong, leave</li>
                <li>Start with smaller, low-risk exchanges</li>
              </ul>
              <p>Report any concerning behaviour to our support team.</p>
            </>
          ),
        },
        {
          question: 'What if someone doesn\'t show up or complete the service?',
          answer: (
            <>
              <p>Communication is key. If there&apos;s an issue:</p>
              <ul>
                <li>Message the member to discuss what happened</li>
                <li>Try to find a resolution together</li>
                <li>If you can&apos;t resolve it, contact support</li>
              </ul>
              <p>Repeated no-shows or poor behaviour may result in account restrictions.</p>
            </>
          ),
        },
        {
          question: 'Are the services insured?',
          answer: (
            <p>Exchanges are informal arrangements between community members. We don&apos;t provide insurance coverage for services. For higher-risk activities, discuss liability with the other member beforehand. Some members have their own professional insurance.</p>
          ),
        },
      ],
    },
    {
      title: 'Badges & Rewards',
      icon: Trophy,
      items: [
        {
          question: 'What are badges and XP?',
          answer: (
            <>
              <p>Our gamification system rewards community participation:</p>
              <ul>
                <li><strong>XP (Experience Points)</strong> &mdash; Earned for various activities, helping you level up</li>
                <li><strong>Badges</strong> &mdash; Achievements for reaching milestones (60+ to collect!)</li>
                <li><strong>Levels</strong> &mdash; Progress from Newcomer to Timebank Legend</li>
                <li><strong>Streaks</strong> &mdash; Rewards for daily engagement</li>
              </ul>
            </>
          ),
        },
        {
          question: 'How do I earn XP?',
          answer: (
            <>
              <p>You earn XP for many activities:</p>
              <ul>
                <li>Sending/receiving time credits</li>
                <li>Creating listings</li>
                <li>Logging volunteer hours</li>
                <li>Attending events</li>
                <li>Making connections</li>
                <li>Daily logins</li>
                <li>Earning badges</li>
              </ul>
            </>
          ),
        },
        {
          question: 'Can I opt out of leaderboards?',
          answer: (
            <p>Yes! If you prefer privacy, you can opt out of appearing on public leaderboards in your <Link to={tenantPath('/settings')} className="text-indigo-500 dark:text-indigo-400 hover:underline">Settings</Link>. You&apos;ll still earn XP and badges, they just won&apos;t be visible to others on the rankings.</p>
          ),
        },
      ],
    },
    {
      title: 'Account & Privacy',
      icon: ShieldCheck,
      items: [
        {
          question: 'How do I change my password?',
          answer: (
            <p>Go to <Link to={tenantPath('/settings')} className="text-indigo-500 dark:text-indigo-400 hover:underline">Settings</Link> &gt; Security to change your password. You&apos;ll need to enter your current password first. If you&apos;ve forgotten your password, use the &quot;Forgot Password&quot; link on the login page.</p>
          ),
        },
        {
          question: 'Who can see my profile?',
          answer: (
            <>
              <p>By default, your profile is visible to other community members. You can control:</p>
              <ul>
                <li>What contact information is displayed</li>
                <li>Whether you appear on leaderboards</li>
                <li>Your general location visibility</li>
              </ul>
              <p>Adjust these in your <Link to={tenantPath('/settings')} className="text-indigo-500 dark:text-indigo-400 hover:underline">Settings</Link>.</p>
            </>
          ),
        },
        {
          question: 'How do I delete my account?',
          answer: (
            <>
              <p>If you wish to leave the community:</p>
              <ul>
                <li>Go to Settings &gt; Account</li>
                <li>Click &quot;Delete Account&quot;</li>
                <li>Confirm your decision</li>
              </ul>
              <p>Note: Your transaction history will be retained for record-keeping, but your personal information will be removed.</p>
            </>
          ),
        },
        {
          question: 'How is my data protected?',
          answer: (
            <>
              <p>We take privacy seriously:</p>
              <ul>
                <li>Your data is encrypted and securely stored</li>
                <li>We never sell your information</li>
                <li>You can request a copy of your data at any time</li>
                <li>We comply with GDPR and data protection regulations</li>
              </ul>
            </>
          ),
        },
      ],
    },
  ], [tenantPath]);

  // Filter by search query
  const filteredCategories = useMemo(() => {
    if (!searchQuery.trim()) return categories;
    const q = searchQuery.toLowerCase();
    return categories
      .map((cat) => ({
        ...cat,
        items: cat.items.filter((item) => item.question.toLowerCase().includes(q)),
      }))
      .filter((cat) => cat.items.length > 0);
  }, [categories, searchQuery]);

  return (
    <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <motion.div
        variants={containerVariants}
        initial="hidden"
        animate="visible"
        className="space-y-6"
      >
        {/* Header */}
        <motion.div variants={itemVariants}>
          <GlassCard className="p-8 sm:p-10">
            <div className="flex items-center gap-3 mb-4">
              <div className="p-2 rounded-xl bg-indigo-500/10">
                <HelpCircle className="w-8 h-8 text-indigo-500 dark:text-indigo-400" />
              </div>
              <h1 className="text-3xl font-bold text-theme-primary">Frequently Asked Questions</h1>
            </div>
            <p className="text-theme-muted mb-6">
              Can&apos;t find what you&apos;re looking for? Visit our{' '}
              <Link to={tenantPath('/help')} className="text-indigo-500 dark:text-indigo-400 hover:underline">
                Help Center
              </Link>{' '}
              for detailed guides.
            </p>

            {/* Search */}
            <Input
              placeholder="Search questions..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-subtle/10 border-theme-default',
              }}
            />
          </GlassCard>
        </motion.div>

        {/* Categories */}
        {filteredCategories.length === 0 ? (
          <motion.div variants={itemVariants}>
            <GlassCard className="p-8 text-center">
              <p className="text-theme-muted">No matching questions found. Try a different search term.</p>
            </GlassCard>
          </motion.div>
        ) : (
          filteredCategories.map((cat) => {
            const Icon = cat.icon;
            return (
              <motion.div key={cat.title} variants={itemVariants}>
                <GlassCard className="p-6">
                  <div className="flex items-center gap-3 mb-4">
                    <div className="p-1.5 rounded-lg bg-indigo-500/10">
                      <Icon className="w-5 h-5 text-indigo-500 dark:text-indigo-400" />
                    </div>
                    <h2 className="text-lg font-semibold text-theme-primary">{cat.title}</h2>
                  </div>

                  <Accordion
                    selectionMode="multiple"
                    variant="bordered"
                    itemClasses={{
                      base: 'border-theme-default/50',
                      title: 'text-theme-primary font-medium text-sm',
                      trigger: 'px-4 py-3 hover:bg-theme-subtle/5 data-[hover=true]:bg-theme-subtle/5',
                      content: 'px-4 pb-4 text-theme-muted text-sm leading-relaxed [&_p]:mb-3 [&_p:last-child]:mb-0 [&_ul]:ml-5 [&_ul]:list-disc [&_ul]:mb-3 [&_li]:mb-1.5',
                      indicator: 'text-theme-subtle',
                    }}
                  >
                    {cat.items.map((item, idx) => (
                      <AccordionItem
                        key={`${cat.title}-${idx}`}
                        aria-label={item.question}
                        title={item.question}
                      >
                        {item.answer}
                      </AccordionItem>
                    ))}
                  </Accordion>
                </GlassCard>
              </motion.div>
            );
          })
        )}

        {/* Contact CTA */}
        <motion.div variants={itemVariants}>
          <GlassCard className="p-8 text-center">
            <h3 className="text-xl font-semibold text-theme-primary mb-2">Still have questions?</h3>
            <p className="text-theme-muted mb-4">Our support team is here to help.</p>
            <Link
              to={tenantPath('/contact')}
              className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl
                         bg-indigo-500 text-white font-medium
                         hover:bg-indigo-600 transition-colors"
            >
              Contact Support
            </Link>
          </GlassCard>
        </motion.div>
      </motion.div>
    </div>
  );
}

export default FaqPage;
