import { expect, test } from '@e2e/fixtures';
test.describe('Settings Modal', () => {
    test('opens over the current page from the fragment', async ({
        page,
        loginAs,
        credentials,
    }) => {
        await loginAs(credentials.user);
        await page.goto('/dashboard');

        await page.evaluate(() => {
            window.location.hash = 'settings/profile';
        });

        await expect(page.getByTestId('settings-modal')).toBeVisible();
        await expect(page.getByTestId('settings-profile-panel')).toBeVisible();

        // Whatever page is behind it, the fragment is what drives the modal.
        await expect(page).toHaveURL(/#settings\/profile$/);
    });

    test('deep link renders the modal on first load', async ({
        page,
        loginAs,
        credentials,
    }) => {
        await loginAs(credentials.user);
        await page.goto('/dashboard#settings/profile');

        await expect(page.getByTestId('settings-modal')).toBeVisible();
        await expect(page.getByTestId('settings-profile-panel')).toBeVisible();
    });

    test('lists the registered sections and marks the active one', async ({
        page,
        loginAs,
        credentials,
    }) => {
        await loginAs(credentials.user);
        await page.goto('/dashboard#settings/profile');

        const profile = page.getByTestId('settings-section-profile');
        await expect(profile).toBeVisible();
        await expect(profile).toHaveAttribute('aria-current', 'page');
    });

    test('opens from the user menu', async ({ page, loginAs, credentials }) => {
        await loginAs(credentials.user);
        await page.goto('/dashboard');

        await page.getByTestId('user-menu-trigger').click();
        await page.getByTestId('open-settings').click();

        await expect(page.getByTestId('settings-modal')).toBeVisible();
        await expect(page.getByTestId('settings-profile-panel')).toBeVisible();
        await expect(page).toHaveURL(/#settings\/profile$/);
    });

    /**
     * Sections other than the one the modal opened with arrive as optional props,
     * so clicking one has to fetch its data before it can render. The skeleton
     * clearing is what proves that partial reload landed.
     */
    test('switching sections from the sidebar loads the panel', async ({
        page,
        loginAs,
        credentials,
    }) => {
        await loginAs(credentials.user);
        await page.goto('/dashboard#settings/profile');
        await expect(page.getByTestId('settings-profile-panel')).toBeVisible();

        const items = page
            .getByTestId('settings-sidebar')
            .locator('[data-testid^="settings-section-"]');

        // Every section past Profile is contributed by another module, so how many
        // there are depends on the checkout.
        test.skip(
            (await items.count()) < 2,
            'Needs a second module-contributed section.',
        );

        const other = items.nth(1);
        const slug = (await other.getAttribute('data-testid'))!.replace(
            'settings-section-',
            '',
        );

        await other.click();

        await expect(page).toHaveURL(new RegExp(`#settings/${slug}$`));
        await expect(other).toHaveAttribute('aria-current', 'page');
        await expect(page.getByTestId('settings-panel-loading')).toHaveCount(0);
        await expect(page.getByTestId('settings-profile-panel')).toHaveCount(0);
        await expect(page.getByTestId('settings-modal')).toBeVisible();
    });

    test('the close button closes the modal', async ({
        page,
        loginAs,
        credentials,
    }) => {
        await loginAs(credentials.user);
        await page.goto('/dashboard#settings/profile');
        await expect(page.getByTestId('settings-modal')).toBeVisible();

        await page.getByTestId('settings-close').click();

        await expect(page.getByTestId('settings-modal')).not.toBeVisible();
        await expect(page).not.toHaveURL(/#settings/);
    });

    /**
     * The fragment was chosen over a route so that the browser's own history
     * works. Opening pushes an entry; closing replaces one, so back lands on the
     * page rather than on a closed-modal step.
     */
    test('back and forward move in and out of the modal', async ({
        page,
        loginAs,
        credentials,
    }) => {
        await loginAs(credentials.user);
        await page.goto('/dashboard');

        await page.getByTestId('user-menu-trigger').click();
        await page.getByTestId('open-settings').click();
        await expect(page.getByTestId('settings-modal')).toBeVisible();

        await page.goBack();
        await expect(page.getByTestId('settings-modal')).not.toBeVisible();
        await expect(page).not.toHaveURL(/#settings/);

        await page.goForward();
        await expect(page.getByTestId('settings-modal')).toBeVisible();
        await expect(page).toHaveURL(/#settings\/profile$/);
    });

    test('closing clears the fragment so it can be reopened', async ({
        page,
        loginAs,
        credentials,
    }) => {
        await loginAs(credentials.user);
        await page.goto('/dashboard#settings/profile');
        await expect(page.getByTestId('settings-modal')).toBeVisible();

        await page.keyboard.press('Escape');

        await expect(page.getByTestId('settings-modal')).not.toBeVisible();
        await expect(page).not.toHaveURL(/#settings/);

        // Reopening has to work, which it cannot if the fragment lingered.
        await page.evaluate(() => {
            window.location.hash = 'settings/profile';
        });
        await expect(page.getByTestId('settings-modal')).toBeVisible();
    });

    test('saves profile changes without leaving the page behind the modal', async ({
        page,
        loginAs,
        credentials,
    }) => {
        await loginAs(credentials.user);
        await page.goto('/dashboard#settings/profile');
        await expect(page.getByTestId('settings-profile-panel')).toBeVisible();

        const newName = `Settings Tester ${Date.now()}`;
        const saved = page.waitForResponse((response) =>
            response.url().includes('/settings/profile/info'),
        );
        await page.locator('input[name="name"]').fill(newName);
        await page.getByTestId('submit-profile-info').click();
        await saved;
        await page.waitForLoadState('networkidle');

        await expect(page.getByTestId('settings-modal')).toBeVisible();
        await expect(page).toHaveURL(/#settings\/profile$/);
        await expect(page.locator('input[name="name"]')).toHaveValue(newName);
    });

    test('the profile route redirects into the settings fragment', async ({
        page,
        loginAs,
        credentials,
    }) => {
        await loginAs(credentials.user);
        await page.goto('/settings/profile');

        await expect(page).toHaveURL(/#settings\/profile$/);
        await expect(page.getByTestId('settings-modal')).toBeVisible();
    });

    test('redirects unauthenticated user to login', async ({ page }) => {
        await page.goto('/settings/profile');

        await expect(page).toHaveURL('/auth/login');
    });
});
