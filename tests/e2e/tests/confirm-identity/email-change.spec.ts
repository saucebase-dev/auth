import { expect, test } from '@e2e/fixtures';

/**
 * Every security alert goes to the email, so moving it asks for the password
 * first; the name does not.
 */
test.describe('Changing the email', () => {
    test('asks to confirm, refuses a wrong password, then saves', async ({
        page,
        loginAs,
        credentials,
    }) => {
        const email = `moved-${Date.now()}@example.com`;

        await loginAs(credentials.user);
        await page.goto('/dashboard#settings/profile');
        await expect(page.getByTestId('settings-profile-panel')).toBeVisible();

        await page.locator('input[name="email"]').fill(email);
        await page.getByTestId('submit-profile-info').click();

        const dialog = page.getByTestId('confirm-identity-dialog');
        await expect(dialog).toBeVisible();

        await page.getByTestId('confirm-identity-password').fill('not-it');
        await page.getByTestId('confirm-identity-submit').click();
        await expect(page.getByTestId('confirm-identity-error')).toBeVisible();

        const saved = page.waitForResponse((response) =>
            response.url().includes('/settings/profile/info'),
        );
        await page
            .getByTestId('confirm-identity-password')
            .fill(credentials.user.password);
        await page.getByTestId('confirm-identity-submit').click();
        await saved;

        await expect(dialog).toBeHidden();
        await page.reload();
        await expect(page.locator('input[name="email"]')).toHaveValue(email);
    });

    test('cancelling leaves the email as it was', async ({
        page,
        loginAs,
        credentials,
    }) => {
        await loginAs(credentials.user);
        await page.goto('/dashboard#settings/profile');
        await expect(page.getByTestId('settings-profile-panel')).toBeVisible();

        await page.locator('input[name="email"]').fill('elsewhere@example.com');
        await page.getByTestId('submit-profile-info').click();
        await page.getByTestId('confirm-identity-cancel').click();
        await expect(page.getByTestId('confirm-identity-dialog')).toBeHidden();

        await page.reload();
        await expect(page.locator('input[name="email"]')).toHaveValue(
            credentials.user.email,
        );
    });
});
