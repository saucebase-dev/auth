import { expect, test } from '@e2e/fixtures';
import type { Page } from '@playwright/test';
import { LoginPage } from '../../pages/LoginPage';

/**
 * A software authenticator through the Chrome DevTools Protocol, so the browser
 * answers WebAuthn prompts itself. Chromium only, which is all the suite runs.
 */
async function addVirtualAuthenticator(page: Page) {
    const client = await page.context().newCDPSession(page);

    await client.send('WebAuthn.enable');
    await client.send('WebAuthn.addVirtualAuthenticator', {
        options: {
            protocol: 'ctap2',
            transport: 'internal',
            hasResidentKey: true,
            hasUserVerification: true,
            isUserVerified: true,
            automaticPresenceSimulation: true,
        },
    });
}

/** Passkeys ship switched off, so the disabled cases need no setup. */
test.describe('Passkeys', () => {
    test.describe.configure({ mode: 'serial' });

    test('the passkey button is hidden while the feature is off', async ({
        page,
    }) => {
        const loginPage = new LoginPage(page);
        await loginPage.goto();
        await loginPage.expectToBeVisible();

        await expect(page.getByTestId('passkey-login-button')).toHaveCount(0);
    });

    test('adds a passkey, signs in with it and removes it', async ({
        page,
        laravel,
        credentials,
        loginAs,
    }) => {
        const name = `E2E passkey ${Date.now()}`;

        await laravel.query('UPDATE settings SET payload = ? WHERE name = ?', [
            'true',
            'passkeys_enabled',
        ]);

        try {
            await addVirtualAuthenticator(page);

            await loginAs(credentials.user);
            await page.goto('/dashboard#settings/security');
            await expect(page.getByTestId('passkeys-section')).toBeVisible();

            await page.getByTestId('passkey-name-input').fill(name);
            await page.getByTestId('add-passkey').click();

            // The ceremony, the store and the panel reload are three round trips.
            await expect(
                page.getByTestId('passkey-name').filter({ hasText: name }),
            ).toBeVisible({ timeout: 15_000 });

            await page.context().clearCookies();

            const loginPage = new LoginPage(page);
            await loginPage.goto();
            await page.getByTestId('passkey-login-button').click();

            await expect(page).not.toHaveURL(/\/auth\/login/, {
                timeout: 15_000,
            });

            await page.goto('/dashboard#settings/security');
            const passkey = page
                .getByTestId('passkey')
                .filter({ hasText: name });
            await passkey.getByTestId('remove-passkey').click();
            await page.getByTestId('confirm-dialog-confirm').click();

            await expect(passkey).toHaveCount(0, { timeout: 15_000 });
        } finally {
            await laravel.query('DELETE FROM passkeys WHERE name = ?', [name]);
            await laravel.query(
                'UPDATE settings SET payload = ? WHERE name = ?',
                ['false', 'passkeys_enabled'],
            );
        }
    });
});
