// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Platform, Text, View } from 'react-native';
import { BottomSheetFlatList, BottomSheetFooter, type BottomSheetFooterProps } from '@gorhom/bottom-sheet';
import { Ionicons } from '@expo/vector-icons';
import { BottomSheet as HeroBottomSheet, Button as HeroButton, Spinner, Surface, useBottomSheetAwareHandlers } from 'heroui-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as Haptics from '@/lib/haptics';

import Avatar from '@/components/ui/Avatar';
import TextArea from '@/components/ui/TextArea';
import { useAppToast } from '@/components/ui/AppToast';
import { useDeferredBottomSheetState } from '@/components/ui/useDeferredBottomSheetState';
import { getComments, submitComment, type CommentItem, type CommentTargetType } from '@/lib/api/comments';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { formatRelativeTime } from '@/lib/utils/formatRelativeTime';

interface CommentSheetStrings {
  title: string;
  placeholder: string;
  empty: string;
  loadFailed: string;
  submitFailed: string;
  actionFailedTitle: string;
  send: string;
  authorFallback: string;
}

interface CommentSheetProps {
  visible: boolean;
  targetType: CommentTargetType;
  targetId: number;
  initialCount?: number;
  strings: CommentSheetStrings;
  onClose: () => void;
  onCountChange?: (count: number) => void;
}

interface FlatComment extends CommentItem {
  depth: number;
}

export default function CommentSheet({
  visible,
  targetType,
  targetId,
  initialCount = 0,
  strings,
  onClose,
  onCountChange,
}: CommentSheetProps) {
  const primary = usePrimaryColor();
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { show: showToast } = useAppToast();
  const [comments, setComments] = useState<CommentItem[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [loadedTargetKey, setLoadedTargetKey] = useState<string | null>(null);
  const [draft, setDraft] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const { mounted: sheetMounted, open: sheetOpen } = useDeferredBottomSheetState(visible);
  const targetKey = `${targetType}-${targetId}`;
  const footerBottomInset = Math.max(0, insets.bottom);
  const composerBottomPadding = 12;
  const listBottomPadding = 112 + footerBottomInset;

  const flattenedComments = useMemo(() => flattenComments(comments), [comments]);

  const loadSheetComments = useCallback(async (force = false) => {
    if (!visible || isLoading || (!force && loadedTargetKey === targetKey)) return;
    setIsLoading(true);
    try {
      const response = await getComments(targetType, targetId);
      const payload = ('data' in response && response.data ? response.data : response) as { comments?: CommentItem[]; count?: number };
      const nextComments = payload.comments ?? [];
      const nextCount = payload.count ?? countComments(nextComments);
      setComments(nextComments);
      setLoadedTargetKey(targetKey);
      onCountChange?.(nextCount);
    } catch {
      showToast({
        title: strings.actionFailedTitle,
        description: strings.loadFailed,
        variant: 'danger',
      });
    } finally {
      setIsLoading(false);
    }
  }, [isLoading, loadedTargetKey, onCountChange, showToast, strings.actionFailedTitle, strings.loadFailed, targetId, targetKey, targetType, visible]);

  useEffect(() => {
    if (visible) {
      void loadSheetComments();
    }
  }, [loadSheetComments, visible]);

  useEffect(() => {
    if (!visible) return;
    setDraft('');
  }, [targetKey, visible]);

  async function handleSubmit() {
    const content = draft.trim();
    if (!content || isSubmitting) return;
    setIsSubmitting(true);
    try {
      await submitComment(targetType, targetId, content);
      setDraft('');
      const optimisticCount = Math.max(initialCount, countComments(comments)) + 1;
      onCountChange?.(optimisticCount);
      await loadSheetComments(true);
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      showToast({
        title: strings.actionFailedTitle,
        description: strings.submitFailed,
        variant: 'danger',
      });
    } finally {
      setIsSubmitting(false);
    }
  }

  if (!sheetMounted) return null;

  return (
    <HeroBottomSheet
      isOpen={sheetOpen}
      onOpenChange={(open) => {
        if (!open && sheetOpen) onClose();
      }}
    >
      <HeroBottomSheet.Portal unstable_accessibilityContainerViewIsModal>
        <HeroBottomSheet.Overlay isCloseOnPress className="bg-black/55" />
        <HeroBottomSheet.Content
          snapPoints={['62%', '90%']}
          enableDynamicSizing={false}
          enableOverDrag={false}
          keyboardBehavior="extend"
          keyboardBlurBehavior="restore"
          contentContainerClassName="h-full bg-background"
          backgroundClassName="rounded-t-[30px] bg-background"
          handleClassName="rounded-t-[30px] bg-background"
          handleIndicatorClassName="bg-muted-foreground/50"
          footerComponent={(footerProps: BottomSheetFooterProps) => (
            <BottomSheetFooter {...footerProps} bottomInset={footerBottomInset} style={{ backgroundColor: theme.bg }}>
              <CommentComposer
                draft={draft}
                placeholder={strings.placeholder}
                primary={primary}
                sendLabel={strings.send}
                isSubmitting={isSubmitting}
                bottomPadding={composerBottomPadding}
                onChangeDraft={setDraft}
                onSubmit={() => void handleSubmit()}
              />
            </BottomSheetFooter>
          )}
        >
          <View className="flex-1 bg-background" style={{ flex: 1, height: '100%' }}>
            <View className="flex-row items-center justify-between gap-3 border-b border-border px-5 pb-4 pt-2">
              <View className="min-w-0 flex-1">
                <HeroBottomSheet.Title className="text-xl font-bold text-foreground">
                  {strings.title}
                </HeroBottomSheet.Title>
              </View>
              <HeroBottomSheet.Close>
                <View className="h-10 w-10 items-center justify-center rounded-full bg-surface">
                  <Ionicons name="close" size={20} color={theme.text} />
                </View>
              </HeroBottomSheet.Close>
            </View>

            <BottomSheetFlatList
              data={flattenedComments}
              keyExtractor={(item) => `${item.id}-${item.depth}`}
              keyboardShouldPersistTaps="handled"
              style={{ flex: 1, backgroundColor: theme.bg }}
              contentContainerStyle={{
                flexGrow: 1,
                gap: 10,
                paddingHorizontal: 16,
                paddingTop: 14,
                paddingBottom: listBottomPadding,
              }}
              ListEmptyComponent={
                isLoading ? (
                  <View className="flex-1 items-center justify-center py-12">
                    <Spinner size="sm" />
                  </View>
                ) : (
                  <Surface variant="secondary" className="items-center gap-3 rounded-panel p-6">
                    <View className="h-12 w-12 items-center justify-center rounded-full" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                      <Ionicons name="chatbubble-ellipses-outline" size={22} color={primary} />
                    </View>
                    <Text className="text-center text-sm text-muted-foreground">{strings.empty}</Text>
                  </Surface>
                )
              }
              renderItem={({ item }) => (
                <CommentRow comment={item} authorFallback={strings.authorFallback} />
              )}
            />
          </View>
        </HeroBottomSheet.Content>
      </HeroBottomSheet.Portal>
    </HeroBottomSheet>
  );
}

function CommentComposer({
  draft,
  placeholder,
  primary,
  sendLabel,
  isSubmitting,
  bottomPadding,
  onChangeDraft,
  onSubmit,
}: {
  draft: string;
  placeholder: string;
  primary: string;
  sendLabel: string;
  isSubmitting: boolean;
  bottomPadding: number;
  onChangeDraft: (value: string) => void;
  onSubmit: () => void;
}) {
  const theme = useTheme();
  const awareHandlers = useBottomSheetAwareHandlers();
  const { onFocus, onBlur } = Platform.OS === 'web'
    ? { onBlur: undefined, onFocus: undefined }
    : awareHandlers;

  return (
    <View className="flex-row items-end gap-2 border-t border-border bg-background px-4 pt-3" style={{ paddingBottom: bottomPadding }}>
      <TextArea
        value={draft}
        onChangeText={onChangeDraft}
        onFocus={onFocus}
        onBlur={onBlur}
        placeholder={placeholder}
        placeholderTextColor={theme.textMuted}
        multiline
        className="min-h-12 text-sm"
        containerClassName="mb-0 min-w-0 flex-1"
        inputClassName="max-h-28 min-h-12 flex-1 rounded-3xl px-4 py-3"
        style={{ color: theme.text, textAlignVertical: 'top' }}
        accessibilityLabel={placeholder}
      />
      <HeroButton
        isIconOnly
        variant="primary"
        isDisabled={isSubmitting || !draft.trim()}
        accessibilityLabel={sendLabel}
        style={{ backgroundColor: primary }}
        onPress={onSubmit}
        className="h-12 w-12 rounded-full"
      >
        {isSubmitting ? <Spinner size="sm" /> : <Ionicons name="send" size={18} color="#fff" />}
      </HeroButton>
    </View>
  );
}

function CommentRow({ comment, authorFallback }: { comment: FlatComment; authorFallback: string }) {
  const author = comment.author ?? { id: 0, name: authorFallback, avatar_url: null };
  const marginLeft = Math.min(comment.depth, 2) * 20;

  return (
    <View style={{ marginLeft }}>
      <Surface variant="secondary" className="rounded-panel p-3.5">
        <View className="flex-row items-start gap-3">
          <Avatar uri={author.avatar_url ?? author.avatar ?? null} name={author.name || authorFallback} size={34} />
          <View className="min-w-0 flex-1 gap-1">
            <View className="flex-row flex-wrap items-center gap-2">
              <Text className="text-sm font-semibold text-foreground" numberOfLines={1}>
                {author.name || authorFallback}
              </Text>
              {comment.created_at ? (
                <Text className="text-[11px] text-muted-foreground">
                  {formatRelativeTime(comment.created_at, true)}
                </Text>
              ) : null}
            </View>
            <Text className="text-sm leading-5 text-muted-foreground">
              {stripHtml(comment.content)}
            </Text>
          </View>
        </View>
      </Surface>
    </View>
  );
}

function flattenComments(comments: CommentItem[], depth = 0): FlatComment[] {
  return comments.flatMap((comment) => [
    { ...comment, depth },
    ...flattenComments(comment.replies ?? [], depth + 1),
  ]);
}

function countComments(comments: CommentItem[]): number {
  return comments.reduce((count, comment) => count + 1 + countComments(comment.replies ?? []), 0);
}

function stripHtml(value: string): string {
  return value.replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();
}
