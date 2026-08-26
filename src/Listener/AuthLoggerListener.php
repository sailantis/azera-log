<?php

declare(strict_types=1);

namespace Azera\Log\Listener;

use Azera\Auth\Event\Authenticated;
use Azera\Auth\Event\Attempting;
use Azera\Auth\Event\Failed;
use Azera\Auth\Event\Lockout;
use Azera\Auth\Event\Login;
use Azera\Auth\Event\Logout;
use Azera\Auth\Event\PasswordResetRequested;
use Azera\Auth\Event\Verified;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * PSR-14 listener that logs authentication lifecycle events.
 *
 * Mirrors the {@see DbQueryLogger} pattern for the `azera-auth`
 * package. By default it logs:
 *
 *  - {@see Attempting}            — debug    — "Authentication attempt"
 *  - {@see Authenticated}         — info     — "User authenticated"
 *  - {@see Login}                 — info     — "User logged in"
 *  - {@see Logout}                — info     — "User logged out"
 *  - {@see Failed}                — warning — "Authentication failed"
 *  - {@see Lockout}               — warning — "Account locked"
 *  - {@see PasswordResetRequested}— info     — "Password reset requested"
 *  - {@see Verified}              — info     — "User verified"
 *
 * Register with the framework's `EventDispatcher`:
 *
 * <code>
 * $dispatcher->listen(Attempting::class, $listener);
 * $dispatcher->listen(Authenticated::class, $listener);
 * $dispatcher->listen(Login::class, $listener);
 * $dispatcher->listen(Logout::class, $listener);
 * $dispatcher->listen(Failed::class, $listener);
 * $dispatcher->listen(Lockout::class, $listener);
 * $dispatcher->listen(PasswordResetRequested::class, $listener);
 * $dispatcher->listen(Verified::class, $listener);
 * </code>
 *
 * The user id is extracted from the `mixed $user` payload using the same
 * heuristic as `SessionGuard::userId()`: array `['id']`, object
 * properties (`id`, `Id`, `ID`), or methods (`getId()`, `id()`). When
 * no id can be resolved, the key is omitted.
 *
 * Credentials in `Failed`/`Attempting` events are *not* logged — only
 * the guard name and (for `Failed`) whether a user was matched.
 */
class AuthLoggerListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(object $event): void
    {
        match (true) {
            $event instanceof Attempting => $this->logger->log(
                LogLevel::DEBUG,
                'Authentication attempt',
                ['guard' => $event->guardName],
            ),

            $event instanceof Authenticated => $this->logger->log(
                LogLevel::INFO,
                'User authenticated',
                array_filter([
                    'guard'   => $event->guardName,
                    'user_id' => $this->userId($event->user),
                ], fn($v) => $v !== null),
            ),

            $event instanceof Login => $this->logger->log(
                LogLevel::INFO,
                'User logged in',
                array_filter([
                    'guard'   => $event->guardName,
                    'user_id' => $this->userId($event->user),
                ], fn($v) => $v !== null),
            ),

            $event instanceof Logout => $this->logger->log(
                LogLevel::INFO,
                'User logged out',
                array_filter([
                    'guard'   => $event->guardName,
                    'user_id' => $this->userId($event->user),
                ], fn($v) => $v !== null),
            ),

            $event instanceof Failed => $this->logger->log(
                LogLevel::WARNING,
                'Authentication failed',
                array_filter([
                    'guard'   => $event->guardName,
                    'user_id' => $this->userId($event->user),
                    'matched' => $event->user !== null,
                ], fn($v) => $v !== null),
            ),

            $event instanceof Lockout => $this->logger->log(
                LogLevel::WARNING,
                'Account locked',
                [
                    'guard' => $event->guardName,
                    'key'   => $event->key,
                ],
            ),

            $event instanceof PasswordResetRequested => $this->logger->log(
                LogLevel::INFO,
                'Password reset requested',
                array_filter([
                    'user_id' => $this->userId($event->user),
                ], fn($v) => $v !== null),
            ),

            $event instanceof Verified => $this->logger->log(
                LogLevel::INFO,
                'User verified',
                array_filter([
                    'user_id' => $this->userId($event->user),
                ], fn($v) => $v !== null),
            ),

            default => null
        };
    }

    /**
     * Extract a user identifier from a mixed user payload.
     *
     * Mirrors `SessionGuard::userId()`: array `['id']`, object
     * properties (`id`, `Id`, `ID`), or methods (`getId()`, `id()`).
     */
    private function userId(mixed $user): mixed
    {
        if (is_array($user)) {
            return $user['id'] ?? null;
        }

        if (is_object($user)) {
            foreach (['id', 'Id', 'ID'] as $prop) {
                if (isset($user->$prop)) {
                    return $user->$prop;
                }
            }
            foreach (['getId', 'id'] as $method) {
                if (method_exists($user, $method)) {
                    return $user->$method();
                }
            }
        }

        return null;
    }
}