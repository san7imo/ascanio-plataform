<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_registration_validation_errors_are_in_spanish(): void
    {
        $this->post(route('login'), ['email' => 'nobody@example.invalid', 'password' => 'incorrect'])
            ->assertSessionHasErrors(['email' => 'Las credenciales no coinciden con nuestros registros.']);
        $this->post(route('register'), ['name' => 'Prueba', 'email' => 'invalid', 'password' => 'password', 'password_confirmation' => 'different'])
            ->assertSessionHasErrors([
                'email' => 'Escribe un correo electrónico válido.',
                'password' => 'La confirmación de contraseña no coincide.',
            ]);
    }

    public function test_invalid_reset_link_has_a_localized_error(): void
    {
        $user = User::factory()->create();
        $this->post(route('password.update'), [
            'email' => $user->email, 'token' => 'invalid',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors(['email' => 'El enlace de recuperación no es válido o ha vencido.']);
    }

    public function test_standard_authentication_emails_render_spanish_without_changing_the_protocols(): void
    {
        $user = User::factory()->unverified()->create();
        $verification = (new VerifyEmail)->toMail($user);
        $this->assertSame('Verificar correo electrónico', $verification->subject);
        $html = (string) $verification->render();
        $this->assertStringContainsString('Pulsa el botón para verificar', $html);
        $this->assertStringContainsString('Si no puedes pulsar', $html);
        $this->assertStringContainsString('signature=', $verification->actionUrl);

        $reset = (new ResetPassword('synthetic-token'))->toMail($user);
        $this->assertSame('Recuperación de contraseña', $reset->subject);
        $this->assertStringContainsString('Este enlace de recuperación vence en', (string) $reset->render());
        $this->assertStringContainsString('synthetic-token', $reset->actionUrl);
    }

    public function test_public_error_pages_are_spanish_and_do_not_reveal_exception_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/testing/denied', static fn () => abort(403, 'synthetic-private-message'));
        $this->get('/testing/denied')->assertForbidden()->assertSee('Acceso no autorizado')->assertDontSee('synthetic-private-message');
        $this->get('/testing/unavailable')->assertNotFound()->assertSee('Página no disponible');
    }
}
