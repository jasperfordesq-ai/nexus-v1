import { useState, useEffect, useCallback } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import {
  Card, CardBody, Button, Input, Select, SelectItem, Switch, Divider,
} from '@heroui/react';
import { Save, ArrowLeft } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { SuperAdminUserDetail, SuperAdminTenant } from '../../api/types';

export function SuperUserForm() {
  const { id } = useParams();
  const isEditing = !!id;
  usePageTitle(isEditing ? 'Super Admin - Edit User' : 'Super Admin - Create User');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [tenants, setTenants] = useState<SuperAdminTenant[]>([]);
  const [form, setForm] = useState({
    tenant_id: '', first_name: '', last_name: '', email: '', password: '',
    role: 'member', location: '', phone: '', is_tenant_super_admin: false,
  });

  const loadTenants = useCallback(async () => {
    const res = await adminSuper.listTenants();
    if (res.success && res.data) {
      setTenants(Array.isArray(res.data) ? res.data as SuperAdminTenant[] : []);
    }
  }, []);

  const loadUser = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    const res = await adminSuper.getUser(Number(id));
    if (res.success && res.data) {
      const u = res.data as SuperAdminUserDetail;
      setForm({
        tenant_id: String(u.tenant_id), first_name: u.first_name, last_name: u.last_name,
        email: u.email, password: '', role: u.role, location: u.location || '',
        phone: u.phone || '', is_tenant_super_admin: u.is_tenant_super_admin,
      });
    }
    setLoading(false);
  }, [id]);

  useEffect(() => { loadTenants(); }, [loadTenants]);
  useEffect(() => { loadUser(); }, [loadUser]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    let res;
    if (isEditing) {
      res = await adminSuper.updateUser(Number(id), {
        first_name: form.first_name, last_name: form.last_name, email: form.email,
        role: form.role, location: form.location || null, phone: form.phone || null,
      });
    } else {
      res = await adminSuper.createUser({
        tenant_id: Number(form.tenant_id), first_name: form.first_name, last_name: form.last_name,
        email: form.email, password: form.password, role: form.role,
        location: form.location || undefined, phone: form.phone || undefined,
        is_tenant_super_admin: form.is_tenant_super_admin,
      });
    }
    if (res?.success) {
      toast.success(isEditing ? 'User updated' : 'User created');
      if (isEditing) {
        navigate(tenantPath(`/admin/super/users/${id}`));
      } else {
        const newId = (res as { data?: { id?: number } }).data?.id;
        navigate(tenantPath(newId ? `/admin/super/users/${newId}` : '/admin/super/users'));
      }
    } else {
      toast.error(res?.error || 'Failed to save user');
    }
    setSaving(false);
  };

  const update = (field: string, value: unknown) => setForm(prev => ({ ...prev, [field]: value }));

  if (loading) return <div className="p-8 text-center text-default-400">Loading...</div>;

  return (
    <div>
      <nav className="flex items-center gap-1 text-sm text-default-500 mb-1">
        <Link to={tenantPath('/admin/super')} className="hover:text-primary">Super Admin</Link>
        <span>/</span>
        <Link to={tenantPath('/admin/super/users')} className="hover:text-primary">Users</Link>
        <span>/</span>
        <span className="text-foreground">{isEditing ? 'Edit' : 'Create'}</span>
      </nav>
      <PageHeader
        title={isEditing ? 'Edit User' : 'Create User'}
        description={isEditing ? 'Edit user details across tenants' : 'Create a new user in any tenant'}
        actions={
          <Button variant="light" startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath(isEditing ? `/admin/super/users/${id}` : '/admin/super/users'))}>
            Back
          </Button>
        }
      />
      <Card className="max-w-2xl">
        <CardBody>
          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            {!isEditing && (
              <Select label="Tenant" isRequired selectedKeys={form.tenant_id ? [form.tenant_id] : []}
                onSelectionChange={(keys) => update('tenant_id', Array.from(keys)[0])}>
                {tenants.map((t) => <SelectItem key={String(t.id)}>{t.name}</SelectItem>)}
              </Select>
            )}
            <div className="grid grid-cols-2 gap-4">
              <Input label="First Name" isRequired value={form.first_name}
                onValueChange={(v) => update('first_name', v)} />
              <Input label="Last Name" value={form.last_name}
                onValueChange={(v) => update('last_name', v)} />
            </div>
            <Input label="Email" type="email" isRequired value={form.email}
              onValueChange={(v) => update('email', v)} />
            {!isEditing && (
              <Input label="Password" type="password" isRequired value={form.password}
                onValueChange={(v) => update('password', v)} />
            )}
            <Select label="Role" selectedKeys={[form.role]}
              onSelectionChange={(keys) => update('role', Array.from(keys)[0])}>
              <SelectItem key="member">Member</SelectItem>
              <SelectItem key="admin">Admin</SelectItem>
              <SelectItem key="tenant_admin">Tenant Admin</SelectItem>
            </Select>
            <div className="grid grid-cols-2 gap-4">
              <Input label="Location" value={form.location} onValueChange={(v) => update('location', v)} />
              <Input label="Phone" value={form.phone} onValueChange={(v) => update('phone', v)} />
            </div>
            <Divider />
            <Switch isSelected={form.is_tenant_super_admin}
              onValueChange={(v) => update('is_tenant_super_admin', v)}>
              Grant Tenant Super Admin
            </Switch>
            <Button type="submit" color="primary" startContent={<Save size={16} />}
              isLoading={saving}>
              {isEditing ? 'Update User' : 'Create User'}
            </Button>
          </form>
        </CardBody>
      </Card>
    </div>
  );
}
export default SuperUserForm;
