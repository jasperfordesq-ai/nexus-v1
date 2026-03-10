// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useMemo } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  Input,
  Button,
} from '@heroui/react';
import { Search, X } from 'lucide-react';
import { ICON_MAP, ICON_NAMES, DynamicIcon } from '@/components/ui';

interface IconPickerProps {
  value: string | null;
  onChange: (icon: string | null) => void;
  label?: string;
}

export function IconPicker({ value, onChange, label = 'Icon' }: IconPickerProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [search, setSearch] = useState('');

  const filteredIcons = useMemo(() => {
    if (!search.trim()) return ICON_NAMES;
    const q = search.toLowerCase();
    return ICON_NAMES.filter((name) => name.toLowerCase().includes(q));
  }, [search]);

  const handleSelect = (iconName: string) => {
    onChange(iconName);
    setIsOpen(false);
    setSearch('');
  };

  const handleClear = () => {
    onChange(null);
  };

  return (
    <div>
      <p className="text-sm font-medium text-theme-primary mb-1.5">{label}</p>
      <div className="flex items-center gap-2">
        <Button
          variant="flat"
          className="flex items-center gap-2 px-3 py-2 bg-theme-elevated hover:bg-theme-hover border border-theme-default rounded-lg h-10 min-w-[140px] justify-start"
          onPress={() => setIsOpen(true)}
        >
          {value ? (
            <>
              <DynamicIcon name={value} className="w-4 h-4 text-theme-primary" />
              <span className="text-sm text-theme-primary">{value}</span>
            </>
          ) : (
            <span className="text-sm text-theme-subtle">Choose icon...</span>
          )}
        </Button>
        {value && (
          <Button
            isIconOnly
            variant="light"
            size="sm"
            onPress={handleClear}
            aria-label="Clear icon"
          >
            <X className="w-4 h-4" />
          </Button>
        )}
      </div>

      <Modal
        isOpen={isOpen}
        onClose={() => { setIsOpen(false); setSearch(''); }}
        size="2xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader className="flex flex-col gap-2">
            <span>Choose Icon</span>
            <Input
              placeholder="Search icons..."
              aria-label="Search icons"
              value={search}
              onValueChange={setSearch}
              startContent={<Search className="w-4 h-4 text-theme-subtle" />}
              size="sm"
              autoFocus
            />
          </ModalHeader>
          <ModalBody className="pb-6">
            {filteredIcons.length === 0 ? (
              <p className="text-center text-theme-subtle py-8">No icons found matching &ldquo;{search}&rdquo;</p>
            ) : (
              <div className="grid grid-cols-6 sm:grid-cols-8 gap-2">
                {filteredIcons.map((name) => {
                  const Icon = ICON_MAP[name];
                  if (!Icon) return null;
                  const isSelected = value === name;
                  return (
                    <Button
                      key={name}
                      onPress={() => handleSelect(name)}
                      variant="flat"
                      className={`flex flex-col items-center gap-1 p-2 rounded-lg transition-all text-center hover:bg-theme-hover h-auto min-w-0 ${
                        isSelected
                          ? 'bg-indigo-500/10 ring-2 ring-indigo-500 text-indigo-600 dark:text-indigo-400'
                          : 'text-theme-muted'
                      }`}
                      title={name}
                    >
                      <Icon className="w-5 h-5" />
                      <span className="text-[10px] leading-tight truncate w-full">{name}</span>
                    </Button>
                  );
                })}
              </div>
            )}
          </ModalBody>
        </ModalContent>
      </Modal>
    </div>
  );
}
