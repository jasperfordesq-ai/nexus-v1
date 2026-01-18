# Project NEXUS Deployment Guide

## Server Details

- **Host:** jasper@35.205.239.67
- **Path:** /var/www/vhosts/project-nexus.ie
- **SSH Key:** ~/.ssh/id_ed25519
- **Hosting:** Plesk on Linux (GCP)

## Live URLs

- **Main Site:** https://project-nexus.ie
- **Alt Domain:** https://hour-timebank.ie

## Quick Deploy Commands

### Deploy a single file
```bash
scp -i ~/.ssh/id_ed25519 "local/path/file.php" jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/path/file.php
```

### Common deploy paths
| Local | Remote |
|-------|--------|
| `httpdocs/` | `/var/www/vhosts/project-nexus.ie/httpdocs/` |
| `views/` | `/var/www/vhosts/project-nexus.ie/views/` |
| `src/` | `/var/www/vhosts/project-nexus.ie/src/` |
| `config/` | `/var/www/vhosts/project-nexus.ie/config/` |

### SSH into server
```bash
ssh -i ~/.ssh/id_ed25519 jasper@35.205.239.67
```

### Check server error logs
```bash
ssh -i ~/.ssh/id_ed25519 jasper@35.205.239.67 "tail -50 /var/www/vhosts/project-nexus.ie/logs/error.log"
```

## Git Workflow

### Commit and push
```bash
cd c:/xampp/htdocs/staging
git add -A
git commit -m "Your message

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>"
git push
```

### Check for local changes
```bash
git status --porcelain
```

## Standard Workflow

When user says "commit then push then check local files for changes and deploy":

1. `git status --porcelain` - see what's changed
2. `git add -A && git commit -m "message" && git push` - commit and push
3. Deploy each changed file via SCP

## Android App (Capacitor)

### Build APK
```bash
cd c:/xampp/htdocs/staging/capacitor
npx cap sync android
cd android && .\gradlew.bat assembleDebug && cd ..
node scripts/copy-apk.js
```

APK output: `httpdocs/downloads/nexus-latest.apk`

### Deploy APK
```bash
scp -i ~/.ssh/id_ed25519 "c:/xampp/htdocs/staging/capacitor/httpdocs/downloads/nexus-latest.apk" jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/httpdocs/downloads/
```

### Key Android files
- `capacitor/capacitor.config.ts` - App configuration
- `capacitor/android/app/src/main/java/com/nexus/timebank/MainActivity.java` - WebView setup with cookie persistence

## Database

- **Type:** MySQL
- **Config:** `.env` file (not in git)
- Access via Plesk or SSH tunnel

## Email

- **Provider:** Gmail API (USE_GMAIL_API=true in .env)
- **Config:** GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET, GMAIL_REFRESH_TOKEN in .env
- **Mailer class:** `src/Core/Mailer.php`

## Notes

- Local server: XAMPP at c:\xampp\htdocs\staging
- Always deploy via SCP (not git pull on server)
- Check `git status` before deploying to catch all changes
- The `.env` file is NOT deployed - it exists separately on the server
