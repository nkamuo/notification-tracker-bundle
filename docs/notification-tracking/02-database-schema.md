# Database Schema Design

## Entity Relationship Diagram

```mermaid
erDiagram
    Message ||--o{ MessageEvent : has
    Message ||--o{ MessageAttachment : has
    Message ||--o{ MessageRecipient : has
    Message ||--|| MessageContent : contains
    EmailMessage ||--|| Message : extends
    SmsMessage ||--|| Message : extends
    SlackMessage ||--|| Message : extends
    TelegramMessage ||--|| Message : extends
    Notification ||--o{ Message : generates
    MessageEvent ||--o| WebhookPayload : references
    MessageTemplate ||--o{ Message : uses

    Message {
        uuid id PK
        string type
        string status
        string transport_name
        string transport_dsn
        json metadata
        datetime created_at
        datetime updated_at
        datetime scheduled_at
        datetime sent_at
        int retry_count
        string failure_reason
        uuid notification_id FK
        uuid template_id FK
    }

    EmailMessage {
        uuid id PK
        string subject
        string from_email
        string from_name
        string reply_to
        json headers
        string message_id
        boolean track_opens
        boolean track_clicks
    }

    SmsMessage {
        uuid id PK
        string from_number
        string provider_message_id
        int segments_count
        decimal cost
    }

    MessageRecipient {
        uuid id PK
        uuid message_id FK
        string type
        string address
        string name
        string status
        datetime delivered_at
        datetime opened_at
        datetime clicked_at
        datetime bounced_at
        json metadata
    }

    MessageContent {
        uuid id PK
        uuid message_id FK
        string content_type
        text body_text
        text body_html
        json structured_data
    }

    MessageEvent {
        uuid id PK
        uuid message_id FK
        uuid recipient_id FK
        string event_type
        json event_data
        string ip_address
        string user_agent
        datetime occurred_at
        uuid webhook_payload_id FK
    }

    WebhookPayload {
        uuid id PK
        string provider
        string event_type
        json raw_payload
        string signature
        boolean processed
        datetime received_at
    }

    Notification {
        uuid id PK
        string type
        string importance
        json channels
        json context
        uuid user_id FK
        datetime created_at
    }

    MessageTemplate {
        uuid id PK
        string name
        string type
        json variables
        text content
        boolean active
    }
```

## Indexes
- `idx_message_status` on Message(status)
- `idx_message_sent_at` on Message(sent_at)
- `idx_message_notification` on Message(notification_id)
- `idx_recipient_message` on MessageRecipient(message_id)
- `idx_event_message` on MessageEvent(message_id)
- `idx_event_type_date` on MessageEvent(event_type, occurred_at)
- `idx_webhook_provider_processed` on WebhookPayload(provider, processed)