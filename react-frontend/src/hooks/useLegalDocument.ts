/**
 * Hook to fetch custom legal documents from the API.
 *
 * Returns the tenant's custom document (managed in admin Legal Documents)
 * or null if no custom document exists — in which case pages should render
 * their default hardcoded content.
 *
 * IMPORTANT: Waits for TenantContext to finish bootstrapping before making
 * the API call. This ensures the X-Tenant-ID header is available in
 * localStorage — without it, the API cannot determine which tenant's
 * documents to return and will always return null.
 */

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useTenant } from '@/contexts';

export interface LegalDocument {
  id: number;
  document_id: number;
  type: string;
  title: string;
  content: string;
  version_number: string;
  effective_date: string;
  summary_of_changes: string | null;
  has_previous_versions: boolean;
}

export interface LegalVersionSummary {
  id: number;
  version_number: string;
  version_label: string | null;
  effective_date: string;
  published_at: string | null;
  is_current: boolean;
  summary_of_changes: string | null;
}

export interface LegalVersionDetail extends LegalVersionSummary {
  document_type: string;
  title: string;
  content: string;
}

export type LegalDocumentType = 'terms' | 'privacy' | 'cookies' | 'accessibility' | 'community_guidelines' | 'acceptable_use';

export function useLegalDocument(type: LegalDocumentType) {
  const { isLoading: tenantLoading, tenant } = useTenant();
  const [document, setDocument] = useState<LegalDocument | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Wait for tenant context to finish bootstrapping so X-Tenant-ID is set
    if (tenantLoading) return;

    // If tenant failed to load, skip fetch — page will show default content
    if (!tenant) {
      setLoading(false);
      return;
    }

    api.get<LegalDocument | null>(`/v2/legal/${type}`)
      .then((res) => {
        if (res.success && res.data) {
          setDocument(res.data);
        }
      })
      .catch(() => {
        // Silently fall through — page will show default content
      })
      .finally(() => setLoading(false));
  }, [type, tenantLoading, tenant]);

  return { document, loading };
}
