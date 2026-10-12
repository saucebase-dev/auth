import { expect, test } from '@e2e/fixtures';

/**
 * Opening a modal is a server round trip, not a local state flip: the click
 * fetches the auth route before anything can render. Under parallel workers the
 * 5s default is tight, the same budget reasoning as `ROLE_CHANGE_TIMEOUT` in the
 * tenancy specs.
 */
const MODAL_OPEN_TIMEOUT = 15_000;

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

        await expect(page.getByTestId('login-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });
        await expect(page.getByTestId('login-form')).toBeVisible();

        // The address bar still names the canonical page behind the modal.
        await expect(page).toHaveURL(/\/auth\/login$/);
    });

    /**
     * The failed sign-in is answered by redirecting back, and in a modal "back" is
     * the page behind it. A flashed message landed there unseen and the modal
     * closed as if the sign-in had worked.
     */
    test('wrong credentials keep the modal open and say why', async ({
        page,
    }) => {
        await page.goto('/');
        await page.getByTestId('header-sign-in').click();
        const modal = page.getByTestId('login-modal');
        await expect(modal).toBeVisible({ timeout: MODAL_OPEN_TIMEOUT });

        await modal.getByTestId('email').fill('nobody@example.com');
        await modal.getByTestId('password').fill('wrong-password');
        await modal.getByTestId('login-button').click();

        await expect(modal.getByTestId('email-error')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });
        await expect(modal).toBeVisible();
        // Still over the site, not replaced by the full login page.
        await expect(page.getByTestId('header-sign-in')).toBeVisible();
    });

    /**
     * A screen reached by swapping inside the modal must remember the page the
     * modal opened over, or its failed form comes back as a full page.
     */
    test('a failed sign-up reached from sign-in stays in the modal over the page', async ({
        page,
    }) => {
        await page.goto('/');
        await page.getByTestId('header-sign-in').click();
        await expect(page.getByTestId('login-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });
        await page.getByTestId('sign-up-link').click();
        const modal = page.getByTestId('register-modal');
        await expect(modal).toBeVisible({ timeout: MODAL_OPEN_TIMEOUT });

        await modal.getByTestId('name').fill('Short Password');
        await modal.getByTestId('email').fill('short-password@example.com');
        await modal.getByTestId('password').fill('1');
        await modal.getByTestId('terms-checkbox').click();
        await modal.locator('[type=submit]').click();

        await expect(modal.getByTestId('password-error')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });
        await expect(page.getByTestId('header-sign-in')).toBeVisible();
    });

    test('a failed reset request reached from sign-in stays in the modal over the page', async ({
        page,
    }) => {
        await page.goto('/');
        await page.getByTestId('header-sign-in').click();
        await expect(page.getByTestId('login-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });
        await page.getByTestId('forgot-password-link').click();
        const modal = page.getByTestId('forgot-password-modal');
        await expect(modal).toBeVisible({ timeout: MODAL_OPEN_TIMEOUT });

        // The browser's own email check would stop the request before the server.
        await modal
            .locator('form')
            .evaluate((form) => form.setAttribute('novalidate', ''));
        await modal.getByTestId('email').fill('not-an-email');
        await modal.locator('[type=submit]').click();

        await expect(modal.getByTestId('email-error')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });
        await expect(page.getByTestId('header-sign-in')).toBeVisible();
    });

    test('a double click on a sibling link opens it once', async ({ page }) => {
        await page.goto('/');
        await page.getByTestId('header-sign-in').click();
        await expect(page.getByTestId('login-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });

        await page.getByTestId('sign-up-link').dblclick();

        await expect(page.getByTestId('register-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });
        await expect(page.getByTestId('register-modal')).toHaveCount(1);
        await expect(page.getByTestId('login-modal')).toHaveCount(0);
    });

    test('the header get-started link opens the registration modal', async ({
        page,
    }) => {
        await page.goto('/');

        await page.getByTestId('header-get-started').click();

        await expect(page.getByTestId('register-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });
        await expect(page).toHaveURL(/\/auth\/register$/);
    });

    test('back closes the modal and returns to the page behind it', async ({
        page,
    }) => {
        await page.goto('/');
        await page.getByTestId('header-sign-in').click();
        await expect(page.getByTestId('login-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });

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
        await expect(page.getByTestId('login-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });

        await page.getByTestId('sign-up-link').click();

        await expect(page.getByTestId('register-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });
        await expect(page).toHaveURL(/\/auth\/register$/);
        await expect(page.getByTestId('login-modal')).toHaveCount(0);
    });

    /**
     * Swapping has to replace, not stack: each screen is its own route, so a
     * plain modal link to a sibling would pile a new modal on every hop.
     */
    test('hopping between the auth screens leaves one modal, not a pile', async ({
        page,
    }) => {
        await page.goto('/');
        await page.getByTestId('header-sign-in').click();
        await expect(page.getByTestId('login-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });

        for (let hop = 0; hop < 3; hop++) {
            await page.getByTestId('sign-up-link').click();
            await expect(page.getByTestId('register-modal')).toBeVisible({
                timeout: MODAL_OPEN_TIMEOUT,
            });

            await page.getByTestId('login-link').click();
            await expect(page.getByTestId('login-modal')).toBeVisible({
                timeout: MODAL_OPEN_TIMEOUT,
            });
        }

        await expect(page.getByTestId('login-modal')).toHaveCount(1);
        await expect(page.getByTestId('register-modal')).toHaveCount(0);
    });

    test('the forgot-password link swaps the modal in place', async ({
        page,
    }) => {
        await page.goto('/');
        await page.getByTestId('header-sign-in').click();
        await expect(page.getByTestId('login-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });

        await page.getByTestId('forgot-password-link').click();

        await expect(page.getByTestId('forgot-password-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });
        await expect(page).toHaveURL(/\/auth\/forgot-password/);
        await expect(page.getByTestId('login-modal')).toHaveCount(0);
    });

    /** Back to login is the same swap, in the other direction. */
    test('back to login swaps the sign-in modal back in', async ({ page }) => {
        await page.goto('/');
        await page.getByTestId('header-sign-in').click();
        await expect(page.getByTestId('login-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });
        await page.getByTestId('forgot-password-link').click();
        await expect(page.getByTestId('forgot-password-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });

        await page.getByTestId('back-to-login-link').click();

        await expect(page.getByTestId('login-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });
        await expect(page.getByTestId('login-form')).toBeVisible();
        await expect(page.getByTestId('forgot-password-modal')).toHaveCount(0);
    });

    test('signing in from the modal lands on the dashboard', async ({
        page,
        credentials,
    }) => {
        await page.goto('/');
        await page.getByTestId('header-sign-in').click();
        await expect(page.getByTestId('login-modal')).toBeVisible({
            timeout: MODAL_OPEN_TIMEOUT,
        });

        await page.getByTestId('email').fill(credentials.user.email);
        await page.getByTestId('password').fill(credentials.user.password);
        await page.getByTestId('login-button').click();

        // Where signing in lands depends on the checkout: with tenancy installed
        // it hands off to a workspace host, a full browser navigation. Wait for
        // that to settle before asking whether the modal is gone, or the answer
        // is about the page being left rather than the page arrived at.
        await expect(page).not.toHaveURL(/\/auth\/login/, {
            timeout: MODAL_OPEN_TIMEOUT,
        });
        await page.waitForLoadState('networkidle');

        await expect(page.getByTestId('login-modal')).toHaveCount(0);
    });
});
