<?php

declare(strict_types=1);

namespace Tests\Support\Listeners;

use Doctrine\Common\EventSubscriber;

/**
 * Event subscriber fixture that subscribes to postLoad.
 */
final class RecordingSubscriber implements EventSubscriber
{
    /**
     * @return list<string>
     */
    public function getSubscribedEvents(): array
    {
        return ['postLoad'];
    }
}
