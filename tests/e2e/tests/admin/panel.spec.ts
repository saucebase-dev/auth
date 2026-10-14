import { expect, test } from '@e2e/fixtures';

test.describe('Admin panel', () => {
    test('an administrator opens the panel', async ({
        page,
        credentials,
        loginAs,
    }) => {
        await loginAs(credentials.admin);
        const response = await page.goto('/admin');

        expect(
            response?.ok(),
            'Expected a successful status code',
        ).toBeTruthy();

        // The panel answers 200 and draws nothing when Livewire's script does not load:
        // it is served by a route, which a web server's static file rule can turn into a 404.
        await expect
            .poll(() => page.evaluate(() => 'Livewire' in globalThis))
            .toBe(true);
        await expect(page.locator('.fi-sidebar')).toBeVisible();
    });
});
