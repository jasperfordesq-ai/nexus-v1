// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * User Permissions (stub)
 * Read-only view of a user's role and active permission slugs.
 * Full per-permission editor is coming soon.
 */

import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Card, CardBody, CardHeader, Chip, Button, Spinner } from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Shield from 'lucide-react/icons/shield';
import Info from 'lucide-react/icons/info';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminUsers } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { AdminUserDetail } from '../../api/types';

export function UserPermissions() {
  usePageTitle("Permissions");
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [user, setUser] = useState<AdminUserDetail | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!id) return;
    let cancelled = false;
    (async () => {
      try {
        const res = await adminUsers.get(Number(id));
        if (!cancelled && res?.success && res.data) {
          setUser(res.data as AdminUserDetail);
        } else if (!cancelled) {
          toast.error("Failed to load user");
        }
      } catch {
        if (!cancelled) toast.error("Failed to load user");
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, [id, toast]);


  const roleColorFor = (role?: string): 'primary' | 'secondary' | 'success' | 'warning' | 'default' => {
    switch (role) {
      case 'super_admin': return 'secondary';
      case 'tenant_admin':
      case 'admin': return 'primary';
      case 'moderator': return 'warning';
      case 'member': return 'success';
      default: return 'default';
    }
  };

  return (
    <div>
      <PageHeader
        title={"Permissions"}
        description={"Permissions."}
        actions={
          <Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/users'))}>
            {"Back"}
          </Button>
        }
      />

      {loading ? (
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      ) : !user ? (
        <Card shadow="sm">
          <CardBody className="py-8 text-center text-default-500">
            {"Failed to load user"}
          </CardBody>
        </Card>
      ) : (
        <div className="flex flex-col gap-4">
          <Card shadow="sm">
            <CardHeader className="flex items-center gap-2">
              <Shield size={20} />
              <h3 className="text-lg font-semibold">{user.name}</h3>
              <span className="text-sm text-default-500">({user.email})</span>
            </CardHeader>
            <CardBody className="gap-4">
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-sm font-medium">{"Role"}:</span>
                <Chip color={roleColorFor(user.role)} variant="flat" size="sm">
                  {user.role}
                </Chip>
                {user.is_super_admin && (
                  <Chip color="secondary" variant="flat" size="sm">super_admin</Chip>
                )}
                {user.is_tenant_super_admin && (
                  <Chip color="primary" variant="flat" size="sm">tenant_super_admin</Chip>
                )}
                {user.is_admin && (
                  <Chip color="primary" variant="flat" size="sm">admin</Chip>
                )}
              </div>

              <div>
                <div className="text-sm font-medium mb-2">{"Permissions Granted"}</div>
                {user.permissions && user.permissions.length > 0 ? (
                  <div className="flex flex-wrap gap-2">
                    {user.permissions.map((p) => (
                      <Chip key={p} variant="flat" size="sm">{p}</Chip>
                    ))}
                  </div>
                ) : (
                  <div className="text-sm text-default-500">{"No explicit permissions found"}</div>
                )}
              </div>
            </CardBody>
          </Card>

          <Card shadow="sm" className="border border-warning/30 bg-warning/5">
            <CardBody className="flex flex-row items-start gap-3">
              <Info size={20} className="text-warning shrink-0 mt-0.5" />
              <div>
                <div className="font-medium">{"Permissions Editor Coming Soon"}</div>
                <p className="text-sm text-default-500 mt-1">{"Permissions Editor Coming Soon."}</p>
              </div>
            </CardBody>
          </Card>
        </div>
      )}
    </div>
  );
}

export default UserPermissions;
