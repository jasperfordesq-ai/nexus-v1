// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Menu Builder
 * Full-featured visual menu builder with drag-drop reordering, icon picker,
 * visibility rules editor, item type selector, and nested item support.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Input,
  Button,
  Spinner,
  Select,
  SelectItem,
  Switch,
  Chip,
  Divider,
} from '@heroui/react';
import {
  Menu,
  ArrowLeft,
  Save,
  Plus,
  Trash2,
  GripVertical,
  Pencil,
  ChevronDown,
  ChevronRight,
  Eye,
  EyeOff,
} from 'lucide-react';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useNavigate, useParams } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { DynamicIcon } from '@/components/ui';
import { adminMenus } from '../../api/adminApi';
import { PageHeader, IconPicker, VisibilityRulesEditor } from '../../components';
import type { MenuItemType, MenuLocation, VisibilityRules } from '@/types/menu';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface MenuItemData {
  id: number;
  label: string;
  url: string | null;
  type: MenuItemType;
  icon: string | null;
  css_class: string | null;
  target: '_self' | '_blank';
  sort_order: number;
  parent_id: number | null;
  visibility_rules: VisibilityRules | null;
  is_active: number;
  route_name?: string | null;
  page_id?: number | null;
  children?: MenuItemData[];
}

interface MenuFormData {
  name: string;
  location: string;
  description: string;
  is_active: boolean;
}

const LOCATION_OPTIONS: { key: MenuLocation; label: string }[] = [
  { key: 'header-main', label: 'Header - Main Navigation' },
  { key: 'header-secondary', label: 'Header - Secondary' },
  { key: 'footer', label: 'Footer' },
  { key: 'sidebar', label: 'Sidebar' },
  { key: 'mobile', label: 'Mobile Menu' },
];

const TYPE_OPTIONS: { key: MenuItemType; label: string; description: string }[] = [
  { key: 'link', label: 'Link', description: 'Internal page link' },
  { key: 'external', label: 'External', description: 'External URL (opens in new tab)' },
  { key: 'dropdown', label: 'Dropdown', description: 'Parent container with child items' },
  { key: 'page', label: 'CMS Page', description: 'Link to a CMS page' },
  { key: 'route', label: 'Named Route', description: 'Link by route name' },
  { key: 'divider', label: 'Divider', description: 'Visual separator' },
];

// ─────────────────────────────────────────────────────────────────────────────
// Sortable Item Component
// ─────────────────────────────────────────────────────────────────────────────

interface SortableItemProps {
  item: MenuItemData;
  isSelected: boolean;
  onSelect: () => void;
  onDelete: () => void;
  depth?: number;
}

function SortableItem({ item, isSelected, onSelect, onDelete, depth = 0 }: SortableItemProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: item.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
    marginLeft: depth * 24,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`flex items-center gap-2 rounded-lg border p-2.5 transition-colors cursor-pointer ${
        isSelected
          ? 'border-indigo-500 bg-indigo-500/5'
          : 'border-default-200 hover:border-default-300'
      } ${!item.is_active ? 'opacity-50' : ''}`}
      onClick={onSelect}
    >
      <Button
        isIconOnly
        variant="light"
        size="sm"
        className="cursor-grab active:cursor-grabbing text-default-300 hover:text-default-500 p-0.5 min-w-0 h-auto"
        {...attributes}
        {...listeners}
        aria-label="Drag to reorder"
        onClick={(e) => e.stopPropagation()}
      >
        <GripVertical size={16} />
      </Button>

      <DynamicIcon name={item.icon} className="w-4 h-4 text-theme-muted shrink-0" />

      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-1.5">
          <p className="text-sm font-medium truncate">{item.label}</p>
          <Chip size="sm" variant="flat" className="text-[10px] h-4">
            {item.type}
          </Chip>
          {!item.is_active && (
            <EyeOff size={12} className="text-default-300" />
          )}
        </div>
        {item.url && (
          <p className="text-[11px] text-default-400 truncate">{item.url}</p>
        )}
      </div>

      {item.children && item.children.length > 0 && (
        <Chip size="sm" variant="flat" color="secondary" className="text-[10px]">
          {item.children.length} sub
        </Chip>
      )}

      <Button
        isIconOnly
        size="sm"
        variant="light"
        onPress={onSelect}
        aria-label="Edit item"
        onClick={(e) => e.stopPropagation()}
      >
        <Pencil size={13} />
      </Button>

      <Button
        isIconOnly
        size="sm"
        variant="light"
        color="danger"
        onPress={() => { onDelete(); }}
        aria-label="Delete item"
        onClick={(e) => e.stopPropagation()}
      >
        <Trash2 size={13} />
      </Button>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function MenuBuilder() {
  const { id } = useParams<{ id: string }>();
  const isEdit = id !== undefined && id !== 'new';
  usePageTitle('Admin - Menu Builder');
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  // Menu metadata
  const [formData, setFormData] = useState<MenuFormData>({
    name: '',
    location: 'header-main',
    description: '',
    is_active: true,
  });

  // Items — flat list, children nested under parents
  const [menuItems, setMenuItems] = useState<MenuItemData[]>([]);
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);

  // Item editor state
  const [selectedItemId, setSelectedItemId] = useState<number | null>(null);
  const [editForm, setEditForm] = useState<Partial<MenuItemData>>({});
  const [showAdvanced, setShowAdvanced] = useState(false);

  // DnD sensors
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  // ─── Data Loading ────────────────────────────────────────────────────────

  const flattenItems = useCallback((items: MenuItemData[], depth = 0): MenuItemData[] => {
    const result: MenuItemData[] = [];
    for (const item of items) {
      result.push(item);
      if (item.children && item.children.length > 0) {
        result.push(...flattenItems(item.children, depth + 1));
      }
    }
    return result;
  }, []);

  const loadMenu = useCallback(async () => {
    if (!isEdit) return;
    setLoading(true);
    try {
      const [menuRes, itemsRes] = await Promise.all([
        adminMenus.get(Number(id)),
        adminMenus.getItems(Number(id)),
      ]);

      if (menuRes.success && menuRes.data) {
        const menu = menuRes.data as Record<string, unknown>;
        setFormData({
          name: (menu.name as string) || '',
          location: (menu.location as string) || 'header-main',
          description: (menu.description as string) || '',
          is_active: Boolean(menu.is_active),
        });
      }

      if (itemsRes.success && itemsRes.data) {
        const raw = itemsRes.data as unknown;
        const items = Array.isArray(raw) ? raw : (raw as { data?: MenuItemData[] })?.data || [];
        setMenuItems(flattenItems(items as MenuItemData[]));
      }
    } catch {
      toast.error('Failed to load menu');
    } finally {
      setLoading(false);
    }
  }, [id, isEdit, flattenItems, toast]);

  useEffect(() => { loadMenu(); }, [loadMenu]);

  // ─── Menu Settings ───────────────────────────────────────────────────────

  const handleChange = (field: keyof MenuFormData, value: string | boolean) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSave = async () => {
    if (!formData.name.trim()) {
      toast.warning('Menu name is required');
      return;
    }
    if (!formData.location) {
      toast.warning('Menu location is required');
      return;
    }
    setSaving(true);
    try {
      if (isEdit) {
        const res = await adminMenus.update(Number(id), {
          name: formData.name,
          location: formData.location,
          description: formData.description,
          is_active: formData.is_active ? 1 : 0,
        });
        if (res?.success) {
          toast.success('Menu updated');
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
          const newMenu = res.data as { id?: number } | undefined;
          const newMenuId = newMenu?.id;
          // Create locally-added items on the new menu
          if (newMenuId && menuItems.length > 0) {
            for (const item of menuItems) {
              await adminMenus.createItem(newMenuId, {
                label: item.label,
                url: item.url,
                type: item.type,
                icon: item.icon,
                target: item.target,
                sort_order: item.sort_order,
                visibility_rules: item.visibility_rules,
                is_active: item.is_active,
              });
            }
          }
          toast.success('Menu created');
          navigate(tenantPath('/admin/menus'));
          return;
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

  // ─── Item CRUD ───────────────────────────────────────────────────────────

  const selectItem = (item: MenuItemData) => {
    setSelectedItemId(item.id);
    setEditForm({
      label: item.label,
      url: item.url,
      type: item.type,
      icon: item.icon,
      css_class: item.css_class,
      target: item.target,
      sort_order: item.sort_order,
      parent_id: item.parent_id,
      visibility_rules: item.visibility_rules,
      is_active: item.is_active,
      route_name: item.route_name,
      page_id: item.page_id,
    });
    setShowAdvanced(Boolean(item.css_class || item.visibility_rules));
  };

  const clearSelection = () => {
    setSelectedItemId(null);
    setEditForm({});
    setShowAdvanced(false);
  };

  const handleAddItem = async () => {
    const newItem: Partial<MenuItemData> = {
      label: 'New Item',
      url: '/',
      type: 'link',
      icon: null,
      css_class: null,
      target: '_self',
      sort_order: menuItems.length,
      parent_id: null,
      visibility_rules: null,
      is_active: 1,
    };

    if (isEdit) {
      try {
        const res = await adminMenus.createItem(Number(id), newItem);
        if (res?.success) {
          toast.success('Item added');
          await loadMenu();
          // Select the new item
          const created = res.data as MenuItemData | undefined;
          if (created?.id) selectItem(created);
        } else {
          toast.error('Failed to add item');
        }
      } catch {
        toast.error('An unexpected error occurred');
      }
    } else {
      const localItem: MenuItemData = {
        ...newItem as MenuItemData,
        id: Date.now(),
      };
      setMenuItems((prev) => [...prev, localItem]);
      selectItem(localItem);
    }
  };

  const handleUpdateItem = async () => {
    if (!selectedItemId) return;

    if (isEdit) {
      try {
        const payload: Record<string, unknown> = { ...editForm };
        if (payload.visibility_rules) {
          payload.visibility_rules = JSON.stringify(payload.visibility_rules);
        }
        const res = await adminMenus.updateItem(selectedItemId, payload);
        if (res?.success) {
          toast.success('Item updated');
          await loadMenu();
        } else {
          toast.error('Failed to update item');
        }
      } catch {
        toast.error('An unexpected error occurred');
      }
    } else {
      // Update locally
      setMenuItems((prev) =>
        prev.map((item) =>
          item.id === selectedItemId ? { ...item, ...editForm } as MenuItemData : item
        )
      );
      toast.success('Item updated');
    }
  };

  const handleDeleteItem = async (itemId: number) => {
    if (isEdit) {
      try {
        const res = await adminMenus.deleteItem(itemId);
        if (res?.success) {
          toast.success('Item deleted');
          if (selectedItemId === itemId) clearSelection();
          await loadMenu();
        } else {
          toast.error('Failed to delete item');
        }
      } catch {
        toast.error('An unexpected error occurred');
      }
    } else {
      setMenuItems((prev) => prev.filter((i) => i.id !== itemId));
      if (selectedItemId === itemId) clearSelection();
    }
  };

  // ─── Drag & Drop ─────────────────────────────────────────────────────────

  const handleDragEnd = async (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;

    const oldIndex = menuItems.findIndex((i) => i.id === active.id);
    const newIndex = menuItems.findIndex((i) => i.id === over.id);
    if (oldIndex === -1 || newIndex === -1) return;

    // Reorder locally
    const updated = [...menuItems];
    const [moved] = updated.splice(oldIndex, 1);
    updated.splice(newIndex, 0, moved);

    // Update sort_order
    const reordered = updated.map((item, idx) => ({ ...item, sort_order: idx }));
    setMenuItems(reordered);

    // Persist to API
    if (isEdit) {
      try {
        await adminMenus.reorderItems(
          Number(id),
          reordered.map((item) => ({
            id: item.id,
            sort_order: item.sort_order,
            parent_id: item.parent_id,
          })),
        );
      } catch {
        toast.error('Failed to save reorder');
        await loadMenu();
      }
    }
  };

  // ─── Item depth (for visual indentation) ─────────────────────────────────

  const getItemDepth = (item: MenuItemData): number => {
    if (!item.parent_id) return 0;
    const parent = menuItems.find((i) => i.id === item.parent_id);
    return parent ? 1 + getItemDepth(parent) : 0;
  };

  // ─── Parent options for dropdown nesting ──────────────────────────────────

  const parentOptions = menuItems
    .filter((i) => i.type === 'dropdown' && i.id !== selectedItemId)
    .map((i) => ({ key: String(i.id), label: i.label }));

  // ─── Render ──────────────────────────────────────────────────────────────

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
        title={isEdit ? 'Edit Menu' : 'New Menu'}
        description="Build and organize navigation menu items with drag-and-drop"
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/menus'))}
            >
              Back
            </Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
            >
              {isEdit ? 'Save' : 'Create'} Menu
            </Button>
          </div>
        }
      />

      {/* Menu Settings */}
      <Card shadow="sm" className="mb-4">
        <CardBody>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Input
              label="Menu Name"
              placeholder="e.g., Main Navigation"
              isRequired
              variant="bordered"
              value={formData.name}
              onValueChange={(v) => handleChange('name', v)}
            />
            <Select
              label="Location"
              isRequired
              variant="bordered"
              selectedKeys={formData.location ? [formData.location] : []}
              onSelectionChange={(keys) => {
                const sel = Array.from(keys)[0] as string;
                if (sel) handleChange('location', sel);
              }}
            >
              {LOCATION_OPTIONS.map((opt) => (
                <SelectItem key={opt.key}>{opt.label}</SelectItem>
              ))}
            </Select>
            <div className="flex items-end gap-4">
              <Input
                label="Description"
                placeholder="Optional"
                variant="bordered"
                value={formData.description}
                onValueChange={(v) => handleChange('description', v)}
                className="flex-1"
              />
              <Switch
                isSelected={formData.is_active}
                onValueChange={(v) => handleChange('is_active', v)}
                size="sm"
              >
                <span className="text-sm">Active</span>
              </Switch>
            </div>
          </div>
        </CardBody>
      </Card>

      <div className="grid grid-cols-1 lg:grid-cols-5 gap-4">
        {/* Left: Item Tree (3/5 width) */}
        <div className="lg:col-span-3">
          <Card shadow="sm">
            <CardHeader className="flex items-center justify-between">
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <Menu size={20} /> Menu Items
                <Chip size="sm" variant="flat">{menuItems.length}</Chip>
              </h3>
              <Button
                size="sm"
                variant="flat"
                color="primary"
                startContent={<Plus size={14} />}
                onPress={handleAddItem}
              >
                Add Item
              </Button>
            </CardHeader>
            <CardBody>
              {menuItems.length === 0 ? (
                <div className="flex flex-col items-center py-12 text-default-400">
                  <Menu size={40} className="mb-3" />
                  <p className="text-sm mb-3">No menu items yet</p>
                  <Button
                    size="sm"
                    color="primary"
                    startContent={<Plus size={14} />}
                    onPress={handleAddItem}
                  >
                    Add First Item
                  </Button>
                </div>
              ) : (
                <DndContext
                  sensors={sensors}
                  collisionDetection={closestCenter}
                  onDragEnd={handleDragEnd}
                >
                  <SortableContext
                    items={menuItems.map((i) => i.id)}
                    strategy={verticalListSortingStrategy}
                  >
                    <div className="space-y-1.5">
                      {menuItems.map((item) => (
                        <SortableItem
                          key={item.id}
                          item={item}
                          isSelected={selectedItemId === item.id}
                          onSelect={() => selectItem(item)}
                          onDelete={() => handleDeleteItem(item.id)}
                          depth={getItemDepth(item)}
                        />
                      ))}
                    </div>
                  </SortableContext>
                </DndContext>
              )}
            </CardBody>
          </Card>
        </div>

        {/* Right: Item Editor (2/5 width) */}
        <div className="lg:col-span-2">
          <Card shadow="sm" className="sticky top-20">
            <CardHeader>
              <h3 className="text-lg font-semibold">
                {selectedItemId ? 'Edit Item' : 'Item Editor'}
              </h3>
            </CardHeader>
            <CardBody className="gap-3">
              {!selectedItemId ? (
                <div className="flex flex-col items-center py-8 text-default-400">
                  <Pencil size={32} className="mb-3" />
                  <p className="text-sm">Select an item to edit, or add a new one</p>
                </div>
              ) : (
                <>
                  {/* Label */}
                  <Input
                    label="Label"
                    placeholder="Menu item text"
                    isRequired
                    variant="bordered"
                    size="sm"
                    value={editForm.label || ''}
                    onValueChange={(v) => setEditForm((f) => ({ ...f, label: v }))}
                  />

                  {/* Type */}
                  <Select
                    label="Type"
                    variant="bordered"
                    size="sm"
                    selectedKeys={editForm.type ? [editForm.type] : ['link']}
                    onSelectionChange={(keys) => {
                      const sel = Array.from(keys)[0] as MenuItemType;
                      if (sel) {
                        const updates: Partial<MenuItemData> = { type: sel };
                        if (sel === 'external') updates.target = '_blank';
                        if (sel === 'dropdown') updates.url = null;
                        if (sel === 'divider') { updates.url = null; updates.icon = null; }
                        setEditForm((f) => ({ ...f, ...updates }));
                      }
                    }}
                  >
                    {TYPE_OPTIONS.map((opt) => (
                      <SelectItem key={opt.key} textValue={opt.label}>
                        <div>
                          <p className="text-sm font-medium">{opt.label}</p>
                          <p className="text-xs text-default-400">{opt.description}</p>
                        </div>
                      </SelectItem>
                    ))}
                  </Select>

                  {/* Conditional URL field */}
                  {(editForm.type === 'link' || editForm.type === 'external' || !editForm.type) && (
                    <Input
                      label="URL"
                      placeholder={editForm.type === 'external' ? 'https://example.com' : '/dashboard'}
                      variant="bordered"
                      size="sm"
                      value={editForm.url || ''}
                      onValueChange={(v) => setEditForm((f) => ({ ...f, url: v }))}
                    />
                  )}

                  {editForm.type === 'route' && (
                    <Input
                      label="Route Name"
                      placeholder="e.g., dashboard"
                      variant="bordered"
                      size="sm"
                      value={editForm.route_name || ''}
                      onValueChange={(v) => setEditForm((f) => ({ ...f, route_name: v }))}
                    />
                  )}

                  {/* Icon picker (not for divider) */}
                  {editForm.type !== 'divider' && (
                    <IconPicker
                      value={editForm.icon || null}
                      onChange={(icon) => setEditForm((f) => ({ ...f, icon }))}
                    />
                  )}

                  {/* Target */}
                  {editForm.type !== 'divider' && editForm.type !== 'dropdown' && (
                    <Select
                      label="Open in"
                      variant="bordered"
                      size="sm"
                      selectedKeys={[editForm.target || '_self']}
                      onSelectionChange={(keys) => {
                        const sel = Array.from(keys)[0] as '_self' | '_blank';
                        if (sel) setEditForm((f) => ({ ...f, target: sel }));
                      }}
                    >
                      <SelectItem key="_self">Same window</SelectItem>
                      <SelectItem key="_blank">New tab</SelectItem>
                    </Select>
                  )}

                  {/* Parent (for nesting under dropdown items) */}
                  {editForm.type !== 'dropdown' && parentOptions.length > 0 && (
                    <Select
                      label="Parent item"
                      variant="bordered"
                      size="sm"
                      selectedKeys={editForm.parent_id ? [String(editForm.parent_id)] : []}
                      onSelectionChange={(keys) => {
                        const sel = Array.from(keys)[0] as string;
                        setEditForm((f) => ({ ...f, parent_id: sel ? Number(sel) : null }));
                      }}
                    >
                      {[
                        { key: '', label: 'None (top level)' },
                        ...parentOptions,
                      ].map((opt) => (
                        <SelectItem key={opt.key}>{opt.label}</SelectItem>
                      ))}
                    </Select>
                  )}

                  {/* Active toggle */}
                  <Switch
                    isSelected={Boolean(editForm.is_active)}
                    onValueChange={(v) => setEditForm((f) => ({ ...f, is_active: v ? 1 : 0 }))}
                    size="sm"
                  >
                    <span className="text-sm flex items-center gap-1.5">
                      {editForm.is_active ? <Eye size={14} /> : <EyeOff size={14} />}
                      {editForm.is_active ? 'Visible' : 'Hidden'}
                    </span>
                  </Switch>

                  <Divider />

                  {/* Advanced section (collapsible) */}
                  <Button
                    variant="light"
                    className="flex items-center gap-1.5 text-sm text-theme-muted hover:text-theme-primary transition-colors h-auto p-0 justify-start"
                    onPress={() => setShowAdvanced(!showAdvanced)}
                  >
                    {showAdvanced ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
                    Advanced options
                  </Button>

                  {showAdvanced && (
                    <div className="space-y-3 pl-2 border-l-2 border-default-100">
                      <Input
                        label="CSS Class"
                        placeholder="e.g., text-red-500"
                        variant="bordered"
                        size="sm"
                        value={editForm.css_class || ''}
                        onValueChange={(v) => setEditForm((f) => ({ ...f, css_class: v || null }))}
                      />

                      <VisibilityRulesEditor
                        value={editForm.visibility_rules || null}
                        onChange={(rules) => setEditForm((f) => ({ ...f, visibility_rules: rules }))}
                      />
                    </div>
                  )}

                  <Divider />

                  {/* Save / Cancel */}
                  <div className="flex gap-2">
                    <Button
                      color="primary"
                      size="sm"
                      className="flex-1"
                      onPress={handleUpdateItem}
                    >
                      Save Item
                    </Button>
                    <Button
                      variant="flat"
                      size="sm"
                      onPress={clearSelection}
                    >
                      Cancel
                    </Button>
                  </div>
                </>
              )}
            </CardBody>
          </Card>
        </div>
      </div>
    </div>
  );
}

export default MenuBuilder;
