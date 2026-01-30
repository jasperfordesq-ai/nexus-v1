import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Login Page Object
 */
export class LoginPage extends BasePage {
  readonly emailInput: Locator;
  readonly passwordInput: Locator;
  readonly submitButton: Locator;
  readonly forgotPasswordLink: Locator;
  readonly registerLink: Locator;
  readonly rememberMeCheckbox: Locator;
  readonly socialLoginButtons: Locator;
  readonly errorMessage: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    // Use specific IDs from the login form (login.php uses #login-email, #login-password)
    this.emailInput = page.locator('#login-email, input[name="email"]').first();
    this.passwordInput = page.locator('#login-password, input[name="password"]').first();
    this.submitButton = page.locator('.auth-card button[type="submit"], form button[type="submit"]').first();
    // Link text is "Forgot?" or may contain "forgot" in href
    this.forgotPasswordLink = page.locator('a[href*="password/forgot"], a:has-text("Forgot")').first();
    this.registerLink = page.locator('a[href*="register"], a:has-text("Join Now")').first();
    this.rememberMeCheckbox = page.locator('input[name="remember"], input#remember').first();
    this.socialLoginButtons = page.locator('.social-login button, .oauth-buttons a, .social-login-buttons a');
    this.errorMessage = page.locator('.alert-danger, .error-message, .govuk-error-summary, .login-error, div[style*="background:#fef2f2"]').first();
  }

  /**
   * Navigate to login page
   */
  async navigate(): Promise<void> {
    await this.goto('login');
  }

  /**
   * Fill login form
   */
  async fillLoginForm(email: string, password: string): Promise<void> {
    await this.emailInput.fill(email);
    await this.passwordInput.fill(password);
  }

  /**
   * Submit login form
   */
  async submit(): Promise<void> {
    await this.submitButton.click();
  }

  /**
   * Perform login with credentials
   */
  async login(email: string, password: string): Promise<void> {
    await this.fillLoginForm(email, password);
    await this.submit();
    await this.page.waitForURL(/\/(dashboard|home|feed|\/)$/, { timeout: 10000 });
  }

  /**
   * Attempt login (may fail) - with short timeout for failed attempts
   */
  async attemptLogin(email: string, password: string): Promise<void> {
    await this.fillLoginForm(email, password);
    await this.submitButton.click();
    // Short timeout - either redirects or stays on login with error
    await this.page.waitForLoadState('domcontentloaded', { timeout: 5000 }).catch(() => {});
  }

  /**
   * Check if login failed
   */
  async hasLoginError(): Promise<boolean> {
    return await this.errorMessage.count() > 0;
  }

  /**
   * Get login error message
   */
  async getLoginError(): Promise<string> {
    if (await this.hasLoginError()) {
      return await this.errorMessage.textContent() || '';
    }
    return '';
  }

  /**
   * Click forgot password
   */
  async clickForgotPassword(): Promise<void> {
    await this.forgotPasswordLink.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Click register link
   */
  async clickRegister(): Promise<void> {
    await this.registerLink.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Check remember me
   */
  async checkRememberMe(): Promise<void> {
    await this.rememberMeCheckbox.check();
  }

  /**
   * Check if social login is available
   */
  async hasSocialLogin(): Promise<boolean> {
    return await this.socialLoginButtons.count() > 0;
  }
}
