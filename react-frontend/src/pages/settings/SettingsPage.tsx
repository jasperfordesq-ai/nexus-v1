/**
 * Settings Page - User account settings
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Input,
  Textarea,
  Switch,
  Avatar,
  Tabs,
  Tab,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
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
  AlertTriangle,
  Eye,
  EyeOff,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';

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
  const navigate = useNavigate();
  const { user, logout, refreshUser } = useAuth();
  const toast = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [activeTab, setActiveTab] = useState('profile');
  const [isSaving, setIsSaving] = useState(false);
  const [isUploading, setIsUploading] = useState(false);

  // Modal states
  const passwordModal = useDisclosure();
  const deleteModal = useDisclosure();

  // Password form
  const [passwordData, setPasswordData] = useState({
    current_password: '',
    new_password: '',
    confirm_password: '',
  });
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [isChangingPassword, setIsChangingPassword] = useState(false);

  // Delete confirmation
  const [deleteConfirmation, setDeleteConfirmation] = useState('');
  const [isDeleting, setIsDeleting] = useState(false);

  // Logout modal
  const logoutModal = useDisclosure();

  // Error state for notification settings
  const [notificationError, setNotificationError] = useState<string | null>(null);

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

  const loadNotificationSettings = useCallback(async () => {
    try {
      setNotificationError(null);
      const response = await api.get<NotificationSettings>('/v2/users/me/notifications');
      if (response.success && response.data) {
        setNotifications(response.data);
      } else {
        setNotificationError('Failed to load notification settings');
      }
    } catch (error) {
      logError('Failed to load notification settings', error);
      setNotificationError('Failed to load notification settings');
    }
  }, []);

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
  }, [user, loadNotificationSettings]);

  const saveProfile = useCallback(async () => {
    try {
      setIsSaving(true);
      const response = await api.put('/v2/users/me', profileData);
      if (response.success) {
        toast.success('Profile updated successfully');
        if (refreshUser) await refreshUser();
      } else {
        toast.error(response.error || 'Failed to save profile');
      }
    } catch (error) {
      logError('Failed to save profile', error);
      toast.error('Failed to save profile');
    } finally {
      setIsSaving(false);
    }
  }, [profileData, refreshUser, toast]);

  const saveNotifications = useCallback(async () => {
    try {
      setIsSaving(true);
      const response = await api.put('/v2/users/me/notifications', notifications);
      if (response.success) {
        toast.success('Notification settings saved');
      } else {
        toast.error(response.error || 'Failed to save notifications');
      }
    } catch (error) {
      logError('Failed to save notifications', error);
      toast.error('Failed to save notifications');
    } finally {
      setIsSaving(false);
    }
  }, [notifications, toast]);

  function handleLogout() {
    logout();
    logoutModal.onClose();
  }

  // Avatar upload handler
  async function handleAvatarUpload(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    if (!file) return;

    // Validate file type
    if (!file.type.startsWith('image/')) {
      toast.error('Invalid file type', 'Please upload an image file (JPG, PNG, or GIF)');
      return;
    }

    // Validate file size (5MB max)
    if (file.size > 5 * 1024 * 1024) {
      toast.error('File too large', 'Please upload an image smaller than 5MB');
      return;
    }

    try {
      setIsUploading(true);
      const formData = new FormData();
      formData.append('avatar', file);

      const response = await api.upload<{ avatar_url: string }>('/v2/users/me/avatar', formData);

      if (response.success && response.data) {
        setProfileData((prev) => ({ ...prev, avatar: response.data!.avatar_url }));
        if (refreshUser) await refreshUser();
        toast.success('Avatar updated', 'Your profile photo has been updated');
      } else {
        toast.error('Upload failed', 'Failed to upload avatar. Please try again.');
      }
    } catch (error) {
      logError('Failed to upload avatar', error);
      toast.error('Upload failed', 'Failed to upload avatar. Please try again.');
    } finally {
      setIsUploading(false);
      // Reset file input
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  }

  // Password change handler
  async function handleChangePassword() {
    // Validate
    if (!passwordData.current_password || !passwordData.new_password || !passwordData.confirm_password) {
      toast.error('Missing fields', 'Please fill in all password fields');
      return;
    }

    if (passwordData.new_password.length < 8) {
      toast.error('Password too short', 'New password must be at least 8 characters');
      return;
    }

    if (passwordData.new_password !== passwordData.confirm_password) {
      toast.error('Passwords don\'t match', 'New password and confirmation must match');
      return;
    }

    try {
      setIsChangingPassword(true);
      const response = await api.post('/v2/users/me/password', {
        current_password: passwordData.current_password,
        new_password: passwordData.new_password,
      });

      if (response.success) {
        toast.success('Password changed', 'Your password has been updated successfully');
        passwordModal.onClose();
        setPasswordData({ current_password: '', new_password: '', confirm_password: '' });
      } else {
        toast.error('Password change failed', response.error || 'Failed to change password');
      }
    } catch (error) {
      logError('Failed to change password', error);
      toast.error('Password change failed', 'Current password may be incorrect');
    } finally {
      setIsChangingPassword(false);
    }
  }

  // Delete account handler
  async function handleDeleteAccount() {
    if (deleteConfirmation !== 'DELETE') {
      toast.error('Confirmation required', 'Please type DELETE to confirm');
      return;
    }

    try {
      setIsDeleting(true);
      const response = await api.delete('/v2/users/me');

      if (response.success) {
        toast.success('Account deleted', 'Your account has been permanently deleted');
        await logout();
        navigate('/');
      } else {
        toast.error('Delete failed', 'Failed to delete account. Please try again.');
      }
    } catch (error) {
      logError('Failed to delete account', error);
      toast.error('Delete failed', 'Failed to delete account. Please try again.');
    } finally {
      setIsDeleting(false);
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
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <Settings className="w-7 h-7 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
          Settings
        </h1>
        <p className="text-theme-muted mt-1">Manage your account preferences</p>
      </motion.div>

      {/* Tabs */}
      <motion.div variants={itemVariants}>
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as string)}
          classNames={{
            tabList: 'bg-theme-elevated p-1 rounded-lg',
            cursor: 'bg-theme-hover',
            tab: 'text-theme-muted data-[selected=true]:text-theme-primary',
          }}
        >
          <Tab
            key="profile"
            title={
              <span className="flex items-center gap-2">
                <User className="w-4 h-4" aria-hidden="true" />
                Profile
              </span>
            }
          />
          <Tab
            key="notifications"
            title={
              <span className="flex items-center gap-2">
                <Bell className="w-4 h-4" aria-hidden="true" />
                Notifications
              </span>
            }
          />
          <Tab
            key="security"
            title={
              <span className="flex items-center gap-2">
                <Shield className="w-4 h-4" aria-hidden="true" />
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
            <h2 className="text-lg font-semibold text-theme-primary mb-6">Profile Information</h2>

            {/* Avatar */}
            <div className="flex items-center gap-6 mb-8">
              <div className="relative">
                <Avatar
                  src={resolveAvatarUrl(profileData.avatar)}
                  name={profileData.name}
                  className="w-20 h-20 ring-4 ring-theme-default"
                />
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/*"
                  onChange={handleAvatarUpload}
                  className="hidden"
                  aria-label="Upload profile photo"
                />
                <button
                  onClick={() => fileInputRef.current?.click()}
                  disabled={isUploading}
                  aria-label="Change profile photo"
                  className="absolute bottom-0 right-0 p-2 rounded-full bg-indigo-500 text-white hover:bg-indigo-600 transition-colors disabled:opacity-50"
                >
                  {isUploading ? (
                    <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" aria-hidden="true" />
                  ) : (
                    <Camera className="w-4 h-4" aria-hidden="true" />
                  )}
                </button>
              </div>
              <div>
                <p className="text-theme-primary font-medium">Profile Photo</p>
                <p className="text-theme-subtle text-sm">JPG, PNG or GIF. Max 5MB.</p>
              </div>
            </div>

            {/* Form */}
            <div className="space-y-6">
              <Input
                label="Display Name"
                value={profileData.name}
                onChange={(e) => setProfileData((prev) => ({ ...prev, name: e.target.value }))}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />

              <Input
                label="Tagline"
                placeholder="A short description about yourself"
                value={profileData.tagline}
                onChange={(e) => setProfileData((prev) => ({ ...prev, tagline: e.target.value }))}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />

              <Textarea
                label="Bio"
                placeholder="Tell others about yourself..."
                value={profileData.bio}
                onChange={(e) => setProfileData((prev) => ({ ...prev, bio: e.target.value }))}
                minRows={4}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />

              <Input
                label="Location"
                placeholder="City, Country"
                value={profileData.location}
                onChange={(e) => setProfileData((prev) => ({ ...prev, location: e.target.value }))}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />

              <Button
                onPress={saveProfile}
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<Save className="w-4 h-4" aria-hidden="true" />}
                isLoading={isSaving}
              >
                Save Changes
              </Button>
            </div>
          </GlassCard>
        )}

        {activeTab === 'notifications' && (
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-theme-primary mb-6">Notification Preferences</h2>

            {notificationError ? (
              <div className="text-center py-8">
                <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
                <p className="text-theme-muted mb-4">{notificationError}</p>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  onPress={loadNotificationSettings}
                >
                  Try Again
                </Button>
              </div>
            ) : (
            <div className="space-y-6">
              <div className="space-y-4">
                <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                  <Mail className="w-4 h-4" aria-hidden="true" />
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

              <div className="pt-4 border-t border-theme-default space-y-4">
                <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                  <Smartphone className="w-4 h-4" aria-hidden="true" />
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
                onPress={saveNotifications}
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<Save className="w-4 h-4" aria-hidden="true" />}
                isLoading={isSaving}
              >
                Save Preferences
              </Button>
            </div>
            )}
          </GlassCard>
        )}

        {activeTab === 'security' && (
          <div className="space-y-6">
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-6">Security Settings</h2>

              <div className="space-y-4">
                <button
                  onClick={passwordModal.onOpen}
                  className="w-full flex items-center justify-between p-4 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors text-left"
                >
                  <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-indigo-500/20">
                      <Lock className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                    </div>
                    <div>
                      <p className="font-medium text-theme-primary">Change Password</p>
                      <p className="text-sm text-theme-subtle">Update your account password</p>
                    </div>
                  </div>
                </button>

                <div className="w-full flex items-center justify-between p-4 rounded-lg bg-theme-elevated opacity-50 cursor-not-allowed text-left" aria-disabled="true">
                  <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-emerald-500/20">
                      <Key className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                    </div>
                    <div>
                      <p className="font-medium text-theme-primary">Two-Factor Authentication</p>
                      <p className="text-sm text-theme-subtle">Coming soon</p>
                    </div>
                  </div>
                </div>

                <div className="w-full flex items-center justify-between p-4 rounded-lg bg-theme-elevated opacity-50 cursor-not-allowed text-left" aria-disabled="true">
                  <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-amber-500/20">
                      <Smartphone className="w-5 h-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                    </div>
                    <div>
                      <p className="font-medium text-theme-primary">Active Sessions</p>
                      <p className="text-sm text-theme-subtle">Coming soon</p>
                    </div>
                  </div>
                </div>
              </div>
            </GlassCard>

            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-4">Account Actions</h2>

              <div className="space-y-3">
                <Button
                  variant="flat"
                  className="w-full justify-start bg-theme-elevated text-theme-primary"
                  startContent={<LogOut className="w-4 h-4" aria-hidden="true" />}
                  onPress={logoutModal.onOpen}
                >
                  Log Out
                </Button>

                <Button
                  variant="flat"
                  className="w-full justify-start bg-red-500/10 text-red-400"
                  startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                  onPress={deleteModal.onOpen}
                >
                  Delete Account
                </Button>
              </div>
            </GlassCard>
          </div>
        )}
      </motion.div>

      {/* Change Password Modal */}
      <Modal
        isOpen={passwordModal.isOpen}
        onClose={passwordModal.onClose}
        classNames={{
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">Change Password</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                type={showCurrentPassword ? 'text' : 'password'}
                label="Current Password"
                value={passwordData.current_password}
                onChange={(e) => setPasswordData((prev) => ({ ...prev, current_password: e.target.value }))}
                endContent={
                  <button
                    type="button"
                    onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                    aria-label={showCurrentPassword ? 'Hide current password' : 'Show current password'}
                  >
                    {showCurrentPassword ? <EyeOff className="w-4 h-4 text-theme-subtle" aria-hidden="true" /> : <Eye className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  </button>
                }
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
              <Input
                type={showNewPassword ? 'text' : 'password'}
                label="New Password"
                value={passwordData.new_password}
                onChange={(e) => setPasswordData((prev) => ({ ...prev, new_password: e.target.value }))}
                endContent={
                  <button
                    type="button"
                    onClick={() => setShowNewPassword(!showNewPassword)}
                    aria-label={showNewPassword ? 'Hide new password' : 'Show new password'}
                  >
                    {showNewPassword ? <EyeOff className="w-4 h-4 text-theme-subtle" aria-hidden="true" /> : <Eye className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  </button>
                }
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
              <Input
                type="password"
                label="Confirm New Password"
                value={passwordData.confirm_password}
                onChange={(e) => setPasswordData((prev) => ({ ...prev, confirm_password: e.target.value }))}
                classNames={{
                  input: 'bg-transparent text-theme-primary',
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={passwordModal.onClose}>
              Cancel
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={handleChangePassword}
              isLoading={isChangingPassword}
            >
              Change Password
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Logout Confirmation Modal */}
      <Modal
        isOpen={logoutModal.isOpen}
        onClose={logoutModal.onClose}
        classNames={{
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">Log Out</ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              Are you sure you want to log out of your account?
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={logoutModal.onClose}>
              Cancel
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={handleLogout}
            >
              Log Out
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Account Modal */}
      <Modal
        isOpen={deleteModal.isOpen}
        onClose={deleteModal.onClose}
        classNames={{
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-red-600 dark:text-red-400 flex items-center gap-2">
            <AlertTriangle className="w-5 h-5" aria-hidden="true" />
            Delete Account
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <div className="p-4 rounded-lg bg-red-500/10 border border-red-500/20">
                <p className="text-red-600 dark:text-red-400 font-medium">Warning: This action cannot be undone</p>
                <p className="text-theme-muted text-sm mt-1">
                  All your data, including listings, messages, and transaction history will be permanently deleted.
                </p>
              </div>
              <div>
                <p className="text-theme-muted mb-2">
                  Type <span className="font-mono text-red-600 dark:text-red-400">DELETE</span> to confirm:
                </p>
                <Input
                  value={deleteConfirmation}
                  onChange={(e) => setDeleteConfirmation(e.target.value)}
                  placeholder="DELETE"
                  classNames={{
                    input: 'bg-transparent text-theme-primary font-mono',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                  }}
                />
              </div>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={deleteModal.onClose}>
              Cancel
            </Button>
            <Button
              className="bg-red-500 text-white"
              onPress={handleDeleteAccount}
              isLoading={isDeleting}
              isDisabled={deleteConfirmation !== 'DELETE'}
            >
              Delete My Account
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
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
    <div className="flex items-center justify-between p-4 rounded-lg bg-theme-elevated">
      <div>
        <p className="font-medium text-theme-primary">{label}</p>
        <p className="text-sm text-theme-subtle">{description}</p>
      </div>
      <Switch
        aria-label={label}
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
