// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplaceCollectionsPage — User's marketplace collections/wishlists.
 *
 * Features:
 * - List of collections with name, item count, public/private badge
 * - Create collection modal
 * - Click collection to view items grid with remove per item
 * - Saved searches section with alert management
 */

import { useState, useEffect, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import {
  Button,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Switch,
  Spinner,
  Tabs,
  Tab,
  Textarea,
  Chip,
} from '@heroui/react';
import {
  FolderHeart,
  Plus,
  Search,
  ArrowLeft,
  Trash2,
  ShoppingBag,
  Package,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { CollectionCard, SavedSearchCard } from '@/components/marketplace';
import type {
  MarketplaceCollection,
  MarketplaceCollectionItem,
  MarketplaceSavedSearch,
} from '@/types/marketplace';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';

export function MarketplaceCollectionsPage() {
  const { t } = useTranslation('marketplace');
  usePageTitle(t('collections.page_title', 'My Collections'));
  const { isAuthenticated } = useAuth();
  const { tenantPath, hasFeature } = useTenant();
  const toast = useToast();
  const [searchParams] = useSearchParams();
  const featureEnabled = hasFeature('marketplace');

  // State — tabs
  const [activeTab, setActiveTab] = useState<string>(
    searchParams.get('tab') === 'searches' ? 'searches' : 'collections',
  );

  // State — collections
  const [collections, setCollections] = useState<MarketplaceCollection[]>([]);
  const [isLoadingCollections, setIsLoadingCollections] = useState(true);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [newName, setNewName] = useState('');
  const [newDescription, setNewDescription] = useState('');
  const [newIsPublic, setNewIsPublic] = useState(false);
  const [isCreating, setIsCreating] = useState(false);

  // State — collection detail view
  const [selectedCollection, setSelectedCollection] = useState<MarketplaceCollection | null>(null);
  const [collectionItems, setCollectionItems] = useState<MarketplaceCollectionItem[]>([]);
  const [isLoadingItems, setIsLoadingItems] = useState(false);

  // State — saved searches
  const [savedSearches, setSavedSearches] = useState<MarketplaceSavedSearch[]>([]);
  const [isLoadingSearches, setIsLoadingSearches] = useState(true);

  // ─── Load collections ──────────────────────────────────────────────
  const loadCollections = useCallback(async () => {
    setIsLoadingCollections(true);
    try {
      const res = await api.get<MarketplaceCollection[]>('/v2/marketplace/collections');
      if (res.success && res.data) {
        setCollections(res.data);
      }
    } catch (err) {
      logError('Failed to load collections', err);
      toast.error(t('collections.load_error', 'Failed to load collections'));
    } finally {
      setIsLoadingCollections(false);
    }
  }, [toast, t]);

  // ─── Load saved searches ───────────────────────────────────────────
  const loadSavedSearches = useCallback(async () => {
    setIsLoadingSearches(true);
    try {
      const res = await api.get<MarketplaceSavedSearch[]>('/v2/marketplace/saved-searches');
      if (res.success && res.data) {
        setSavedSearches(res.data);
      }
    } catch (err) {
      logError('Failed to load saved searches', err);
    } finally {
      setIsLoadingSearches(false);
    }
  }, []);

  useEffect(() => {
    if (!featureEnabled || !isAuthenticated) return;
    loadCollections();
    loadSavedSearches();
  }, [featureEnabled, isAuthenticated, loadCollections, loadSavedSearches]);

  // ─── Create collection ─────────────────────────────────────────────
  const handleCreateCollection = async () => {
    if (!newName.trim()) return;
    setIsCreating(true);
    try {
      const res = await api.post<MarketplaceCollection>('/v2/marketplace/collections', {
        name: newName.trim(),
        description: newDescription.trim() || null,
        is_public: newIsPublic,
      });
      if (res.success && res.data) {
        setCollections((prev) => [res.data!, ...prev]);
        setShowCreateModal(false);
        setNewName('');
        setNewDescription('');
        setNewIsPublic(false);
        toast.success(t('collections.created', 'Collection created'));
      }
    } catch (err) {
      logError('Failed to create collection', err);
      toast.error(t('collections.create_error', 'Failed to create collection'));
    } finally {
      setIsCreating(false);
    }
  };

  // ─── Delete collection ─────────────────────────────────────────────
  const handleDeleteCollection = async (id: number) => {
    try {
      await api.delete(`/v2/marketplace/collections/${id}`);
      setCollections((prev) => prev.filter((c) => c.id !== id));
      if (selectedCollection?.id === id) {
        setSelectedCollection(null);
        setCollectionItems([]);
      }
      toast.success(t('collections.deleted', 'Collection deleted'));
    } catch (err) {
      logError('Failed to delete collection', err);
      toast.error(t('collections.delete_error', 'Failed to delete collection'));
    }
  };

  // ─── View collection items ─────────────────────────────────────────
  const handleViewCollection = async (collection: MarketplaceCollection) => {
    setSelectedCollection(collection);
    setIsLoadingItems(true);
    try {
      const res = await api.get<MarketplaceCollectionItem[]>(
        `/v2/marketplace/collections/${collection.id}/items?limit=50`,
      );
      if (res.success && res.data) {
        setCollectionItems(res.data);
      }
    } catch (err) {
      logError('Failed to load collection items', err);
      toast.error(t('collections.items_load_error', 'Failed to load items'));
    } finally {
      setIsLoadingItems(false);
    }
  };

  // ─── Remove item from collection ──────────────────────────────────
  const handleRemoveItem = async (listingId: number) => {
    if (!selectedCollection) return;
    try {
      await api.delete(
        `/v2/marketplace/collections/${selectedCollection.id}/items/${listingId}`,
      );
      setCollectionItems((prev) =>
        prev.filter((item) => item.listing.id !== listingId),
      );
      // Update count
      setSelectedCollection((prev) =>
        prev ? { ...prev, item_count: Math.max(0, prev.item_count - 1) } : null,
      );
      setCollections((prev) =>
        prev.map((c) =>
          c.id === selectedCollection.id
            ? { ...c, item_count: Math.max(0, c.item_count - 1) }
            : c,
        ),
      );
    } catch (err) {
      logError('Failed to remove item', err);
      toast.error(t('collections.remove_item_error', 'Failed to remove item'));
    }
  };

  // ─── Saved search actions ──────────────────────────────────────────
  const handleDeleteSearch = async (id: number) => {
    try {
      await api.delete(`/v2/marketplace/saved-searches/${id}`);
      setSavedSearches((prev) => prev.filter((s) => s.id !== id));
      toast.success(t('saved_searches.deleted', 'Saved search deleted'));
    } catch (err) {
      logError('Failed to delete saved search', err);
      toast.error(t('saved_searches.delete_error', 'Failed to delete'));
    }
  };

  const handleRunSearch = (search: MarketplaceSavedSearch) => {
    const params = new URLSearchParams();
    if (search.search_query) params.set('q', search.search_query);
    if (search.filters?.category_id) params.set('category_id', String(search.filters.category_id));
    window.location.href = tenantPath(`/marketplace/search?${params.toString()}`);
  };

  // ─── Feature gate ──────────────────────────────────────────────────
  if (!featureEnabled) {
    return (
      <div className="max-w-5xl mx-auto px-4 py-12">
        <EmptyState
          icon={<ShoppingBag className="w-8 h-8" />}
          title={t('hub_feature_gate.title', 'Marketplace Not Available')}
          description={t('hub_feature_gate.description', 'The marketplace feature is not enabled for this community.')}
        />
      </div>
    );
  }

  if (!isAuthenticated) {
    return (
      <div className="max-w-5xl mx-auto px-4 py-12">
        <EmptyState
          icon={<FolderHeart className="w-8 h-8" />}
          title={t('collections.sign_in_title', 'Sign In Required')}
          description={t('collections.sign_in_description', 'Sign in to manage your collections and saved searches.')}
        />
      </div>
    );
  }

  // ─── Collection detail view ────────────────────────────────────────
  if (selectedCollection) {
    return (
      <>
        <PageMeta title={selectedCollection.name} />
        <div className="max-w-7xl mx-auto px-4 py-6 space-y-6">
          <div className="flex items-center gap-3">
            <Button
              isIconOnly
              variant="light"
              onPress={() => {
                setSelectedCollection(null);
                setCollectionItems([]);
              }}
            >
              <ArrowLeft className="w-5 h-5" />
            </Button>
            <div>
              <h1 className="text-2xl font-bold text-foreground">{selectedCollection.name}</h1>
              {selectedCollection.description && (
                <p className="text-sm text-default-500 mt-1">{selectedCollection.description}</p>
              )}
              <div className="flex items-center gap-2 mt-1">
                <Chip size="sm" variant="flat">
                  {t('collections.item_count', '{{count}} items', { count: selectedCollection.item_count })}
                </Chip>
                <Chip
                  size="sm"
                  variant="flat"
                  color={selectedCollection.is_public ? 'success' : 'default'}
                >
                  {selectedCollection.is_public
                    ? t('collections.public', 'Public')
                    : t('collections.private', 'Private')}
                </Chip>
              </div>
            </div>
          </div>

          {isLoadingItems ? (
            <div className="flex justify-center py-16">
              <Spinner size="lg" color="primary" />
            </div>
          ) : collectionItems.length === 0 ? (
            <EmptyState
              icon={<Package className="w-8 h-8" />}
              title={t('collections.empty_title', 'No Items Yet')}
              description={t('collections.empty_description', 'Add items from marketplace listings to this collection.')}
              action={{
                label: t('collections.browse_marketplace', 'Browse Marketplace'),
                onClick: () => { window.location.href = tenantPath('/marketplace'); },
              }}
            />
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              {collectionItems.map((item) => (
                <GlassCard key={item.collection_item_id} className="overflow-hidden">
                  <Link to={tenantPath(`/marketplace/${item.listing.id}`)}>
                    <div className="aspect-square bg-default-100 overflow-hidden">
                      {item.listing.image ? (
                        <img
                          src={item.listing.image.thumbnail_url || item.listing.image.url}
                          alt={item.listing.title}
                          className="w-full h-full object-cover"
                          loading="lazy"
                        />
                      ) : (
                        <div className="w-full h-full flex items-center justify-center">
                          <Package className="w-10 h-10 text-default-300" />
                        </div>
                      )}
                    </div>
                  </Link>
                  <div className="p-3">
                    <Link
                      to={tenantPath(`/marketplace/${item.listing.id}`)}
                      className="font-medium text-foreground text-sm hover:text-primary transition-colors line-clamp-2"
                    >
                      {item.listing.title}
                    </Link>
                    <p className="text-sm font-semibold text-primary mt-1">
                      {item.listing.price_type === 'free'
                        ? t('common.free', 'Free')
                        : item.listing.price != null
                          ? `${item.listing.price_currency} ${Number(item.listing.price).toFixed(2)}`
                          : ''}
                    </p>
                    {item.note && (
                      <p className="text-xs text-default-400 mt-1 italic line-clamp-2">{item.note}</p>
                    )}
                    <Button
                      size="sm"
                      variant="flat"
                      color="danger"
                      className="mt-2 w-full"
                      startContent={<Trash2 className="w-3.5 h-3.5" />}
                      onPress={() => handleRemoveItem(item.listing.id)}
                    >
                      {t('collections.remove_item', 'Remove')}
                    </Button>
                  </div>
                </GlassCard>
              ))}
            </div>
          )}
        </div>
      </>
    );
  }

  // ─── Main view ─────────────────────────────────────────────────────
  return (
    <>
      <PageMeta
        title={t('collections.page_title', 'My Collections')}
        description={t('collections.meta_description', 'Manage your marketplace collections and saved searches.')}
      />

      <div className="max-w-7xl mx-auto px-4 py-6 space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between flex-wrap gap-4">
          <div>
            <h1 className="text-2xl font-bold text-foreground flex items-center gap-2">
              <FolderHeart className="w-7 h-7 text-primary" />
              {t('collections.page_title', 'My Collections')}
            </h1>
            <p className="text-default-500 text-sm mt-1">
              {t('collections.subtitle', 'Organize and track your favorite marketplace items')}
            </p>
          </div>
          <Button
            color="primary"
            startContent={<Plus className="w-4 h-4" />}
            onPress={() => setShowCreateModal(true)}
          >
            {t('collections.create', 'New Collection')}
          </Button>
        </div>

        {/* Tabs */}
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as string)}
          variant="underlined"
          color="primary"
        >
          <Tab
            key="collections"
            title={
              <div className="flex items-center gap-2">
                <FolderHeart className="w-4 h-4" />
                {t('collections.tab_collections', 'Collections')}
                {collections.length > 0 && (
                  <Chip size="sm" variant="flat">{collections.length}</Chip>
                )}
              </div>
            }
          >
            {isLoadingCollections ? (
              <div className="flex justify-center py-16">
                <Spinner size="lg" color="primary" />
              </div>
            ) : collections.length === 0 ? (
              <EmptyState
                icon={<FolderHeart className="w-8 h-8" />}
                title={t('collections.empty_collections_title', 'No Collections Yet')}
                description={t('collections.empty_collections_description', 'Create a collection to organize your favorite marketplace items.')}
                action={{
                  label: t('collections.create', 'New Collection'),
                  onClick: () => setShowCreateModal(true),
                }}
              />
            ) : (
              <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mt-4">
                {collections.map((collection) => (
                  <CollectionCard
                    key={collection.id}
                    collection={collection}
                    onClick={handleViewCollection}
                  />
                ))}
              </div>
            )}
          </Tab>

          <Tab
            key="searches"
            title={
              <div className="flex items-center gap-2">
                <Search className="w-4 h-4" />
                {t('saved_searches.tab_title', 'Saved Searches')}
                {savedSearches.length > 0 && (
                  <Chip size="sm" variant="flat">{savedSearches.length}</Chip>
                )}
              </div>
            }
          >
            {isLoadingSearches ? (
              <div className="flex justify-center py-16">
                <Spinner size="lg" color="primary" />
              </div>
            ) : savedSearches.length === 0 ? (
              <EmptyState
                icon={<Search className="w-8 h-8" />}
                title={t('saved_searches.empty_title', 'No Saved Searches')}
                description={t('saved_searches.empty_description', 'Save a search from the marketplace to get alerts when new matching items are listed.')}
                action={{
                  label: t('saved_searches.browse', 'Search Marketplace'),
                  onClick: () => { window.location.href = tenantPath('/marketplace/search'); },
                }}
              />
            ) : (
              <div className="space-y-3 mt-4">
                {savedSearches.map((search) => (
                  <SavedSearchCard
                    key={search.id}
                    search={search}
                    onDelete={handleDeleteSearch}
                    onRun={handleRunSearch}
                  />
                ))}
              </div>
            )}
          </Tab>
        </Tabs>
      </div>

      {/* Create Collection Modal */}
      <Modal isOpen={showCreateModal} onClose={() => setShowCreateModal(false)}>
        <ModalContent>
          <ModalHeader>{t('collections.create_title', 'Create Collection')}</ModalHeader>
          <ModalBody>
            <Input
              label={t('collections.name_label', 'Name')}
              placeholder={t('collections.name_placeholder', 'e.g. Gift Ideas, Home Decor...')}
              value={newName}
              onValueChange={setNewName}
              maxLength={100}
              isRequired
              variant="bordered"
            />
            <Textarea
              label={t('collections.description_label', 'Description (optional)')}
              placeholder={t('collections.description_placeholder', 'What is this collection for?')}
              value={newDescription}
              onValueChange={setNewDescription}
              maxLength={500}
              variant="bordered"
            />
            <div className="flex items-center justify-between">
              <span className="text-sm text-foreground">
                {t('collections.make_public', 'Make this collection public')}
              </span>
              <Switch
                isSelected={newIsPublic}
                onValueChange={setNewIsPublic}
                size="sm"
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setShowCreateModal(false)}>
              {t('common.cancel', 'Cancel')}
            </Button>
            <Button
              color="primary"
              isDisabled={!newName.trim()}
              isLoading={isCreating}
              onPress={handleCreateCollection}
            >
              {t('collections.create', 'New Collection')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}

export default MarketplaceCollectionsPage;
