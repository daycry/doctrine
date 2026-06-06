<?php

declare(strict_types=1);

namespace Tests\Support\Listeners;

/**
 * Plain event listener fixture (method named after the Doctrine event).
 */
final class RecordingListener
{
    public function prePersist(): void
    {
    }
}
