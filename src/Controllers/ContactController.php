<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Core\Mailer;
use Nexus\Core\Database;

class ContactController
{
    /**
     * Handle contact form submission
     * Supports both /contact/submit and /contact/send
     */
    public function submit()
    {
        // Verify CSRF token
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid request. Please try again.';
            $this->redirect('/contact');
            return;
        }

        // Get and sanitize form data
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? 'General Inquiry');
        $message = trim($_POST['message'] ?? '');

        // Validate required fields
        $errors = [];
        if (empty($name)) {
            $errors[] = 'Name is required.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (empty($message)) {
            $errors[] = 'Message is required.';
        }

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            $_SESSION['contact_form_data'] = [
                'name' => $name,
                'email' => $email,
                'subject' => $subject,
                'message' => $message
            ];
            $this->redirect('/contact');
            return;
        }

        // Get tenant info for the email
        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Project NEXUS';
        $tenantEmail = $tenant['contact_email'] ?? $tenant['email'] ?? '';

        // Build email content
        $emailSubject = "[{$tenantName}] Contact Form: {$subject}";
        $emailBody = $this->buildEmailBody($name, $email, $subject, $message, $tenantName);

        // Send email with Reply-To set to the sender's email
        $sent = false;
        if (!empty($tenantEmail)) {
            try {
                $mailer = new Mailer();
                $replyTo = "{$name} <{$email}>";
                $sent = $mailer->send($tenantEmail, $emailSubject, $emailBody, null, $replyTo);
            } catch (\Exception $e) {
                error_log("Contact form email error: " . $e->getMessage());
            }
        }

        // Log the contact submission regardless of email success
        $this->logContactSubmission($name, $email, $subject, $message, $sent);

        // Set success message and redirect
        $_SESSION['flash_success'] = 'Thank you for your message! We\'ll get back to you soon.';
        unset($_SESSION['contact_form_data']);

        $this->redirect('/contact');
    }

    /**
     * Build HTML email body
     */
    private function buildEmailBody($name, $email, $subject, $message, $tenantName)
    {
        $escapedName = htmlspecialchars($name);
        $escapedEmail = htmlspecialchars($email);
        $escapedSubject = htmlspecialchars($subject);
        $escapedMessage = nl2br(htmlspecialchars($message));
        $timestamp = date('F j, Y \a\t g:i A');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #059669; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #374151; }
        .value { margin-top: 5px; }
        .message-box { background: white; padding: 15px; border-radius: 8px; border: 1px solid #d1d5db; }
        .footer { font-size: 12px; color: #6b7280; padding: 15px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="margin: 0;">New Contact Form Submission</h2>
            <p style="margin: 5px 0 0 0; opacity: 0.9;">{$tenantName}</p>
        </div>
        <div class="content">
            <div class="field">
                <div class="label">From:</div>
                <div class="value">{$escapedName} &lt;{$escapedEmail}&gt;</div>
            </div>
            <div class="field">
                <div class="label">Subject:</div>
                <div class="value">{$escapedSubject}</div>
            </div>
            <div class="field">
                <div class="label">Message:</div>
                <div class="message-box">{$escapedMessage}</div>
            </div>
        </div>
        <div class="footer">
            Submitted on {$timestamp}
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Log contact submission to database
     */
    private function logContactSubmission($name, $email, $subject, $message, $emailSent)
    {
        try {
            $tenantId = TenantContext::getId();

            // Check if table exists, create if not
            $tableExists = Database::query("SHOW TABLES LIKE 'contact_submissions'")->rowCount() > 0;

            if (!$tableExists) {
                Database::query("
                    CREATE TABLE contact_submissions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        tenant_id INT NOT NULL,
                        name VARCHAR(255) NOT NULL,
                        email VARCHAR(255) NOT NULL,
                        subject VARCHAR(255),
                        message TEXT NOT NULL,
                        email_sent TINYINT(1) DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_tenant (tenant_id),
                        INDEX idx_created (created_at)
                    )
                ");
            }

            Database::query(
                "INSERT INTO contact_submissions (tenant_id, name, email, subject, message, email_sent)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$tenantId, $name, $email, $subject, $message, $emailSent ? 1 : 0]
            );
        } catch (\Exception $e) {
            error_log("Failed to log contact submission: " . $e->getMessage());
        }
    }

    /**
     * Redirect helper
     */
    private function redirect($path)
    {
        $basePath = TenantContext::getBasePath();
        header("Location: {$basePath}{$path}");
        exit;
    }
}
