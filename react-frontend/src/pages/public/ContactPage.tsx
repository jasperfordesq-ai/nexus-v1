/**
 * Contact Page
 */

import { useState, type FormEvent } from 'react';
import { motion } from 'framer-motion';
import { Button, Input, Textarea } from '@heroui/react';
import { Mail, MessageSquare, Loader2 } from 'lucide-react';
import { useTenant } from '@/contexts';
import { GlassCard } from '@/components/ui';

export function ContactPage() {
  const { branding } = useTenant();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    // Simulate submission
    await new Promise((r) => setTimeout(r, 1000));
    setSubmitted(true);
    setIsSubmitting(false);
  };

  return (
    <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <GlassCard className="p-8">
          <div className="text-center mb-8">
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4">
              <MessageSquare className="w-8 h-8 text-indigo-400" />
            </div>
            <h1 className="text-2xl font-bold text-white">Contact Us</h1>
            <p className="text-white/60 mt-2">
              Have a question about {branding.name}? We&apos;d love to hear from you.
            </p>
          </div>

          {submitted ? (
            <div className="text-center py-8">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-500/20 mb-4">
                <Mail className="w-8 h-8 text-green-400" />
              </div>
              <h2 className="text-xl font-semibold text-white mb-2">Message Sent!</h2>
              <p className="text-white/60">
                We&apos;ll get back to you as soon as possible.
              </p>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="space-y-5">
              <Input
                label="Name"
                placeholder="Your name"
                isRequired
                classNames={{
                  inputWrapper: 'glass-card border-glass-border',
                  label: 'text-white/70',
                  input: 'text-white placeholder:text-white/30',
                }}
              />

              <Input
                type="email"
                label="Email"
                placeholder="you@example.com"
                isRequired
                classNames={{
                  inputWrapper: 'glass-card border-glass-border',
                  label: 'text-white/70',
                  input: 'text-white placeholder:text-white/30',
                }}
              />

              <Textarea
                label="Message"
                placeholder="How can we help?"
                minRows={4}
                isRequired
                classNames={{
                  inputWrapper: 'glass-card border-glass-border',
                  label: 'text-white/70',
                  input: 'text-white placeholder:text-white/30',
                }}
              />

              <Button
                type="submit"
                isLoading={isSubmitting}
                className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
                size="lg"
                spinner={<Loader2 className="w-4 h-4 animate-spin" />}
              >
                Send Message
              </Button>
            </form>
          )}
        </GlassCard>
      </motion.div>
    </div>
  );
}

export default ContactPage;
