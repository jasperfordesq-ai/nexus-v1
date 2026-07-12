// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import { renderEventComponent } from '@/test/events-test-harness';
import {
  EMPTY_VENUE_ACCESSIBILITY,
  EventVenueAccessibilityFields,
  type VenueAccessibilityDraft,
} from './EventVenueAccessibilityFields';

function Harness() {
  const [value, setValue] = useState<VenueAccessibilityDraft>({
    ...EMPTY_VENUE_ACCESSIBILITY,
  });
  return (
    <>
      <EventVenueAccessibilityFields value={value} onChange={setValue} />
      <output data-testid="venue-profile">{JSON.stringify(value)}</output>
    </>
  );
}

describe('EventVenueAccessibilityFields', () => {
  it('keeps unknown distinct from no and edits public details', async () => {
    const user = userEvent.setup();
    renderEventComponent(<Harness />);

    const stepFree = screen.getByRole('button', { name: /Step-free access/ });
    expect(stepFree).toHaveTextContent('Not known');
    await user.click(stepFree);
    await user.click(await screen.findByRole('option', { name: 'Yes' }));

    await user.type(
      screen.getByRole('textbox', { name: 'Additional access information' }),
      'Level side entrance.',
    );

    expect(screen.getByTestId('venue-profile')).toHaveTextContent(
      '"step_free_access":true',
    );
    expect(screen.getByTestId('venue-profile')).toHaveTextContent(
      '"accessible_toilet":null',
    );
    expect(screen.getByTestId('venue-profile')).toHaveTextContent(
      '"notes":"Level side entrance."',
    );
    expect(screen.getByText(/Private accommodation requests belong in the registration form/i))
      .toBeInTheDocument();
  });
});
