import { expect, test } from '@e2e/fixtures';

/**
 * Sign-in and registration are pages first. They open in a modal only where the
 * site asks for one, so the URL stays something you can type, bookmark or share.
 */
test.describe('Auth modal', () => {
    test('a direct visit renders the full page, not a modal', async ({
        page,
    }) => {
        await page.goto('/auth/login');

        await expect(page.getByTestId('login-form')).toBeVisible();
        await expect(page.getByTestId('login-modal')).toHaveCount(0);
    });

    test('the header sign-in link opens a modal over the page', async ({
        page,
    }) => {
        await page.goto('/');

        await page.getByTestId('header-sign-in').click();

        await expect(page.getByTestId('login-modal')).toBeVisible();
        await expect(page.getByTestId('login-form')).toBeVisible();

        // The address bar still names the canonical page behind the modal.
        await expect(page).toHaveURL(/\/auth\/login$/);
    });

    test('the header get-started link opens the registration modal', async ({
        page,
    }) => {
        await page.goto('/');

        await page.getByTestId('header-get-started').click();

        await expect(page.getByTestId('register-modal')).toBeVisible();
        await expect(page).toHaveURL(/\/auth\/register$/);
    });

    test('back closes the modal and returns to the page behind it', async ({
        page,
    }) => {
        await page.goto('/');
        await page.getByTestId('header-sign-in').click();
        await expect(page.getByTestId('login-modal')).toBeVisible();

        await page.goBack();

        await expect(page.getByTestId('login-modal')).toHaveCount(0);
        await expect(page).not.toHaveURL(/\/auth\/login/);
    });

    /**
     * The sibling link swaps the modal rather than navigating, so somebody who
     * meant to register does not lose the page they started from.
     */
    test('the sign-up link swaps the modal in place', async ({ page }) => {
        await page.goto('/');
        await page.getByTestId('header-sign-in').click();
        await expect(page.getByTestId('login-modal')).toBeVisible();

        await page.getByTestId('sign-up-link').click();

        await expect(page.getByTestId('register-modal')).toBeVisible();
        await expect(page).toHaveURL(/\/auth\/register$/);
    });

    test('signing in from the modal lands on the dashboard', async ({
        page,
        credentials,
    }) => {
        await page.goto('/');
        await page.getByTestId('header-sign-in').click();
        await expect(page.getByTestId('login-modal')).toBeVisible();

        await page.getByTestId('email').fill(credentials.user.email);
        await page.getByTestId('password').fill(credentials.user.password);
        await page.getByTestId('login-button').click();

        await expect(page.getByTestId('login-modal')).toHaveCount(0);
        await expect(page).not.toHaveURL(/\/auth\/login/);
    });
});
