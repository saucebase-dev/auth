import { expect, test } from '@e2e/fixtures';

test.describe('Security Socialite Settings', () => {
    test.describe.configure({ mode: 'serial' });

    test('keeps a disabled connected provider available for disconnect', async ({
        page,
        laravel,
        credentials,
        loginAs,
    }) => {
        const providerId = `e2e-disabled-google-${Date.now()}`;
        const [socialiteProviderSetting] = await laravel.select(
            'SELECT payload FROM settings WHERE name = :name',
            { name: 'enabled_socialite_providers' },
        );
        const originalPayload = socialiteProviderSetting?.payload;

        if (typeof originalPayload !== 'string') {
            throw new Error('Socialite provider setting was not found.');
        }

        try {
            await laravel.query(
                'UPDATE settings SET payload = ? WHERE name = ?',
                ['[]', 'enabled_socialite_providers'],
            );
            await laravel.query(
                'INSERT INTO social_accounts (user_id, provider, provider_id, last_login_at, created_at, updated_at) SELECT id, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM users WHERE email = ?',
                ['google', providerId, credentials.user.email],
            );

            await loginAs(credentials.user);
            await page.goto('/dashboard#settings/security');
            await expect(
                page.getByTestId('settings-security-panel'),
            ).toBeVisible();

            await expect(
                page.getByTestId('socialite-account-google'),
            ).toBeVisible();
            await expect(
                page.getByTestId('connect-socialite-google'),
            ).not.toBeVisible();

            await page.getByTestId('disconnect-socialite-google').click();
            // Disconnect confirms through the app-level dialog now.
            await page.getByTestId('confirm-dialog-confirm').click();

            await expect(
                page.getByTestId('socialite-account-google'),
            ).not.toBeVisible();
        } finally {
            await laravel.query(
                'DELETE FROM social_accounts WHERE provider = ? AND provider_id = ?',
                ['google', providerId],
            );
            await laravel.query(
                'UPDATE settings SET payload = ? WHERE name = ?',
                [originalPayload, 'enabled_socialite_providers'],
            );
        }
    });
});
