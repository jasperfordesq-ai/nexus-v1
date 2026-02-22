// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenant } from '@/contexts';

/**
 * Redirect page for /matches and /matches?type=mutual.
 *
 * The smart-matching email system links to /matches but there is no dedicated
 * user-facing matches page. This redirects to /listings which shows all
 * community listings including matched ones.
 *
 * Email links that land here:
 *   - /matches           (hot match digest)
 *   - /matches?type=mutual (mutual match email)
 */
export default function MatchesRedirectPage() {
  const navigate = useNavigate();
  const { tenantPath } = useTenant();

  useEffect(() => {
    navigate(tenantPath('/listings'), { replace: true });
  }, [navigate, tenantPath]);

  return null;
}
