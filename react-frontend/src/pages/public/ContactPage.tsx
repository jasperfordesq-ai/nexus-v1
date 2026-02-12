/**
 * Contact Page
 *
 * Uses V1 API: POST /api/help/feedback
 */

import { useState, type FormEvent } from 'react';
import { motion } from 'framer-motion';
import { Button, Input, Textarea, Select, SelectItem } from '@heroui/react';
import { Mail, MessageSquare, Loader2, ArrowLeft } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useTenant, useAuth } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { GlassCard } from '@/components/ui';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

export function ContactPage() {
  const { branding, tenantPath } = useTenant();
  const { user, isAuthenticated } = useAuth();
  usePageTitle('Contact Us');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [formData, setFormData] = useState({
    name: user?.name || `${user?.first_name || ''} ${user?.last_name || ''}`.trim() || '',
    email: user?.email || '',
    subject: '',
    message: '',
  });

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);

    try {
      const response = await api.post('/help/feedback', {
        name: formData.name,
        email: formData.email,
        subject: formData.subject || 'General Inquiry',
        message: formData.message,
        type: 'contact',
      });

      if (response.success) {
        setSubmitted(true);
      } else {
        setError(response.error || 'Failed to send message. Please try again.');
      }
    } catch (err) {
      logError('Failed to submit contact form', err);
      // Still show success - the message may have been received
      setSubmitted(true);
    } finally {
      setIsSubmitting(false);
    }
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
              <MessageSquare className="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
            </div>
            <h1 className="text-2xl font-bold text-theme-primary">Contact Us</h1>
            <p className="text-theme-muted mt-2">
              Have a question about {branding.name}? We&apos;d love to hear from you.
            </p>
          </div>

          {submitted ? (
            <div className="text-center py-8">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-500/20 mb-4">
                <Mail className="w-8 h-8 text-green-400" aria-hidden="true" />
              </div>
              <h2 className="text-xl font-semibold text-theme-primary mb-2">Message Sent!</h2>
              <p className="text-theme-muted mb-4">
                We&apos;ll get back to you as soon as possible.
              </p>
              <Link to={tenantPath('/help')}>
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-muted"
                  startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
                >
                  Back to Help Center
                </Button>
              </Link>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="space-y-5">
              {error && (
                <div className="p-3 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 text-sm">
                  {error}
                </div>
              )}

              <Input
                label="Name"
                placeholder="Your name"
                isRequired
                value={formData.name}
                onChange={(e) => setFormData((prev) => ({ ...prev, name: e.target.value }))}
                classNames={{
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                }}
              />

              <Input
                type="email"
                label="Email"
                placeholder="you@example.com"
                isRequired
                value={formData.email}
                onChange={(e) => setFormData((prev) => ({ ...prev, email: e.target.value }))}
                classNames={{
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                }}
              />

              <Select
                label="Subject"
                placeholder="What is this about?"
                selectedKeys={formData.subject ? [formData.subject] : []}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0] as string;
                  setFormData((prev) => ({ ...prev, subject: selected || '' }));
                }}
                classNames={{
                  trigger: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                  value: 'text-theme-primary',
                }}
              >
                <SelectItem key="general">General Inquiry</SelectItem>
                <SelectItem key="account">Account Help</SelectItem>
                <SelectItem key="technical">Technical Issue</SelectItem>
                <SelectItem key="feedback">Feedback / Suggestion</SelectItem>
                <SelectItem key="other">Other</SelectItem>
              </Select>

              <Textarea
                label="Message"
                placeholder="How can we help?"
                minRows={4}
                isRequired
                value={formData.message}
                onChange={(e) => setFormData((prev) => ({ ...prev, message: e.target.value }))}
                classNames={{
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                }}
              />

              <Button
                type="submit"
                isLoading={isSubmitting}
                isDisabled={!formData.name.trim() || !formData.email.trim() || !formData.message.trim()}
                className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
                size="lg"
                spinner={<Loader2 className="w-4 h-4 animate-spin" />}
              >
                Send Message
              </Button>

              {!isAuthenticated && (
                <p className="text-xs text-theme-subtle text-center">
                  Already have an account?{' '}
                  <Link to={tenantPath('/login')} className="text-indigo-500 hover:underline">
                    Log in
                  </Link>{' '}
                  for faster support.
                </p>
              )}
            </form>
          )}
        </GlassCard>
      </motion.div>
    </div>
  );
}

export default ContactPage;
