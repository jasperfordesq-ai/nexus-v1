# Messaging & Email Notification Deployment Checklist

This checklist covers deploying the messaging and email notification fixes to Azure production.

## Summary of Changes

### Bug Fixes
1. **MessageService::send() now triggers email notifications** - V2 API messages (React/mobile) now send emails
2. **Added sender_id to message response** - Fixes frontend compatibility for message ownership detection
3. **Added curl timeouts to Mailer** - Prevents email sending from blocking HTTP responses (10s send, 5s connect)
4. **Made Message::sendEmailNotification public** - Allows MessageService to call it

### Files Modified
- `src/Services/MessageService.php` - Added email notification call and sender_id to response
- `src/Models/Message.php` - Made sendEmailNotification public
- `src/Core/Mailer.php` - Added CURLOPT_TIMEOUT and CURLOPT_CONNECTTIMEOUT

### New Files
- `tests/Controllers/Api/MessagesApiControllerTest.php` - Tests for message API response shape

---

## Pre-Deployment Checks

### 1. Environment Variables
Ensure these are set in `/opt/nexus-php/.env`:

```bash
# Gmail API (if using Gmail)
USE_GMAIL_API=true
GMAIL_CLIENT_ID=your_client_id
GMAIL_CLIENT_SECRET=your_client_secret
GMAIL_REFRESH_TOKEN=your_refresh_token
GMAIL_SENDER_EMAIL=noreply@project-nexus.ie
GMAIL_SENDER_NAME="Project NEXUS"

# OR SMTP (if using SMTP)
USE_GMAIL_API=false
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=your_smtp_user
SMTP_PASS=your_smtp_password
SMTP_ENCRYPTION=tls
SMTP_FROM_EMAIL=noreply@project-nexus.ie
SMTP_FROM_NAME="Project NEXUS"
```

### 2. Test Gmail API Connection (if using Gmail)
SSH to Azure and run:
```bash
cd /opt/nexus-php
sudo docker exec nexus-php-app php -r "
require 'vendor/autoload.php';
\$result = \\Nexus\\Core\\Mailer::testGmailConnection();
print_r(\$result);
"
```

Expected output: `[success] => 1, [message] => Connected to Gmail API successfully...`

### 3. DNS Configuration (if new email sender domain)
Ensure SPF, DKIM, and DMARC records are configured for your sender domain:

**SPF Record (TXT):**
```
v=spf1 include:_spf.google.com ~all
```

**DKIM:** Configure via Google Workspace or your email provider

**DMARC Record (TXT) at _dmarc.your-domain.ie:**
```
v=DMARC1; p=none; rua=mailto:dmarc-reports@your-domain.ie
```

---

## Deployment Steps

### 1. Deploy to Azure
```bash
# From local Windows machine
scripts\deploy-production.bat

# Or for quick code sync (no dependency rebuild)
scripts\deploy-production.bat quick
```

### 2. Clear OPcache
**CRITICAL**: After deployment, restart the container to clear OPcache:
```bash
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253 "cd /opt/nexus-php && sudo docker compose restart app"
```

### 3. Verify Container Health
```bash
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253 "sudo docker ps --format 'table {{.Names}}\t{{.Status}}' | grep nexus-php"
```

Expected: `nexus-php-app   Up X minutes (healthy)`

### 4. Test Email Sending
1. Log into React frontend (app.project-nexus.ie)
2. Send a message to another user
3. Check if email arrives

**Debug if email doesn't arrive:**
```bash
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253 "sudo docker compose -f /opt/nexus-php/compose.yml logs -f app 2>&1 | grep -i 'email\|gmail\|mailer'"
```

### 5. Verify API Response Shape
Test the API response from a test message:
```bash
curl -X POST https://api.project-nexus.ie/api/v2/messages \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"recipient_id": 123, "body": "Test message"}'
```

Expected response should include:
- `data.id` (int)
- `data.body` (string)
- `data.sender_id` (int)
- `data.is_own` (true)
- `data.created_at` (string)

---

## Post-Deployment Verification

### 1. Check Notification Queue
```bash
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253 "sudo docker exec nexus-mysql-db mysql -unexus -p'nexus_secret' nexus -e 'SELECT id, user_id, activity_type, status, created_at FROM notification_queue ORDER BY id DESC LIMIT 5;'"
```

### 2. Monitor Logs for Errors
```bash
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253 "sudo docker compose -f /opt/nexus-php/compose.yml logs --tail=100 app 2>&1 | grep -iE 'error|exception|failed'"
```

### 3. Run PHPUnit Tests (Optional)
```bash
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253 "cd /opt/nexus-php && sudo docker exec nexus-php-app vendor/bin/phpunit tests/Controllers/Api/MessagesApiControllerTest.php"
```

---

## Rollback Procedure

If issues occur, revert to previous commit:

```bash
# SSH to Azure
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# Navigate and revert
cd /opt/nexus-php
sudo git log --oneline -5  # Find previous commit hash
sudo git checkout PREVIOUS_COMMIT_HASH -- src/Services/MessageService.php src/Models/Message.php src/Core/Mailer.php

# Restart container
sudo docker compose restart app
```

---

## Troubleshooting

### Email Not Sending
1. Check Gmail API credentials in .env
2. Verify OAuth token hasn't expired
3. Check curl can reach Gmail API: `curl -v https://gmail.googleapis.com`
4. Review error logs for specific failure messages

### "Failed to Send" on Mobile
1. Check browser console for network errors
2. Verify CSRF token is being sent (X-CSRF-Token header)
3. Check rate limiting (X-RateLimit-Remaining header)
4. Ensure response includes `sender_id` field

### Slow Message Sending
1. Email timeouts are now 10s max - should not block
2. Check Pusher connection for real-time delivery
3. Monitor curl connection times in logs

---

## Performance Notes

- Email sending now has 10s timeout (prevents blocking)
- OAuth token refresh has 5s connection timeout
- Email notification errors are logged but don't fail message creation
- In-app notifications still delivered even if email fails
