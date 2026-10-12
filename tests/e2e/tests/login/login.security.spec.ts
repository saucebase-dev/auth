import { expect, test } from '@e2e/fixtures';
import { LoginPage } from '../../pages/LoginPage';

test.describe('Login Security', () => {
    let loginPage: LoginPage;

    test.beforeEach(async ({ page }) => {
        loginPage = new LoginPage(page);
        await loginPage.goto();
    });

    test.describe('CSRF Protection', () => {
        test('rejects submission with invalid CSRF token', async ({
            credentials,
        }) => {
            const user = credentials.user;

            await loginPage.mockServerResponse(419, {
                message: 'CSRF token mismatch',
                errors: { _token: ['The CSRF token is invalid'] },
            });
            const responsePromise = loginPage.waitForLoginResponse();

            await loginPage.login(user.email, user.password);

            await expect(loginPage.page).toHaveURL(loginPage.loginEndpoint);

            const response = await responsePromise;
            expect(response.status()).toBe(419);
        });
    });
});
