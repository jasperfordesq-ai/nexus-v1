// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { EventVenueAccessibility } from '@/lib/events-api';
import { renderEventComponent } from '@/test/events-test-harness';
import { EventVenueAccessibilityCard } from './EventVenueAccessibilityCard';

const profile: EventVenueAccessibility = {
  schema_version: 1,
  provided: true,
  step_free_access: true,
  accessible_toilet: false,
  hearing_loop: null,
  quiet_space: true,
  seating_available: true,
  accessible_parking: null,
  parking_details: 'Two marked bays.',
  transit_details: 'Level route from the bus stop.',
  assistance_contact: 'Message the event team.',
  notes: 'Use the east entrance.',
};

describe('EventVenueAccessibilityCard', () => {
  it('communicates yes, no and unknown without relying on colour', () => {
    renderEventComponent(<EventVenueAccessibilityCard profile={profile} />);

    expect(screen.getByRole('heading', { name: 'Venue accessibility' })).toBeInTheDocument();
    expect(screen.getByText('Step-free access').parentElement).toHaveTextContent('Yes');
    expect(screen.getByText('Accessible toilet').parentElement).toHaveTextContent('No');
    expect(screen.getByText('Hearing loop').parentElement).toHaveTextContent('Not known');
    expect(screen.getByText('Two marked bays.')).toBeInTheDocument();
    expect(screen.getByText('Message the event team.')).toBeInTheDocument();
  });

  it('does not render an empty or unverified profile', () => {
    const { container } = renderEventComponent(
      <EventVenueAccessibilityCard profile={{ ...profile, provided: false }} />,
    );
    expect(container).toBeEmptyDOMElement();
  });
});
