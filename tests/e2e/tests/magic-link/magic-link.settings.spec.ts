import { expect, test } from '@e2e/fixtures';
import { LoginPage } from '../../pages/LoginPage';

/**
 * Magic links ship switched off, so the disabled cases need no setup — they are
 * the state a fresh install is in.
 */
test.describe('Magic Link Settings', () => {
    test('magic link page returns 404 while the feature is off', async ({
        page,
    }) => {
        const response = await page.goto('/auth/magic-link');

        expect(response?.status()).toBe(404);
    });

    test('magic link is hidden on the login page while the feature is off', async ({
        page,
    }) => {
        const loginPage = new LoginPage(page);
        await loginPage.goto();
        await loginPage.expectToBeVisible();

        await expect(
            page.getByTestId('magic-link-login-link'),
        ).not.toBeVisible();
    });

    test('switching the feature on offers magic link from the login page', async ({
        page,
        laravel,
    }) => {
        await laravel.query('UPDATE settings SET payload = ? WHERE name = ?', [
            'true',
            'magic_link_enabled',
        ]);

        try {
            const loginPage = new LoginPage(page);
            await loginPage.goto();
            await loginPage.expectToBeVisible();

            await expect(
                page.getByTestId('magic-link-login-link'),
            ).toBeVisible();

            const response = await page.goto('/auth/magic-link');

            expect(response?.status()).toBe(200);
            await expect(page.getByTestId('magic-link-form')).toBeVisible();
        } finally {
            await laravel.query(
                'UPDATE settings SET payload = ? WHERE name = ?',
                ['false', 'magic_link_enabled'],
            );
        }
    });
});
