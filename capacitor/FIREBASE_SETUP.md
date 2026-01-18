# Firebase Cloud Messaging (FCM) Setup Guide

This guide explains how to set up Firebase Cloud Messaging for native push notifications in the Hour Timebank Android app.

> **Note:** This guide uses the FCM HTTP v1 API with service account authentication. The Legacy API was deprecated in June 2024.

## Step 1: Create Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Click "Add project"
3. Enter project name: "Hour Timebank" (or similar)
4. Enable/disable Google Analytics as needed
5. Click "Create project"

## Step 2: Add Android App to Firebase

1. In your Firebase project, click the Android icon to add an Android app
2. Enter the package name: `com.nexus.timebank`
3. Enter app nickname: "Hour Timebank"
4. Click "Register app"

## Step 3: Download google-services.json

1. Download the `google-services.json` file
2. Place it in: `capacitor/android/app/google-services.json`

```
capacitor/
  android/
    app/
      google-services.json   <-- Place file here
      build.gradle
      src/
```

## Step 4: Create Service Account for Server

The FCM HTTP v1 API requires a service account for server-side authentication:

1. In Firebase Console, go to **Project Settings** (gear icon)
2. Go to the **Service accounts** tab
3. Click **"Generate new private key"**
4. A JSON file will be downloaded (e.g., `your-project-firebase-adminsdk-xxxxx.json`)

## Step 5: Install Service Account on Server

Place the service account JSON file in one of these locations (checked in order):

1. `firebase-service-account.json` (project root)
2. `config/firebase-service-account.json`
3. `capacitor/firebase-service-account.json`

Or specify a custom path in `.env`:

```env
# Firebase Cloud Messaging - Service Account Path
FCM_SERVICE_ACCOUNT_PATH=path/to/your-service-account.json
```

Or provide the JSON content directly:

```env
# Firebase Cloud Messaging - Service Account JSON (entire file contents)
FCM_SERVICE_ACCOUNT_JSON={"type":"service_account","project_id":"...","private_key":"..."}
```

> **Security:** Never commit the service account JSON file to version control. Add it to `.gitignore`.

## Step 6: Rebuild the APK

```bash
cd capacitor
npm run build:apk
```

## How It Works

### Native App (Capacitor/Android):
1. App registers with FCM on first launch
2. FCM token is sent to your server via `/api/push/register-device`
3. Server stores token in `fcm_device_tokens` table
4. When notifications are created, `FCMPushService` sends to registered devices

### PWA (Web):
1. Uses Web Push API with VAPID keys
2. Subscriptions stored in `push_subscriptions` table
3. `WebPushService` handles delivery

### No Conflicts:
- Native push uses FCM (separate system)
- PWA uses Web Push (browser-based)
- Both can be enabled simultaneously
- User gets notification via whichever platform they're using

## Testing Push Notifications

### Test from PHP:
```php
use Nexus\Services\FCMPushService;

// Send to specific user
FCMPushService::sendToUser(
    userId: 123,
    title: 'Test Notification',
    body: 'This is a test message',
    data: ['url' => '/dashboard']
);
```

### Test from Firebase Console:
1. Go to Firebase Console > Engage > Messaging
2. Click "Create your first campaign"
3. Select "Firebase Notification messages"
4. Enter title and body
5. Target your app
6. Review and publish

## Troubleshooting

### Token not registering:
- Check browser console in app for errors
- Verify `/api/push/register-device` endpoint is accessible
- Check `fcm_device_tokens` table in database

### Notifications not arriving:
- Verify `FCM_SERVER_KEY` is set correctly in `.env`
- Check server logs for FCM errors
- Ensure device has internet connection
- Check if app is in background (foreground shows toast instead)

### Database table missing:
The table is created automatically on first registration. If needed, create manually:

```sql
CREATE TABLE IF NOT EXISTS fcm_device_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    platform VARCHAR(20) DEFAULT 'android',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_token (token),
    INDEX idx_user_tenant (user_id, tenant_id),
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Security Notes

- Never commit `google-services.json` to public repos
- Keep `FCM_SERVER_KEY` secret (server-side only)
- FCM tokens are device-specific and can change
- Clean up invalid tokens automatically (handled by FCMPushService)
