// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Profile (My Listing)
 * View and edit this community's profile in the federation directory.
 * Supports editing name, description, contact email, website, and topic/interest tags.
 */

import { useState, useCallback, useEffect } from 'react';
import { Card, CardBody, CardHeader, Input, Textarea, Button, Spinner, Chip, Skeleton } from '@heroui/react';
import { Building, RefreshCw, Save, Star, Tag } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminFederation } from '../../api/adminApi';
import { PageHeader } from '../../components';

import { useTranslation } from 'react-i18next';

interface FedProfile {
  id: number;
  name: string;
  slug: string;
  status: string;
  federation_profile: {
    description: string;
    contact_email: string;
    website: string;
    categories: string[];
  };
}

interface FedTopic {
  id: number;
  name: string;
  slug: string;
  icon: string;
  category: string;
  tenant_count?: number;
  is_primary?: boolean;
}

export function MyProfile() {
  const { t } = useTranslation('admin');

  const CATEGORY_LABELS: Record<string, string> = {
    care: t('federation.category_care', 'Care & Support'),
    skills: t('federation.category_skills', 'Skills & Education'),
    creative: t('federation.category_creative', 'Creative'),
    home: t('federation.category_home', 'Home & Garden'),
    health: t('federation.category_health', 'Health & Fitness'),
    community: t('federation.category_community', 'Community'),
    services: t('federation.category_services', 'Services'),
  };
  usePageTitle("Federation");
  const toast = useToast();

  const [profile, setProfile] = useState<FedProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);

  // Editable form fields
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [contactEmail, setContactEmail] = useState('');
  const [website, setWebsite] = useState('');

  // Topics
  const [allTopics, setAllTopics] = useState<FedTopic[]>([]);
  const [selectedTopicIds, setSelectedTopicIds] = useState<Set<number>>(new Set());
  const [primaryTopicIds, setPrimaryTopicIds] = useState<Set<number>>(new Set());
  const [topicsLoading, setTopicsLoading] = useState(true);
  const [topicsDirty, setTopicsDirty] = useState(false);
  const [topicsSaving, setTopicsSaving] = useState(false);

  const populateForm = useCallback((p: FedProfile) => {
    setName(p.name || '');
    setDescription(p.federation_profile?.description || '');
    setContactEmail(p.federation_profile?.contact_email || '');
    setWebsite(p.federation_profile?.website || '');
    setDirty(false);
  }, []);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminFederation.getProfile();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        let profileData: FedProfile;
        if (payload && typeof payload === 'object' && 'data' in payload) {
          profileData = (payload as { data: FedProfile }).data;
        } else {
          profileData = payload as FedProfile;
        }
        setProfile(profileData);
        populateForm(profileData);
      }
    } catch {
      toast.error("Failed to load federation profile");
      setProfile(null);
    }
    setLoading(false);
  }, [toast, populateForm, t]);

  const loadTopics = useCallback(async () => {
    setTopicsLoading(true);
    try {
      const [allRes, myRes] = await Promise.all([
        adminFederation.getTopics(),
        adminFederation.getMyTopics(),
      ]);

      if (allRes.success && allRes.data) {
        const payload = allRes.data as unknown;
        const topics = (payload && typeof payload === 'object' && 'data' in payload)
          ? (payload as { data: FedTopic[] }).data
          : payload as FedTopic[];
        setAllTopics(topics);
      }

      if (myRes.success && myRes.data) {
        const payload = myRes.data as unknown;
        const myTopics = (payload && typeof payload === 'object' && 'data' in payload)
          ? (payload as { data: FedTopic[] }).data
          : payload as FedTopic[];
        setSelectedTopicIds(new Set(myTopics.map((t) => t.id)));
        setPrimaryTopicIds(new Set(myTopics.filter((t) => t.is_primary).map((t) => t.id)));
      }
    } catch {
      // Topics are optional — fail silently
    }
    setTopicsLoading(false);
  }, []);

  useEffect(() => { loadData(); loadTopics(); }, [loadData, loadTopics]);

  const markDirty = useCallback(() => {
    if (!dirty) setDirty(true);
  }, [dirty]);

  const toggleTopic = (topicId: number) => {
    setSelectedTopicIds((prev) => {
      const next = new Set(prev);
      if (next.has(topicId)) {
        next.delete(topicId);
        // Also remove from primary if deselected
        setPrimaryTopicIds((p) => {
          const np = new Set(p);
          np.delete(topicId);
          return np;
        });
      } else {
        if (next.size >= 10) return prev; // Max 10
        next.add(topicId);
      }
      setTopicsDirty(true);
      return next;
    });
  };

  const togglePrimary = (topicId: number) => {
    if (!selectedTopicIds.has(topicId)) return;
    setPrimaryTopicIds((prev) => {
      const next = new Set(prev);
      if (next.has(topicId)) {
        next.delete(topicId);
      } else {
        if (next.size >= 3) return prev; // Max 3 primary
        next.add(topicId);
      }
      setTopicsDirty(true);
      return next;
    });
  };

  const handleSaveProfile = useCallback(async () => {
    if (!profile) return;
    setSaving(true);
    try {
      const res = await adminFederation.updateProfile({
        name,
        federation_profile: {
          description,
          contact_email: contactEmail,
          website,
          categories: [], // Legacy field — topics replace this
        },
      });
      if (res.success) {
        toast.success("Federation profile updated successfully");
        setDirty(false);
        await loadData();
      } else {
        toast.error("Failed to update federation profile");
      }
    } catch {
      toast.error("Failed to update federation profile");
    } finally {
      setSaving(false);
    }
  }, [profile, name, description, contactEmail, website, toast, loadData, t]);

  const handleSaveTopics = useCallback(async () => {
    setTopicsSaving(true);
    try {
      const res = await adminFederation.updateMyTopics(
        Array.from(selectedTopicIds),
        Array.from(primaryTopicIds),
      );
      if (res.success) {
        toast.success(t('federation.topics_updated_successfully', 'Topics updated successfully'));
        setTopicsDirty(false);
      } else {
        toast.error(t('federation.failed_to_update_topics', 'Failed to update topics'));
      }
    } catch {
      toast.error(t('federation.failed_to_update_topics', 'Failed to update topics'));
    } finally {
      setTopicsSaving(false);
    }
  }, [selectedTopicIds, primaryTopicIds, toast, t]);

  // Group all topics by category
  const topicsByCategory = allTopics.reduce<Record<string, FedTopic[]>>((acc, topic) => {
    const cat = topic.category || 'other';
    if (!acc[cat]) acc[cat] = [];
    acc[cat].push(topic);
    return acc;
  }, {});

  if (loading) {
    return (
      <div>
        <PageHeader
          title={"My Profile"}
          description={"Configure how your community appears to other communities in the federation"}
        />
        <div className="space-y-6">
          <Card shadow="sm">
            <CardHeader><Skeleton className="h-5 w-36 rounded-lg" /></CardHeader>
            <CardBody className="space-y-4">
              <Skeleton className="h-10 w-full rounded-lg" />
              <Skeleton className="h-10 w-full rounded-lg" />
              <Skeleton className="h-24 w-full rounded-lg" />
            </CardBody>
          </Card>
        </div>
      </div>
    );
  }

  if (!profile) {
    return (
      <div>
        <PageHeader
          title={"My Profile"}
          description={"Configure how your community appears to other communities in the federation"}
          actions={
            <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData}>
              {"Refresh"}
            </Button>
          }
        />
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center py-8 text-default-400">
            <Building size={40} className="mb-2" />
            <p>{"Profile not Available"}</p>
            <p className="text-xs mt-1">{"Enable Federation to Create Profile"}</p>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={"My Profile"}
        description={"Configure how your community appears to other communities in the federation"}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={() => { loadData(); loadTopics(); }}
            size="sm"
          >
            {"Refresh"}
          </Button>
        }
      />

      <div className="space-y-6">
        {/* Profile Details Card */}
        <Card shadow="sm">
          <CardHeader className="flex items-center justify-between">
            <h3 className="text-lg font-semibold">{"Community Profile"}</h3>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSaveProfile}
              isLoading={saving}
              isDisabled={!dirty}
              size="sm"
            >
              {"Save Changes"}
            </Button>
          </CardHeader>
          <CardBody className="gap-4">
            <Input
              label={"Community Name"}
              value={name}
              onValueChange={(val) => { setName(val); markDirty(); }}
              variant="bordered"
              description={"The public name of your community in the federation network"}
            />
            <Input
              label={"Slug"}
              value={profile.slug}
              isReadOnly
              variant="bordered"
              description={"Slug"}
            />
            <Input
              label={"Contact Email"}
              type="email"
              value={contactEmail}
              onValueChange={(val) => { setContactEmail(val); markDirty(); }}
              variant="bordered"
              description={"Public contact email shown to partner communities"}
            />
            <Input
              label={"Website"}
              type="url"
              value={website}
              onValueChange={(val) => { setWebsite(val); markDirty(); }}
              variant="bordered"
              placeholder="https://"
              description={"Website"}
            />
            <Textarea
              label={"Description"}
              value={description}
              onValueChange={(val) => { setDescription(val); markDirty(); }}
              variant="bordered"
              minRows={3}
              maxRows={6}
              description={"A brief description of your community for federation partners"}
            />
          </CardBody>
        </Card>

        {/* Topic / Interest Tags Card */}
        <Card shadow="sm">
          <CardHeader className="flex items-center justify-between">
            <div>
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <Tag size={18} />
                {t('federation.topic_tags_title', 'Topic & Interest Tags')}
              </h3>
              <p className="text-sm text-default-400 mt-1">
                {t('federation.topic_tags_desc', 'Select up to 10 topics that describe your community. Star up to 3 as primary — these appear first in the directory.')}
              </p>
            </div>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSaveTopics}
              isLoading={topicsSaving}
              isDisabled={!topicsDirty}
              size="sm"
            >
              {t('federation.save_topics', 'Save Topics')}
            </Button>
          </CardHeader>
          <CardBody className="gap-5">
            {topicsLoading ? (
              <div className="flex justify-center py-6">
                <Spinner size="sm" />
              </div>
            ) : (
              <>
                {/* Selection count */}
                <div className="text-sm text-default-500">
                  {`Topics Selected`}
                  {primaryTopicIds.size > 0 && (
                    <span className="ml-2 text-warning">
                      ({`Primary Count`})
                    </span>
                  )}
                </div>

                {/* Helper text for primary topic toggle */}
                <p className="text-xs text-default-400">
                  {t('federation.click_star_for_primary_hint', 'Click \u2605 in the summary below to set a topic as primary')}
                </p>

                {/* Topics grouped by category */}
                {Object.entries(topicsByCategory).map(([category, topics]) => (
                  <div key={category}>
                    <h4 className="text-sm font-medium text-default-600 mb-2">
                      {CATEGORY_LABELS[category] || category}
                    </h4>
                    <div className="flex flex-wrap gap-2">
                      {topics.map((topic) => {
                        const isSelected = selectedTopicIds.has(topic.id);
                        const isPrimary = primaryTopicIds.has(topic.id);

                        return (
                          <Chip
                            key={topic.id}
                            variant={isSelected ? 'solid' : 'bordered'}
                            color={isPrimary ? 'warning' : isSelected ? 'primary' : 'default'}
                            className="cursor-pointer select-none"
                            startContent={isPrimary ? <Star size={12} className="fill-current" /> : undefined}
                            onClick={() => toggleTopic(topic.id)}
                            onContextMenu={(e) => {
                              e.preventDefault();
                              if (isSelected) togglePrimary(topic.id);
                            }}
                          >
                            {topic.name}
                            {topic.tenant_count ? (
                              <span className="ml-1 opacity-60 text-xs">({topic.tenant_count})</span>
                            ) : null}
                          </Chip>
                        );
                      })}
                    </div>
                  </div>
                ))}

                {/* Selected topics summary with primary toggle */}
                {selectedTopicIds.size > 0 && (
                  <div className="border-t border-divider pt-4">
                    <h4 className="text-sm font-medium text-default-600 mb-2">
                      {t('federation.your_selected_topics', 'Your Selected Topics')}
                      <span className="font-normal text-default-400 ml-2">
                        ({t('federation.click_star_for_primary', 'click star to toggle primary')})
                      </span>
                    </h4>
                    <div className="flex flex-wrap gap-2">
                      {allTopics
                        .filter((topic) => selectedTopicIds.has(topic.id))
                        .map((topic) => {
                          const isPrimary = primaryTopicIds.has(topic.id);
                          return (
                            <Chip
                              key={topic.id}
                              variant="solid"
                              color={isPrimary ? 'warning' : 'primary'}
                              startContent={
                                <Button
                                  isIconOnly
                                  variant="light"
                                  size="sm"
                                  className="flex items-center min-w-0 w-auto h-auto p-0"
                                  onClick={(e) => { e.stopPropagation(); togglePrimary(topic.id); }}
                                  aria-label={isPrimary ? t('federation.remove_primary', 'Remove primary') : t('federation.set_as_primary', 'Set as primary')}
                                >
                                  <Star size={12} className={isPrimary ? 'fill-current' : 'opacity-40'} />
                                </Button>
                              }
                              onClose={() => toggleTopic(topic.id)}
                            >
                              {topic.name}
                            </Chip>
                          );
                        })}
                    </div>
                  </div>
                )}
              </>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default MyProfile;
