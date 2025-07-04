<?php

use CodeIgniter\Config\Services;
use Daycry\Doctrine\Doctrine;

if (! function_exists('doctrine_instance')) {
    /**
     * load twig
     *
     * @return Doctrine
     */
    function doctrine_instance()
    {
        return Services::doctrine();
    }
}
