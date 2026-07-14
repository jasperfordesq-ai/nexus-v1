// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Group Organization
 *
 * Manages the three group-discovery building blocks that previously had
 * no admin UI:
 *  - Tags: lightweight labels members browse groups by
 *  - Collections: curated bundles of groups shown on the discovery page
 *  - Auto-assign rules: automatically add members to a group based on
 *    their location, interest, role, or attribute
 */

import { getFormattingLocale } from '@/lib/helpers';
import { useState, useEffect, useCallback, type CSSProperties } from 'react';
import { useTranslation } from 'react-i18next';

import Plus from 'lucide-react/icons/plus';
import Trash2 from 'lucide-react/icons/trash-2';
import Edit2 from 'lucide-react/icons/pen';
import Layers from 'lucide-react/icons/layers';
import UsersIcon from 'lucide-react/icons/users';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { adminGroups } from '@/admin/api/adminApi';
import type {
  AdminGroup,
  GroupTag,
  GroupCollection,
  GroupAutoAssignRule,
  GroupAutoAssignRuleType,
} from '@/admin/api/types';
import {
  useDisclosure,
  Button,
  Card,
  Checkbox,
  Chip,
  Input,
  Textarea,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Select,
  SelectItem,
  Switch,
  Tabs,
  Tab,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
} from '@/components/ui';
import { ConfirmModal } from '../../components/ConfirmModal';

const RULE_TYPES: GroupAutoAssignRuleType[] = ['location', 'interest', 'role', 'attribute'];

const EMPTY_COLLECTION_FORM = { name: '', description: '', image_url: '', sort_order: '0', is_active: true };

const formatDate = (value?: string | null) => (value ? new Date(value).toLocaleDateString(getFormattingLocale()) : '—');

export default function GroupOrganization() {
  const { t } = useTranslation('admin_groups');
  usePageTitle(t('group_organization.page_title'));
  const { success, error } = useToast();

  const [activeTab, setActiveTab] = useState('tags');

  // ── Tags state ──────────────────────────────────────────────────────────
  const [tags, setTags] = useState<GroupTag[]>([]);
  const [tagsLoading, setTagsLoading] = useState(true);
  const [tagForm, setTagForm] = useState({ name: '', color: '#6366f1' });
  const [tagSaving, setTagSaving] = useState(false);
  const [deleteTagTarget, setDeleteTagTarget] = useState<GroupTag | null>(null);
  const [deleteTagLoading, setDeleteTagLoading] = useState(false);
  const { isOpen: isTagOpen, onOpen: onTagOpen, onClose: onTagClose } = useDisclosure();

  // ── Collections state ───────────────────────────────────────────────────
  const [collections, setCollections] = useState<GroupCollection[]>([]);
  const [collectionsLoading, setCollectionsLoading] = useState(true);
  const [collectionForm, setCollectionForm] = useState(EMPTY_COLLECTION_FORM);
  const [editingCollection, setEditingCollection] = useState<GroupCollection | null>(null);
  const [collectionSaving, setCollectionSaving] = useState(false);
  const [deleteCollectionTarget, setDeleteCollectionTarget] = useState<GroupCollection | null>(null);
  const [deleteCollectionLoading, setDeleteCollectionLoading] = useState(false);
  const { isOpen: isCollectionOpen, onOpen: onCollectionOpen, onClose: onCollectionClose } = useDisclosure();

  // Manage-groups modal
  const [groupsTarget, setGroupsTarget] = useState<GroupCollection | null>(null);
  const [selectedGroupIds, setSelectedGroupIds] = useState<Set<number>>(new Set());
  const [groupsSaving, setGroupsSaving] = useState(false);

  // ── Auto-assign rules state ─────────────────────────────────────────────
  const [rules, setRules] = useState<GroupAutoAssignRule[]>([]);
  const [rulesLoading, setRulesLoading] = useState(true);
  const [ruleForm, setRuleForm] = useState<{ group_id: string; rule_type: GroupAutoAssignRuleType; rule_value: string }>({
    group_id: '',
    rule_type: 'location',
    rule_value: '',
  });
  const [ruleSaving, setRuleSaving] = useState(false);
  const [ruleToggleLoadingId, setRuleToggleLoadingId] = useState<number | null>(null);
  const [deleteRuleTarget, setDeleteRuleTarget] = useState<GroupAutoAssignRule | null>(null);
  const [deleteRuleLoading, setDeleteRuleLoading] = useState(false);
  const { isOpen: isRuleOpen, onOpen: onRuleOpen, onClose: onRuleClose } = useDisclosure();

  // ── Shared: tenant groups for pickers ───────────────────────────────────
  const [allGroups, setAllGroups] = useState<AdminGroup[]>([]);

  const loadTags = useCallback(async () => {
    setTagsLoading(true);
    try {
      const res = await adminGroups.getTags({ limit: 500 });
      if (res.success) {
        setTags(res.data || []);
      } else {
        error(t('group_organization.load_failed'));
      }
    } catch {
      error(t('group_organization.load_failed'));
    } finally {
      setTagsLoading(false);
    }
  }, [error, t]);

  const loadCollections = useCallback(async () => {
    setCollectionsLoading(true);
    try {
      const res = await adminGroups.getCollections();
      if (res.success) {
        setCollections(res.data || []);
      } else {
        error(t('group_organization.load_failed'));
      }
    } catch {
      error(t('group_organization.load_failed'));
    } finally {
      setCollectionsLoading(false);
    }
  }, [error, t]);

  const loadRules = useCallback(async () => {
    setRulesLoading(true);
    try {
      const res = await adminGroups.getAutoAssignRules();
      if (res.success) {
        setRules(res.data || []);
      } else {
        error(t('group_organization.load_failed'));
      }
    } catch {
      error(t('group_organization.load_failed'));
    } finally {
      setRulesLoading(false);
    }
  }, [error, t]);

  const loadGroups = useCallback(async () => {
    try {
      const res = await adminGroups.list({ per_page: 200 });
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setAllGroups(data as AdminGroup[]);
        } else if (data && typeof data === 'object') {
          setAllGroups((data as { data?: AdminGroup[] }).data || []);
        }
      }
    } catch {
      // Group picker degrades to empty; tag/collection lists still work
    }
  }, []);

  useEffect(() => {
    loadTags();
    loadCollections();
    loadRules();
    loadGroups();
  }, [loadTags, loadCollections, loadRules, loadGroups]);

  // ── Tags handlers ───────────────────────────────────────────────────────
  const handleCreateTag = async () => {
    if (!tagForm.name.trim()) {
      error(t('group_organization.name_required'));
      return;
    }
    setTagSaving(true);
    try {
      const res = await adminGroups.createTag({ name: tagForm.name.trim(), color: tagForm.color });
      if (res.success) {
        success(t('group_organization.tag_created'));
        onTagClose();
        setTagForm({ name: '', color: '#6366f1' });
        loadTags();
      } else {
        error(t('group_organization.tag_create_failed'));
      }
    } catch {
      error(t('group_organization.tag_create_failed'));
    } finally {
      setTagSaving(false);
    }
  };

  const handleDeleteTag = async () => {
    if (!deleteTagTarget) return;
    setDeleteTagLoading(true);
    try {
      const res = await adminGroups.deleteTag(deleteTagTarget.id);
      if (res.success) {
        success(t('group_organization.tag_deleted'));
        setDeleteTagTarget(null);
        loadTags();
      } else {
        error(t('group_organization.tag_delete_failed'));
        setDeleteTagTarget(null);
      }
    } catch {
      error(t('group_organization.tag_delete_failed'));
      setDeleteTagTarget(null);
    } finally {
      setDeleteTagLoading(false);
    }
  };

  // ── Collections handlers ────────────────────────────────────────────────
  const openCreateCollection = () => {
    setEditingCollection(null);
    setCollectionForm(EMPTY_COLLECTION_FORM);
    onCollectionOpen();
  };

  const openEditCollection = (collection: GroupCollection) => {
    setEditingCollection(collection);
    setCollectionForm({
      name: collection.name,
      description: collection.description || '',
      image_url: collection.image_url || '',
      sort_order: String(collection.sort_order ?? 0),
      is_active: collection.is_active === undefined ? true : Boolean(Number(collection.is_active)),
    });
    onCollectionOpen();
  };

  const handleSaveCollection = async () => {
    if (!collectionForm.name.trim()) {
      error(t('group_organization.name_required'));
      return;
    }
    setCollectionSaving(true);
    const payload = {
      name: collectionForm.name.trim(),
      description: collectionForm.description.trim() || null,
      image_url: collectionForm.image_url.trim() || null,
      sort_order: parseInt(collectionForm.sort_order, 10) || 0,
    };
    try {
      const res = editingCollection
        ? await adminGroups.updateCollection(editingCollection.id, { ...payload, is_active: collectionForm.is_active })
        : await adminGroups.createCollection(payload);
      if (res.success) {
        success(editingCollection ? t('group_organization.collection_updated') : t('group_organization.collection_created'));
        onCollectionClose();
        setEditingCollection(null);
        setCollectionForm(EMPTY_COLLECTION_FORM);
        loadCollections();
      } else {
        error(
          (editingCollection
              ? t('group_organization.collection_update_failed')
              : t('group_organization.collection_create_failed'))
        );
      }
    } catch {
      error(
        editingCollection
          ? t('group_organization.collection_update_failed')
          : t('group_organization.collection_create_failed')
      );
    } finally {
      setCollectionSaving(false);
    }
  };

  const handleDeleteCollection = async () => {
    if (!deleteCollectionTarget) return;
    setDeleteCollectionLoading(true);
    try {
      const res = await adminGroups.deleteCollection(deleteCollectionTarget.id);
      if (res.success) {
        success(t('group_organization.collection_deleted'));
        setDeleteCollectionTarget(null);
        loadCollections();
      } else {
        error(t('group_organization.collection_delete_failed'));
        setDeleteCollectionTarget(null);
      }
    } catch {
      error(t('group_organization.collection_delete_failed'));
      setDeleteCollectionTarget(null);
    } finally {
      setDeleteCollectionLoading(false);
    }
  };

  const openManageGroups = (collection: GroupCollection) => {
    setGroupsTarget(collection);
    setSelectedGroupIds(new Set((collection.groups || []).map((g) => g.id)));
  };

  const toggleGroupSelection = (groupId: number, checked: boolean) => {
    setSelectedGroupIds((prev) => {
      const next = new Set(prev);
      if (checked) next.add(groupId);
      else next.delete(groupId);
      return next;
    });
  };

  const handleSaveCollectionGroups = async () => {
    if (!groupsTarget) return;
    setGroupsSaving(true);
    try {
      const res = await adminGroups.setCollectionGroups(groupsTarget.id, Array.from(selectedGroupIds));
      if (res.success) {
        success(t('group_organization.groups_saved'));
        setGroupsTarget(null);
        loadCollections();
      } else {
        error(t('group_organization.groups_save_failed'));
      }
    } catch {
      error(t('group_organization.groups_save_failed'));
    } finally {
      setGroupsSaving(false);
    }
  };

  // ── Auto-assign rules handlers ──────────────────────────────────────────
  const handleCreateRule = async () => {
    if (!ruleForm.group_id || !ruleForm.rule_value.trim()) {
      error(t('group_organization.rule_fields_required'));
      return;
    }
    setRuleSaving(true);
    try {
      const res = await adminGroups.createAutoAssignRule({
        group_id: parseInt(ruleForm.group_id, 10),
        rule_type: ruleForm.rule_type,
        rule_value: ruleForm.rule_value.trim(),
      });
      if (res.success) {
        success(t('group_organization.rule_created'));
        onRuleClose();
        setRuleForm({ group_id: '', rule_type: 'location', rule_value: '' });
        loadRules();
      } else {
        error(t('group_organization.rule_create_failed'));
      }
    } catch {
      error(t('group_organization.rule_create_failed'));
    } finally {
      setRuleSaving(false);
    }
  };

  const handleDeleteRule = async () => {
    if (!deleteRuleTarget) return;
    setDeleteRuleLoading(true);
    try {
      const res = await adminGroups.deleteAutoAssignRule(deleteRuleTarget.id);
      if (res.success) {
        success(t('group_organization.rule_deleted'));
        setDeleteRuleTarget(null);
        loadRules();
      } else {
        error(t('group_organization.rule_delete_failed'));
        setDeleteRuleTarget(null);
      }
    } catch {
      error(t('group_organization.rule_delete_failed'));
      setDeleteRuleTarget(null);
    } finally {
      setDeleteRuleLoading(false);
    }
  };

  const handleToggleRule = async (rule: GroupAutoAssignRule, isActive: boolean) => {
    if (ruleToggleLoadingId !== null) return;
    setRuleToggleLoadingId(rule.id);
    try {
      const res = await adminGroups.updateAutoAssignRule(rule.id, { is_active: isActive });
      if (res.success) {
        setRules((current) => current.map((candidate) => (
          candidate.id === rule.id ? { ...candidate, is_active: isActive } : candidate
        )));
        success(t('group_organization.rule_updated'));
      } else {
        error(t('group_organization.rule_update_failed'));
      }
    } catch {
      error(t('group_organization.rule_update_failed'));
    } finally {
      setRuleToggleLoadingId(null);
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">{t('group_organization.page_title')}</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{t('group_organization.page_desc')}</p>
        </div>
      </div>

      <Tabs
        aria-label={t('group_organization.tabs_aria')}
        selectedKey={activeTab}
        onSelectionChange={(key) => setActiveTab(key as string)}
        variant="underlined"
      >
        <Tab key="tags" title={t('group_organization.tab_tags')} />
        <Tab key="collections" title={t('group_organization.tab_collections')} />
        <Tab key="rules" title={t('group_organization.tab_rules')} />
      </Tabs>

      {/* ── Tags tab ─────────────────────────────────────────────────────── */}
      {activeTab === 'tags' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <Button startContent={<Plus className="w-4 h-4" aria-hidden="true" />} onPress={onTagOpen}>
              {t('group_organization.create_tag')}
            </Button>
          </div>
          <Card className="p-4">
            <Table aria-label={t('group_organization.tags_table_aria')}>
              <TableHeader>
                <TableColumn>{t('group_organization.col_name')}</TableColumn>
                <TableColumn>{t('group_organization.col_slug')}</TableColumn>
                <TableColumn>{t('group_organization.col_color')}</TableColumn>
                <TableColumn>{t('group_organization.col_usage')}</TableColumn>
                <TableColumn>{t('group_organization.col_created')}</TableColumn>
                <TableColumn>{t('group_organization.col_actions')}</TableColumn>
              </TableHeader>
              <TableBody
                emptyContent={tagsLoading ? t('group_organization.loading') : t('group_organization.no_tags')}
                items={tags}
              >
                {(tag) => (
                  <TableRow key={tag.id}>
                    <TableCell>
                      <span className="font-medium">{tag.name}</span>
                    </TableCell>
                    <TableCell>
                      <code className="text-xs text-gray-500">{tag.slug}</code>
                    </TableCell>
                    <TableCell>
                      {tag.color ? (
                        <span className="inline-flex items-center gap-2">
                          <span
                            className="inline-block w-4 h-4 rounded-full border border-black/10"
                            style={{ '--tag-color': tag.color, backgroundColor: 'var(--tag-color)' } as CSSProperties}
                            aria-hidden="true"
                          />
                          <code className="text-xs text-gray-500">{tag.color}</code>
                        </span>
                      ) : (
                        '—'
                      )}
                    </TableCell>
                    <TableCell>{tag.usage_count}</TableCell>
                    <TableCell>{formatDate(tag.created_at)}</TableCell>
                    <TableCell>
                      <Button
                        size="sm"
                        variant="danger"
                        isIconOnly
                        aria-label={t('group_organization.label_delete_tag')}
                        onPress={() => setDeleteTagTarget(tag)}
                      >
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </Card>
        </div>
      )}

      {/* ── Collections tab ──────────────────────────────────────────────── */}
      {activeTab === 'collections' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <Button startContent={<Plus className="w-4 h-4" aria-hidden="true" />} onPress={openCreateCollection}>
              {t('group_organization.create_collection')}
            </Button>
          </div>
          <Card className="p-4">
            <Table aria-label={t('group_organization.collections_table_aria')}>
              <TableHeader>
                <TableColumn>{t('group_organization.col_name')}</TableColumn>
                <TableColumn>{t('group_organization.col_groups')}</TableColumn>
                <TableColumn>{t('group_organization.col_sort')}</TableColumn>
                <TableColumn>{t('group_organization.col_created')}</TableColumn>
                <TableColumn>{t('group_organization.col_actions')}</TableColumn>
              </TableHeader>
              <TableBody
                emptyContent={collectionsLoading ? t('group_organization.loading') : t('group_organization.no_collections')}
                items={collections}
              >
                {(collection) => (
                  <TableRow key={collection.id}>
                    <TableCell>
                      <div>
                        <div className="font-medium">{collection.name}</div>
                        {collection.description && (
                          <div className="text-xs text-gray-500 mt-1">{collection.description}</div>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-wrap items-center gap-1">
                        <Chip size="sm" variant="soft">
                          {t('group_organization.group_count', { total: collection.group_count })}
                        </Chip>
                      </div>
                    </TableCell>
                    <TableCell>{collection.sort_order}</TableCell>
                    <TableCell>{formatDate(collection.created_at)}</TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Button
                          size="sm"
                          variant="tertiary"
                          startContent={<UsersIcon className="w-3 h-3" aria-hidden="true" />}
                          onPress={() => openManageGroups(collection)}
                        >
                          {t('group_organization.manage_groups')}
                        </Button>
                        <Button
                          size="sm"
                          variant="tertiary"
                          isIconOnly
                          aria-label={t('group_organization.label_edit_collection')}
                          onPress={() => openEditCollection(collection)}
                        >
                          <Edit2 className="w-4 h-4" />
                        </Button>
                        <Button
                          size="sm"
                          variant="danger"
                          isIconOnly
                          aria-label={t('group_organization.label_delete_collection')}
                          onPress={() => setDeleteCollectionTarget(collection)}
                        >
                          <Trash2 className="w-4 h-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </Card>
        </div>
      )}

      {/* ── Auto-assign rules tab ────────────────────────────────────────── */}
      {activeTab === 'rules' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between gap-4">
            <p className="text-sm text-gray-500 dark:text-gray-400">{t('group_organization.rules_intro')}</p>
            <Button startContent={<Plus className="w-4 h-4" aria-hidden="true" />} onPress={onRuleOpen}>
              {t('group_organization.create_rule')}
            </Button>
          </div>
          <Card className="p-4">
            <Table aria-label={t('group_organization.rules_table_aria')}>
              <TableHeader>
                <TableColumn>{t('group_organization.col_group')}</TableColumn>
                <TableColumn>{t('group_organization.col_rule_type')}</TableColumn>
                <TableColumn>{t('group_organization.col_rule_value')}</TableColumn>
                <TableColumn>{t('group_organization.col_status')}</TableColumn>
                <TableColumn>{t('group_organization.col_created')}</TableColumn>
                <TableColumn>{t('group_organization.col_actions')}</TableColumn>
              </TableHeader>
              <TableBody
                emptyContent={rulesLoading ? t('group_organization.loading') : t('group_organization.no_rules')}
                items={rules}
              >
                {(rule) => (
                  <TableRow key={rule.id}>
                    <TableCell>
                      <span className="font-medium">{rule.group_name || `#${rule.group_id}`}</span>
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" variant="soft" startContent={<Layers className="w-3 h-3" aria-hidden="true" />}>
                        {t(`group_organization.rule_type_${rule.rule_type}`)}
                      </Chip>
                    </TableCell>
                    <TableCell>{rule.rule_value}</TableCell>
                    <TableCell>
                      <Switch
                        size="sm"
                        isSelected={Boolean(Number(rule.is_active))}
                        isDisabled={ruleToggleLoadingId !== null}
                        aria-label={t('group_organization.toggle_rule', {
                          group: rule.group_name || `#${rule.group_id}`,
                        })}
                        onValueChange={(isActive) => void handleToggleRule(rule, isActive)}
                      >
                        {Number(rule.is_active)
                          ? t('group_organization.status_active')
                          : t('group_organization.status_inactive')}
                      </Switch>
                    </TableCell>
                    <TableCell>{formatDate(rule.created_at)}</TableCell>
                    <TableCell>
                      <Button
                        size="sm"
                        variant="danger"
                        isIconOnly
                        aria-label={t('group_organization.label_delete_rule')}
                        onPress={() => setDeleteRuleTarget(rule)}
                      >
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </Card>
        </div>
      )}

      {/* Create tag modal */}
      <Modal isOpen={isTagOpen} onClose={onTagClose}>
        <ModalContent>
          <ModalHeader>{t('group_organization.create_tag')}</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                label={t('group_organization.tag_name')}
                placeholder={t('group_organization.tag_name_placeholder')}
                value={tagForm.name}
                onValueChange={(value) => setTagForm({ ...tagForm, name: value })}
                isRequired
              />
              <Input
                type="color"
                label={t('group_organization.tag_color')}
                value={tagForm.color}
                onValueChange={(value) => setTagForm({ ...tagForm, color: value })}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="tertiary" onPress={onTagClose} isDisabled={tagSaving}>
              {t('group_organization.cancel')}
            </Button>
            <Button onPress={handleCreateTag} isDisabled={tagSaving}>
              {t('group_organization.create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Create / edit collection modal */}
      <Modal isOpen={isCollectionOpen} onClose={onCollectionClose}>
        <ModalContent>
          <ModalHeader>
            {editingCollection ? t('group_organization.edit_collection') : t('group_organization.create_collection')}
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                label={t('group_organization.collection_name')}
                placeholder={t('group_organization.collection_name_placeholder')}
                value={collectionForm.name}
                onValueChange={(value) => setCollectionForm({ ...collectionForm, name: value })}
                isRequired
              />
              <Textarea
                label={t('group_organization.collection_description')}
                placeholder={t('group_organization.collection_description_placeholder')}
                value={collectionForm.description}
                onValueChange={(value) => setCollectionForm({ ...collectionForm, description: value })}
              />
              <Input
                label={t('group_organization.collection_image_url')}
                placeholder="https://"
                value={collectionForm.image_url}
                onValueChange={(value) => setCollectionForm({ ...collectionForm, image_url: value })}
              />
              <Input
                type="number"
                label={t('group_organization.collection_sort_order')}
                value={collectionForm.sort_order}
                onValueChange={(value) => setCollectionForm({ ...collectionForm, sort_order: value })}
              />
              {editingCollection && (
                <Switch
                  isSelected={collectionForm.is_active}
                  onValueChange={(value) => setCollectionForm({ ...collectionForm, is_active: value })}
                >
                  {t('group_organization.collection_active')}
                </Switch>
              )}
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="tertiary" onPress={onCollectionClose} isDisabled={collectionSaving}>
              {t('group_organization.cancel')}
            </Button>
            <Button onPress={handleSaveCollection} isDisabled={collectionSaving}>
              {editingCollection ? t('group_organization.save') : t('group_organization.create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Manage collection groups modal */}
      <Modal isOpen={groupsTarget !== null} onClose={() => setGroupsTarget(null)}>
        <ModalContent>
          <ModalHeader>
            {t('group_organization.groups_in_collection', { name: groupsTarget?.name ?? '' })}
          </ModalHeader>
          <ModalBody>
            {allGroups.length === 0 ? (
              <p className="text-sm text-gray-500">{t('group_organization.no_groups_available')}</p>
            ) : (
              <div className="space-y-1 max-h-80 overflow-y-auto">
                {allGroups.map((group) => (
                  <div key={group.id} className="flex items-center gap-2 py-1">
                    <Checkbox
                      isSelected={selectedGroupIds.has(group.id)}
                      onValueChange={(checked) => toggleGroupSelection(group.id, checked)}
                      aria-label={group.name}
                    >
                      {group.name}
                    </Checkbox>
                  </div>
                ))}
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="tertiary" onPress={() => setGroupsTarget(null)} isDisabled={groupsSaving}>
              {t('group_organization.cancel')}
            </Button>
            <Button onPress={handleSaveCollectionGroups} isDisabled={groupsSaving || allGroups.length === 0}>
              {t('group_organization.save')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Create auto-assign rule modal */}
      <Modal isOpen={isRuleOpen} onClose={onRuleClose}>
        <ModalContent>
          <ModalHeader>{t('group_organization.create_rule')}</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Select
                label={t('group_organization.rule_group')}
                placeholder={t('group_organization.rule_group_placeholder')}
                selectedKeys={ruleForm.group_id ? [ruleForm.group_id] : []}
                onSelectionChange={(keys) =>
                  setRuleForm({ ...ruleForm, group_id: (Array.from(keys)[0] as string) || '' })
                }
                isRequired
              >
                {allGroups.map((group) => (
                  <SelectItem key={String(group.id)} id={String(group.id)}>
                    {group.name}
                  </SelectItem>
                ))}
              </Select>
              <Select
                label={t('group_organization.rule_type')}
                selectedKeys={[ruleForm.rule_type]}
                onSelectionChange={(keys) =>
                  setRuleForm({
                    ...ruleForm,
                    rule_type: ((Array.from(keys)[0] as GroupAutoAssignRuleType) || 'location'),
                  })
                }
                isRequired
              >
                {RULE_TYPES.map((type) => (
                  <SelectItem key={type} id={type}>
                    {t(`group_organization.rule_type_${type}`)}
                  </SelectItem>
                ))}
              </Select>
              <Input
                label={t('group_organization.rule_value')}
                placeholder={t('group_organization.rule_value_hint')}
                value={ruleForm.rule_value}
                onValueChange={(value) => setRuleForm({ ...ruleForm, rule_value: value })}
                isRequired
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="tertiary" onPress={onRuleClose} isDisabled={ruleSaving}>
              {t('group_organization.cancel')}
            </Button>
            <Button onPress={handleCreateRule} isDisabled={ruleSaving}>
              {t('group_organization.create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Confirm deletions */}
      <ConfirmModal
        isOpen={deleteTagTarget !== null}
        onClose={() => setDeleteTagTarget(null)}
        onConfirm={handleDeleteTag}
        title={t('group_organization.confirm')}
        message={t('group_organization.confirm_delete_tag', { name: deleteTagTarget?.name ?? '' })}
        confirmLabel={t('group_organization.delete')}
        cancelLabel={t('group_organization.cancel')}
        confirmColor="danger"
        isLoading={deleteTagLoading}
      />
      <ConfirmModal
        isOpen={deleteCollectionTarget !== null}
        onClose={() => setDeleteCollectionTarget(null)}
        onConfirm={handleDeleteCollection}
        title={t('group_organization.confirm')}
        message={t('group_organization.confirm_delete_collection', { name: deleteCollectionTarget?.name ?? '' })}
        confirmLabel={t('group_organization.delete')}
        cancelLabel={t('group_organization.cancel')}
        confirmColor="danger"
        isLoading={deleteCollectionLoading}
      />
      <ConfirmModal
        isOpen={deleteRuleTarget !== null}
        onClose={() => setDeleteRuleTarget(null)}
        onConfirm={handleDeleteRule}
        title={t('group_organization.confirm')}
        message={t('group_organization.confirm_delete_rule')}
        confirmLabel={t('group_organization.delete')}
        cancelLabel={t('group_organization.cancel')}
        confirmColor="danger"
        isLoading={deleteRuleLoading}
      />
    </div>
  );
}
