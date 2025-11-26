<?php

use CodeIgniter\Config\Services;
use Daycry\Doctrine\Doctrine;

if (! function_exists('doctrine_instance')) {
    /**
     * Returns the shared Doctrine integration service.
     *
     * @return Doctrine
     */
    function doctrine_instance()
    {
        return Services::doctrine();
    }
}
