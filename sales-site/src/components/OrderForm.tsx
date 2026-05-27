// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Card, Input, TextArea } from '@heroui/react';
import { AlertCircle, CheckCircle2, LoaderCircle, Mail, Send, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

import { submitSalesOrder } from '../lib/salesOrderApi';
import { formatCurrency, formatQuoteAmount, type QuoteEstimate } from '../lib/pricingEngine';

interface OrderFormProps {
  quote: QuoteEstimate;
}

type SubmissionState = 'idle' | 'submitting' | 'success' | 'error';

export default function OrderForm({ quote }: OrderFormProps) {
  const [contactName, setContactName] = useState('');
  const [organisation, setOrganisation] = useState('');
  const [email, setEmail] = useState('');
  const [region, setRegion] = useState('');
  const [note, setNote] = useState('');
  const [website, setWebsite] = useState('');
  const [submissionState, setSubmissionState] = useState<SubmissionState>('idle');
  const [reference, setReference] = useState('');
  const [errorMessage, setErrorMessage] = useState('');

  const handleSubmit = async () => {
    if (submissionState === 'submitting') {
      return;
    }

    const trimmedName = contactName.trim();
    const trimmedEmail = email.trim();

    if (trimmedName === '' || trimmedEmail === '') {
      setSubmissionState('error');
      setErrorMessage('Please add your name and email address before sending the enquiry.');
      return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmedEmail)) {
      setSubmissionState('error');
      setErrorMessage('Please enter a valid email address so we can reply professionally.');
      return;
    }

    setSubmissionState('submitting');
    setErrorMessage('');

    try {
      const result = await submitSalesOrder(
        {
          contactName,
          organisation,
          email,
          region,
          note,
          website,
          pageUrl: typeof window !== 'undefined' ? window.location.href : undefined,
        },
        quote,
      );
      setReference(result.reference);
      setSubmissionState('success');
    } catch (error) {
      setSubmissionState('error');
      setErrorMessage(error instanceof Error ? error.message : 'The order enquiry could not be sent. Please try again.');
    }
  };

  return (
    <Card className="scroll-mt-28 border border-white/10 bg-white/[0.065] p-5" id="order">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="flex items-center gap-2 text-sm font-bold tracking-[0.16em] text-[var(--color-accent)] uppercase">
            <Mail className="size-4" />
            Order enquiry
          </p>
          <h3 className="mt-2 text-2xl font-black text-white">Send this quote for professional follow-up.</h3>
          <p className="mt-2 max-w-2xl text-sm leading-6 text-white/58">
            No card capture and no surprise charge. This sends the complete quote and contact form through the Project NEXUS backend email delivery route, then confirms the enquiry was received.
          </p>
        </div>
        <div className="rounded-xl border border-white/10 bg-black/20 p-3 text-right">
          <p className="text-xs font-semibold text-white/45 uppercase">{quote.productLineLabel}</p>
          <p className="text-xs font-semibold text-white/45 uppercase">
            {quote.pricingMode === 'custom' ? 'Enterprise estimate' : 'First-year estimate'}
          </p>
          <p className="text-2xl font-black text-white">{formatQuoteAmount(quote, quote.firstYearTotal)}</p>
        </div>
      </div>

      <div className="mt-5 grid gap-3 md:grid-cols-4">
        {['Form received', 'Discovery review', 'Written quote', 'Order agreement'].map((stage, index) => (
          <div key={stage} className="rounded-xl border border-white/10 bg-black/18 p-3">
            <p className="text-xs font-black text-[var(--color-primary)]">0{index + 1}</p>
            <p className="mt-1 text-sm font-bold text-white">{stage}</p>
          </div>
        ))}
      </div>

      <div className="mt-5 grid gap-4 rounded-2xl border border-white/10 bg-black/18 p-4 lg:grid-cols-[0.9fr_1.1fr]">
        <div>
          <p className="flex items-center gap-2 text-sm font-black text-white">
            <ShieldCheck className="size-4 text-[var(--color-accent)]" />
            Order snapshot
          </p>
          <div className="mt-4 grid gap-3 text-sm">
            <SnapshotRow label="Product" value={quote.productLineLabel} />
            <SnapshotRow label="Plan" value={quote.hostingPlan.name} />
            <SnapshotRow label="Capacity" value={quote.hostingPlan.activeMemberLabel} />
            <SnapshotRow label="First year" value={formatQuoteAmount(quote, quote.firstYearTotal)} />
          </div>
        </div>
        <div>
          <p className="text-sm font-black text-white">Included in the enquiry</p>
          <div className="mt-3 grid gap-2">
            {quote.lineItems.length > 0 ? (
              quote.lineItems.map((item) => (
                <div key={`${item.id}-${item.cadence}`} className="grid gap-2 rounded-xl border border-white/10 bg-white/[0.035] p-3 text-sm sm:grid-cols-[1fr_auto] sm:items-center">
                  <span className="font-semibold text-white/78">{item.label}</span>
                  <span className="font-black text-[var(--color-primary)]">
                    {quote.pricingMode === 'custom' && item.amountEur === 0
                      ? 'Custom quote'
                      : `${formatCurrency(item.amountEur)}${item.cadence === 'monthly' ? '/mo' : ''}`}
                  </span>
                </div>
              ))
            ) : (
              <p className="rounded-xl border border-white/10 bg-white/[0.035] p-3 text-sm text-white/55">No paid line items selected yet.</p>
            )}
          </div>
        </div>
      </div>

      <div className="mt-5 grid form-grid gap-4">
        <Field label="Name" value={contactName} onChange={setContactName} placeholder="Ava Murphy" />
        <Field label="Organisation" value={organisation} onChange={setOrganisation} placeholder="Civic Network" />
        <Field label="Email" value={email} onChange={setEmail} placeholder="ava@example.org" />
        <Field label="Region" value={region} onChange={setRegion} placeholder="Ireland, UK, EU, global" />
      </div>

      <label className="mt-4 block">
        <span className="mb-2 block text-xs font-bold tracking-[0.12em] text-white/50 uppercase">Notes</span>
        <TextArea
          className="min-h-28"
          fullWidth
          value={note}
          onChange={(event) => setNote(event.target.value)}
          placeholder="Tell us about tenants, migration, federation, procurement, accessibility, or support needs."
        />
      </label>

      <label className="hidden" aria-hidden="true">
        Website
        <input tabIndex={-1} autoComplete="off" value={website} onChange={(event) => setWebsite(event.target.value)} />
      </label>

      {submissionState === 'success' ? (
        <div className="mt-5 rounded-2xl border border-[color:var(--color-accent)]/28 bg-[color:var(--color-accent)]/10 p-4" role="status">
          <p className="flex items-center gap-2 font-black text-white">
            <CheckCircle2 className="size-5 text-[var(--color-accent)]" />
            Message received
          </p>
          <p className="mt-2 text-sm leading-6 text-white/62">
            Thanks. The full quote and contact form have been sent for review. Reference <span className="font-black text-white">{reference}</span>. We will follow up with a written quote, discovery questions, and the right order route.
          </p>
        </div>
      ) : null}

      {submissionState === 'error' ? (
        <div className="mt-5 rounded-2xl border border-red-400/30 bg-red-500/10 p-4" role="alert">
          <p className="flex items-center gap-2 font-black text-white">
            <AlertCircle className="size-5 text-red-300" />
            Could not send yet
          </p>
          <p className="mt-2 text-sm leading-6 text-white/62">{errorMessage}</p>
        </div>
      ) : null}

      <div className="mt-5 flex flex-wrap items-center gap-3">
        <Button isDisabled={submissionState === 'submitting'} onPress={handleSubmit}>
          {submissionState === 'submitting' ? <LoaderCircle className="size-4 animate-spin" /> : <Send className="size-4" />}
          {submissionState === 'success' ? 'Send updated enquiry' : 'Send order enquiry'}
        </Button>
        <p className="text-sm text-white/48">The final quote, contract terms, and payment route are confirmed in writing after discovery.</p>
      </div>
    </Card>
  );
}

function SnapshotRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="grid grid-cols-[8rem_1fr] gap-3">
      <span className="font-semibold text-white/42 uppercase">{label}</span>
      <span className="font-bold text-white/78">{value}</span>
    </div>
  );
}

function Field({
  label,
  value,
  onChange,
  placeholder,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder: string;
}) {
  return (
    <label className="block">
      <span className="mb-2 block text-xs font-bold tracking-[0.12em] text-white/50 uppercase">{label}</span>
      <Input fullWidth value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} />
    </label>
  );
}
