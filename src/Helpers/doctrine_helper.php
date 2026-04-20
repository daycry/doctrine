<?php

declare(strict_types=1);

use CodeIgniter\Config\Services;
use Daycry\Doctrine\Doctrine;

if (! function_exists('doctrine_instance')) {
    /**
     * Returns the shared Doctrine integration service for the given DB group.
     */
    function doctrine_instance(?string $dbGroup = null): Doctrine
    {
        return Services::doctrine(true, $dbGroup);
    }
}
