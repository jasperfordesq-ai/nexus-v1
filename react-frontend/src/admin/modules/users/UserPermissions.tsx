import { Card, CardBody, CardHeader, Chip, Button, Spinner } from '@/components/ui';
import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Shield from 'lucide-react/icons/shield';
import Info from 'lucide-react/icons/info';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminUsers } from '../../api/adminApi';
import { PageHeader } from '../../components/PageHeader';
import type { AdminUserDetail } from '../../api/types';
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * User Permissions
 * Read-only view of a user's role and active permission slugs.
 * Per-permission editing is not available here; access is managed by
 * changing the user's role on the user edit page.
 */


const roleColorFor = (role?: string): 'primary' | 'secondary' | 'success' | 'warning' | 'default' => {
  switch (role) {
    case 'super_admin': return 'secondary';
    case 'tenant_admin':
    case 'admin': return 'primary';
    case 'member': return 'success';
    default: return 'default';
  }
};

export function UserPermissions() {
  const { t } = useTranslation('admin_users');
  usePageTitle(t('users.permissions_title'));
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [user, setUser] = useState<AdminUserDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const roleLabels: Record<string, string> = {
    admin: t('users.role_admin'),
    broker: t('users.role_broker'),
    member: t('users.role_member'),
    moderator: t('users.role_moderator'),
    newsletter_admin: t('users.role_newsletter_admin'),
    super_admin: t('users.role_super_admin'),
    tenant_admin: t('users.role_tenant_admin'),
  };

  useEffect(() => {
    if (!id) return;
    let cancelled = false;
    (async () => {
      try {
        const res = await adminUsers.get(Number(id));
        if (!cancelled && res?.success && res.data) {
          setUser(res.data as AdminUserDetail);
        } else if (!cancelled) {
          toast.error(t('users.failed_to_load_user'));
        }
      } catch {
        if (!cancelled) toast.error(t('users.failed_to_load_user'));
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, [id, t, toast]);


  return (
    <div>
      <PageHeader
        title={t('users.permissions_title')}
        description={t('users.permissions_description')}
        actions={
          <Button variant="tertiary" startContent={<ArrowLeft aria-hidden="true" size={16} />} onPress={() => navigate(tenantPath('/admin/users'))}>
            {t('common.back')}
          </Button>
        }
      />

      {loading ? (
        <div role="status" aria-busy="true" aria-label={t('common.loading')} className="flex justify-center py-12"><Spinner size="lg" /></div>
      ) : !user ? (
        <Card >
          <CardBody className="py-8 text-center text-muted">
            {t('users.failed_to_load_user')}
          </CardBody>
        </Card>
      ) : (
        <div className="flex flex-col gap-4">
          <Card >
            <CardHeader className="flex items-center gap-2">
              <Shield aria-hidden="true" size={20} />
              <h3 className="text-lg font-semibold">{user.name}</h3>
              <span className="text-sm text-muted">({user.email})</span>
            </CardHeader>
            <CardBody className="gap-4">
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-sm font-medium">{t('users.role_label')}:</span>
                <Chip color={roleColorFor(user.role)} variant="soft" size="sm">
                  {roleLabels[user.role] ?? t('users.role_unknown')}
                </Chip>
                {user.is_super_admin && (
                  <Chip variant="soft" size="sm">{t('users.role_super_admin')}</Chip>
                )}
                {user.is_tenant_super_admin && (
                  <Chip variant="soft" size="sm">{t('users.role_tenant_super_admin')}</Chip>
                )}
                {user.is_admin && (
                  <Chip variant="soft" size="sm">{t('users.role_admin')}</Chip>
                )}
              </div>

              <div>
                <div className="text-sm font-medium mb-2">{t('users.permissions_granted')}</div>
                {user.permissions && user.permissions.length > 0 ? (
                  <div className="flex flex-wrap gap-2">
                    {user.permissions.map((p) => (
                      <Chip key={p} variant="soft" size="sm">{p}</Chip>
                    ))}
                  </div>
                ) : (
                  <div className="text-sm text-muted">{t('users.no_explicit_permissions')}</div>
                )}
              </div>
            </CardBody>
          </Card>

          <Card  className="border border-warning/30 bg-warning/5">
            <CardBody className="flex flex-row items-start gap-3">
              <Info aria-hidden="true" size={20} className="text-warning shrink-0 mt-0.5" />
              <div>
                <div className="font-medium">{t('users.permissions_editor_unavailable_title')}</div>
                <p className="text-sm text-muted mt-1">{t('users.permissions_editor_unavailable_desc')}</p>
              </div>
            </CardBody>
          </Card>
        </div>
      )}
    </div>
  );
}

export default UserPermissions;
