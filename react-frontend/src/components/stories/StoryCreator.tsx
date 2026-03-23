// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * StoryCreator — Fullscreen modal for creating stories.
 *
 * Supports three modes:
 * - Photo: upload/take a photo with optional text overlay
 * - Text: text on a gradient background
 * - Poll: question with 2-4 answer options
 */

import { useState, useRef, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Textarea,
  Input,
} from '@heroui/react';
import {
  X,
  Camera,
  Type,
  BarChart3,
  ImagePlus,
  Send,
  Trash2,
  Plus,
  Minus,
} from 'lucide-react';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { compressImage } from '@/lib/compress-image';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

type StoryMode = 'photo' | 'text' | 'poll';

const GRADIENT_PRESETS = [
  { label: 'Purple Blue', class: 'from-purple-600 to-blue-500', css: 'linear-gradient(135deg, #9333ea, #3b82f6)' },
  { label: 'Orange Pink', class: 'from-orange-500 to-pink-500', css: 'linear-gradient(135deg, #f97316, #ec4899)' },
  { label: 'Green Teal', class: 'from-green-500 to-teal-500', css: 'linear-gradient(135deg, #22c55e, #14b8a6)' },
  { label: 'Blue Indigo', class: 'from-blue-600 to-indigo-500', css: 'linear-gradient(135deg, #2563eb, #6366f1)' },
  { label: 'Red Yellow', class: 'from-red-500 to-yellow-500', css: 'linear-gradient(135deg, #ef4444, #eab308)' },
  { label: 'Dark', class: 'from-gray-700 to-gray-900', css: 'linear-gradient(135deg, #374151, #111827)' },
  { label: 'Pink Rose', class: 'from-pink-500 to-rose-400', css: 'linear-gradient(135deg, #ec4899, #fb7185)' },
  { label: 'Cyan Blue', class: 'from-cyan-500 to-blue-500', css: 'linear-gradient(135deg, #06b6d4, #3b82f6)' },
];

const FONT_STYLES = [
  { label: 'Sans', family: 'sans-serif' },
  { label: 'Serif', family: 'serif' },
  { label: 'Mono', family: 'monospace' },
];

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

interface StoryCreatorProps {
  onClose: () => void;
  onCreated: () => void;
}

export function StoryCreator({ onClose, onCreated }: StoryCreatorProps) {
  const toast = useToast();
  const [mode, setMode] = useState<StoryMode>('text');
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Photo mode state
  const [selectedImage, setSelectedImage] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [photoText, setPhotoText] = useState('');
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Text mode state
  const [textContent, setTextContent] = useState('');
  const [selectedGradient, setSelectedGradient] = useState(0);
  const [selectedFont, setSelectedFont] = useState(0);

  // Poll mode state
  const [pollQuestion, setPollQuestion] = useState('');
  const [pollOptions, setPollOptions] = useState(['', '']);
  const [pollGradient, setPollGradient] = useState(3);

  // Handle image selection
  const handleImageSelect = useCallback(async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      toast.error('Please select an image file');
      return;
    }

    if (file.size > 10 * 1024 * 1024) {
      toast.error('Image must be less than 10MB');
      return;
    }

    try {
      const compressed = await compressImage(file, 1080, 0.85);
      setSelectedImage(compressed);

      const reader = new FileReader();
      reader.onload = (ev) => setImagePreview(ev.target?.result as string);
      reader.readAsDataURL(compressed);
    } catch (err) {
      logError('Failed to process image', err);
      toast.error('Failed to process image');
    }
  }, [toast]);

  // Add/remove poll options
  const addPollOption = () => {
    if (pollOptions.length < 4) {
      setPollOptions([...pollOptions, '']);
    }
  };

  const removePollOption = (index: number) => {
    if (pollOptions.length > 2) {
      setPollOptions(pollOptions.filter((_, i) => i !== index));
    }
  };

  const updatePollOption = (index: number, value: string) => {
    const newOptions = [...pollOptions];
    newOptions[index] = value;
    setPollOptions(newOptions);
  };

  // Submit story
  const handleSubmit = async () => {
    if (isSubmitting) return;

    // Validation
    if (mode === 'photo' && !selectedImage) {
      toast.error('Please select an image');
      return;
    }
    if (mode === 'text' && !textContent.trim()) {
      toast.error('Please enter some text');
      return;
    }
    if (mode === 'poll') {
      if (!pollQuestion.trim()) {
        toast.error('Please enter a poll question');
        return;
      }
      const filledOptions = pollOptions.filter((o) => o.trim());
      if (filledOptions.length < 2) {
        toast.error('Please provide at least 2 poll options');
        return;
      }
    }

    setIsSubmitting(true);

    try {
      if (mode === 'photo') {
        // Use FormData for image upload
        const formData = new FormData();
        formData.append('media_type', 'image');
        formData.append('media', selectedImage!);
        if (photoText.trim()) {
          formData.append('text_content', photoText.trim());
        }
        formData.append('duration', '5');

        const response = await api.upload('/v2/stories', formData);
        if (!response.success) {
          throw new Error(response.error || 'Failed to create story');
        }
      } else if (mode === 'text') {
        const gradient = GRADIENT_PRESETS[selectedGradient];
        const font = FONT_STYLES[selectedFont];

        const response = await api.post('/v2/stories', {
          media_type: 'text',
          text_content: textContent.trim(),
          background_gradient: gradient.class,
          text_style: { fontFamily: font.family },
          duration: 5,
        });
        if (!response.success) {
          throw new Error(response.error || 'Failed to create story');
        }
      } else if (mode === 'poll') {
        const gradient = GRADIENT_PRESETS[pollGradient];
        const filledOptions = pollOptions.filter((o) => o.trim());

        const response = await api.post('/v2/stories', {
          media_type: 'poll',
          poll_question: pollQuestion.trim(),
          poll_options: filledOptions,
          background_gradient: gradient.class,
          duration: 10,
        });
        if (!response.success) {
          throw new Error(response.error || 'Failed to create story');
        }
      }

      toast.success('Story shared!');
      onCreated();
    } catch (err) {
      logError('Failed to create story', err);
      toast.error(err instanceof Error ? err.message : 'Failed to create story');
    } finally {
      setIsSubmitting(false);
    }
  };

  // Discard
  const handleDiscard = () => {
    if (mode === 'photo' && selectedImage) {
      setSelectedImage(null);
      setImagePreview(null);
      setPhotoText('');
      return;
    }
    onClose();
  };

  const creatorContent = (
    <motion.div
      initial={{ opacity: 0, scale: 0.95 }}
      animate={{ opacity: 1, scale: 1 }}
      exit={{ opacity: 0, scale: 0.95 }}
      transition={{ duration: 0.2 }}
      className="fixed inset-0 z-[9999] bg-black flex flex-col"
      role="dialog"
      aria-modal="true"
      aria-label="Create story"
    >
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 z-10">
        <button
          onClick={onClose}
          className="p-2 rounded-full hover:bg-white/10 transition-colors"
          aria-label="Close creator"
        >
          <X className="w-5 h-5 text-white" />
        </button>

        <h2 className="text-white font-semibold text-lg">Create Story</h2>

        <div className="w-9" /> {/* Spacer for centering */}
      </div>

      {/* Mode tabs */}
      <div className="flex items-center gap-1 px-4 pb-3">
        {([
          { key: 'photo' as StoryMode, icon: Camera, label: 'Photo' },
          { key: 'text' as StoryMode, icon: Type, label: 'Text' },
          { key: 'poll' as StoryMode, icon: BarChart3, label: 'Poll' },
        ]).map(({ key, icon: Icon, label }) => (
          <button
            key={key}
            onClick={() => setMode(key)}
            className={`flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium transition-all ${
              mode === key
                ? 'bg-white text-black'
                : 'bg-white/10 text-white/70 hover:bg-white/20'
            }`}
            aria-label={`${label} mode`}
            aria-pressed={mode === key}
          >
            <Icon className="w-4 h-4" />
            {label}
          </button>
        ))}
      </div>

      {/* Content area */}
      <div className="flex-1 overflow-y-auto">
        {/* Photo Mode */}
        {mode === 'photo' && (
          <div className="flex flex-col items-center justify-center h-full px-4 gap-4">
            {imagePreview ? (
              <div className="relative w-full max-w-sm aspect-[9/16] rounded-2xl overflow-hidden">
                <img
                  src={imagePreview}
                  alt="Story preview"
                  className="w-full h-full object-cover"
                />
                {/* Text overlay input */}
                <div className="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/60 to-transparent">
                  <Input
                    value={photoText}
                    onValueChange={setPhotoText}
                    placeholder="Add text overlay..."
                    variant="bordered"
                    size="sm"
                    classNames={{
                      input: 'text-white placeholder:text-white/50',
                      inputWrapper: 'border-white/30 bg-white/10 backdrop-blur',
                    }}
                    maxLength={200}
                  />
                </div>
              </div>
            ) : (
              <button
                onClick={() => fileInputRef.current?.click()}
                className="flex flex-col items-center gap-4 p-12 rounded-2xl border-2 border-dashed border-white/20 hover:border-white/40 transition-colors cursor-pointer"
                aria-label="Select image for story"
              >
                <div className="w-16 h-16 rounded-full bg-white/10 flex items-center justify-center">
                  <ImagePlus className="w-8 h-8 text-white/70" />
                </div>
                <div className="text-center">
                  <p className="text-white font-medium">Add Photo</p>
                  <p className="text-white/50 text-sm mt-1">Tap to select from your device</p>
                </div>
              </button>
            )}
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              onChange={handleImageSelect}
              className="hidden"
              aria-label="Choose image file"
            />
          </div>
        )}

        {/* Text Mode */}
        {mode === 'text' && (
          <div className="flex flex-col h-full">
            {/* Preview */}
            <div
              className="flex-1 flex items-center justify-center px-8 py-12 mx-4 rounded-2xl min-h-[300px]"
              style={{ background: GRADIENT_PRESETS[selectedGradient].css }}
            >
              {textContent ? (
                <p
                  className="text-white text-center max-w-sm drop-shadow-lg"
                  style={{
                    fontSize: '1.5rem',
                    fontFamily: FONT_STYLES[selectedFont].family,
                    lineHeight: 1.4,
                  }}
                >
                  {textContent}
                </p>
              ) : (
                <p className="text-white/40 text-lg">Start typing...</p>
              )}
            </div>

            {/* Controls */}
            <div className="px-4 py-4 space-y-4">
              <Textarea
                value={textContent}
                onValueChange={setTextContent}
                placeholder="What's on your mind?"
                variant="bordered"
                maxLength={500}
                minRows={2}
                maxRows={4}
                description={`${textContent.length}/500`}
                classNames={{
                  input: 'text-white',
                  inputWrapper: 'border-white/20 bg-white/5',
                  description: 'text-white/40 text-right',
                }}
              />

              {/* Gradient picker */}
              <div>
                <p className="text-white/50 text-xs mb-2 uppercase tracking-wider">Background</p>
                <div className="flex gap-2 flex-wrap">
                  {GRADIENT_PRESETS.map((g, idx) => (
                    <button
                      key={idx}
                      onClick={() => setSelectedGradient(idx)}
                      className={`w-8 h-8 rounded-full transition-all ${
                        selectedGradient === idx ? 'ring-2 ring-white ring-offset-2 ring-offset-black scale-110' : 'hover:scale-105'
                      }`}
                      style={{ background: g.css }}
                      aria-label={g.label}
                      aria-pressed={selectedGradient === idx}
                    />
                  ))}
                </div>
              </div>

              {/* Font picker */}
              <div>
                <p className="text-white/50 text-xs mb-2 uppercase tracking-wider">Font</p>
                <div className="flex gap-2">
                  {FONT_STYLES.map((f, idx) => (
                    <button
                      key={idx}
                      onClick={() => setSelectedFont(idx)}
                      className={`px-4 py-1.5 rounded-full text-sm transition-all ${
                        selectedFont === idx
                          ? 'bg-white text-black font-medium'
                          : 'bg-white/10 text-white/70 hover:bg-white/20'
                      }`}
                      style={{ fontFamily: f.family }}
                      aria-label={`${f.label} font`}
                      aria-pressed={selectedFont === idx}
                    >
                      {f.label}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Poll Mode */}
        {mode === 'poll' && (
          <div className="flex flex-col h-full">
            {/* Preview */}
            <div
              className="flex flex-col items-center justify-center px-8 py-12 mx-4 rounded-2xl min-h-[250px] gap-4"
              style={{ background: GRADIENT_PRESETS[pollGradient].css }}
            >
              {pollQuestion ? (
                <h3 className="text-white text-xl font-bold text-center">{pollQuestion}</h3>
              ) : (
                <p className="text-white/40 text-lg">Your question...</p>
              )}
              <div className="w-full max-w-sm flex flex-col gap-2">
                {pollOptions.filter((o) => o.trim()).map((option, idx) => (
                  <div
                    key={idx}
                    className="w-full px-4 py-3 rounded-xl bg-white/30 text-white text-sm font-medium"
                  >
                    {option}
                  </div>
                ))}
              </div>
            </div>

            {/* Poll Controls */}
            <div className="px-4 py-4 space-y-4">
              <Input
                value={pollQuestion}
                onValueChange={setPollQuestion}
                placeholder="Ask a question..."
                variant="bordered"
                maxLength={255}
                classNames={{
                  input: 'text-white',
                  inputWrapper: 'border-white/20 bg-white/5',
                }}
                aria-label="Poll question"
              />

              <div className="space-y-2">
                <p className="text-white/50 text-xs uppercase tracking-wider">Options</p>
                {pollOptions.map((option, idx) => (
                  <div key={idx} className="flex gap-2">
                    <Input
                      value={option}
                      onValueChange={(v) => updatePollOption(idx, v)}
                      placeholder={`Option ${idx + 1}`}
                      variant="bordered"
                      maxLength={100}
                      classNames={{
                        input: 'text-white',
                        inputWrapper: 'border-white/20 bg-white/5',
                      }}
                      aria-label={`Poll option ${idx + 1}`}
                    />
                    {pollOptions.length > 2 && (
                      <Button
                        isIconOnly
                        size="sm"
                        variant="flat"
                        className="bg-white/10 text-white/70"
                        onPress={() => removePollOption(idx)}
                        aria-label={`Remove option ${idx + 1}`}
                      >
                        <Minus className="w-4 h-4" />
                      </Button>
                    )}
                  </div>
                ))}
                {pollOptions.length < 4 && (
                  <Button
                    size="sm"
                    variant="flat"
                    className="bg-white/10 text-white/70"
                    startContent={<Plus className="w-4 h-4" />}
                    onPress={addPollOption}
                  >
                    Add Option
                  </Button>
                )}
              </div>

              {/* Background picker for poll */}
              <div>
                <p className="text-white/50 text-xs mb-2 uppercase tracking-wider">Background</p>
                <div className="flex gap-2 flex-wrap">
                  {GRADIENT_PRESETS.map((g, idx) => (
                    <button
                      key={idx}
                      onClick={() => setPollGradient(idx)}
                      className={`w-8 h-8 rounded-full transition-all ${
                        pollGradient === idx ? 'ring-2 ring-white ring-offset-2 ring-offset-black scale-110' : 'hover:scale-105'
                      }`}
                      style={{ background: g.css }}
                      aria-label={g.label}
                      aria-pressed={pollGradient === idx}
                    />
                  ))}
                </div>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Bottom bar */}
      <div className="flex items-center justify-between px-4 py-4 border-t border-white/10">
        <Button
          variant="flat"
          className="bg-white/10 text-white/70"
          onPress={handleDiscard}
          startContent={<Trash2 className="w-4 h-4" />}
        >
          Discard
        </Button>

        <Button
          color="primary"
          className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
          onPress={handleSubmit}
          isLoading={isSubmitting}
          startContent={!isSubmitting ? <Send className="w-4 h-4" /> : undefined}
        >
          Share Story
        </Button>
      </div>
    </motion.div>
  );

  return createPortal(creatorContent, document.body);
}

export default StoryCreator;
