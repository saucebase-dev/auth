import { test as base } from '@saucebase/laravel-playwright';
import { expect } from '@playwright/test';

export type UserCredential = { email: string; password: string };

export type TestCredentials = {
    admin: UserCredential;
    user: UserCredential;
    subscriber: UserCredential;
    cancelled: UserCredential;
};

export const test = base.extend<{ credentials: TestCredentials }>({
    credentials: async ({ laravel }, use) => {
        const creds = await laravel.callFunction<TestCredentials>(
            'Tests\\Support\\TestFixtures::credentials',
        );
        await use(creds);
    },
});

export { expect };
