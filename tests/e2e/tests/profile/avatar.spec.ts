import { expect, test } from '@e2e/fixtures';
import type { Page } from '@playwright/test';

const tinyPng = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
    'base64',
);

async function openProfileSettings(page: Page) {
    await page.goto('/dashboard#settings/profile');
    await expect(page.getByTestId('settings-profile-panel')).toBeVisible();
}

async function uploadAvatar(page: Page) {
    await page.setInputFiles('input[type="file"]', {
        name: 'avatar.png',
        mimeType: 'image/png',
        buffer: tinyPng,
    });
}

test.describe('Profile Avatar', () => {
    test('redirects unauthenticated user to login', async ({ page }) => {
        await page.goto('/settings/profile');

        await expect(page).toHaveURL('/auth/login');
    });

    test('spinner clears after avatar upload', async ({ page, loginAs, credentials }) => {
        await loginAs(credentials.user);
        await openProfileSettings(page);

        await uploadAvatar(page);

        const loadingOverlay = page.getByTestId('avatar-busy');

        // Spinner must appear while the upload request is in flight
        await expect(loadingOverlay).toBeVisible();

        // Spinner must clear after onFinish runs (regression: was stuck before the fix)
        await expect(loadingOverlay).not.toBeVisible({ timeout: 10000 });
    });

    test('delete avatar dialog opens and closes', async ({ page, loginAs, credentials }) => {
        await loginAs(credentials.user);
        await openProfileSettings(page);

        // Upload an avatar so the trash button becomes visible
        await uploadAvatar(page);

        // Wait for upload to complete
        const loadingOverlay = page.getByTestId('avatar-busy');
        await expect(loadingOverlay).toBeVisible();
        await expect(loadingOverlay).not.toBeVisible({ timeout: 10000 });

        // Click the trash button (only visible when hasUploadedAvatar is true)
        await page.getByTestId('remove-avatar').click();

        // Confirm delete dialog is visible. By test id, not by role: the settings
        // modal is a dialog too, so the role alone is ambiguous now.
        const dialog = page.getByTestId('confirm-dialog');
        await expect(dialog).toBeVisible();

        // Click Cancel and verify the dialog closes
        await page.getByTestId('confirm-dialog-cancel').click();
        await expect(dialog).not.toBeVisible();

        // The modal must survive the dialog it opened.
        await expect(page.getByTestId('settings-modal')).toBeVisible();
    });
});
