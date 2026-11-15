<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\IdentityAccess\Jobs\SendAccountEmail;
use App\Modules\IdentityAccess\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountDeliveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertSuccessful();
        RefreshDatabaseState::$migrated = false;
        Notification::fake();
    }

    protected function tearDown(): void
    {
        try {
            $this->artisan('migrate:fresh')->assertSuccessful();
        } finally {
            RefreshDatabaseState::$migrated = false;
            parent::tearDown();
        }
    }

    public function test_verification_and_reset_wait_for_commit_and_rollback_sends_nothing(): void
    {
        $user = User::factory()->unverified()->create();
        DB::beginTransaction();
        $user->sendEmailVerificationNotification();
        $user->sendPasswordResetNotification(Password::createToken($user));
        Notification::assertNothingSent();
        DB::rollBack();
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);

        DB::beginTransaction();
        $user->sendEmailVerificationNotification();
        $token = Password::createToken($user);
        $user->sendPasswordResetNotification($token);
        Notification::assertNothingSent();
        DB::commit();
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertSentTo($user, ResetPassword::class, static fn ($notification) => $notification->token === $token);
        Notification::assertCount(2);
    }

    public function test_stale_reset_tokens_and_changed_or_inactive_recipients_are_not_notified(): void
    {
        $user = User::factory()->unverified()->create();
        $hash = hash('sha256', $user->email);
        $token = Password::createToken($user);
        $staleJob = new SendAccountEmail($user->id, 'reset', $hash, $token);
        Password::createToken($user);
        $staleJob->handle(app(TenantContext::class));
        $user->update(['email' => 'changed@example.invalid']);
        (new SendAccountEmail($user->id, 'verify', $hash))->handle(app(TenantContext::class));
        $user->forceFill(['status' => 'suspended'])->save();
        (new SendAccountEmail($user->id, 'verify', hash('sha256', $user->email)))->handle(app(TenantContext::class));
        Notification::assertNothingSent();
    }

    public function test_account_reset_command_is_encrypted_in_redis_and_log_transport_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $token = Password::createToken($user);
        $job = new SendAccountEmail($user->id, 'reset', hash('sha256', $user->email), $token);
        $queue = 'account-test-'.Str::uuid();
        try {
            Queue::connection('redis')->push($job, '', $queue);
            $payload = json_decode(Redis::lindex('queues:'.$queue, 0), true, flags: JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString($token, $payload['data']['command']);
            $this->assertStringNotContainsString('SendAccountEmail', $payload['data']['command']);
        } finally {
            Queue::connection('redis')->clear($queue);
        }
        config(['mail.default' => 'log']);
        try {
            $job->handle(app(TenantContext::class));
            $this->fail('Authentication links must not be written through log mail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Transporte', $exception->getMessage());
            $this->assertStringNotContainsString($token, $exception->getMessage());
        }
        Notification::assertNothingSent();
    }

    public function test_account_provider_failure_retains_no_sensitive_exception(): void
    {
        $user = User::factory()->unverified()->create();
        Notification::shouldReceive('sendNow')->once()->andThrow(new \RuntimeException('synthetic-provider-secret'));
        try {
            (new SendAccountEmail($user->id, 'verify', hash('sha256', $user->email)))->handle(app(TenantContext::class));
            $this->fail('Provider failure must remain actionable.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('No se pudo entregar', $exception->getMessage());
            $this->assertStringNotContainsString('synthetic-provider-secret', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }
}
