<?php

use CodeIgniter\Config\Services;

if (! function_exists('doctrine_instance')) {
    /**
     * load twig
     *
     * @return class
     */
    function doctrine_instance()
    {
        return Services::doctrine();
    }
}
