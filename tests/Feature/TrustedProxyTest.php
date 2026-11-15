<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_forwarded_https_requests_generate_secure_redirects(): void
    {
        $response = $this
            ->withHeaders([
                'Host' => 'eel-inspired-satyr.ngrok-free.app',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/dashboard');

        $response->assertRedirect('https://eel-inspired-satyr.ngrok-free.app/login');
    }
}
