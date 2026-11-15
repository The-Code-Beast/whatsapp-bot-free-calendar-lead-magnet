<?php

namespace Tests\Feature;

use Tests\TestCase;

class SetLocaleMiddlewareTest extends TestCase
{
    public function test_spanish_cookie_sets_the_application_locale(): void
    {
        $response = $this->withUnencryptedCookie('bookline_language', 'es')->get('/');

        $response->assertOk();
        $this->assertSame('es', app()->getLocale());
    }

    public function test_unsupported_cookie_falls_back_to_english(): void
    {
        $response = $this->withUnencryptedCookie('bookline_language', 'fr')->get('/');

        $response->assertOk();
        $this->assertSame('en', app()->getLocale());
    }

    public function test_validation_messages_use_the_selected_spanish_locale(): void
    {
        $response = $this
            ->withUnencryptedCookie('bookline_language', 'es')
            ->post(route('register'), []);

        $response->assertSessionHasErrors([
            'name' => 'El campo nombre es obligatorio.',
            'email' => 'El campo correo electrónico es obligatorio.',
            'password' => 'El campo contraseña es obligatorio.',
        ]);
    }
}
