// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Card, Input, TextArea } from '@heroui/react';
import { Mail, Send } from 'lucide-react';
import { useMemo, useState } from 'react';

import { buildOrderEmail, formatCurrency, type QuoteEstimate } from '../lib/pricingEngine';

interface OrderFormProps {
  quote: QuoteEstimate;
}

export default function OrderForm({ quote }: OrderFormProps) {
  const [contactName, setContactName] = useState('');
  const [organisation, setOrganisation] = useState('');
  const [email, setEmail] = useState('');
  const [region, setRegion] = useState('');
  const [note, setNote] = useState('');

  const href = useMemo(
    () =>
      buildOrderEmail({
        contactName,
        organisation,
        email,
        region,
        note,
        quote,
      }),
    [contactName, email, note, organisation, quote, region],
  );

  return (
    <Card className="border border-white/10 bg-white/[0.065] p-5" id="order">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="flex items-center gap-2 text-sm font-bold tracking-[0.16em] text-[#9edbd2] uppercase">
            <Mail className="size-4" />
            Order enquiry
          </p>
          <h3 className="mt-2 text-2xl font-black text-white">Send this quote for professional follow-up.</h3>
          <p className="mt-2 max-w-2xl text-sm leading-6 text-white/58">
            No card capture and no surprise charge. The button creates a structured email to jasper@hour-timebank.ie with your product line, plan, add-ons, launch services, and notes. This is ready to become a proper order intake later.
          </p>
        </div>
        <div className="rounded-xl border border-white/10 bg-black/20 p-3 text-right">
          <p className="text-xs font-semibold text-white/45 uppercase">{quote.productLineLabel}</p>
          <p className="text-xs font-semibold text-white/45 uppercase">First-year estimate</p>
          <p className="text-2xl font-black text-white">{formatCurrency(quote.firstYearTotal)}</p>
        </div>
      </div>

      <div className="mt-5 grid gap-3 md:grid-cols-4">
        {['Estimate saved', 'Discovery call', 'Written quote', 'Order agreement'].map((stage, index) => (
          <div key={stage} className="rounded-xl border border-white/10 bg-black/18 p-3">
            <p className="text-xs font-black text-[#f5c86a]">0{index + 1}</p>
            <p className="mt-1 text-sm font-bold text-white">{stage}</p>
          </div>
        ))}
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

      <div className="mt-5 flex flex-wrap items-center gap-3">
        <Button onPress={() => window.location.assign(href)}>
          <Send className="size-4" />
          Open email order
        </Button>
        <p className="text-sm text-white/48">The final quote, contract terms, and payment route are confirmed in writing after discovery.</p>
      </div>
    </Card>
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
