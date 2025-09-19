# UI Component Specifications

## Overview
This document provides detailed specifications for building a comprehensive UI for the Notification Tracker Bundle. Each component includes layout, functionality, data requirements, and interaction patterns.

## Design System Guidelines

### Color Palette
```css
:root {
  /* Primary Colors */
  --primary-50: #eff6ff;
  --primary-500: #3b82f6;
  --primary-600: #2563eb;
  --primary-700: #1d4ed8;
  
  /* Status Colors */
  --success-500: #10b981;
  --warning-500: #f59e0b;
  --error-500: #ef4444;
  --info-500: #06b6d4;
  
  /* Channel Colors */
  --email-color: #3b82f6;
  --sms-color: #10b981;
  --push-color: #f59e0b;
  --slack-color: #4a154b;
  --telegram-color: #0088cc;
  
  /* Neutral Colors */
  --gray-50: #f9fafb;
  --gray-100: #f3f4f6;
  --gray-200: #e5e7eb;
  --gray-500: #6b7280;
  --gray-900: #111827;
}
```

### Typography Scale
```css
.text-xs { font-size: 0.75rem; line-height: 1rem; }
.text-sm { font-size: 0.875rem; line-height: 1.25rem; }
.text-base { font-size: 1rem; line-height: 1.5rem; }
.text-lg { font-size: 1.125rem; line-height: 1.75rem; }
.text-xl { font-size: 1.25rem; line-height: 1.75rem; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.text-3xl { font-size: 1.875rem; line-height: 2.25rem; }
```

---

## 1. Dashboard Overview Component

### Layout
```
┌─────────────────────────────────────────────────────────────┐
│ 📊 Notification Dashboard                           🔄 ⚙️   │
├─────────────────────────────────────────────────────────────┤
│ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ │
│ │📧 Email │ │📱 SMS   │ │🔔 Push  │ │💬 Slack │ │✈️ Teleg.│ │
│ │  1,247  │ │   892   │ │   634   │ │   156   │ │    89   │ │
│ │ ↗️ +12% │ │ ↗️ +8%  │ │ ↘️ -3%  │ │ ↗️ +24% │ │ ↗️ +15% │ │
│ └─────────┘ └─────────┘ └─────────┘ └─────────┘ └─────────┘ │
├─────────────────────────────────────────────────────────────┤
│ 📈 Performance Metrics (Last 30 Days)                      │
│ ┌───────────────────────────────────────────────────────┐   │
│ │ 📊 [Chart: Delivery Rates by Channel]                │   │
│ │                                                       │   │
│ │     100% ┌─┐                                         │   │
│ │      80% │█│ ┌─┐ ┌─┐                                 │   │
│ │      60% │█│ │█│ │█│ ┌─┐                             │   │
│ │      40% │█│ │█│ │█│ │█│ ┌─┐                         │   │
│ │      20% │█│ │█│ │█│ │█│ │█│                         │   │
│ │       0% └─┘ └─┘ └─┘ └─┘ └─┘                         │   │
│ │         Email SMS Push Slack Tele                    │   │
│ └───────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Data Requirements
```typescript
interface DashboardData {
  channelStats: {
    email: ChannelStat;
    sms: ChannelStat;
    push: ChannelStat;
    slack: ChannelStat;
    telegram: ChannelStat;
  };
  performanceMetrics: {
    deliveryRates: ChartData;
    engagementRates: ChartData;
    volumeTrends: ChartData;
  };
  recentActivity: ActivityItem[];
}

interface ChannelStat {
  total: number;
  percentChange: number;
  deliveryRate: number;
  engagementRate: number;
}

interface ChartData {
  labels: string[];
  datasets: {
    label: string;
    data: number[];
    backgroundColor: string;
    borderColor: string;
  }[];
}
```

### API Calls
```typescript
// Dashboard stats
GET /notification-tracker/statistics/dashboard

// Channel performance
GET /notification-tracker/statistics/channels?period=30d

// Recent activity
GET /notification-tracker/notifications?order[createdAt]=desc&itemsPerPage=10
```

---

## 2. Notification List Component

### Layout
```
┌─────────────────────────────────────────────────────────────┐
│ 📋 Notifications                               [+ Create]   │
├─────────────────────────────────────────────────────────────┤
│ 🔍 Search: [_______________] 📅 Date: [_______] 🏷️ Type: [▼] │
│ 📊 Status: [All ▼] 🎯 Importance: [All ▼] 🔄 Auto-refresh  │
├─────────────────────────────────────────────────────────────┤
│ ✅ Select All | 📧 Email | 📱 SMS | 🔔 Push | 💬 Chat       │
├─────────────────────────────────────────────────────────────┤
│ ☐ 📧📱 Welcome Series                          🟢 2 sent    │
│     welcome • normal • 2025-09-19 10:30       📊 View      │
│     john@example.com, +1234567890              🔄 Retry     │
├─────────────────────────────────────────────────────────────┤
│ ☐ 🔔💬 System Alert                            🔴 1 failed  │
│     alert • urgent • 2025-09-19 09:15         📊 View      │
│     #general, admin@example.com                🔄 Retry     │
├─────────────────────────────────────────────────────────────┤
│ ☐ 📧 Marketing Campaign                        🟡 3 pending │
│     marketing • normal • 2025-09-19 08:00     📊 View      │
│     campaign@example.com (1,247 recipients)    ⏸️ Pause     │
└─────────────────────────────────────────────────────────────┘
│ ← Prev | Page 1 of 24 | Next → | Show: [20 ▼] per page    │
└─────────────────────────────────────────────────────────────┘
```

### Component Props
```typescript
interface NotificationListProps {
  notifications: NotificationListItem[];
  pagination: PaginationData;
  filters: FilterOptions;
  onFilterChange: (filters: FilterOptions) => void;
  onPageChange: (page: number) => void;
  onNotificationSelect: (id: string) => void;
  onBulkAction: (action: string, ids: string[]) => void;
  loading: boolean;
}

interface NotificationListItem {
  id: string;
  type: string;
  importance: 'low' | 'normal' | 'high' | 'urgent';
  subject: string;
  channels: string[];
  createdAt: string;
  totalMessages: number;
  messageStats: MessageStats;
  recipients: string[];
  status: 'pending' | 'sending' | 'sent' | 'failed' | 'completed';
}

interface FilterOptions {
  search?: string;
  dateRange?: { start: string; end: string };
  type?: string;
  importance?: string;
  status?: string;
  channels?: string[];
}
```

### Status Indicators
```css
.status-indicator {
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
}

.status-pending { background: var(--warning-500); color: white; }
.status-sending { background: var(--info-500); color: white; }
.status-sent { background: var(--success-500); color: white; }
.status-failed { background: var(--error-500); color: white; }
.status-completed { background: var(--gray-500); color: white; }
```

---

## 3. Create Notification Form

### Layout
```
┌─────────────────────────────────────────────────────────────┐
│ ✨ Create New Notification                          [✕]     │
├─────────────────────────────────────────────────────────────┤
│ Basic Information                                           │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Type: [_______________] *                              │ │
│ │ Subject: [_______________________________________] *   │ │
│ │ Importance: ○ Low ○ Normal ● High ○ Urgent            │ │
│ │ User ID: [_______________] (optional)                  │ │
│ └─────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────┤
│ Channels & Configuration                                    │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ ☑️ Email     ☑️ SMS      ☐ Push     ☐ Slack     ☐ Telegram │ │
│ │                                                         │ │
│ │ 📧 Email Settings:                                      │ │
│ │   Transport: [SendGrid ▼]  From: [noreply@app.com]    │ │
│ │   From Name: [Your App]    Template: [welcome.html]    │ │
│ │                                                         │ │
│ │ 📱 SMS Settings:                                        │ │
│ │   Transport: [Twilio ▼]    From: [+1234567890]        │ │
│ └─────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────┤
│ Content                                                     │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 📝 Message Content                                      │ │
│ │ ┌─────────────────────────────────────────────────────┐ │ │
│ │ │ Welcome to our platform! We're excited to have     │ │ │
│ │ │ you on board. Here's what you can do:              │ │ │
│ │ │                                                     │ │ │
│ │ │ • Explore our features                              │ │ │
│ │ │ • Set up your profile                               │ │ │
│ │ │ • Join our community                                │ │ │
│ │ │                                                     │ │ │
│ │ │ Best regards,                                       │ │ │
│ │ │ The Team                                            │ │ │
│ │ └─────────────────────────────────────────────────────┘ │ │
│ │ [📎 Rich Editor] [📷 Insert Image] [🔗 Insert Link]    │ │
│ └─────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────┤
│ Recipients                                   [📥 Import CSV] │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 👤 Recipient 1                                [🗑️ Remove] │ │
│ │   Email: [john@example.com]  Name: [John Doe]          │ │
│ │   Phone: [+1234567890]       (for SMS channel)         │ │
│ │                                                         │ │
│ │ 👤 Recipient 2                                [🗑️ Remove] │ │
│ │   Email: [jane@example.com]  Name: [Jane Smith]        │ │
│ │   Phone: [+1987654321]       (for SMS channel)         │ │
│ │                                                         │ │
│ │ [+ Add Recipient] [📤 Bulk Import]                      │ │
│ └─────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────┤
│ Advanced Options                                            │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 📅 Send Schedule: ○ Send Now ● Schedule for Later      │ │
│ │    Send At: [2025-09-20] [14:30]                       │ │
│ │                                                         │ │
│ │ 🏷️ Context Data (JSON):                                 │ │
│ │ {                                                       │ │
│ │   "campaign_id": "welcome_series_2025",                │ │
│ │   "user_segment": "new_users"                          │ │
│ │ }                                                       │ │
│ └─────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────┤
│                           [Cancel] [💾 Save Draft] [🚀 Send] │
└─────────────────────────────────────────────────────────────┘
```

### Form Validation
```typescript
interface CreateNotificationForm {
  // Basic Information
  type: string;           // Required, min: 2, max: 100
  subject?: string;       // Optional, max: 255
  importance: 'low' | 'normal' | 'high' | 'urgent'; // Default: 'normal'
  userId?: string;        // Optional, must be valid ULID
  
  // Channels
  channels: string[];     // Required, min: 1 channel
  channelSettings: {
    email?: EmailSettings;
    sms?: SmsSettings;
    push?: PushSettings;
    slack?: SlackSettings;
    telegram?: TelegramSettings;
  };
  
  // Content
  content?: string;       // Optional, max: 65535
  
  // Recipients
  recipients: Recipient[]; // Required, min: 1 recipient
  
  // Advanced
  scheduledAt?: string;   // Optional, ISO datetime
  context?: object;       // Optional, valid JSON
}

interface EmailSettings {
  transport?: string;
  from_email?: string;    // Valid email format
  from_name?: string;
  template_id?: string;
}

interface Recipient {
  name?: string;
  email?: string;         // Required for email channel
  phone?: string;         // Required for SMS channel
  device_token?: string;  // Required for push channel
  channel?: string;       // Required for Slack channel
  chat_id?: string;       // Required for Telegram channel
  user_id?: string;       // Optional, must be valid ULID
}
```

### Validation Rules
```typescript
const validationSchema = {
  type: {
    required: true,
    minLength: 2,
    maxLength: 100,
    pattern: /^[a-zA-Z0-9_-]+$/
  },
  subject: {
    maxLength: 255
  },
  channels: {
    required: true,
    minItems: 1,
    enum: ['email', 'sms', 'push', 'slack', 'telegram']
  },
  recipients: {
    required: true,
    minItems: 1,
    maxItems: 1000,
    custom: (recipients, channels) => {
      // Validate that each recipient has required fields for selected channels
      for (const recipient of recipients) {
        if (channels.includes('email') && !recipient.email) {
          throw new Error('Email is required for email channel recipients');
        }
        if (channels.includes('sms') && !recipient.phone) {
          throw new Error('Phone is required for SMS channel recipients');
        }
        // ... other validations
      }
    }
  }
};
```

---

## 4. Notification Detail View

### Layout
```
┌─────────────────────────────────────────────────────────────┐
│ ← Back │ 🔄 Refresh │ 📊 Analytics │ 🗑️ Delete │ ⚙️ Settings │
├─────────────────────────────────────────────────────────────┤
│ Welcome Series                                 🟢 Active    │
│ welcome • normal • Created: 2025-09-19 10:30               │
│ 📧📱 2 channels • 1 recipient • Campaign: welcome_series    │
├─────────────────────────────────────────────────────────────┤
│ 📊 Performance Summary                                      │
│ ┌─────────────┬─────────────┬─────────────┬─────────────┐   │
│ │ 📧 Email    │ 📱 SMS      │ 👥 Total    │ 📈 Rates    │   │
│ │ ✅ Sent: 1  │ ✅ Sent: 1  │ Recipients  │ Open: 50%   │   │
│ │ 📖 Opened:1 │ 💬 Reply: 0 │     1       │ Click: 25%  │   │
│ │ 🖱️ Click: 0 │ ❌ Failed:0 │ Messages: 2 │ Bounce: 0%  │   │
│ └─────────────┴─────────────┴─────────────┴─────────────┘   │
├─────────────────────────────────────────────────────────────┤
│ 📧 Messages                                                 │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ ✅ Email to john@example.com                            │ │
│ │    Subject: Welcome to Our Platform!                   │ │
│ │    Status: Delivered • Opened 2x • Sent via SendGrid  │ │
│ │    📅 Sent: 10:30:15 • 📖 Opened: 10:45:00             │ │
│ │    [📊 Details] [📧 View Content] [🔄 Resend]          │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ ✅ SMS to +1234567890                                   │ │
│ │    Content: "Welcome! Thank you for joining..."        │ │
│ │    Status: Delivered • Sent via Twilio                 │ │
│ │    📅 Sent: 10:30:20 • ✅ Delivered: 10:30:25          │ │
│ │    [📊 Details] [💬 View Thread] [🔄 Resend]           │ │
│ └─────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────┤
│ 📈 Timeline                                                 │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 10:30:00 📝 Notification created                        │ │
│ │ 10:30:15 📧 Email queued for sending                    │ │
│ │ 10:30:15 📱 SMS queued for sending                      │ │
│ │ 10:30:18 📧 Email sent via SendGrid                     │ │
│ │ 10:30:22 📱 SMS sent via Twilio                         │ │
│ │ 10:30:25 📱 SMS delivered                               │ │
│ │ 10:30:30 📧 Email delivered                             │ │
│ │ 10:45:00 📖 Email opened by recipient                   │ │
│ │ 11:15:30 📖 Email opened again                          │ │
│ └─────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────┤
│ 📝 Metadata                                                 │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Context Data:                                           │ │
│ │ {                                                       │ │
│ │   "campaign_id": "welcome_series_2025",                │ │
│ │   "user_segment": "new_users",                         │ │
│ │   "source": "website_signup"                           │ │
│ │ }                                                       │ │
│ │                                                         │ │
│ │ User ID: 01ARZ3NDEKTSV4RRFFQ69G5FB0                     │ │
│ │ Created: 2025-09-19T10:30:00+00:00                     │ │
│ │ Updated: 2025-09-19T11:15:30+00:00                     │ │
│ └─────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### Data Loading
```typescript
interface NotificationDetail {
  notification: Notification;
  messages: Message[];
  events: Event[];
  analytics: {
    performance: PerformanceMetrics;
    timeline: TimelineEvent[];
    channelBreakdown: ChannelBreakdown;
  };
}

// API calls for detail view
const loadNotificationDetail = async (id: string): Promise<NotificationDetail> => {
  const [notification, messages, events] = await Promise.all([
    fetch(`/notification-tracker/notifications/${id}`),
    fetch(`/notification-tracker/messages?notification.id=${id}`),
    fetch(`/notification-tracker/events?notification.id=${id}&order[createdAt]=asc`)
  ]);
  
  return {
    notification: await notification.json(),
    messages: await messages.json(),
    events: await events.json()
  };
};
```

---

## 5. Analytics Dashboard

### Layout
```
┌─────────────────────────────────────────────────────────────┐
│ 📊 Analytics Dashboard                    📅 Last 30 Days ▼ │
├─────────────────────────────────────────────────────────────┤
│ Key Metrics                                                 │
│ ┌─────────────┬─────────────┬─────────────┬─────────────┐   │
│ │ 📬 Total    │ ✅ Delivery │ 📖 Open     │ 🖱️ Click    │   │
│ │ Sent        │ Rate        │ Rate        │ Rate        │   │
│ │ 12,847      │ 98.2%       │ 24.6%       │ 3.2%        │   │
│ │ ↗️ +15%     │ ↗️ +0.8%    │ ↘️ -2.1%    │ ↗️ +0.5%    │   │
│ └─────────────┴─────────────┴─────────────┴─────────────┘   │
├─────────────────────────────────────────────────────────────┤
│ 📈 Volume Trends                                            │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ [Interactive Chart: Messages Sent Over Time]           │ │
│ │                                                         │ │
│ │ 2000 ┐                                                  │ │
│ │ 1500 ┤    ●                                             │ │
│ │ 1000 ┤  ●   ●     ●                                     │ │
│ │  500 ┤●       ● ●   ●   ●                              │ │
│ │    0 └─────────────────────────────────────────────────  │ │
│ │      1  3  5  7  9 11 13 15 17 19 21 23 25 27 29      │ │
│ │                                                         │ │
│ │ Legend: ● Email ▲ SMS ■ Push ♦ Slack ▼ Telegram       │ │
│ └─────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────┤
│ 🎯 Channel Performance                                      │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Channel     │ Volume │ Delivery │ Engagement │ Cost     │ │
│ ├─────────────┼────────┼──────────┼────────────┼──────────┤ │
│ │ 📧 Email    │ 8,234  │ 98.5%    │ 28.4%      │ $124.50  │ │
│ │ 📱 SMS      │ 2,891  │ 97.8%    │ 12.1%      │ $347.20  │ │
│ │ 🔔 Push     │ 1,456  │ 94.2%    │ 8.7%       │ $23.10   │ │
│ │ 💬 Slack    │ 203    │ 99.1%    │ 45.3%      │ $0.00    │ │
│ │ ✈️ Telegram │ 63     │ 98.4%    │ 31.7%      │ $0.00    │ │
│ └─────────────┴────────┴──────────┴────────────┴──────────┘ │
├─────────────────────────────────────────────────────────────┤
│ 🏆 Top Performing                   📉 Needs Attention     │
│ ┌─────────────────────────────────┬─────────────────────────┐ │
│ │ 🥇 Welcome Series              │ ⚠️ Password Reset       │ │
│ │    Open Rate: 87.3%            │    Delivery: 78.2%      │ │
│ │    Click Rate: 12.4%           │    Bounce Rate: 21.8%   │ │
│ │                                │                         │ │
│ │ 🥈 Weekly Newsletter           │ ⚠️ Promotional SMS      │ │
│ │    Open Rate: 45.6%            │    Opt-out Rate: 8.7%   │ │
│ │    Click Rate: 8.9%            │    Complaint Rate: 2.1% │ │
│ └─────────────────────────────────┴─────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### Chart Components
```typescript
interface ChartProps {
  data: ChartData;
  type: 'line' | 'bar' | 'pie' | 'area';
  options: ChartOptions;
  responsive: boolean;
}

// Volume trends chart
const VolumeTrendsChart: React.FC = () => {
  const chartData = {
    labels: last30Days,
    datasets: [
      {
        label: 'Email',
        data: emailVolumeData,
        borderColor: 'var(--email-color)',
        backgroundColor: 'rgba(59, 130, 246, 0.1)'
      },
      {
        label: 'SMS', 
        data: smsVolumeData,
        borderColor: 'var(--sms-color)',
        backgroundColor: 'rgba(16, 185, 129, 0.1)'
      }
      // ... other channels
    ]
  };
  
  return <LineChart data={chartData} responsive={true} />;
};

// Performance metrics component
const PerformanceMetrics: React.FC = () => {
  const metrics = useMetrics('30d');
  
  return (
    <div className="metrics-grid">
      {metrics.map(metric => (
        <MetricCard
          key={metric.key}
          title={metric.title}
          value={metric.value}
          change={metric.change}
          trend={metric.trend}
          format={metric.format}
        />
      ))}
    </div>
  );
};
```

---

## 6. Real-time Activity Feed

### Layout
```
┌─────────────────────────────────────────────────────────────┐
│ 🔴 Live Activity                               [⏸️ Pause]   │
├─────────────────────────────────────────────────────────────┤
│ 🟢 10:45:23 📧 Email delivered to user@example.com         │
│              Welcome Series • SendGrid                     │
│                                                             │
│ 🟢 10:45:18 📱 SMS delivered to +1234567890                │
│              Order Confirmation • Twilio                   │
│                                                             │
│ 🔵 10:45:15 📖 Email opened by premium@company.com          │
│              Weekly Newsletter • 2nd open                  │
│                                                             │
│ 🟡 10:45:12 ⚠️ SMS failed to +1999999999                   │
│              Invalid number • Twilio Error 21211           │
│              [🔄 Retry] [📝 Edit Contact]                   │
│                                                             │
│ 🟢 10:45:08 🔔 Push notification delivered                  │
│              Breaking News Alert • Firebase                │
│              Device: iPhone 14 Pro                         │
│                                                             │
│ 🔵 10:45:03 🖱️ Link clicked in email                        │
│              Product Launch • CTA: "Learn More"            │
│              user@startup.com                              │
│                                                             │
│ 🟢 10:44:58 💬 Slack message sent to #general              │
│              Daily Standup Reminder                        │
│                                                             │
│ 🔴 10:44:55 ❌ Email bounced from old@domain.com            │
│              Hard bounce • Domain not found                │
│              [🗑️ Remove] [📝 Update Contact]               │
└─────────────────────────────────────────────────────────────┘
```

### WebSocket Integration
```typescript
interface ActivityFeedProps {
  maxItems?: number;
  autoScroll?: boolean;
  filters?: ActivityFilter[];
}

interface ActivityEvent {
  id: string;
  timestamp: string;
  type: 'sent' | 'delivered' | 'opened' | 'clicked' | 'bounced' | 'failed';
  channel: string;
  message: string;
  recipient: string;
  notification: {
    id: string;
    type: string;
    subject: string;
  };
  metadata?: Record<string, any>;
  severity: 'success' | 'info' | 'warning' | 'error';
}

const ActivityFeed: React.FC<ActivityFeedProps> = ({ maxItems = 100 }) => {
  const [events, setEvents] = useState<ActivityEvent[]>([]);
  const [isPaused, setIsPaused] = useState(false);
  
  useEffect(() => {
    const ws = new WebSocket('ws://localhost:8080/notification-tracker/activity');
    
    ws.onmessage = (event) => {
      if (!isPaused) {
        const newEvent = JSON.parse(event.data);
        setEvents(prev => [newEvent, ...prev.slice(0, maxItems - 1)]);
      }
    };
    
    return () => ws.close();
  }, [isPaused, maxItems]);
  
  return (
    <div className="activity-feed">
      <div className="activity-header">
        <h3>🔴 Live Activity</h3>
        <button onClick={() => setIsPaused(!isPaused)}>
          {isPaused ? '▶️ Resume' : '⏸️ Pause'}
        </button>
      </div>
      
      <div className="activity-list">
        {events.map(event => (
          <ActivityItem key={event.id} event={event} />
        ))}
      </div>
    </div>
  );
};
```

---

## 7. Settings & Configuration

### Layout
```
┌─────────────────────────────────────────────────────────────┐
│ ⚙️ Settings                                                  │
├─────────────────────────────────────────────────────────────┤
│ 🔧 Transport Configuration                                  │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 📧 Email Providers                                      │ │
│ │ ┌─────────────┬─────────────┬─────────────┬─────────┐   │ │
│ │ │ Provider    │ Status      │ Rate Limit  │ Actions │   │ │
│ │ ├─────────────┼─────────────┼─────────────┼─────────┤   │ │
│ │ │ SendGrid    │ 🟢 Active   │ 100/min     │ [Edit]  │   │ │
│ │ │ Mailgun     │ 🔴 Disabled │ 50/min      │ [Edit]  │   │ │
│ │ │ SMTP        │ 🟡 Testing  │ 10/min      │ [Edit]  │   │ │
│ │ └─────────────┴─────────────┴─────────────┴─────────┘   │ │
│ │                                          [+ Add Provider] │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 📱 SMS Providers                                        │ │
│ │ ┌─────────────┬─────────────┬─────────────┬─────────┐   │ │
│ │ │ Twilio      │ 🟢 Active   │ 1/sec       │ [Edit]  │   │ │
│ │ │ Nexmo       │ 🔴 Disabled │ 5/sec       │ [Edit]  │   │ │
│ │ └─────────────┴─────────────┴─────────────┴─────────┘   │ │
│ │                                          [+ Add Provider] │ │
│ └─────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────┤
│ 🔔 Webhook Configuration                                    │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Webhook URL: https://yourapp.com/webhooks/               │ │
│ │              notification-tracker/{provider}            │ │
│ │                                                         │ │
│ │ ☑️ Verify Signatures    ☑️ Async Processing             │ │
│ │ ☑️ Retry Failed Events  ☑️ Log All Events              │ │
│ │                                                         │ │
│ │ Retry Policy: [Exponential Backoff ▼]                  │ │
│ │ Max Retries: [5        ] Timeout: [30s      ]          │ │
│ └─────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────┤
│ 📊 Data Retention                                           │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Keep notifications for: [365 days ▼]                   │ │
│ │ Keep events for:        [90 days  ▼]                   │ │
│ │ Keep content for:       [30 days  ▼]                   │ │
│ │ Keep attachments for:   [30 days  ▼]                   │ │
│ │                                                         │ │
│ │ ☑️ Auto-cleanup enabled                                 │ │
│ │ Cleanup schedule: [Daily at 2:00 AM]                   │ │
│ │                                                         │ │
│ │ [🗑️ Run Cleanup Now] [📊 Storage Usage]                │ │
│ └─────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────┤
│                                        [Cancel] [💾 Save]   │ └─────────────────────────────────────────────────────────────┘
```

This comprehensive UI specification provides everything needed to build a professional, feature-rich interface for the Notification Tracker Bundle. Each component includes detailed layouts, data requirements, API integration points, and interaction patterns.
