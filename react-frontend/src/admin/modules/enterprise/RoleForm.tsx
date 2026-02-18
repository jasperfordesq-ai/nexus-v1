/**
 * Role Form
 * Create/Edit role form with name, description, permissions checkboxes.
 */

import { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Card, CardBody, Input, Textarea, Button, Checkbox, Spinner } from '@heroui/react';
import { Save, ArrowLeft } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';

export function RoleForm() {
  const { id } = useParams();
  const isEdit = !!id;
  usePageTitle(`Admin - ${isEdit ? 'Edit' : 'Create'} Role`);
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [selectedPermissions, setSelectedPermissions] = useState<Set<string>>(new Set());
  const [allPermissions, setAllPermissions] = useState<Record<string, string[]>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      // Load permissions
      const permRes = await adminEnterprise.getPermissions();
      if (permRes.success && permRes.data) {
        setAllPermissions(permRes.data as unknown as Record<string, string[]>);
      }

      // Load role if editing
      if (isEdit && id) {
        const roleRes = await adminEnterprise.getRole(parseInt(id));
        if (roleRes.success && roleRes.data) {
          const role = roleRes.data as unknown as { name: string; description: string; permissions: string[] };
          setName(role.name || '');
          setDescription(role.description || '');
          setSelectedPermissions(new Set(role.permissions || []));
        }
      }
    } catch {
      toast.error('Failed to load data');
    } finally {
      setLoading(false);
    }
  }, [id, isEdit, toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const togglePermission = (perm: string) => {
    setSelectedPermissions((prev) => {
      const next = new Set(prev);
      if (next.has(perm)) {
        next.delete(perm);
      } else {
        next.add(perm);
      }
      return next;
    });
  };

  const toggleCategory = (category: string) => {
    const perms = allPermissions[category] || [];
    setSelectedPermissions((prev) => {
      const next = new Set(prev);
      const allSelected = perms.every((p) => next.has(p));
      if (allSelected) {
        perms.forEach((p) => next.delete(p));
      } else {
        perms.forEach((p) => next.add(p));
      }
      return next;
    });
  };

  const handleSubmit = async () => {
    if (!name.trim()) {
      toast.error('Name is required');
      return;
    }

    setSaving(true);
    try {
      const payload = {
        name: name.trim(),
        description: description.trim(),
        permissions: Array.from(selectedPermissions),
      };

      let res;
      if (isEdit && id) {
        res = await adminEnterprise.updateRole(parseInt(id), payload);
      } else {
        res = await adminEnterprise.createRole(payload);
      }

      if (res.success) {
        toast.success(isEdit ? 'Role updated' : 'Role created');
        navigate(tenantPath('/admin/enterprise/roles'));
      } else {
        const error = (res as { error?: string }).error || 'Save failed';
        toast.error(error);
      }
    } catch (err) {
      toast.error(`Failed to ${isEdit ? 'update' : 'create'} role`);
      console.error('Role save error:', err);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={isEdit ? 'Edit Role' : 'Create Role'}
        description={isEdit ? 'Update role details and permissions' : 'Define a new role with specific permissions'}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/enterprise/roles'))}
            size="sm"
          >
            Back to Roles
          </Button>
        }
      />

      <div className="space-y-6">
        {/* Basic Info */}
        <Card shadow="sm">
          <CardBody className="p-4 space-y-4">
            <h3 className="text-lg font-semibold">Basic Information</h3>
            <Input
              label="Role Name"
              value={name}
              onValueChange={setName}
              variant="bordered"
              isRequired
              placeholder="e.g. Content Manager"
            />
            <Textarea
              label="Description"
              value={description}
              onValueChange={setDescription}
              variant="bordered"
              placeholder="Describe what this role is for"
              minRows={2}
            />
          </CardBody>
        </Card>

        {/* Permissions */}
        <Card shadow="sm">
          <CardBody className="p-4">
            <h3 className="text-lg font-semibold mb-4">Permissions</h3>
            <div className="space-y-6">
              {Object.entries(allPermissions).map(([category, perms]) => (
                <div key={category}>
                  <div className="flex items-center gap-2 mb-2">
                    <Checkbox
                      isSelected={perms.every((p) => selectedPermissions.has(p))}
                      isIndeterminate={
                        perms.some((p) => selectedPermissions.has(p)) &&
                        !perms.every((p) => selectedPermissions.has(p))
                      }
                      onValueChange={() => toggleCategory(category)}
                    >
                      <span className="font-medium capitalize">{category}</span>
                    </Checkbox>
                  </div>
                  <div className="ml-6 grid grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-3">
                    {perms.map((perm) => (
                      <Checkbox
                        key={perm}
                        isSelected={selectedPermissions.has(perm)}
                        onValueChange={() => togglePermission(perm)}
                        size="sm"
                      >
                        <span className="text-sm text-default-600">{perm}</span>
                      </Checkbox>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>

        {/* Actions */}
        <div className="flex justify-end gap-3">
          <Button
            variant="flat"
            onPress={() => navigate(tenantPath('/admin/enterprise/roles'))}
          >
            Cancel
          </Button>
          <Button
            color="primary"
            startContent={<Save size={16} />}
            onPress={handleSubmit}
            isLoading={saving}
          >
            {isEdit ? 'Update Role' : 'Create Role'}
          </Button>
        </div>
      </div>
    </div>
  );
}

export default RoleForm;
