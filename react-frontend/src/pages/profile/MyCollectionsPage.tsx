// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SOC10 — MyCollectionsPage at /me/collections
 * Grid of saved collections owned by the authenticated user.
 */

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Button, Card, CardBody, Input, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, Switch } from '@heroui/react';
import Bookmark from 'lucide-react/icons/bookmark';
import Plus from 'lucide-react/icons/plus';
import FolderOpen from 'lucide-react/icons/folder-open';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { usePageTitle } from '@/hooks';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';

interface Collection {
  id: number;
  name: string;
  description: string | null;
  color: string;
  icon: string;
  items_count: number;
  is_public: boolean;
}

export default function MyCollectionsPage() {
  const { t } = useTranslation('common');
  const toast = useToast();
  usePageTitle(t('collections.my_title', 'My Collections'));

  const [collections, setCollections] = useState<Collection[]>([]);
  const [loading, setLoading] = useState(true);
  const [showCreate, setShowCreate] = useState(false);
  const [newName, setNewName] = useState('');
  const [newDescription, setNewDescription] = useState('');
  const [newPublic, setNewPublic] = useState(false);
  const [creating, setCreating] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<Collection[]>('/v2/me/collections');
      if (res.success && Array.isArray(res.data)) {
        setCollections(res.data);
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const handleCreate = useCallback(async () => {
    if (!newName.trim()) return;
    setCreating(true);
    try {
      const res = await api.post<Collection>('/v2/me/collections', {
        name: newName.trim(),
        description: newDescription.trim() || null,
        is_public: newPublic,
      });
      if (res.success && res.data) {
        setCollections((prev) => [...prev, res.data as Collection]);
        toast.success(t('collections.created', 'Collection created'));
        setNewName('');
        setNewDescription('');
        setNewPublic(false);
        setShowCreate(false);
      }
    } catch {
      toast.error(t('common.error', 'Something went wrong'));
    } finally {
      setCreating(false);
    }
  }, [newName, newDescription, newPublic, toast, t]);

  if (loading) return <LoadingScreen />;

  return (
    <div className="container mx-auto px-4 py-6 max-w-5xl">
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold flex items-center gap-2">
          <Bookmark className="w-6 h-6 text-[var(--color-warning)]" />
          {t('collections.my_title', 'My Collections')}
        </h1>
        <Button color="primary" startContent={<Plus className="w-4 h-4" />} onPress={() => setShowCreate(true)}>
          {t('collections.new', 'New collection')}
        </Button>
      </div>

      {collections.length === 0 ? (
        <EmptyState
          icon={<FolderOpen className="w-12 h-12 text-[var(--text-muted)]" />}
          title={t('collections.empty_title', 'No collections yet')}
          description={t('collections.empty_desc', 'Save items from across the platform into your own collections.')}
        />
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {collections.map((c) => (
            <Link key={c.id} to={`/me/collections/${c.id}`} className="block">
              <Card className="hover:shadow-md transition-shadow">
                <CardBody className="space-y-2">
                  <div className="flex items-center gap-2">
                    <span
                      className="w-4 h-4 rounded-full"
                      style={{ backgroundColor: c.color || '#6366f1' }}
                      aria-hidden="true"
                    />
                    <h3 className="font-semibold flex-1 truncate">{c.name}</h3>
                    <span className="text-sm text-[var(--text-muted)]">{c.items_count}</span>
                  </div>
                  {c.description && (
                    <p className="text-sm text-[var(--text-muted)] line-clamp-2">{c.description}</p>
                  )}
                  {c.is_public && (
                    <span className="text-xs text-[var(--color-primary)]">
                      {t('collections.public_label', 'Public')}
                    </span>
                  )}
                </CardBody>
              </Card>
            </Link>
          ))}
        </div>
      )}

      <Modal isOpen={showCreate} onClose={() => setShowCreate(false)}>
        <ModalContent>
          <ModalHeader>{t('collections.new', 'New collection')}</ModalHeader>
          <ModalBody className="space-y-3">
            <Input
              label={t('collections.name_label', 'Name')}
              value={newName}
              onValueChange={setNewName}
              variant="bordered"
              autoFocus
            />
            <Input
              label={t('collections.description_label', 'Description (optional)')}
              value={newDescription}
              onValueChange={setNewDescription}
              variant="bordered"
            />
            <Switch isSelected={newPublic} onValueChange={setNewPublic}>
              {t('collections.make_public', 'Make public')}
            </Switch>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={() => setShowCreate(false)}>
              {t('common.cancel', 'Cancel')}
            </Button>
            <Button color="primary" onPress={handleCreate} isLoading={creating}>
              {t('collections.create', 'Create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
