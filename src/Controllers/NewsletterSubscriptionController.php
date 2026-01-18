<?php

namespace Nexus\Controllers;

use Nexus\Models\NewsletterSubscriber;
use Nexus\Core\View;
use Nexus\Core\Csrf;
use Nexus\Core\TenantContext;
use Nexus\Core\Mailer;
use Nexus\Core\EmailTemplate;
use Nexus\Core\Env;

class NewsletterSubscriptionController
{
    /**
     * Show public subscription form
     */
    public function showForm()
    {
        $tenant = TenantContext::get();

        View::render('newsletter/subscribe', [
            'pageTitle' => 'Subscribe to Newsletter',
            'tenantName' => $tenant['name'] ?? 'Our Newsletter'
        ]);
    }

    /**
     * Handle subscription form submission
     */
    public function subscribe()
    {
        Csrf::verifyOrDie();

        $email = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Please enter a valid email address.';
            header('Location: ' . TenantContext::getBasePath() . '/newsletter/subscribe');
            exit;
        }

        // Check if already subscribed
        $existing = NewsletterSubscriber::findByEmail($email);
        if ($existing && $existing['status'] === 'active') {
            $_SESSION['flash_success'] = 'You are already subscribed to our newsletter!';
            header('Location: ' . TenantContext::getBasePath() . '/newsletter/subscribe');
            exit;
        }

        // Create subscriber (pending confirmation)
        $subscriberId = NewsletterSubscriber::create($email, $firstName, $lastName, 'signup');

        // Get the subscriber to access confirmation token
        $subscriber = NewsletterSubscriber::findByEmail($email);

        // Send confirmation email
        $this->sendConfirmationEmail($subscriber);

        $_SESSION['flash_success'] = 'Please check your email to confirm your subscription.';
        header('Location: ' . TenantContext::getBasePath() . '/newsletter/subscribe');
        exit;
    }

    /**
     * Confirm subscription via token
     */
    public function confirm()
    {
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            $this->renderMessage('Invalid Link', 'The confirmation link is invalid or has expired.', 'error');
            return;
        }

        $subscriber = NewsletterSubscriber::confirm($token);

        if ($subscriber) {
            $this->renderMessage(
                'Subscription Confirmed!',
                'Thank you for subscribing to our newsletter. You will now receive updates from us.',
                'success'
            );
        } else {
            $this->renderMessage(
                'Link Expired',
                'This confirmation link has already been used or has expired.',
                'error'
            );
        }
    }

    /**
     * Show unsubscribe confirmation page
     */
    public function showUnsubscribe()
    {
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            $this->renderMessage('Invalid Link', 'The unsubscribe link is invalid.', 'error');
            return;
        }

        $subscriber = NewsletterSubscriber::findByUnsubscribeToken($token);

        if (!$subscriber) {
            $this->renderMessage('Invalid Link', 'The unsubscribe link is invalid or has expired.', 'error');
            return;
        }

        if ($subscriber['status'] === 'unsubscribed') {
            $this->renderMessage('Already Unsubscribed', 'You have already been unsubscribed from our newsletter.', 'info');
            return;
        }

        View::render('newsletter/unsubscribe', [
            'pageTitle' => 'Unsubscribe',
            'subscriber' => $subscriber,
            'token' => $token
        ]);
    }

    /**
     * Process unsubscribe
     */
    public function unsubscribe()
    {
        $token = $_POST['token'] ?? $_GET['token'] ?? '';
        $reason = trim($_POST['reason'] ?? '');

        if (empty($token)) {
            $this->renderMessage('Invalid Request', 'The unsubscribe request is invalid.', 'error');
            return;
        }

        $subscriber = NewsletterSubscriber::unsubscribe($token, $reason);

        if ($subscriber) {
            $this->renderMessage(
                'Unsubscribed Successfully',
                'You have been removed from our mailing list. We\'re sorry to see you go!',
                'success'
            );
        } else {
            $this->renderMessage(
                'Error',
                'Unable to process your unsubscribe request. The link may be invalid.',
                'error'
            );
        }
    }

    /**
     * One-click unsubscribe (for email clients that support it)
     */
    public function oneClickUnsubscribe()
    {
        $token = $_GET['token'] ?? $_POST['token'] ?? '';

        if (empty($token)) {
            http_response_code(400);
            echo 'Invalid token';
            exit;
        }

        $subscriber = NewsletterSubscriber::unsubscribe($token, 'one-click');

        if ($subscriber) {
            // Return success for email client
            http_response_code(200);
            $this->renderMessage(
                'Unsubscribed',
                'You have been successfully unsubscribed.',
                'success'
            );
        } else {
            http_response_code(400);
            echo 'Invalid or expired token';
        }
        exit;
    }

    /**
     * Send confirmation email
     */
    private function sendConfirmationEmail($subscriber)
    {
        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Project NEXUS';
        $appUrl = Env::get('APP_URL') ?? '';
        $basePath = TenantContext::getBasePath();

        $confirmUrl = rtrim($appUrl, '/') . $basePath . '/newsletter/confirm?token=' . $subscriber['confirmation_token'];

        $body = "
            <p>Thank you for subscribing to the <strong>$tenantName</strong> newsletter!</p>
            <p>Please click the button below to confirm your subscription:</p>
        ";

        $html = EmailTemplate::render(
            'Confirm Your Subscription',
            'One more step to complete your signup',
            $body,
            'Confirm Subscription',
            $confirmUrl,
            $tenantName
        );

        $mailer = new Mailer();
        return $mailer->send($subscriber['email'], "Confirm your subscription to $tenantName", $html);
    }

    /**
     * Render a simple message page
     */
    private function renderMessage($title, $message, $type = 'info')
    {
        $tenant = TenantContext::get();

        View::render('newsletter/message', [
            'pageTitle' => $title,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'tenantName' => $tenant['name'] ?? 'Newsletter'
        ]);
    }
}
