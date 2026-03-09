// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Stub type declarations for expo-notifications and expo-device.
 * These are replaced automatically once `npm install expo-notifications expo-device` is run.
 * They exist solely to satisfy the TypeScript compiler in the interim.
 */

declare module 'expo-notifications' {
  export type PermissionStatus = 'granted' | 'denied' | 'undetermined';

  export interface NotificationPermissionsStatus {
    status: PermissionStatus;
  }

  export interface ExpoPushToken {
    data: string;
    type: 'expo';
  }

  export interface NotificationContent {
    title: string | null;
    body: string | null;
    data: Record<string, unknown>;
  }

  export interface NotificationRequest {
    identifier: string;
    content: NotificationContent;
    trigger: unknown;
  }

  export interface Notification {
    date: number;
    request: NotificationRequest;
  }

  export interface NotificationChannel {
    name: string;
    importance: number;
    vibrationPattern?: number[];
    lightColor?: string;
  }

  export const AndroidImportance: {
    MAX: number;
    HIGH: number;
    DEFAULT: number;
    LOW: number;
    MIN: number;
  };

  export function setNotificationHandler(handler: {
    handleNotification: (notification: Notification) => Promise<{
      shouldShowAlert: boolean;
      shouldPlaySound: boolean;
      shouldSetBadge: boolean;
    }>;
  }): void;

  export function getPermissionsAsync(): Promise<NotificationPermissionsStatus>;
  export function requestPermissionsAsync(): Promise<NotificationPermissionsStatus>;
  export function getExpoPushTokenAsync(): Promise<ExpoPushToken>;
  export function setNotificationChannelAsync(
    channelId: string,
    channel: Partial<NotificationChannel>,
  ): Promise<void>;
}

declare module 'expo-image-picker' {
  export type MediaTypeOptions = { Images: 'Images'; Videos: 'Videos'; All: 'All' };
  export const MediaTypeOptions: MediaTypeOptions;

  export interface ImagePickerResult {
    canceled: boolean;
    assets?: Array<{ uri: string; type?: string; fileName?: string }>;
  }

  export interface ImagePickerOptions {
    mediaTypes?: string;
    allowsEditing?: boolean;
    aspect?: [number, number];
    quality?: number;
  }

  export function requestMediaLibraryPermissionsAsync(): Promise<{ status: string }>;
  export function launchImageLibraryAsync(options?: ImagePickerOptions): Promise<ImagePickerResult>;
}

declare module 'expo-device' {
  /** true on a physical device, false in simulators/emulators */
  export const isDevice: boolean;
  export const brand: string | null;
  export const modelName: string | null;
  export const osName: string | null;
  export const osVersion: string | null;
}

declare module 'expo-haptics' {
  export enum ImpactFeedbackStyle {
    Light = 'light',
    Medium = 'medium',
    Heavy = 'heavy',
  }
  export enum NotificationFeedbackType {
    Success = 'success',
    Warning = 'warning',
    Error = 'error',
  }
  export function impactAsync(style?: ImpactFeedbackStyle): Promise<void>;
  export function notificationAsync(type?: NotificationFeedbackType): Promise<void>;
  export function selectionAsync(): Promise<void>;
}

declare module 'pusher-js' {
  export interface AuthorizerCallback {
    (error: Error | null, authData: { auth: string; channel_data?: string } | null): void;
  }

  export interface Authorizer {
    authorize(socketId: string, callback: AuthorizerCallback): void;
  }

  export type AuthorizerGenerator = (channel: Channel) => Authorizer;

  export interface PusherOptions {
    cluster?: string;
    forceTLS?: boolean;
    authEndpoint?: string;
    auth?: { headers?: Record<string, string> };
    authorizer?: AuthorizerGenerator;
    disableStats?: boolean;
  }

  export interface Channel {
    name: string;
    bind(eventName: string, callback: (data: unknown) => void): Channel;
    unbind(eventName?: string, callback?: (data: unknown) => void): Channel;
    unbind_all(): Channel;
  }

  export interface ConnectionManager {
    state: 'initialized' | 'connecting' | 'connected' | 'disconnected' | 'failed' | 'unavailable';
    bind(eventName: string, callback: (data: unknown) => void): void;
  }

  export default class Pusher {
    connection: ConnectionManager;
    constructor(appKey: string, options?: PusherOptions);
    subscribe(channelName: string): Channel;
    unsubscribe(channelName: string): void;
    channel(channelName: string): Channel | undefined;
    disconnect(): void;
    connect(): void;
  }
}
