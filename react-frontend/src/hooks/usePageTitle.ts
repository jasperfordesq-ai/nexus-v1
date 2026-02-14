/**
 * usePageTitle Hook
 * Sets the document title to "Page Title - Tenant Name" format.
 */

import { useEffect } from 'react';

export function usePageTitle(title: string) {
  useEffect(() => {
    const prev = document.title;
    document.title = title;
    return () => {
      document.title = prev;
    };
  }, [title]);
}
