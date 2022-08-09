<?php

if (!function_exists('doctrine_instance')) {
    /**
     * load twig
     *
     * @return class
     */
    function doctrine_instance()
    {
        return \CodeIgniter\Config\Services::doctrine();
    }
}
