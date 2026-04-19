// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Menu Builder
 * Visual menu builder with drag-drop reordering, drag-to-nest, live preview,
 * icon picker, visibility rules editor, page/route pickers, and nested item support.
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
  DragOverlay,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragStartEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
  arrayMove,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useNavigate, useParams } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { DynamicIcon } from '@/components/ui';
import { adminMenus, adminPages } from '../../api/adminApi';
import { PageHeader, IconPicker, VisibilityRulesEditor, ConfirmModal } from '../../components';
import type { MenuItemType, MenuLocation, VisibilityRules } from '@/types/menu';
import { useTranslation } from 'react-i18next';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

/** Known app routes for the route-type picker */
const APP_ROUTES: { value: string; label: string; group: string }[] = [
  { value: '/dashboard', label: 'Dashboard', group: 'Core' },
  { value: '/feed', label: 'Feed', group: 'Core' },
  { value: '/listings', label: 'Listings', group: 'Core' },
  { value: '/listings/new', label: 'Create Listing', group: 'Core' },
  { value: '/messages', label: 'Messages', group: 'Core' },
  { value: '/wallet', label: 'Wallet', group: 'Core' },
  { value: '/members', label: 'Members', group: 'Community' },
  { value: '/groups', label: 'Groups', group: 'Community' },
  { value: '/events', label: 'Events', group: 'Community' },
  { value: '/search', label: 'Search', group: 'Community' },
  { value: '/volunteering', label: 'Volunteering', group: 'Features' },
  { value: '/organisations', label: 'Organisations', group: 'Features' },
  { value: '/goals', label: 'Goals', group: 'Features' },
  { value: '/blog', label: 'Blog', group: 'Features' },
  { value: '/resources', label: 'Resources', group: 'Features' },
  { value: '/jobs', label: 'Jobs', group: 'Features' },
  { value: '/marketplace', label: 'Marketplace', group: 'Features' },
  { value: '/leaderboard', label: 'Leaderboard', group: 'Gamification' },
  { value: '/achievements', label: 'Achievements', group: 'Gamification' },
  { value: '/about', label: 'About', group: 'Info' },
  { value: '/contact', label: 'Contact', group: 'Info' },
  { value: '/help', label: 'Help Center', group: 'Info' },
  { value: '/terms', label: 'Terms', group: 'Legal' },
  { value: '/privacy', label: 'Privacy Policy', group: 'Legal' },
];

const NEST_THRESHOLD = 40;

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

interface PageOption {
  id: number;
  title: string;
  slug: string;
}

type TFunction = (key: string, options?: Record<string, unknown>) => string;

const getLocationOptions = (t: TFunction): { key: MenuLocation; label: string }[] => [
  { key: 'header-main', label: t('menu_builder.location_header_main') },
  { key: 'header-secondary', label: t('menu_builder.location_header_secondary') },
  { key: 'footer', label: t('menu_builder.location_footer') },
  { key: 'sidebar', label: t('menu_builder.location_sidebar') },
  { key: 'mobile', label: t('menu_builder.location_mobile') },
];

const getTypeOptions = (t: TFunction): { key: MenuItemType; label: string; description: string }[] => [
  { key: 'link', label: t('menu_builder.type_link_label'), description: t('menu_builder.type_link_desc') },
  { key: 'external', label: t('menu_builder.type_external_label'), description: t('menu_builder.type_external_desc') },
  { key: 'dropdown', label: t('menu_builder.type_dropdown_label'), description: t('menu_builder.type_dropdown_desc') },
  { key: 'page', label: t('menu_builder.type_page_label'), description: t('menu_builder.type_page_desc') },
  { key: 'route', label: t('menu_builder.type_route_label'), description: t('menu_builder.type_route_desc') },
  { key: 'divider', label: t('menu_builder.type_divider_label'), description: t('menu_builder.type_divider_desc') },
];

// ─────────────────────────────────────────────────────────────────────────────
// Live Preview Component
// ─────────────────────────────────────────────────────────────────────────────

function LivePreview({ items }: { items: MenuItemData[] }) {
  const { t } = useTranslation('admin');
  const topLevel = items.filter((i) => !i.parent_id && i.is_active);

  return (
    <Card shadow="sm" className="mb-4 border border-indigo-200 dark:border-indigo-800">
      <CardHeader className="pb-2">
        <h3 className="text-sm font-semibold flex items-center gap-2 text-indigo-600 dark:text-indigo-400">
          <Eye size={15} />
          {t('content.live_preview')}
          <Chip size="sm" variant="flat" color="secondary" className="text-[10px]">
            {t('content.preview')}
          </Chip>
        </h3>
      </CardHeader>
      <CardBody className="pt-0">
        <div className="flex items-center gap-1 min-h-[40px] px-3 py-2 rounded-lg bg-[var(--color-surface,#f9fafb)] dark:bg-default-800/50 flex-wrap border border-dashed border-default-200">
          {topLevel.length === 0 ? (
            <p className="text-xs text-default-400">{t('no_data')}</p>
          ) : (
            topLevel.map((item) => {
              const children = items.filter((i) => i.parent_id === item.id && i.is_active);

              if (item.type === 'divider') {
                return <div key={item.id} className="w-px h-5 bg-default-300 mx-1 shrink-0" />;
              }

              if (item.type === 'dropdown' || children.length > 0) {
                return (
                  <div key={item.id} className="relative group/preview">
                    <button className="flex items-center gap-1.5 px-3 py-1.5 rounded text-xs font-medium text-default-600 hover:bg-default-100 transition-colors">
                      <DynamicIcon name={item.icon} className="w-3.5 h-3.5 shrink-0" />
                      <span>{item.label}</span>
                      <ChevronDown size={11} />
                    </button>
                    {children.length > 0 && (
                      <div className="hidden group-hover/preview:block absolute top-full left-0 z-20 bg-white dark:bg-default-800 border border-default-200 rounded-lg shadow-lg p-1 min-w-[150px]">
                        {children.map((child) => (
                          <div key={child.id} className="flex items-center gap-2 px-3 py-1.5 text-xs rounded hover:bg-default-100">
                            <DynamicIcon name={child.icon} className="w-3 h-3 text-default-400 shrink-0" />
                            <span>{child.label}</span>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                );
              }

              return (
                <div key={item.id} className="flex items-center gap-1.5 px-3 py-1.5 rounded text-xs font-medium text-default-600">
                  <DynamicIcon name={item.icon} className="w-3.5 h-3.5 shrink-0" />
                  <span>{item.label}</span>
                </div>
              );
            })
          )}
        </div>
        <p className="text-[10px] text-default-400 mt-1.5">{t('menu_builder.drag_hint')}</p>
      </CardBody>
    </Card>
  );
}

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
  const { t } = useTranslation('admin');
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
    opacity: isDragging ? 0.4 : 1,
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
        className="cursor-grab active:cursor-grabbing text-default-300 hover:text-default-500 p-0.5 min-w-0 h-auto shrink-0"
        {...attributes}
        {...listeners}
        aria-label={t('content.drag_to_reorder')}
        onClick={(e) => e.stopPropagation()}
      >
        <GripVertical size={16} />
      </Button>

      <DynamicIcon name={item.icon} className="w-4 h-4 text-theme-muted shrink-0" />

      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-1.5">
          <p className="text-sm font-medium truncate">{item.label}</p>
          <Chip size="sm" variant="flat" className="text-[10px] h-4 shrink-0">
            {item.type}
          </Chip>
          {!item.is_active && <EyeOff size={12} className="text-default-300 shrink-0" />}
        </div>
        {item.url && (
          <p className="text-[11px] text-default-400 truncate">{item.url}</p>
        )}
      </div>

      {item.children && item.children.length > 0 && (
        <Chip size="sm" variant="flat" color="secondary" className="text-[10px] shrink-0">
          {t('menu_builder.sub_items', { count: item.children.length })}
        </Chip>
      )}

      <Button
        isIconOnly
        size="sm"
        variant="light"
        onPress={onSelect}
        aria-label={t('content.label_edit_item')}
        onClick={(e) => e.stopPropagation()}
      >
        <Pencil size={13} />
      </Button>

      <Button
        isIconOnly
        size="sm"
        variant="light"
        color="danger"
        onPress={() => onDelete()}
        aria-label={t('content.label_delete_item')}
        onClick={(e) => e.stopPropagation()}
      >
        <Trash2 size={13} />
      </Button>
    </div>
  );
}

/** Static card used in DragOverlay — no DnD hooks */
function DragItemCard({ item }: { item: MenuItemData }) {
  return (
    <div className="flex items-center gap-2 rounded-lg border border-indigo-400 bg-indigo-50 dark:bg-indigo-950 p-2.5 shadow-lg opacity-90">
      <GripVertical size={16} className="text-indigo-400 shrink-0" />
      <DynamicIcon name={item.icon} className="w-4 h-4 text-indigo-500 shrink-0" />
      <p className="text-sm font-medium truncate text-indigo-700 dark:text-indigo-300">{item.label}</p>
      <Chip size="sm" variant="flat" color="secondary" className="text-[10px] h-4 shrink-0">
        {item.type}
      </Chip>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function MenuBuilder() {
  const { t } = useTranslation('admin');
  const { id } = useParams<{ id: string }>();
  const isEdit = id !== undefined && id !== 'new';
  usePageTitle(t('content.page_title'));
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [formData, setFormData] = useState<MenuFormData>({
    name: '',
    location: 'header-main',
    description: '',
    is_active: true,
  });

  const [menuItems, setMenuItems] = useState<MenuItemData[]>([]);
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);

  // Item editor state
  const [selectedItemId, setSelectedItemId] = useState<number | null>(null);
  const [editForm, setEditForm] = useState<Partial<MenuItemData>>({});
  const [showAdvanced, setShowAdvanced] = useState(false);

  // Live preview toggle
  const [showPreview, setShowPreview] = useState(false);

  // Drag state
  const [activeItem, setActiveItem] = useState<MenuItemData | null>(null);

  // Pages for page-type picker
  const [pages, setPages] = useState<PageOption[]>([]);
  const [pagesLoaded, setPagesLoaded] = useState(false);

  // Delete confirmation
  const [deleteTarget, setDeleteTarget] = useState<number | null>(null);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  // ─── Data Loading ────────────────────────────────────────────────────────

  const flattenItems = useCallback((items: MenuItemData[], _depth = 0): MenuItemData[] => {
    const result: MenuItemData[] = [];
    for (const item of items) {
      result.push(item);
      if (item.children && item.children.length > 0) {
        result.push(...flattenItems(item.children, _depth + 1));
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
      toast.error(t('content.failed_to_load_menu'));
    } finally {
      setLoading(false);
    }
  }, [id, isEdit, flattenItems, toast, t]);

  useEffect(() => { loadMenu(); }, [loadMenu]);

  const loadPages = useCallback(async () => {
    if (pagesLoaded) return;
    try {
      const res = await adminPages.list();
      if (res.success && res.data) {
        const raw = Array.isArray(res.data) ? res.data : (res.data as { data?: PageOption[] })?.data ?? [];
        setPages(raw.map((p: { id: number; title: string; slug: string }) => ({
          id: p.id,
          title: p.title,
          slug: p.slug,
        })));
      }
    } catch {
      // non-fatal
    } finally {
      setPagesLoaded(true);
    }
  }, [pagesLoaded]);

  // ─── Menu Settings ───────────────────────────────────────────────────────

  const handleChange = (field: keyof MenuFormData, value: string | boolean) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSave = async () => {
    if (!formData.name.trim()) {
      toast.warning(t('content.menu_name_is_required'));
      return;
    }
    if (!formData.location) {
      toast.warning(t('content.menu_location_is_required'));
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
          toast.success(t('content.menu_updated'));
        } else {
          toast.error(t('content.failed_to_update_menu'));
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
          toast.success(t('content.menu_created'));
          navigate(tenantPath('/admin/menus'));
          return;
        } else {
          toast.error(t('content.failed_to_create_menu'));
        }
      }
    } catch {
      toast.error(t('content.an_unexpected_error_occurred'));
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
    if (item.type === 'page') loadPages();
  };

  const clearSelection = () => {
    setSelectedItemId(null);
    setEditForm({});
    setShowAdvanced(false);
  };

  const handleAddItem = async () => {
    const newItem: Partial<MenuItemData> = {
      label: t('menu_builder.new_item_default'),
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
          toast.success(t('content.item_added'));
          await loadMenu();
          const created = res.data as MenuItemData | undefined;
          if (created?.id) selectItem(created);
        } else {
          toast.error(t('content.failed_to_add_item'));
        }
      } catch {
        toast.error(t('content.an_unexpected_error_occurred'));
      }
    } else {
      const localItem: MenuItemData = { ...newItem as MenuItemData, id: Date.now() };
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
          toast.success(t('content.item_updated'));
          await loadMenu();
        } else {
          toast.error(t('content.failed_to_update_item'));
        }
      } catch {
        toast.error(t('content.an_unexpected_error_occurred'));
      }
    } else {
      setMenuItems((prev) =>
        prev.map((item) =>
          item.id === selectedItemId ? { ...item, ...editForm } as MenuItemData : item
        )
      );
      toast.success(t('content.item_updated'));
    }
  };

  const handleDeleteItem = async (itemId: number) => {
    if (isEdit) {
      try {
        const res = await adminMenus.deleteItem(itemId);
        if (res?.success) {
          toast.success(t('content.item_deleted'));
          if (selectedItemId === itemId) clearSelection();
          await loadMenu();
        } else {
          toast.error(t('content.failed_to_delete_item'));
        }
      } catch {
        toast.error(t('content.an_unexpected_error_occurred'));
      }
    } else {
      setMenuItems((prev) => prev.filter((i) => i.id !== itemId));
      if (selectedItemId === itemId) clearSelection();
    }
  };

  // ─── Drag & Drop (with nest/un-nest via horizontal delta) ─────────────────

  const handleDragStart = (event: DragStartEvent) => {
    const item = menuItems.find((i) => i.id === event.active.id);
    setActiveItem(item ?? null);
  };

  const handleDragEnd = async (event: DragEndEvent) => {
    const { active, over, delta } = event;
    setActiveItem(null);

    if (!over || active.id === over.id) return;

    const oldIndex = menuItems.findIndex((i) => i.id === active.id);
    const newIndex = menuItems.findIndex((i) => i.id === over.id);
    if (oldIndex === -1 || newIndex === -1) return;

    const moved = menuItems[oldIndex];
    if (!moved) return;

    const reordered = arrayMove([...menuItems], oldIndex, newIndex);

    // Determine nesting change from horizontal drag displacement
    let newParentId = moved.parent_id;
    if (delta.x > NEST_THRESHOLD) {
      // Indent: nest under the item immediately before the new position
      const movedIdx = reordered.findIndex((i) => i.id === moved.id);
      const prevItem = movedIdx > 0 ? reordered[movedIdx - 1] : null;
      if (prevItem && prevItem.id !== moved.id && prevItem.type === 'dropdown') {
        newParentId = prevItem.id;
      }
    } else if (delta.x < -NEST_THRESHOLD) {
      // Outdent: remove from any parent
      newParentId = null;
    }

    const final = reordered.map((item, idx) => ({
      ...item,
      sort_order: idx,
      ...(item.id === moved.id ? { parent_id: newParentId } : {}),
    }));

    setMenuItems(final);

    // Update edit form parent if this item is selected
    if (selectedItemId === moved.id && newParentId !== moved.parent_id) {
      setEditForm((f) => ({ ...f, parent_id: newParentId }));
    }

    if (isEdit) {
      try {
        await adminMenus.reorderItems(
          Number(id),
          final.map((item) => ({
            id: item.id,
            sort_order: item.sort_order,
            parent_id: item.parent_id,
          })),
        );
      } catch {
        toast.error(t('content.failed_to_save_reorder'));
        await loadMenu();
      }
    }
  };

  // ─── Depth (for visual indentation) ──────────────────────────────────────

  const getItemDepth = (item: MenuItemData): number => {
    if (!item.parent_id) return 0;
    const parent = menuItems.find((i) => i.id === item.parent_id);
    return parent ? 1 + getItemDepth(parent) : 0;
  };

  // ─── Translated option arrays ─────────────────────────────────────────────

  const LOCATION_OPTIONS = getLocationOptions(t as TFunction);
  const TYPE_OPTIONS = getTypeOptions(t as TFunction);

  const parentOptions = menuItems
    .filter((i) => i.type === 'dropdown' && i.id !== selectedItemId)
    .map((i) => ({ key: String(i.id), label: i.label }));

  // ─── Render ──────────────────────────────────────────────────────────────

  if (loading) {
    return (
      <div>
        <PageHeader title={t('content.menu_builder_title')} description={t('content.menu_builder_desc')} />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={isEdit ? t('content.edit_menu_title') : t('content.create_menu_title')}
        description={t('content.menu_builder_desc')}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              size="sm"
              startContent={showPreview ? <EyeOff size={15} /> : <Eye size={15} />}
              onPress={() => setShowPreview((v) => !v)}
            >
              {showPreview ? t('content.hide_preview') : t('content.live_preview')}
            </Button>
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/menus'))}
            >
              {t('common.back')}
            </Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
            >
              {isEdit ? t('federation.save_changes') : t('content.create_menu_title')}
            </Button>
          </div>
        }
      />

      {/* Menu Settings */}
      <Card shadow="sm" className="mb-4">
        <CardBody>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Input
              label={t('content.label_menu_name')}
              placeholder={t('content.placeholder_menu_item_text')}
              isRequired
              variant="bordered"
              value={formData.name}
              onValueChange={(v) => handleChange('name', v)}
            />
            <Select
              label={t('content.label_location')}
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
                label={t('content.label_description')}
                placeholder={t('content.placeholder_optional')}
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
                <span className="text-sm">{t('content.label_active')}</span>
              </Switch>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Live Preview */}
      {showPreview && <LivePreview items={menuItems} />}

      <div className="grid grid-cols-1 lg:grid-cols-5 gap-4">
        {/* Left: Item Tree */}
        <div className="lg:col-span-3">
          <Card shadow="sm">
            <CardHeader className="flex items-center justify-between">
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <Menu size={20} /> {t('content.menu_builder_title')}
                <Chip size="sm" variant="flat">{menuItems.length}</Chip>
              </h3>
              <Button
                size="sm"
                variant="flat"
                color="primary"
                startContent={<Plus size={14} />}
                onPress={handleAddItem}
              >
                {t('federation.add')}
              </Button>
            </CardHeader>
            <CardBody>
              {menuItems.length === 0 ? (
                <div className="flex flex-col items-center py-12 text-default-400">
                  <Menu size={40} className="mb-3" />
                  <p className="text-sm mb-3">{t('no_data')}</p>
                  <Button
                    size="sm"
                    color="primary"
                    startContent={<Plus size={14} />}
                    onPress={handleAddItem}
                  >
                    {t('federation.add')}
                  </Button>
                </div>
              ) : (
                <>
                  <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragStart={handleDragStart}
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
                            onDelete={() => setDeleteTarget(item.id)}
                            depth={getItemDepth(item)}
                          />
                        ))}
                      </div>
                    </SortableContext>
                    <DragOverlay>
                      {activeItem ? <DragItemCard item={activeItem} /> : null}
                    </DragOverlay>
                  </DndContext>
                  <p className="text-[11px] text-default-400 mt-3 text-center">
                    {t('menu_builder.drag_hint')}
                  </p>
                </>
              )}
            </CardBody>
          </Card>
        </div>

        {/* Right: Item Editor */}
        <div className="lg:col-span-2">
          <Card shadow="sm" className="sticky top-20">
            <CardHeader>
              <h3 className="text-lg font-semibold">
                {selectedItemId ? t('content.label_edit_item') : t('content.menu_builder_title')}
              </h3>
            </CardHeader>
            <CardBody className="gap-3">
              {!selectedItemId ? (
                <div className="flex flex-col items-center py-8 text-default-400">
                  <Pencil size={32} className="mb-3" />
                  <p className="text-sm">{t('content.menu_builder_desc')}</p>
                </div>
              ) : (
                <>
                  {/* Label */}
                  <Input
                    label={t('content.label_label')}
                    placeholder={t('content.placeholder_menu_item_text')}
                    isRequired
                    variant="bordered"
                    size="sm"
                    value={editForm.label || ''}
                    onValueChange={(v) => setEditForm((f) => ({ ...f, label: v }))}
                  />

                  {/* Type */}
                  <Select
                    label={t('content.label_type')}
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
                        if (sel === 'page') loadPages();
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

                  {/* URL field for link / external */}
                  {(editForm.type === 'link' || editForm.type === 'external' || !editForm.type) && (
                    <Input
                      label={t('content.label_u_r_l')}
                      placeholder={editForm.type === 'external' ? 'https://example.com' : '/dashboard'}
                      variant="bordered"
                      size="sm"
                      value={editForm.url || ''}
                      onValueChange={(v) => setEditForm((f) => ({ ...f, url: v }))}
                    />
                  )}

                  {/* Page picker */}
                  {editForm.type === 'page' && (
                    <Select
                      label={t('content.select_page')}
                      variant="bordered"
                      size="sm"
                      isLoading={!pagesLoaded}
                      selectedKeys={editForm.page_id ? [String(editForm.page_id)] : []}
                      onSelectionChange={(keys) => {
                        const sel = Array.from(keys)[0] as string;
                        const page = pages.find((p) => p.id === Number(sel));
                        if (page) {
                          setEditForm((f) => ({
                            ...f,
                            page_id: page.id,
                            url: `/page/${page.slug}`,
                          }));
                        }
                      }}
                    >
                      {pages.map((page) => (
                        <SelectItem key={String(page.id)} textValue={page.title}>
                          <div>
                            <p className="text-sm font-medium">{page.title}</p>
                            <p className="text-xs text-default-400">/page/{page.slug}</p>
                          </div>
                        </SelectItem>
                      ))}
                    </Select>
                  )}

                  {/* Route picker */}
                  {editForm.type === 'route' && (
                    <Select
                      label={t('content.select_route')}
                      variant="bordered"
                      size="sm"
                      selectedKeys={editForm.url ? [editForm.url] : []}
                      onSelectionChange={(keys) => {
                        const sel = Array.from(keys)[0] as string;
                        if (sel) {
                          const route = APP_ROUTES.find((r) => r.value === sel);
                          setEditForm((f) => ({
                            ...f,
                            url: sel,
                            route_name: route?.label ?? sel,
                          }));
                        }
                      }}
                    >
                      {APP_ROUTES.map((route) => (
                        <SelectItem key={route.value} textValue={route.label}>
                          <div className="flex items-center justify-between gap-2">
                            <span className="text-sm font-medium">{route.label}</span>
                            <span className="text-xs text-default-400 font-mono">{route.value}</span>
                          </div>
                        </SelectItem>
                      ))}
                    </Select>
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
                      label={t('content.label_open_in')}
                      variant="bordered"
                      size="sm"
                      selectedKeys={[editForm.target || '_self']}
                      onSelectionChange={(keys) => {
                        const sel = Array.from(keys)[0] as '_self' | '_blank';
                        if (sel) setEditForm((f) => ({ ...f, target: sel }));
                      }}
                    >
                      <SelectItem key="_self">{t('content.same_window')}</SelectItem>
                      <SelectItem key="_blank">{t('content.new_tab')}</SelectItem>
                    </Select>
                  )}

                  {/* Parent (nest under dropdown) */}
                  {editForm.type !== 'dropdown' && parentOptions.length > 0 && (
                    <Select
                      label={t('content.label_parent_item')}
                      variant="bordered"
                      size="sm"
                      selectedKeys={editForm.parent_id ? [String(editForm.parent_id)] : ['']}
                      onSelectionChange={(keys) => {
                        const sel = Array.from(keys)[0] as string;
                        setEditForm((f) => ({ ...f, parent_id: sel ? Number(sel) : null }));
                      }}
                    >
                      {[
                        { key: '', label: t('content.none_top_level') },
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
                      {editForm.is_active ? t('moderation.visible') : t('moderation.hidden')}
                    </span>
                  </Switch>

                  <Divider />

                  {/* Advanced (collapsible) */}
                  <Button
                    variant="light"
                    className="flex items-center gap-1.5 text-sm text-theme-muted hover:text-theme-primary transition-colors h-auto p-0 justify-start"
                    onPress={() => setShowAdvanced(!showAdvanced)}
                  >
                    {showAdvanced ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
                    {t('content.advanced_options')}
                  </Button>

                  {showAdvanced && (
                    <div className="space-y-3 pl-2 border-l-2 border-default-100">
                      <Input
                        label={t('content.label_c_s_s_class')}
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

                  <div className="flex gap-2">
                    <Button
                      color="primary"
                      size="sm"
                      className="flex-1"
                      onPress={handleUpdateItem}
                    >
                      {t('federation.save_changes')}
                    </Button>
                    <Button
                      variant="flat"
                      size="sm"
                      onPress={clearSelection}
                    >
                      {t('cancel')}
                    </Button>
                  </div>
                </>
              )}
            </CardBody>
          </Card>
        </div>
      </div>

      <ConfirmModal
        isOpen={deleteTarget !== null}
        onClose={() => setDeleteTarget(null)}
        onConfirm={() => {
          if (deleteTarget !== null) {
            handleDeleteItem(deleteTarget);
            setDeleteTarget(null);
          }
        }}
        title={t('content.delete_item_title')}
        message={t('content.delete_item_message')}
        confirmLabel={t('common.delete')}
        confirmColor="danger"
      />
    </div>
  );
}

export default MenuBuilder;
