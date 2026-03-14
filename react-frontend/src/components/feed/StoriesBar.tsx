// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * StoriesBar — Horizontal scrollable friend avatars at top of feed.
 */

import { Link } from 'react-router-dom';
import { Avatar } from '@heroui/react';
import { useTenant } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';

interface StoryFriend {
  id: number;
  name: string;
  avatar_url?: string;
  is_online?: boolean;
}

interface StoriesBarProps {
  friends: StoryFriend[];
}

export function StoriesBar({ friends }: StoriesBarProps) {
  const { tenantPath } = useTenant();

  if (!friends || friends.length === 0) return null;

  const truncateName = (name: string, max = 8): string =>
    name.length > max ? `${name.slice(0, max)}...` : name;

  return (
    <div className="w-full overflow-x-auto scrollbar-hide">
      <div className="flex items-start gap-3 px-1 py-2 min-w-min">
        {/* Friend avatars */}
        {friends.map((friend) => (
          <Link
            key={friend.id}
            to={tenantPath(`/profile/${friend.id}`)}
            className="flex flex-col items-center gap-1.5 flex-shrink-0 w-16 no-underline"
          >
            <div className="relative">
              {/* Gradient ring */}
              <div className="w-14 h-14 rounded-full p-[2px] bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500">
                <div className="w-full h-full rounded-full bg-[var(--surface-elevated)] p-[2px]">
                  <Avatar
                    src={resolveAvatarUrl(friend.avatar_url ?? null)}
                    name={friend.name}
                    className="w-full h-full"
                    size="md"
                  />
                </div>
              </div>

              {/* Online indicator */}
              {friend.is_online && (
                <span className="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-500 border-2 border-[var(--surface-elevated)] rounded-full" />
              )}
            </div>
            <span
              className="text-xs truncate w-full text-center"
              style={{ color: 'var(--text-primary)' }}
            >
              {truncateName((friend.name || '').split(' ')[0])}
            </span>
          </Link>
        ))}
      </div>
    </div>
  );
}

export default StoriesBar;
