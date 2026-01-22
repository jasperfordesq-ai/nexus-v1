/**
 * Auth Register JavaScript
 * CivicOne Theme
 */

        color: white;
        border-radius: 8px;
        margin-top: 10px;
    }

    .auth-login-link {
        margin-top: 25px;
        text-align: center;
        font-size: 0.95rem;
        color: var(--htb-text-muted, #6b7280);
    }

    .auth-login-link a {
        color: #0ea5e9;
        font-weight: 600;
        text-decoration: none;
    }

    /* Desktop spacing for no-hero layout */
    @media (min-width: 601px) {
        .auth-wrapper {
            padding-top: 140px;
        }
    }

    /* Mobile Responsiveness */
    @media (max-width: 600px) {
        .auth-wrapper {
            padding-top: 120px;
            padding-left: 10px;
            padding-right: 10px;
        }

        .auth-card-body {
            padding: 25px !important;
        }

        .form-row {
            gap: 0;
        }

        .form-group {
            margin-bottom: 15px;
        }
    }

    /* ========================================
       DARK MODE FOR REGISTRATION
       ======================================== */

    [data-theme="dark"] .auth-card {
        background: rgba(30, 41, 59, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
    }

    [data-theme="dark"] .form-label {
        color: #e2e8f0;
    }

    [data-theme="dark"] .form-input {
        background: rgba(15, 23, 42, 0.6);
        border-color: rgba(255, 255, 255, 0.15);
        color: #f1f5f9;
    }

    [data-theme="dark"] .form-input::placeholder {
        color: #64748b;
    }

    [data-theme="dark"] .form-input:focus {
        border-color: #6366f1 !important;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }

    [data-theme="dark"] .form-note {
        color: #94a3b8;
    }

    /* Password Rules */
    [data-theme="dark"] .password-rules {
        background: rgba(15, 23, 42, 0.6);
        color: #94a3b8;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    [data-theme="dark"] .rules-title {
        color: #e2e8f0;
    }

    /* GDPR Consent Label */
    [data-theme="dark"] label[style*="color: #374151"] {
        color: #e2e8f0 !important;
    }

    /* Data Protection Notice Box */
    [data-theme="dark"] div[style*="background: #f9fafb"][style*="border-radius: 8px"] {
        background: rgba(15, 23, 42, 0.6) !important;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    [data-theme="dark"] div[style*="background: #f9fafb"] p[style*="color: #374151"] {
        color: #e2e8f0 !important;
    }

    [data-theme="dark"] div[style*="background: #f9fafb"] p,
    [data-theme="dark"] div[style*="background: #f9fafb"] li {
        color: #94a3b8 !important;
    }

