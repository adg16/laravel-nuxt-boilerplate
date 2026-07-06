<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    private function dispatch(?string $acceptLanguage): void
    {
        $request = Request::create('/api/user', 'GET');

        if ($acceptLanguage !== null) {
            $request->headers->set('Accept-Language', $acceptLanguage);
        }

        (new SetLocale)->handle($request, fn () => new Response);
    }

    public function test_it_sets_the_app_locale_from_a_supported_accept_language(): void
    {
        config(['app.supported_locales' => ['en', 'es']]);

        $this->dispatch('es');

        $this->assertSame('es', App::getLocale());
    }

    public function test_it_falls_back_to_the_first_supported_locale_when_unsupported(): void
    {
        config(['app.supported_locales' => ['en', 'es']]);

        $this->dispatch('de');

        $this->assertSame('en', App::getLocale());
    }

    public function test_it_falls_back_when_no_accept_language_is_sent(): void
    {
        config(['app.supported_locales' => ['en', 'es']]);

        $this->dispatch(null);

        $this->assertSame('en', App::getLocale());
    }
}
