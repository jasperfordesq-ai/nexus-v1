// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Create/Edit Listing Page
 *
 * Thin page wrapper around the shared ListingForm (also used by the
 * ComposeHub listing tab). Page chrome only — the form owns all state.
 */

import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { motion } from '@/lib/motion';

import { ListingForm } from '@/components/listings/ListingForm';
import { Breadcrumbs } from '@/components/navigation';
import { useTenant } from '@/contexts';
import { PageMeta } from '@/components/seo';
import { usePageTitle } from '@/hooks';

export function CreateListingPage() {
  const { t } = useTranslation('listings');
  const { id } = useParams<{ id: string }>();
  const { tenantPath } = useTenant();
  const isEditing = !!id;
  const pageTitle = isEditing ? t('page_meta.edit.title') : t('page_meta.create.title');
  usePageTitle(pageTitle);

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="mx-auto max-w-5xl space-y-6"
    >
      <PageMeta title={pageTitle} noIndex />
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: t('title'), href: tenantPath('/listings') },
        { label: isEditing ? t('form.edit_title') : t('form.new_title') },
      ]} />

      <ListingForm variant="page" listingId={id} />
    </motion.div>
  );
}

export default CreateListingPage;
