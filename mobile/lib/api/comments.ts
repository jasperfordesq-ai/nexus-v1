// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export type CommentTargetType =
  | 'post'
  | 'listing'
  | 'event'
  | 'poll'
  | 'goal'
  | 'job'
  | 'challenge'
  | 'volunteer'
  | 'review'
  | 'blog'
  | 'discussion'
  | 'resource';

export interface CommentItem {
  id: number;
  content: string;
  created_at: string;
  edited?: boolean;
  is_own?: boolean;
  author: {
    id: number;
    name: string;
    avatar_url?: string | null;
    avatar?: string | null;
  };
  replies?: CommentItem[];
}

export interface CommentsResponse {
  comments: CommentItem[];
  count: number;
}

export function getComments(targetType: CommentTargetType, targetId: number): Promise<{ data?: CommentsResponse } | CommentsResponse> {
  return api.get<{ data?: CommentsResponse } | CommentsResponse>(`${API_V2}/comments`, {
    target_type: targetType,
    target_id: String(targetId),
  });
}

export function submitComment(
  targetType: CommentTargetType,
  targetId: number,
  content: string,
): Promise<{ data?: CommentItem }> {
  return api.post<{ data?: CommentItem }>(`${API_V2}/comments`, {
    target_type: targetType,
    target_id: targetId,
    content,
  });
}
