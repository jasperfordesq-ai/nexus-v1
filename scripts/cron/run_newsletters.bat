@echo off
REM ========================================
REM Recurring Newsletter Processor - Windows
REM ========================================
REM
REM To set up as a Windows Scheduled Task:
REM 1. Open Task Scheduler (taskschd.msc)
REM 2. Create Basic Task
REM 3. Set trigger: Every 15 minutes
REM 4. Action: Start a program
REM 5. Program: C:\path\to\php.exe
REM 6. Arguments: "C:\path\to\scripts\cron\process_recurring_newsletters.php"
REM 7. Start in: C:\path\to\project
REM
REM Or run this batch file directly

cd /d "%~dp0..\.."
php scripts\cron\process_recurring_newsletters.php
