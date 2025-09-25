# Duplicate Message Tracking Fix

## Problem Summary

The notification tracker was creating duplicate message entities for the same email, as evidenced by the log output showing two different message IDs for the same email:
- First: `01K5Q4KF1TSV2DM1YJ5YYZBKZJ`
- Second: `01K5Q4KHW5MGS577CD4WJ1NGP4`

Additionally, messages were only tracked after they reached the transport, not when they were first queued, leading to delayed visibility.

## Root Causes

### 1. Late Tracking
- Messages were only tracked in `MessageEvent` or when they reached the transport
- No early tracking when messages were first queued via `SendMessageToTransportsEvent`
- Users couldn't see messages until they were actually being sent

### 2. Insufficient Duplicate Prevention
- The `onFailedMessage` method created new tracked messages without properly checking for existing ones
- Only checked object mapping and X-Tracking-ID headers, not stamp IDs
- Different events could create separate message entities for the same email

### 3. Event Flow Issues
```
Previous Flow (causing duplicates):
MessageEvent -> creates message entity
FailedMessageEvent -> creates ANOTHER message entity (duplicate!)
```

## Solution Implemented

### 1. Early Tracking in SendMessageToTransportsEvent
- Added `trackEmailEarly()` method to track messages when they first hit the messenger system
- Messages are now visible immediately when queued, not just when sent
- Uses stamp ID to ensure consistency across retries

### 2. Enhanced Duplicate Prevention
- Added stamp ID checking in all event handlers
- Multiple lookup strategies:
  1. Object mapping (existing)
  2. X-Tracking-ID header (existing)  
  3. **NEW**: Stamp ID lookup via `findByStampId()`
- Prevents creation of duplicate entities across all events

### 3. Improved Event Flow
```
New Flow (preventing duplicates):
SendMessageToTransportsEvent -> creates message entity (early tracking)
MessageEvent -> finds existing message by stamp ID (no duplicate)
FailedMessageEvent -> finds existing message by stamp ID (no duplicate)
SentMessageEvent -> finds existing message by stamp ID (no duplicate)
```

## Key Changes Made

### MailerEventSubscriber.php

1. **Enhanced onSendMessageToTransports()**:
   - Added early tracking for new messages
   - Creates tracked message immediately when message hits messenger
   - Uses stamp ID for consistent tracking across retries

2. **Improved onMessage()**:
   - Added stamp ID checking before creating new messages
   - Prevents duplicate creation when message was already tracked early
   - Fallback tracking for direct mailer usage (e.g., `mailer:test`)

3. **Fixed onFailedMessage()**:
   - Added stamp ID lookup before creating new tracked messages
   - Only creates new message entity as absolute last resort
   - Prevents the main cause of duplicate messages

4. **Updated onSentMessage()**:
   - Added stamp ID checking to find existing messages
   - Consistent with other event handlers

5. **Added trackEmailEarly() method**:
   - Handles early tracking with proper stamp ID storage
   - Ensures consistent fingerprinting and metadata

6. **Fixed import conflicts**:
   - Used alias for `MessageEvent` entity to avoid conflict with Symfony's `MessageEvent`
   - Updated all references to use `TrackedMessageEvent`

## Benefits

### ✅ No More Duplicates
- Single message entity per email, regardless of failures or retries
- Consistent tracking across all Symfony mailer events

### ✅ Immediate Visibility  
- Messages visible to admins/users as soon as they're queued
- No waiting for transport attempts to see message status

### ✅ Robust Retry Handling
- Proper detection and logging of retry attempts
- No duplicate entities created during retry scenarios

### ✅ Fallback Compatibility
- Still handles direct mailer usage (like `mailer:test` command)
- Maintains backward compatibility

### ✅ Enhanced Debugging
- Better logging with stamp IDs and tracking sources
- Clear indication of early vs fallback tracking

## Testing

The fix has been validated with:
- Content fingerprint consistency testing
- Event flow analysis
- Stamp ID tracking logic verification
- Early tracking workflow confirmation

## Expected Behavior After Fix

When running the same `mailer:test` command that previously created duplicates:

1. **SendMessageToTransportsEvent**: Creates single message entity with stamp ID
2. **MessageEvent**: Finds existing message by stamp ID, no duplicate created
3. **FailedMessageEvent**: Finds existing message by stamp ID, adds failure event to same entity
4. **Retry attempts**: Use existing message entity, add retry events

Result: **One message entity** with multiple events showing the complete lifecycle, instead of multiple duplicate message entities.

## Configuration

No configuration changes required. The middleware is already properly tagged and will automatically:
- Add `NotificationTrackingStamp` to all mailer messages
- Add `X-Stamp-ID` headers to emails for tracking consistency
- Enable early tracking via `SendMessageToTransportsEvent`

The fix is backward compatible and doesn't affect existing message tracking functionality.
