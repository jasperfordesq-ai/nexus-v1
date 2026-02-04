/**
 * Settings Page - User account settings
 */

import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { Button, Input, Textarea, Switch, Avatar, Tabs, Tab } from '@heroui/react';
import {
  User,
  Bell,
  Shield,
  Save,
  Camera,
  Mail,
  Lock,
  Smartphone,
  Key,
  LogOut,
  Trash2,
  Settings,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface ProfileFormData {
  name: string;
  tagline: string;
  bio: string;
  location: string;
  avatar: string | null;
}

interface NotificationSettings {
  email_messages: boolean;
  email_listings: boolean;
  email_digest: boolean;
  push_enabled: boolean;
}

export function SettingsPage() {
  const { user, logout } = useAuth();
  const [activeTab, setActiveTab] = useState('profile');
  const [isSaving, setIsSaving] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');

  // Profile form
  const [profileData, setProfileData] = useState<ProfileFormData>({
    name: '',
    tagline: '',
    bio: '',
    location: '',
    avatar: null,
  });

  // Notification settings
  const [notifications, setNotifications] = useState<NotificationSettings>({
    email_messages: true,
    email_listings: true,
    email_digest: false,
    push_enabled: true,
  });

  useEffect(() => {
    if (user) {
      setProfileData({
        name: user.name || '',
        tagline: user.tagline || '',
        bio: user.bio || '',
        location: user.location || '',
        avatar: user.avatar || null,
      });
    }
    loadNotificationSettings();
  }, [user]);

  async function loadNotificationSettings() {
    try {
      const response = await api.get<NotificationSettings>('/v2/users/me/notifications');
      if (response.success && response.data) {
        setNotifications(response.data);
      }
    } catch (error) {
      logError('Failed to load notification settings', error);
    }
  }

  async function saveProfile() {
    try {
      setIsSaving(true);
      await api.put('/v2/users/me', profileData);
      showSuccess('Profile updated successfully');
    } catch (error) {
      logError('Failed to save profile', error);
    } finally {
      setIsSaving(false);
    }
  }

  async function saveNotifications() {
    try {
      setIsSaving(true);
      await api.put('/v2/users/me/notifications', notifications);
      showSuccess('Notification settings saved');
    } catch (error) {
      logError('Failed to save notifications', error);
    } finally {
      setIsSaving(false);
    }
  }

  function showSuccess(message: string) {
    setSuccessMessage(message);
    setTimeout(() => setSuccessMessage(''), 3000);
  }

  function handleLogout() {
    if (window.confirm('Are you sure you want to log out?')) {
      logout();
    }
  }

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.1 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="max-w-3xl mx-auto space-y-6"
    >
      {/* Header */}
      <motion.div variants={itemVariants}>
        <h1 className="text-2xl font-bold text-white flex items-center gap-3">
          <Settings className="w-7 h-7 text-indigo-400" />
          Settings
        </h1>
        <p className="text-white/60 mt-1">Manage your account preferences</p>
      </motion.div>

      {/* Success Message */}
      {successMessage && (
        <motion.div
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          className="p-4 rounded-lg bg-emerald-500/20 border border-emerald-500/30 text-emerald-400"
        >
          {successMessage}
        </motion.div>
      )}

      {/* Tabs */}
      <motion.div variants={itemVariants}>
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as string)}
          classNames={{
            tabList: 'bg-white/5 p-1 rounded-lg',
            cursor: 'bg-white/10',
            tab: 'text-white/60 data-[selected=true]:text-white',
          }}
        >
          <Tab
            key="profile"
            title={
              <span className="flex items-center gap-2">
                <User className="w-4 h-4" />
                Profile
              </span>
            }
          />
          <Tab
            key="notifications"
            title={
              <span className="flex items-center gap-2">
                <Bell className="w-4 h-4" />
                Notifications
              </span>
            }
          />
          <Tab
            key="security"
            title={
              <span className="flex items-center gap-2">
                <Shield className="w-4 h-4" />
                Security
              </span>
            }
          />
        </Tabs>
      </motion.div>

      {/* Tab Content */}
      <motion.div variants={itemVariants}>
        {activeTab === 'profile' && (
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-white mb-6">Profile Information</h2>

            {/* Avatar */}
            <div className="flex items-center gap-6 mb-8">
              <div className="relative">
                <Avatar
                  src={profileData.avatar || undefined}
                  name={profileData.name}
                  className="w-20 h-20 ring-4 ring-white/20"
                />
                <button className="absolute bottom-0 right-0 p-2 rounded-full bg-indigo-500 text-white hover:bg-indigo-600 transition-colors">
                  <Camera className="w-4 h-4" />
                </button>
              </div>
              <div>
                <p className="text-white font-medium">Profile Photo</p>
                <p className="text-white/50 text-sm">JPG, PNG or GIF. Max 5MB.</p>
              </div>
            </div>

            {/* Form */}
            <div className="space-y-6">
              <Input
                label="Display Name"
                value={profileData.name}
                onChange={(e) => setProfileData((prev) => ({ ...prev, name: e.target.value }))}
                classNames={{
                  input: 'bg-transparent text-white',
                  inputWrapper: 'bg-white/5 border-white/10',
                  label: 'text-white/80',
                }}
              />

              <Input
                label="Tagline"
                placeholder="A short description about yourself"
                value={profileData.tagline}
                onChange={(e) => setProfileData((prev) => ({ ...prev, tagline: e.target.value }))}
                classNames={{
                  input: 'bg-transparent text-white',
                  inputWrapper: 'bg-white/5 border-white/10',
                  label: 'text-white/80',
                }}
              />

              <Textarea
                label="Bio"
                placeholder="Tell others about yourself..."
                value={profileData.bio}
                onChange={(e) => setProfileData((prev) => ({ ...prev, bio: e.target.value }))}
                minRows={4}
                classNames={{
                  input: 'bg-transparent text-white',
                  inputWrapper: 'bg-white/5 border-white/10',
                  label: 'text-white/80',
                }}
              />

              <Input
                label="Location"
                placeholder="City, Country"
                value={profileData.location}
                onChange={(e) => setProfileData((prev) => ({ ...prev, location: e.target.value }))}
                classNames={{
                  input: 'bg-transparent text-white',
                  inputWrapper: 'bg-white/5 border-white/10',
                  label: 'text-white/80',
                }}
              />

              <Button
                onClick={saveProfile}
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<Save className="w-4 h-4" />}
                isLoading={isSaving}
              >
                Save Changes
              </Button>
            </div>
          </GlassCard>
        )}

        {activeTab === 'notifications' && (
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-white mb-6">Notification Preferences</h2>

            <div className="space-y-6">
              <div className="space-y-4">
                <h3 className="text-sm font-medium text-white/80 flex items-center gap-2">
                  <Mail className="w-4 h-4" />
                  Email Notifications
                </h3>

                <SettingToggle
                  label="New Messages"
                  description="Get notified when you receive a new message"
                  checked={notifications.email_messages}
                  onChange={(checked) => setNotifications((prev) => ({ ...prev, email_messages: checked }))}
                />

                <SettingToggle
                  label="Listing Activity"
                  description="Updates about your listings (new responses, etc.)"
                  checked={notifications.email_listings}
                  onChange={(checked) => setNotifications((prev) => ({ ...prev, email_listings: checked }))}
                />

                <SettingToggle
                  label="Weekly Digest"
                  description="A weekly summary of community activity"
                  checked={notifications.email_digest}
                  onChange={(checked) => setNotifications((prev) => ({ ...prev, email_digest: checked }))}
                />
              </div>

              <div className="pt-4 border-t border-white/10 space-y-4">
                <h3 className="text-sm font-medium text-white/80 flex items-center gap-2">
                  <Smartphone className="w-4 h-4" />
                  Push Notifications
                </h3>

                <SettingToggle
                  label="Enable Push Notifications"
                  description="Receive real-time notifications on your device"
                  checked={notifications.push_enabled}
                  onChange={(checked) => setNotifications((prev) => ({ ...prev, push_enabled: checked }))}
                />
              </div>

              <Button
                onClick={saveNotifications}
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<Save className="w-4 h-4" />}
                isLoading={isSaving}
              >
                Save Preferences
              </Button>
            </div>
          </GlassCard>
        )}

        {activeTab === 'security' && (
          <div className="space-y-6">
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-white mb-6">Security Settings</h2>

              <div className="space-y-4">
                <button className="w-full flex items-center justify-between p-4 rounded-lg bg-white/5 hover:bg-white/10 transition-colors text-left">
                  <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-indigo-500/20">
                      <Lock className="w-5 h-5 text-indigo-400" />
                    </div>
                    <div>
                      <p className="font-medium text-white">Change Password</p>
                      <p className="text-sm text-white/50">Update your account password</p>
                    </div>
                  </div>
                </button>

                <button className="w-full flex items-center justify-between p-4 rounded-lg bg-white/5 hover:bg-white/10 transition-colors text-left">
                  <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-emerald-500/20">
                      <Key className="w-5 h-5 text-emerald-400" />
                    </div>
                    <div>
                      <p className="font-medium text-white">Two-Factor Authentication</p>
                      <p className="text-sm text-white/50">Add an extra layer of security</p>
                    </div>
                  </div>
                </button>

                <button className="w-full flex items-center justify-between p-4 rounded-lg bg-white/5 hover:bg-white/10 transition-colors text-left">
                  <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-amber-500/20">
                      <Smartphone className="w-5 h-5 text-amber-400" />
                    </div>
                    <div>
                      <p className="font-medium text-white">Active Sessions</p>
                      <p className="text-sm text-white/50">Manage devices where you're logged in</p>
                    </div>
                  </div>
                </button>
              </div>
            </GlassCard>

            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-white mb-4">Account Actions</h2>

              <div className="space-y-3">
                <Button
                  variant="flat"
                  className="w-full justify-start bg-white/5 text-white"
                  startContent={<LogOut className="w-4 h-4" />}
                  onClick={handleLogout}
                >
                  Log Out
                </Button>

                <Button
                  variant="flat"
                  className="w-full justify-start bg-red-500/10 text-red-400"
                  startContent={<Trash2 className="w-4 h-4" />}
                >
                  Delete Account
                </Button>
              </div>
            </GlassCard>
          </div>
        )}
      </motion.div>
    </motion.div>
  );
}

interface SettingToggleProps {
  label: string;
  description: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
}

function SettingToggle({ label, description, checked, onChange }: SettingToggleProps) {
  return (
    <div className="flex items-center justify-between p-4 rounded-lg bg-white/5">
      <div>
        <p className="font-medium text-white">{label}</p>
        <p className="text-sm text-white/50">{description}</p>
      </div>
      <Switch
        isSelected={checked}
        onValueChange={onChange}
        classNames={{
          wrapper: 'group-data-[selected=true]:bg-indigo-500',
        }}
      />
    </div>
  );
}

export default SettingsPage;
