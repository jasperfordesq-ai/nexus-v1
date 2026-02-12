/**
 * Quick Create Menu
 * Modal overlay triggered by the MobileTabBar Create button
 * Feature/module-gated options for creating new content
 */

import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Modal,
  ModalContent,
  ModalBody,
  Button,
} from '@heroui/react';
import {
  ListTodo,
  Calendar,
  Users,
  Target,
  X,
} from 'lucide-react';
import { useTenant } from '@/contexts';
import type { TenantFeatures, TenantModules } from '@/types/api';

interface QuickCreateMenuProps {
  isOpen: boolean;
  onClose: () => void;
}

interface CreateOption {
  label: string;
  description: string;
  href: string;
  icon: React.ComponentType<{ className?: string }>;
  color: string;
  feature?: keyof TenantFeatures;
  module?: keyof TenantModules;
}

const createOptions: CreateOption[] = [
  {
    label: 'New Listing',
    description: 'Offer or request a service',
    href: '/listings/create',
    icon: ListTodo,
    color: 'from-emerald-500 to-teal-600',
    module: 'listings',
  },
  {
    label: 'New Event',
    description: 'Organise a community event',
    href: '/events/create',
    icon: Calendar,
    color: 'from-amber-500 to-orange-600',
    feature: 'events',
  },
  {
    label: 'New Group',
    description: 'Start a community group',
    href: '/groups/create',
    icon: Users,
    color: 'from-purple-500 to-pink-600',
    feature: 'groups',
  },
  {
    label: 'New Goal',
    description: 'Set a personal goal',
    href: '/goals',
    icon: Target,
    color: 'from-blue-500 to-cyan-600',
    feature: 'goals',
  },
];

export function QuickCreateMenu({ isOpen, onClose }: QuickCreateMenuProps) {
  const navigate = useNavigate();
  const { hasFeature, hasModule, tenantPath } = useTenant();

  const visibleOptions = createOptions.filter((option) => {
    if (option.feature && !hasFeature(option.feature)) return false;
    if (option.module && !hasModule(option.module)) return false;
    return true;
  });

  const handleSelect = (href: string) => {
    onClose();
    navigate(tenantPath(href));
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      placement="center"
      size="sm"
      hideCloseButton
      classNames={{
        backdrop: 'bg-black/60 backdrop-blur-sm',
        base: 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-2xl mx-4',
        body: 'p-0',
      }}
    >
      <ModalContent>
        <ModalBody>
          <AnimatePresence>
            {isOpen && (
              <motion.div
                initial={{ opacity: 0, scale: 0.95 }}
                animate={{ opacity: 1, scale: 1 }}
                exit={{ opacity: 0, scale: 0.95 }}
                transition={{ duration: 0.15 }}
              >
                {/* Header */}
                <div className="flex items-center justify-between px-5 pt-5 pb-3">
                  <h2 className="text-lg font-semibold text-theme-primary">
                    Create New
                  </h2>
                  <Button
                    isIconOnly
                    variant="light"
                    size="sm"
                    onPress={onClose}
                    aria-label="Close create menu"
                    className="text-theme-muted hover:text-theme-primary"
                  >
                    <X className="w-5 h-5" aria-hidden="true" />
                  </Button>
                </div>

                {/* Options Grid */}
                <div className="px-5 pb-5 grid grid-cols-2 gap-3">
                  {visibleOptions.map((option, index) => {
                    const Icon = option.icon;
                    return (
                      <motion.div
                        key={option.href}
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: index * 0.05, duration: 0.15 }}
                      >
                        <Button
                          onPress={() => handleSelect(option.href)}
                          className="w-full h-auto flex flex-col items-center gap-2 p-4 rounded-xl bg-theme-elevated hover:bg-theme-hover border border-theme-default transition-all group"
                          variant="light"
                        >
                          <div
                            className={`w-10 h-10 rounded-xl bg-gradient-to-br ${option.color} flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform`}
                          >
                            <Icon className="w-5 h-5 text-white" aria-hidden="true" />
                          </div>
                          <div className="text-center">
                            <p className="text-sm font-medium text-theme-primary">
                              {option.label}
                            </p>
                            <p className="text-[11px] text-theme-subtle leading-tight mt-0.5">
                              {option.description}
                            </p>
                          </div>
                        </Button>
                      </motion.div>
                    );
                  })}
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </ModalBody>
      </ModalContent>
    </Modal>
  );
}

export default QuickCreateMenu;
