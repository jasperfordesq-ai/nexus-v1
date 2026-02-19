// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Menu Builder
 * Visual menu builder for creating and editing navigation menus.
 * Wired to adminMenus API for get/update/create items.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Input, Button, Spinner } from '@heroui/react';
import { Menu, ArrowLeft, Save, Plus, Trash2, GripVertical } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminMenus } from '../../api/adminApi';
import { PageHeader } from '../../components';

interface MenuItemData {
  id: number;
  label: string;
  url: string;
  sort_order: number;
  parent_id: number | null;
}

interface MenuFormData {
  name: string;
  location: string;
  description: string;
}

export function MenuBuilder() {
  const { id } = useParams<{ id: string }>();
  const isEdit = id !== undefined && id !== 'new';
  usePageTitle('Admin - Menu Builder');
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [formData, setFormData] = useState<MenuFormData>({
    name: '',
    location: '',
    description: '',
  });
  const [menuItems, setMenuItems] = useState<MenuItemData[]>([]);
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [newItemLabel, setNewItemLabel] = useState('');
  const [newItemUrl, setNewItemUrl] = useState('');

  useEffect(() => {
    if (isEdit) {
      Promise.all([
        adminMenus.get(Number(id)),
        adminMenus.getItems(Number(id)),
      ])
        .then(([menuRes, itemsRes]) => {
          if (menuRes.success && menuRes.data) {
            const menu = menuRes.data as Record<string, unknown>;
            setFormData({
              name: (menu.name as string) || '',
              location: (menu.location as string) || '',
              description: (menu.description as string) || '',
            });
          }
          if (itemsRes.success && itemsRes.data) {
            const items = itemsRes.data as unknown;
            if (Array.isArray(items)) {
              setMenuItems(items);
            } else if (items && typeof items === 'object') {
              const pd = items as { data?: MenuItemData[] };
              setMenuItems(pd.data || []);
            }
          }
        })
        .catch(() => toast.error('Failed to load menu'))
        .finally(() => setLoading(false));
    }
  }, [id, isEdit]);

  const handleChange = (field: keyof MenuFormData, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleAddItem = async () => {
    if (!newItemLabel.trim()) {
      toast.warning('Menu item label is required');
      return;
    }
    if (isEdit) {
      try {
        const res = await adminMenus.createItem(Number(id), {
          label: newItemLabel,
          url: newItemUrl || '#',
          sort_order: menuItems.length,
        });
        if (res?.success) {
          toast.success('Menu item added');
          // Reload items
          const itemsRes = await adminMenus.getItems(Number(id));
          if (itemsRes.success && itemsRes.data) {
            const items = itemsRes.data as unknown;
            if (Array.isArray(items)) setMenuItems(items);
          }
          setNewItemLabel('');
          setNewItemUrl('');
        } else {
          toast.error('Failed to add menu item');
        }
      } catch {
        toast.error('An unexpected error occurred');
      }
    } else {
      // For new menus, add locally until menu is created
      setMenuItems((prev) => [
        ...prev,
        { id: Date.now(), label: newItemLabel, url: newItemUrl || '#', sort_order: prev.length, parent_id: null },
      ]);
      setNewItemLabel('');
      setNewItemUrl('');
    }
  };

  const handleRemoveItem = async (item: MenuItemData) => {
    if (isEdit) {
      try {
        const res = await adminMenus.deleteItem(item.id);
        if (res?.success) {
          toast.success('Menu item removed');
          setMenuItems((prev) => prev.filter((i) => i.id !== item.id));
        } else {
          toast.error('Failed to remove menu item');
        }
      } catch {
        toast.error('An unexpected error occurred');
      }
    } else {
      setMenuItems((prev) => prev.filter((i) => i.id !== item.id));
    }
  };

  const handleSave = async () => {
    if (!formData.name.trim()) {
      toast.warning('Menu name is required');
      return;
    }
    setSaving(true);
    try {
      if (isEdit) {
        const res = await adminMenus.update(Number(id), formData as unknown as Record<string, unknown>);
        if (res?.success) {
          toast.success('Menu updated successfully');
          navigate(tenantPath('/admin/menus'));
        } else {
          toast.error('Failed to update menu');
        }
      } else {
        const res = await adminMenus.create({
          name: formData.name,
          location: formData.location,
          description: formData.description,
        });
        if (res?.success) {
          // If there are locally-added items, create them on the new menu
          const newMenu = res.data as { id?: number } | undefined;
          const newMenuId = newMenu?.id;
          if (newMenuId && menuItems.length > 0) {
            for (const item of menuItems) {
              await adminMenus.createItem(newMenuId, {
                label: item.label,
                url: item.url,
                sort_order: item.sort_order,
              });
            }
          }
          toast.success('Menu created successfully');
          navigate(tenantPath('/admin/menus'));
        } else {
          toast.error('Failed to create menu');
        }
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div>
        <PageHeader title="Menu Builder" description="Loading menu..." />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={isEdit ? 'Edit Menu' : 'Menu Builder'}
        description="Build and organize navigation menu items"
        actions={<Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/menus'))}>Back</Button>}
      />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Menu size={20} /> Menu Structure</h3></CardHeader>
          <CardBody>
            {menuItems.length === 0 ? (
              <div className="flex flex-col items-center py-8 text-default-400">
                <Menu size={40} className="mb-3" />
                <p className="text-sm">No menu items yet. Add items below.</p>
              </div>
            ) : (
              <div className="space-y-2">
                {menuItems.map((item) => (
                  <div key={item.id} className="flex items-center gap-2 rounded-lg border border-default-200 p-3">
                    <GripVertical size={16} className="text-default-300 shrink-0" />
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium truncate">{item.label}</p>
                      <p className="text-xs text-default-400 truncate">{item.url}</p>
                    </div>
                    <Button
                      isIconOnly
                      size="sm"
                      variant="flat"
                      color="danger"
                      onPress={() => handleRemoveItem(item)}
                      aria-label="Remove item"
                    >
                      <Trash2 size={14} />
                    </Button>
                  </div>
                ))}
              </div>
            )}

            <div className="mt-4 space-y-2 border-t border-default-100 pt-4">
              <Input
                label="Item Label"
                placeholder="e.g., Home"
                size="sm"
                variant="bordered"
                value={newItemLabel}
                onValueChange={setNewItemLabel}
              />
              <Input
                label="Item URL"
                placeholder="e.g., /dashboard"
                size="sm"
                variant="bordered"
                value={newItemUrl}
                onValueChange={setNewItemUrl}
              />
              <Button
                size="sm"
                variant="flat"
                startContent={<Plus size={14} />}
                onPress={handleAddItem}
              >
                Add Menu Item
              </Button>
            </div>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Menu Settings</h3></CardHeader>
          <CardBody className="gap-4">
            <Input
              label="Menu Name"
              placeholder="e.g., Main Navigation"
              isRequired
              variant="bordered"
              value={formData.name}
              onValueChange={(v) => handleChange('name', v)}
            />
            <Input
              label="Menu Location"
              placeholder="e.g., header, footer, sidebar"
              variant="bordered"
              value={formData.location}
              onValueChange={(v) => handleChange('location', v)}
            />
            <Input
              label="Description"
              placeholder="Optional description"
              variant="bordered"
              value={formData.description}
              onValueChange={(v) => handleChange('description', v)}
            />
            <div className="flex justify-end gap-2 pt-2">
              <Button variant="flat" onPress={() => navigate(tenantPath('/admin/menus'))}>Cancel</Button>
              <Button
                color="primary"
                startContent={<Save size={16} />}
                onPress={handleSave}
                isLoading={saving}
              >
                {isEdit ? 'Update' : 'Save'} Menu
              </Button>
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default MenuBuilder;
