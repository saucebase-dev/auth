<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use InertiaUI\Modal\Modal;
use Modules\Auth\Settings\AuthSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sign-in and registration are pages that can also be shown in a modal.
 *
 * The URL is the canonical thing: typing it, bookmarking it or following an
 * ordinary link renders the page. A modal needs two things to agree — the site
 * has them switched on, and the caller explicitly asked by sending the header
 * the package's `ModalLink` sends. The referer deliberately does not count: the
 * package treats it as "open me over that page", which would turn every in-app
 * link into a modal.
 *
 * `component(..., false)` skips Inertia's page-file check throughout: module
 * components live outside `inertia.pages.paths`, so it never finds them.
 */
class AuthModalTest extends TestCase
{
    use RefreshDatabase;

    private function enableModal(bool $enabled = true): void
    {
        $settings = app(AuthSettings::class);
        $settings->modal_enabled = $enabled;
        $settings->save();
    }

    private function enableMagicLink(bool $enabled = true): void
    {
        $settings = app(AuthSettings::class);
        $settings->magic_link_enabled = $enabled;
        $settings->save();
    }

    private function assertRenderedAsPage(string $route, string $component): void
    {
        $this->get(route($route))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page): AssertableInertia => $page
                    ->component($component, false)
                    ->missing('modal'),
            );
    }

    public function test_login_url_renders_the_page_without_the_modal_header(): void
    {
        $this->enableModal();

        $this->assertRenderedAsPage('login', 'Auth::Login');
    }

    public function test_login_url_renders_the_modal_when_one_is_asked_for(): void
    {
        $this->enableModal();

        $this->withHeader(Modal::HEADER_MODAL, 'test-modal-id')
            ->get(route('login'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('Auth::Login', false)
                    ->where('modal', true),
            );
    }

    /**
     * A referer is what an ordinary in-app link sends. The page must win, or
     * every link to sign-in would become a modal over whatever came before it.
     */
    public function test_a_referer_alone_does_not_turn_the_login_page_into_a_modal(): void
    {
        $this->enableModal();

        $this->withHeader('referer', url('/'))
            ->get(route('login'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('Auth::Login', false)
                    ->missing('modal'),
            );
    }

    public function test_login_ignores_the_modal_header_when_the_setting_is_off(): void
    {
        $this->enableModal(false);

        $this->withHeader(Modal::HEADER_MODAL, 'test-modal-id')
            ->get(route('login'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('Auth::Login', false)
                    ->missing('modal'),
            );
    }

    public function test_register_url_renders_the_page_without_the_modal_header(): void
    {
        $this->enableModal();

        $this->assertRenderedAsPage('register', 'Auth::Register');
    }

    public function test_register_url_renders_the_modal_when_one_is_asked_for(): void
    {
        $this->enableModal();

        $this->withHeader(Modal::HEADER_MODAL, 'test-modal-id')
            ->get(route('register'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('Auth::Register', false)
                    ->where('modal', true),
            );
    }

    public function test_register_ignores_the_modal_header_when_the_setting_is_off(): void
    {
        $this->enableModal(false);

        $this->withHeader(Modal::HEADER_MODAL, 'test-modal-id')
            ->get(route('register'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('Auth::Register', false)
                    ->missing('modal'),
            );
    }

    /**
     * Registration being switched off is a route-level concern, so the modal
     * must not become a way around it.
     */
    public function test_the_register_modal_is_unreachable_when_registration_is_disabled(): void
    {
        $this->enableModal();
        app(AuthSettings::class)->fill(['registration_enabled' => false])->save();

        $this->withHeader(Modal::HEADER_MODAL, 'test-modal-id')
            ->get(route('register'))
            ->assertNotFound();
    }

    /**
     * Forgot-password and magic link follow the same rule as sign-in and
     * registration: the URL is a page, the modal is the exception.
     *
     * @return array<string, array{string, string}>
     */
    public static function siblingScreenProvider(): array
    {
        return [
            'forgot password' => ['password.request', 'Auth::ForgotPassword'],
            'magic link' => ['magic-link.create', 'Auth::MagicLink'],
        ];
    }

    #[DataProvider('siblingScreenProvider')]
    public function test_a_sibling_screen_renders_the_page_without_the_modal_header(
        string $route,
        string $component,
    ): void {
        $this->enableModal();
        $this->enableMagicLink();

        $this->assertRenderedAsPage($route, $component);
    }

    #[DataProvider('siblingScreenProvider')]
    public function test_a_sibling_screen_renders_the_modal_when_one_is_asked_for(
        string $route,
        string $component,
    ): void {
        $this->enableModal();
        $this->enableMagicLink();

        $this->withHeader(Modal::HEADER_MODAL, 'test-modal-id')
            ->get(route($route))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page): AssertableInertia => $page
                    ->component($component, false)
                    ->where('modal', true),
            );
    }

    #[DataProvider('siblingScreenProvider')]
    public function test_a_sibling_screen_ignores_the_modal_header_when_the_setting_is_off(
        string $route,
        string $component,
    ): void {
        $this->enableModal(false);
        $this->enableMagicLink();

        $this->withHeader(Modal::HEADER_MODAL, 'test-modal-id')
            ->get(route($route))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page): AssertableInertia => $page
                    ->component($component, false)
                    ->missing('modal'),
            );
    }

    /**
     * Magic links being switched off is a route-level concern, so the modal must
     * not become a way around it.
     */
    public function test_the_magic_link_modal_is_unreachable_when_magic_links_are_disabled(): void
    {
        $this->enableModal();

        $this->withHeader(Modal::HEADER_MODAL, 'test-modal-id')
            ->get(route('magic-link.create'))
            ->assertNotFound();
    }

    /** The frontend needs the setting to choose between a modal link and a page link. */
    public function test_the_modal_setting_is_shared_with_the_frontend(): void
    {
        $this->enableModal(false);

        $this->get(route('login'))
            ->assertInertia(
                fn (AssertableInertia $page): AssertableInertia => $page
                    ->where('auth.modal_enabled', false),
            );
    }
}
