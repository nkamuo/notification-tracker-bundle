# React/TypeScript Implementation Guide

## Overview
This guide provides complete TypeScript interfaces, React components, and implementation patterns for building a UI that consumes the Notification Tracker Bundle API.

## Installation & Setup

### Dependencies
```bash
npm install @tanstack/react-query axios react-hook-form @hookform/resolvers yup
npm install @types/react @types/node typescript
npm install chart.js react-chartjs-2 date-fns lucide-react
npm install tailwindcss @tailwindcss/forms @tailwindcss/typography
```

### API Client Setup
```typescript
// src/lib/api-client.ts
import axios, { AxiosInstance, AxiosRequestConfig } from 'axios';

interface ApiClientConfig {
  baseURL: string;
  apiKey?: string;
  bearerToken?: string;
}

class NotificationTrackerAPI {
  private client: AxiosInstance;

  constructor(config: ApiClientConfig) {
    this.client = axios.create({
      baseURL: config.baseURL,
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
        ...(config.apiKey && { 'X-API-Key': config.apiKey }),
        ...(config.bearerToken && { 'Authorization': `Bearer ${config.bearerToken}` }),
      },
    });

    // Response interceptor for error handling
    this.client.interceptors.response.use(
      (response) => response,
      (error) => {
        if (error.response?.status === 401) {
          // Handle authentication error
          window.location.href = '/login';
        }
        return Promise.reject(error);
      }
    );
  }

  // Notifications
  async getNotifications(params?: GetNotificationsParams): Promise<NotificationCollection> {
    const response = await this.client.get('/notifications', { params });
    return response.data;
  }

  async getNotification(id: string): Promise<NotificationDetail> {
    const response = await this.client.get(`/notifications/${id}`);
    return response.data;
  }

  async createNotification(data: CreateNotificationRequest): Promise<NotificationDetail> {
    const response = await this.client.post('/notifications', data);
    return response.data;
  }

  // Messages
  async getMessages(params?: GetMessagesParams): Promise<MessageCollection> {
    const response = await this.client.get('/messages', { params });
    return response.data;
  }

  async getMessage(id: string): Promise<MessageDetail> {
    const response = await this.client.get(`/messages/${id}`);
    return response.data;
  }

  async retryMessage(id: string): Promise<MessageDetail> {
    const response = await this.client.post(`/messages/${id}/retry`);
    return response.data;
  }

  async cancelMessage(id: string): Promise<MessageDetail> {
    const response = await this.client.post(`/messages/${id}/cancel`);
    return response.data;
  }

  async deleteMessage(id: string): Promise<void> {
    await this.client.delete(`/messages/${id}`);
  }

  // Analytics
  async getDashboardStats(period: string = '30d'): Promise<DashboardStats> {
    const response = await this.client.get('/statistics/dashboard', { params: { period } });
    return response.data;
  }

  // Events
  async getEvents(params?: GetEventsParams): Promise<EventCollection> {
    const response = await this.client.get('/events', { params });
    return response.data;
  }
}

// Export singleton instance
export const apiClient = new NotificationTrackerAPI({
  baseURL: process.env.REACT_APP_API_BASE_URL || 'http://localhost:8000/notification-tracker',
  apiKey: process.env.REACT_APP_API_KEY,
});
```

## TypeScript Interfaces

### Core Types
```typescript
// src/types/notification-tracker.ts

export type ImportanceLevel = 'low' | 'normal' | 'high' | 'urgent';
export type ChannelType = 'email' | 'sms' | 'push' | 'slack' | 'telegram';
export type MessageStatus = 'pending' | 'queued' | 'sending' | 'sent' | 'delivered' | 'failed' | 'bounced' | 'cancelled' | 'retrying';
export type RecipientStatus = 'pending' | 'sent' | 'delivered' | 'failed' | 'bounced' | 'opened' | 'clicked' | 'unsubscribed';
export type EventType = 'sent' | 'delivered' | 'opened' | 'clicked' | 'bounced' | 'failed' | 'replied' | 'unsubscribed';

export interface MessageStats {
  total: number;
  sent: number;
  delivered: number;
  failed: number;
  pending: number;
  queued: number;
  cancelled: number;
}

export interface EngagementStats {
  totalRecipients: number;
  uniqueOpens: number;
  uniqueClicks: number;
  openRate: number;
  clickRate: number;
  bounceRate: number;
}

export interface NotificationListItem {
  '@id': string;
  '@type': string;
  id: string;
  type: string;
  importance: ImportanceLevel;
  subject?: string;
  channels: ChannelType[];
  userId?: string;
  createdAt: string;
  totalMessages: number;
  messageStats: MessageStats;
  engagementStats: EngagementStats;
}

export interface NotificationDetail extends NotificationListItem {
  context?: Record<string, any>;
  messages: MessageSummary[];
  channelBreakdown: Record<string, ChannelStats>;
}

export interface MessageSummary {
  '@id': string;
  id: string;
  type: ChannelType;
  status: MessageStatus;
  subject?: string;
  transportName?: string;
  messageId?: string;
  createdAt: string;
  sentAt?: string;
  notification: {
    id: string;
    type: string;
    subject?: string;
  };
  recipients: RecipientSummary[];
}

export interface MessageDetail extends MessageSummary {
  content?: MessageContent;
  events: MessageEvent[];
  metadata?: Record<string, any>;
}

export interface MessageContent {
  id: string;
  contentType: string;
  bodyText?: string;
  bodyHtml?: string;
  structuredData?: Record<string, any>;
  rawContent?: string;
}

export interface RecipientSummary {
  id: string;
  type: 'to' | 'cc' | 'bcc';
  address: string;
  name?: string;
  status: RecipientStatus;
  deliveredAt?: string;
  openedAt?: string;
  clickedAt?: string;
  bouncedAt?: string;
  openCount: number;
  clickCount: number;
}

export interface MessageEvent {
  id: string;
  type: EventType;
  createdAt: string;
  metadata?: Record<string, any>;
  recipient: RecipientSummary;
}

export interface ChannelStats {
  total: number;
  sent: number;
  delivered: number;
  failed: number;
  deliveryRate: number;
  engagementRate: number;
  cost?: number;
}

export interface DashboardStats {
  summary: {
    totalSent: number;
    deliveryRate: number;
    openRate: number;
    clickRate: number;
    bounceRate: number;
  };
  channels: Record<string, ChannelStats>;
  trends: {
    volume: ChartData;
    deliveryRates: ChartData;
    engagementRates: ChartData;
  };
  topPerforming: Array<{
    notificationType: string;
    metrics: PerformanceMetrics;
  }>;
}

export interface ChartData {
  labels: string[];
  datasets: Array<{
    label: string;
    data: number[];
    backgroundColor: string;
    borderColor: string;
  }>;
}

export interface PerformanceMetrics {
  deliveryRate: number;
  openRate: number;
  clickRate: number;
  bounceRate: number;
  unsubscribeRate: number;
}

// API Request/Response Types
export interface HydraCollection<T> {
  '@context': string;
  '@id': string;
  '@type': string;
  'hydra:member': T[];
  'hydra:totalItems': number;
  'hydra:view': {
    '@id': string;
    '@type': string;
    'hydra:first'?: string;
    'hydra:last'?: string;
    'hydra:previous'?: string;
    'hydra:next'?: string;
  };
}

export type NotificationCollection = HydraCollection<NotificationListItem>;
export type MessageCollection = HydraCollection<MessageSummary>;
export type EventCollection = HydraCollection<MessageEvent>;

// Query Parameters
export interface GetNotificationsParams {
  page?: number;
  itemsPerPage?: number;
  type?: string;
  importance?: ImportanceLevel;
  subject?: string;
  'order[createdAt]'?: 'asc' | 'desc';
  'order[type]'?: 'asc' | 'desc';
  'order[importance]'?: 'asc' | 'desc';
  'createdAt[after]'?: string;
  'createdAt[before]'?: string;
}

export interface GetMessagesParams {
  page?: number;
  itemsPerPage?: number;
  status?: MessageStatus;
  type?: ChannelType;
  transportName?: string;
  subject?: string;
  'notification.type'?: string;
  'order[createdAt]'?: 'asc' | 'desc';
  'createdAt[after]'?: string;
  'createdAt[before]'?: string;
}

export interface GetEventsParams {
  page?: number;
  itemsPerPage?: number;
  type?: EventType;
  'message.id'?: string;
  'notification.id'?: string;
  'order[createdAt]'?: 'asc' | 'desc';
}

// Form Types
export interface RecipientInput {
  name?: string;
  email?: string;
  phone?: string;
  device_token?: string;
  channel?: string;
  chat_id?: string;
  user_id?: string;
}

export interface ChannelSettings {
  email?: {
    transport?: string;
    from_email?: string;
    from_name?: string;
    subject?: string;
    template_id?: string;
  };
  sms?: {
    transport?: string;
    from_number?: string;
  };
  push?: {
    transport?: string;
    title?: string;
    icon?: string;
    click_action?: string;
  };
  slack?: {
    transport?: string;
    username?: string;
    icon_emoji?: string;
  };
  telegram?: {
    transport?: string;
    parse_mode?: 'HTML' | 'Markdown';
    disable_notification?: boolean;
  };
}

export interface CreateNotificationRequest {
  type: string;
  importance?: ImportanceLevel;
  subject?: string;
  channels: ChannelType[];
  content?: string;
  recipients: RecipientInput[];
  channelSettings?: ChannelSettings;
  context?: Record<string, any>;
  userId?: string;
}

// Filter Types
export interface NotificationFilters {
  search?: string;
  dateRange?: { start: string; end: string };
  type?: string;
  importance?: ImportanceLevel;
  status?: string;
  channels?: ChannelType[];
}

export interface MessageFilters {
  search?: string;
  dateRange?: { start: string; end: string };
  status?: MessageStatus;
  type?: ChannelType;
  transportName?: string;
  notificationType?: string;
}
```

## React Query Hooks

### Custom Hooks
```typescript
// src/hooks/useNotificationTracker.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../lib/api-client';
import type {
  NotificationCollection,
  NotificationDetail,
  MessageCollection,
  MessageDetail,
  DashboardStats,
  GetNotificationsParams,
  GetMessagesParams,
  CreateNotificationRequest,
} from '../types/notification-tracker';

// Query Keys
export const queryKeys = {
  notifications: ['notifications'] as const,
  notificationsList: (params?: GetNotificationsParams) => 
    ['notifications', 'list', params] as const,
  notification: (id: string) => ['notifications', id] as const,
  messages: ['messages'] as const,
  messagesList: (params?: GetMessagesParams) => 
    ['messages', 'list', params] as const,
  message: (id: string) => ['messages', id] as const,
  dashboardStats: (period: string) => ['dashboard', 'stats', period] as const,
  events: ['events'] as const,
};

// Notifications Hooks
export function useNotifications(params?: GetNotificationsParams) {
  return useQuery({
    queryKey: queryKeys.notificationsList(params),
    queryFn: () => apiClient.getNotifications(params),
    staleTime: 30 * 1000, // 30 seconds
  });
}

export function useNotification(id: string) {
  return useQuery({
    queryKey: queryKeys.notification(id),
    queryFn: () => apiClient.getNotification(id),
    enabled: !!id,
  });
}

export function useCreateNotification() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data: CreateNotificationRequest) => 
      apiClient.createNotification(data),
    onSuccess: () => {
      // Invalidate and refetch notifications list
      queryClient.invalidateQueries({ queryKey: queryKeys.notifications });
    },
  });
}

// Messages Hooks
export function useMessages(params?: GetMessagesParams) {
  return useQuery({
    queryKey: queryKeys.messagesList(params),
    queryFn: () => apiClient.getMessages(params),
    staleTime: 15 * 1000, // 15 seconds
  });
}

export function useMessage(id: string) {
  return useQuery({
    queryKey: queryKeys.message(id),
    queryFn: () => apiClient.getMessage(id),
    enabled: !!id,
  });
}

export function useRetryMessage() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string) => apiClient.retryMessage(id),
    onSuccess: (data, id) => {
      // Update specific message and invalidate lists
      queryClient.setQueryData(queryKeys.message(id), data);
      queryClient.invalidateQueries({ queryKey: queryKeys.messages });
      queryClient.invalidateQueries({ queryKey: queryKeys.notifications });
    },
  });
}

export function useCancelMessage() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string) => apiClient.cancelMessage(id),
    onSuccess: (data, id) => {
      queryClient.setQueryData(queryKeys.message(id), data);
      queryClient.invalidateQueries({ queryKey: queryKeys.messages });
      queryClient.invalidateQueries({ queryKey: queryKeys.notifications });
    },
  });
}

export function useDeleteMessage() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string) => apiClient.deleteMessage(id),
    onSuccess: (_, id) => {
      // Remove from cache and invalidate lists
      queryClient.removeQueries({ queryKey: queryKeys.message(id) });
      queryClient.invalidateQueries({ queryKey: queryKeys.messages });
      queryClient.invalidateQueries({ queryKey: queryKeys.notifications });
    },
  });
}

// Analytics Hooks
export function useDashboardStats(period: string = '30d') {
  return useQuery({
    queryKey: queryKeys.dashboardStats(period),
    queryFn: () => apiClient.getDashboardStats(period),
    staleTime: 60 * 1000, // 1 minute
  });
}

// Real-time Updates Hook
export function useRealTimeUpdates(enabled: boolean = true) {
  const queryClient = useQueryClient();

  useEffect(() => {
    if (!enabled) return;

    const ws = new WebSocket(
      process.env.REACT_APP_WS_URL || 'ws://localhost:8080/notification-tracker/activity'
    );

    ws.onmessage = (event) => {
      const data = JSON.parse(event.data);
      
      // Invalidate relevant queries based on event type
      if (data.type === 'notification.created' || data.type === 'notification.updated') {
        queryClient.invalidateQueries({ queryKey: queryKeys.notifications });
      }
      
      if (data.type === 'message.updated' || data.type === 'message.status_changed') {
        queryClient.invalidateQueries({ queryKey: queryKeys.messages });
        if (data.messageId) {
          queryClient.invalidateQueries({ queryKey: queryKeys.message(data.messageId) });
        }
      }
      
      if (data.type === 'stats.updated') {
        queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      }
    };

    return () => {
      ws.close();
    };
  }, [enabled, queryClient]);
}
```

## Form Components

### Create Notification Form
```typescript
// src/components/CreateNotificationForm.tsx
import React, { useState } from 'react';
import { useForm, useFieldArray } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from 'yup';
import { useCreateNotification } from '../hooks/useNotificationTracker';
import type { CreateNotificationRequest, ChannelType } from '../types/notification-tracker';

const validationSchema = yup.object({
  type: yup.string().required('Type is required').min(2).max(100),
  subject: yup.string().max(255),
  importance: yup.string().oneOf(['low', 'normal', 'high', 'urgent']).default('normal'),
  channels: yup.array().of(yup.string()).min(1, 'At least one channel is required'),
  content: yup.string().max(65535),
  recipients: yup.array().min(1, 'At least one recipient is required'),
  userId: yup.string().matches(/^[0-9A-HJKMNP-TV-Z]{26}$/, 'Invalid ULID format').nullable(),
});

interface CreateNotificationFormProps {
  onSuccess?: (notification: any) => void;
  onCancel?: () => void;
}

export const CreateNotificationForm: React.FC<CreateNotificationFormProps> = ({
  onSuccess,
  onCancel,
}) => {
  const [selectedChannels, setSelectedChannels] = useState<ChannelType[]>(['email']);
  const createNotification = useCreateNotification();
  
  const {
    register,
    control,
    handleSubmit,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<CreateNotificationRequest>({
    resolver: yupResolver(validationSchema),
    defaultValues: {
      importance: 'normal',
      channels: ['email'],
      recipients: [{ name: '', email: '' }],
    },
  });

  const { fields: recipients, append: addRecipient, remove: removeRecipient } = useFieldArray({
    control,
    name: 'recipients',
  });

  const channels = watch('channels');

  const handleChannelChange = (channel: ChannelType, checked: boolean) => {
    if (checked) {
      setSelectedChannels(prev => [...prev, channel]);
    } else {
      setSelectedChannels(prev => prev.filter(c => c !== channel));
    }
  };

  const onSubmit = async (data: CreateNotificationRequest) => {
    try {
      data.channels = selectedChannels;
      const result = await createNotification.mutateAsync(data);
      onSuccess?.(result);
    } catch (error) {
      console.error('Failed to create notification:', error);
    }
  };

  return (
    <div className="max-w-4xl mx-auto bg-white shadow-lg rounded-lg overflow-hidden">
      <div className="px-6 py-4 bg-gray-50 border-b border-gray-200">
        <h2 className="text-xl font-semibold text-gray-900">
          ✨ Create New Notification
        </h2>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="p-6 space-y-6">
        {/* Basic Information */}
        <div className="space-y-4">
          <h3 className="text-lg font-medium text-gray-900">Basic Information</h3>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700">
                Type *
              </label>
              <input
                {...register('type')}
                type="text"
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="welcome, alert, marketing..."
              />
              {errors.type && (
                <p className="mt-1 text-sm text-red-600">{errors.type.message}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700">
                Subject
              </label>
              <input
                {...register('subject')}
                type="text"
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Notification subject..."
              />
              {errors.subject && (
                <p className="mt-1 text-sm text-red-600">{errors.subject.message}</p>
              )}
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Importance
            </label>
            <div className="flex space-x-4">
              {['low', 'normal', 'high', 'urgent'].map((level) => (
                <label key={level} className="flex items-center">
                  <input
                    {...register('importance')}
                    type="radio"
                    value={level}
                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                  />
                  <span className="ml-2 text-sm text-gray-700 capitalize">{level}</span>
                </label>
              ))}
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">
              User ID (optional)
            </label>
            <input
              {...register('userId')}
              type="text"
              className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              placeholder="01ARZ3NDEKTSV4RRFFQ69G5FAV"
            />
            {errors.userId && (
              <p className="mt-1 text-sm text-red-600">{errors.userId.message}</p>
            )}
          </div>
        </div>

        {/* Channels */}
        <div className="space-y-4">
          <h3 className="text-lg font-medium text-gray-900">Channels & Configuration</h3>
          
          <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
            {[
              { key: 'email', label: '📧 Email', color: 'blue' },
              { key: 'sms', label: '📱 SMS', color: 'green' },
              { key: 'push', label: '🔔 Push', color: 'yellow' },
              { key: 'slack', label: '💬 Slack', color: 'purple' },
              { key: 'telegram', label: '✈️ Telegram', color: 'cyan' },
            ].map((channel) => (
              <label key={channel.key} className="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                <input
                  type="checkbox"
                  checked={selectedChannels.includes(channel.key as ChannelType)}
                  onChange={(e) => handleChannelChange(channel.key as ChannelType, e.target.checked)}
                  className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                />
                <span className="ml-2 text-sm font-medium">{channel.label}</span>
              </label>
            ))}
          </div>

          {/* Channel Settings */}
          {selectedChannels.includes('email') && (
            <div className="p-4 bg-blue-50 rounded-lg">
              <h4 className="text-md font-medium text-blue-900 mb-3">📧 Email Settings</h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input
                  {...register('channelSettings.email.transport')}
                  placeholder="Transport (e.g., sendgrid)"
                  className="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                />
                <input
                  {...register('channelSettings.email.from_email')}
                  placeholder="From Email"
                  className="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                />
                <input
                  {...register('channelSettings.email.from_name')}
                  placeholder="From Name"
                  className="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                />
                <input
                  {...register('channelSettings.email.template_id')}
                  placeholder="Template ID"
                  className="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                />
              </div>
            </div>
          )}

          {selectedChannels.includes('sms') && (
            <div className="p-4 bg-green-50 rounded-lg">
              <h4 className="text-md font-medium text-green-900 mb-3">📱 SMS Settings</h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input
                  {...register('channelSettings.sms.transport')}
                  placeholder="Transport (e.g., twilio)"
                  className="rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                />
                <input
                  {...register('channelSettings.sms.from_number')}
                  placeholder="From Number (+1234567890)"
                  className="rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                />
              </div>
            </div>
          )}
        </div>

        {/* Content */}
        <div className="space-y-4">
          <h3 className="text-lg font-medium text-gray-900">Content</h3>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Message Content
            </label>
            <textarea
              {...register('content')}
              rows={6}
              className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              placeholder="Enter your message content here..."
            />
          </div>
        </div>

        {/* Recipients */}
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-medium text-gray-900">Recipients</h3>
            <button
              type="button"
              onClick={() => addRecipient({ name: '', email: '' })}
              className="px-3 py-1 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
            >
              + Add Recipient
            </button>
          </div>

          <div className="space-y-3">
            {recipients.map((field, index) => (
              <div key={field.id} className="p-4 border rounded-lg">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="text-sm font-medium text-gray-700">
                    👤 Recipient {index + 1}
                  </h4>
                  {recipients.length > 1 && (
                    <button
                      type="button"
                      onClick={() => removeRecipient(index)}
                      className="text-red-600 hover:text-red-800"
                    >
                      🗑️ Remove
                    </button>
                  )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <input
                    {...register(`recipients.${index}.name`)}
                    placeholder="Name"
                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  />
                  
                  {selectedChannels.includes('email') && (
                    <input
                      {...register(`recipients.${index}.email`)}
                      type="email"
                      placeholder="Email Address"
                      className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                  )}
                  
                  {selectedChannels.includes('sms') && (
                    <input
                      {...register(`recipients.${index}.phone`)}
                      placeholder="Phone Number (+1234567890)"
                      className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                  )}
                  
                  {selectedChannels.includes('push') && (
                    <input
                      {...register(`recipients.${index}.device_token`)}
                      placeholder="Device Token"
                      className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                  )}
                  
                  {selectedChannels.includes('slack') && (
                    <input
                      {...register(`recipients.${index}.channel`)}
                      placeholder="Slack Channel (#general)"
                      className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                  )}
                  
                  {selectedChannels.includes('telegram') && (
                    <input
                      {...register(`recipients.${index}.chat_id`)}
                      placeholder="Chat ID"
                      className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Advanced Options */}
        <div className="space-y-4">
          <h3 className="text-lg font-medium text-gray-900">Advanced Options</h3>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Context Data (JSON)
            </label>
            <textarea
              {...register('context')}
              rows={4}
              className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
              placeholder='{"campaign_id": "welcome_series_2025", "user_segment": "new_users"}'
            />
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
          {onCancel && (
            <button
              type="button"
              onClick={onCancel}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
            >
              Cancel
            </button>
          )}
          <button
            type="button"
            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50"
          >
            💾 Save Draft
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 disabled:opacity-50"
          >
            {isSubmitting ? 'Creating...' : '🚀 Send Notification'}
          </button>
        </div>
      </form>
    </div>
  );
};
```

This comprehensive React implementation guide provides everything needed to build a professional UI! The guide includes:

✅ **Complete TypeScript Definitions** for type safety  
✅ **API Client Setup** with error handling and interceptors  
✅ **React Query Hooks** for efficient data management  
✅ **Form Components** with validation and user experience  
✅ **Real-time Updates** via WebSocket integration  
✅ **Responsive Design** patterns and accessibility  

The implementation covers all the major components needed for a complete notification management UI! 🚀
