<?php

declare(strict_types=1);

namespace Azera\Log\Tests;

use Azera\Auth\Event\Attempting;
use Azera\Auth\Event\Authenticated;
use Azera\Auth\Event\Failed;
use Azera\Auth\Event\Lockout;
use Azera\Auth\Event\Login;
use Azera\Auth\Event\Logout;
use Azera\Auth\Event\PasswordResetRequested;
use Azera\Auth\Event\Verified;
use Azera\Log\Listener\AuthLoggerListener;
use PHPUnit\Framework\TestCase;

final class AuthLoggerListenerTest extends TestCase
{
    private RecordingLogger $logger;

    private AuthLoggerListener $listener;

    protected function setUp(): void
    {
        $this->logger   = new RecordingLogger();
        $this->listener = new AuthLoggerListener($this->logger);
    }

    public function test_attempting_logs_debug(): void
    {
        ($this->listener)(new Attempting('web', ['email' => 'ada@example.com']));

        self::assertCount(1, $this->logger->records);
        self::assertSame('debug', $this->logger->records[0][0]);
        self::assertSame('Authentication attempt', $this->logger->records[0][1]);
        self::assertSame('web', $this->logger->records[0][2]['guard']);
        // Credentials are never logged.
        self::assertArrayNotHasKey('credentials', $this->logger->records[0][2]);
        self::assertArrayNotHasKey('email', $this->logger->records[0][2]);
    }

    public function test_authenticated_logs_info_with_user_id(): void
    {
        $user = new class
        {
            public int $id = 42;
        };

        ($this->listener)(new Authenticated('web', $user));

        self::assertSame('info', $this->logger->records[0][0]);
        self::assertSame('User authenticated', $this->logger->records[0][1]);
        self::assertSame(42, $this->logger->records[0][2]['user_id']);
        self::assertSame('web', $this->logger->records[0][2]['guard']);
    }

    public function test_login_logs_info(): void
    {
        $user = ['id' => 7, 'name' => 'ada'];

        ($this->listener)(new Login('api', $user));

        self::assertSame('info', $this->logger->records[0][0]);
        self::assertSame('User logged in', $this->logger->records[0][1]);
        self::assertSame(7, $this->logger->records[0][2]['user_id']);
    }

    public function test_logout_logs_info(): void
    {
        ($this->listener)(new Logout('web', ['id' => 42]));

        self::assertSame('info', $this->logger->records[0][0]);
        self::assertSame('User logged out', $this->logger->records[0][1]);
        self::assertSame(42, $this->logger->records[0][2]['user_id']);
    }

    public function test_failed_logs_warning_with_matched_flag(): void
    {
        ($this->listener)(new Failed('web', ['email' => 'ada@example.com']));

        self::assertSame('warning', $this->logger->records[0][0]);
        self::assertSame('Authentication failed', $this->logger->records[0][1]);
        self::assertFalse($this->logger->records[0][2]['matched']);
        self::assertArrayNotHasKey('credentials', $this->logger->records[0][2]);
    }

    public function test_failed_with_matched_user_logs_user_id(): void
    {
        $user = new class
        {
            public function getId(): int
            {
                return 99;
            }
        };

        ($this->listener)(new Failed('web', ['email' => 'ada@example.com'], $user));

        self::assertSame(99, $this->logger->records[0][2]['user_id']);
        self::assertTrue($this->logger->records[0][2]['matched']);
    }

    public function test_lockout_logs_warning_with_key(): void
    {
        ($this->listener)(new Lockout('web', 'login:ada@example.com'));

        self::assertSame('warning', $this->logger->records[0][0]);
        self::assertSame('Account locked', $this->logger->records[0][1]);
        self::assertSame('login:ada@example.com', $this->logger->records[0][2]['key']);
    }

    public function test_password_reset_requested_logs_info(): void
    {
        ($this->listener)(new PasswordResetRequested(['id' => 5], 'token-abc'));

        self::assertSame('info', $this->logger->records[0][0]);
        self::assertSame('Password reset requested', $this->logger->records[0][1]);
        self::assertSame(5, $this->logger->records[0][2]['user_id']);
    }

    public function test_verified_logs_info(): void
    {
        ($this->listener)(new Verified(['id' => 3]));

        self::assertSame('info', $this->logger->records[0][0]);
        self::assertSame('User verified', $this->logger->records[0][1]);
        self::assertSame(3, $this->logger->records[0][2]['user_id']);
    }

    public function test_user_id_extraction_from_method(): void
    {
        $user = new class
        {
            public function id(): string
            {
                return 'method-id';
            }
        };

        ($this->listener)(new Login('web', $user));

        self::assertSame('method-id', $this->logger->records[0][2]['user_id']);
    }

    public function test_user_id_omitted_when_unresolvable(): void
    {
        ($this->listener)(new Login('web', 'opaque-string-user'));

        self::assertArrayNotHasKey('user_id', $this->logger->records[0][2]);
    }

    public function test_unknown_event_does_nothing(): void
    {
        ($this->listener)(new \stdClass());

        self::assertSame([], $this->logger->records);
    }
}