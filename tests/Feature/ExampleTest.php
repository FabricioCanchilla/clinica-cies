<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_la_raiz_redirige_al_panel(): void
    {
        $response = $this->get('/');

        // La raíz lleva al panel según el rol (sin sesión, termina en el login)
        $response->assertRedirect(route('panel'));
    }
}
