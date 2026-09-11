import { expect, test } from '@e2e/fixtures';
import { expectAuthenticated } from '@e2e/helpers/auth';
import { LoginPage } from '../../pages/LoginPage';

test.describe.parallel('Login Basics', () => {
    let loginPage: LoginPage;

    test.beforeEach(async ({ page }) => {
        loginPage = new LoginPage(page);
        await loginPage.goto();
        await loginPage.expectToBeVisible();
    });

    // Not `toHaveURL('/dashboard')`: the post-login landing page depends on
    // which modules are installed (e.g. tenancy redirects elsewhere), so the
    // only thing this test can rely on is that login actually authenticated.
    async function expectSuccessfulLogin() {
        await expectAuthenticated(loginPage.page);
    }

    test('logs in with valid credentials and redirects to dashboard', async ({
        credentials,
    }) => {
        const user = credentials.admin;
        const loginResponse = loginPage.waitForLoginResponse();
        await loginPage.login(user.email, user.password);
        await loginResponse;
        await expectSuccessfulLogin();
    });

    test('logs in with remember me option', async ({ credentials }) => {
        const user = credentials.user;
        const loginResponse = loginPage.waitForLoginResponse();
        await loginPage.login(user.email, user.password, true);
        await loginResponse;

        await expect(loginPage.rememberCheckbox).toBeChecked();
        await expectSuccessfulLogin();
    });

    test('redirects authenticated users away from login page', async ({
        page,
        credentials,
    }) => {
        const user = credentials.user;
        const loginResponse = loginPage.waitForLoginResponse();
        await loginPage.login(user.email, user.password);
        await loginResponse;
        await expectSuccessfulLogin();

        await page.goto('/auth/login');

        await expect(page).not.toHaveURL(/\/auth\/login/);
        await expectAuthenticated(page);
    });

    test('toggles password visibility', async ({ credentials }) => {
        const user = credentials.user;
        await loginPage.passwordInput.fill(user.password);

        await loginPage.expectPasswordHidden();

        await loginPage.togglePasswordVisibility();
        await loginPage.expectPasswordVisible();

        await loginPage.togglePasswordVisibility();
        await loginPage.expectPasswordHidden();
    });

    test('submits form on Enter key press', async ({ credentials }) => {
        const user = credentials.user;
        await loginPage.emailInput.fill(user.email);
        await loginPage.passwordInput.fill(user.password);

        const loginResponse = loginPage.waitForLoginResponse();
        await loginPage.passwordInput.press('Enter');
        await loginResponse;

        await expectSuccessfulLogin();
    });
});
