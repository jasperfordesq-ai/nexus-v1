/**
 * Hook to fetch custom legal documents from the API.
 *
 * Returns the tenant's custom document (managed in admin Legal Documents)
 * or null if no custom document exists — in which case pages should render
 * their default hardcoded content.
 */

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';

export interface LegalDocument {
  id: number;
  type: string;
  title: string;
  content: string;
  version_number: string;
  effective_date: string;
  summary_of_changes: string | null;
}

type LegalDocumentType = 'terms' | 'privacy' | 'cookies' | 'accessibility' | 'community_guidelines' | 'acceptable_use';

export function useLegalDocument(type: LegalDocumentType) {
  const [document, setDocument] = useState<LegalDocument | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get<{ data: LegalDocument | null }>(`/v2/legal/${type}`)
      .then((res) => {
        if (res.success && res.data?.data) {
          setDocument(res.data.data);
        }
      })
      .catch(() => {
        // Silently fall through — page will show default content
      })
      .finally(() => setLoading(false));
  }, [type]);

  return { document, loading };
}
