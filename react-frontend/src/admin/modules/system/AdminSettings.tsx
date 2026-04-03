// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Settings — Redirects to System Config (enterprise module)
 * All settings are now managed in the unified System Config page.
 */

import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenant } from '@/contexts';

export function AdminSettings() {
  const navigate = useNavigate();
  const { tenantPath } = useTenant();

  useEffect(() => {
    navigate(tenantPath('/admin/enterprise/config'), { replace: true });
  }, [navigate, tenantPath]);

  return null;
}

export default AdminSettings;
