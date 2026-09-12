<?php

namespace Modules\Auth\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Modules\Auth\Settings\AuthSettings;

abstract class Controller
{
    /**
     * Render an auth screen as a page, or as a modal over the current one.
     *
     * The URL is the canonical page; a modal is the exception, and only when both
     * the site allows it and the caller explicitly asked. Deciding on the request
     * header rather than the referer matters: the package would otherwise treat
     * any ordinary in-app link as "open me over the previous page".
     *
     * @param  array<string, mixed>  $props
     */
    protected function pageOrModal(string $component, array $props = []): Response|Modal
    {
        $modalEnabled = app(AuthSettings::class)->modal_enabled;

        if (! $modalEnabled || ! request()->hasHeader(Modal::HEADER_MODAL)) {
            return Inertia::render($component, $props);
        }

        return Inertia::modal($component, [...$props, 'modal' => true]);
    }
}
