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

/** The password step, asked before a passkey is added or removed. */
async function confirmIdentity(page: Page, password: string) {
    const dialog = page.getByTestId('confirm-identity-dialog');

    await expect(dialog).toBeVisible();
    await page.getByTestId('confirm-identity-password').fill(password);
    await page.getByTestId('confirm-identity-submit').click();
    await expect(dialog).toBeHidden();
}

/** Passkeys ship switched on; the disabled case switches them off and back. */
test.describe('Passkeys', () => {
    test.describe.configure({ mode: 'serial' });

    test('the passkey button is hidden while the feature is off', async ({
        page,
        laravel,
    }) => {
        await laravel.query('UPDATE settings SET payload = ? WHERE name = ?', [
            'false',
            'passkeys_enabled',
        ]);

        try {
            const loginPage = new LoginPage(page);
            await loginPage.goto();
            await loginPage.expectToBeVisible();

            await expect(page.getByTestId('passkey-login-button')).toHaveCount(
                0,
            );
        } finally {
            await laravel.query(
                'UPDATE settings SET payload = ? WHERE name = ?',
                ['true', 'passkeys_enabled'],
            );
        }
    });

    test('adds a passkey, signs in with it and removes it', async ({
        page,
        laravel,
        credentials,
        loginAs,
    }) => {
        const name = `E2E passkey ${Date.now()}`;

        try {
            await addVirtualAuthenticator(page);

            await loginAs(credentials.user);
            await page.goto('/dashboard#settings/security');
            await expect(page.getByTestId('passkeys-section')).toBeVisible();
            expect(
                await page
                    .getByTestId('passkeys-section')
                    .evaluate((section) =>
                        Boolean(
                            section.compareDocumentPosition(
                                document.querySelector(
                                    '[data-testid="submit-change-password"]',
                                )!,
                            ) & Node.DOCUMENT_POSITION_FOLLOWING,
                        ),
                    ),
            ).toBe(true);

            await page.getByTestId('passkey-name-input').fill(name);
            await page.getByTestId('add-passkey').click();
            await confirmIdentity(page, credentials.user.password);

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
            // A passkey sign-in is a new session: removing asks again.
            await confirmIdentity(page, credentials.user.password);

            await expect(passkey).toHaveCount(0, { timeout: 15_000 });
        } finally {
            await laravel.query('DELETE FROM passkeys WHERE name = ?', [name]);
        }
    });
});
