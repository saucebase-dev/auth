import { expect, test } from '@e2e/fixtures';
import { expectAuthenticated } from '@e2e/helpers/auth';
import { faker } from '@faker-js/faker';
import { RegisterPage } from '../../pages/RegisterPage';

test.describe.parallel('Register Basics', () => {
    let registerPage: RegisterPage;

    test.beforeEach(async ({ page }) => {
        registerPage = new RegisterPage(page);
        await registerPage.goto();
        await registerPage.expectToBeVisible();
    });

    // Not a hardcoded landing URL: where an authenticated-but-unverified user
    // lands is stable (`/auth/verify-email`), but where a verified one lands
    // depends on which modules are installed, so we only assert auth state.
    async function expectSuccessfulRegistration() {
        if (registerPage.userMustVerifyEmail) {
            await expect(registerPage.page).toHaveURL('/auth/verify-email');
            return;
        }

        await expectAuthenticated(registerPage.page);
    }

    function newUser() {
        return {
            name: faker.person.fullName(),
            email: faker.internet.exampleEmail(),
            password: faker.internet.password(),
        };
    }

    test('registers with valid details and redirects to dashboard', async () => {
        const user = newUser(); // Using the newUser function to generate user details

        const responsePromise = registerPage.waitForLoginResponse();
        await registerPage.register(user.name, user.email, user.password);
        await responsePromise;

        await expectSuccessfulRegistration();
    });

    test('toggles password visibility', async () => {
        await registerPage.passwordInput.fill('password123');

        await registerPage.expectPasswordHidden();

        await registerPage.togglePasswordVisibility();
        await registerPage.expectPasswordVisible();

        await registerPage.togglePasswordVisibility();
        await registerPage.expectPasswordHidden();
    });

    test('submits form on Enter key press', async () => {
        const user = newUser();
        await registerPage.nameInput.fill(user.name);
        await registerPage.emailInput.fill(user.email);
        await registerPage.passwordInput.fill(user.password);
        await registerPage.termsCheckbox.check();

        const registerResponse = registerPage.waitForLoginResponse();
        await registerPage.passwordInput.press('Enter');
        await registerResponse;

        await expectSuccessfulRegistration();
    });

    test('disables register button until all fields are filled and terms are accepted', async () => {
        const user = newUser();

        await expect(registerPage.registerButton).toBeDisabled();

        await registerPage.nameInput.fill(user.name);
        await registerPage.emailInput.fill(user.email);
        await registerPage.passwordInput.fill(user.password);
        await expect(registerPage.registerButton).toBeDisabled();

        await registerPage.termsCheckbox.check();
        await expect(registerPage.registerButton).toBeEnabled();
    });
});
