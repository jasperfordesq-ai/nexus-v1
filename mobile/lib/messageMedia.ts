// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import { authenticatedMediaRequest } from '@/lib/api/client';

export async function openAuthenticatedMessageMedia(path: string, filename: string): Promise<void> {
  const request = await authenticatedMediaRequest(path);
  const safeName = filename.replace(/[^A-Za-z0-9._-]/g, '_').slice(0, 120) || 'attachment';
  const target = `${FileSystem.cacheDirectory}nexus-message-${Date.now()}-${safeName}`;
  const result = await FileSystem.downloadAsync(request.uri, target, { headers: request.headers });
  if (!await Sharing.isAvailableAsync()) throw new Error('sharing_unavailable');
  await Sharing.shareAsync(result.uri);
}
