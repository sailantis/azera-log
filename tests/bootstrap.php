<?php

declare(strict_types=1);

/**
 * Test bootstrap: load Composer's autoloader, then alias fixture event
 * classes to the real namespaces when the sibling packages
 * (azera-queue, azera-auth) are not installed.
 *
 * This lets the listener tests run without the real packages, while the
 * listener code references the real event class names as-is.
 */

require __DIR__ . '/../vendor/autoload.php';

// --- azera-queue events ---
if (!class_exists(\Azera\Queue\Event\JobPushed::class)) {
    class_alias(\Azera\Log\Tests\Fixtures\Queue\JobPushed::class, \Azera\Queue\Event\JobPushed::class);
}
if (!class_exists(\Azera\Queue\Event\JobReserved::class)) {
    class_alias(\Azera\Log\Tests\Fixtures\Queue\JobReserved::class, \Azera\Queue\Event\JobReserved::class);
}
if (!class_exists(\Azera\Queue\Event\JobProcessed::class)) {
    class_alias(\Azera\Log\Tests\Fixtures\Queue\JobProcessed::class, \Azera\Queue\Event\JobProcessed::class);
}
if (!class_exists(\Azera\Queue\Event\JobReleased::class)) {
    class_alias(\Azera\Log\Tests\Fixtures\Queue\JobReleased::class, \Azera\Queue\Event\JobReleased::class);
}
if (!class_exists(\Azera\Queue\Event\JobRetried::class)) {
    class_alias(\Azera\Log\Tests\Fixtures\Queue\JobRetried::class, \Azera\Queue\Event\JobRetried::class);
}
if (!class_exists(\Azera\Queue\Event\JobFailed::class)) {
    class_alias(\Azera\Log\Tests\Fixtures\Queue\JobFailed::class, \Azera\Queue\Event\JobFailed::class);
}

// --- azera-auth events ---
if (!class_exists(\Azera\Auth\Event\Attempting::class)) {
    class_alias(\Azera\Log\Tests\Fixtures\Auth\Attempting::class, \Azera\Auth\Event\Attempting::class);
}
if (!class_exists(\Azera\Auth\Event\Authenticated::class)) {
    class_alias(\Azera\Log\Tests\Fixtures\Auth\Authenticated::class, \Azera\Auth\Event\Authenticated::class);
}
if (!class_exists(\Azera\Auth\Event\Login::class)) {
    class_alias(\Azera\Log\Tests\Fixtures\Auth\Login::class, \Azera\Auth\Event\Login::class);
}
if (!class_exists(\Azera\Auth\Event\Logout::class)) {
    class_alias(\Azera\Log\Tests\Fixtures\Auth\Logout::class, \Azera\Auth\Event\Logout::class);
}
if (!class_exists(\Azera\Auth\Event\Failed::class)) {
    class_alias(\Azera\Log\Tests\Fixtures\Auth\Failed::class, \Azera\Auth\Event\Failed::class);
}
if (!class_exists(\Azera\Auth\Event\Lockout::class)) {
    class_alias(\Azera\Log\Tests\Fixtures\Auth\Lockout::class, \Azera\Auth\Event\Lockout::class);
}
if (!class_exists(\Azera\Auth\Event\PasswordResetRequested::class)) {
    class_alias(\Azera\Log\Tests\Fixtures\Auth\PasswordResetRequested::class, \Azera\Auth\Event\PasswordResetRequested::class);
}
if (!class_exists(\Azera\Auth\Event\Verified::class)) {
    class_alias(\Azera\Log\Tests\Fixtures\Auth\Verified::class, \Azera\Auth\Event\Verified::class);
}