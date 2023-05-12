<?php

namespace Daycry\Doctrine\Libraries;

use CodeIgniter\Cache\Handlers\MemcachedHandler;
use Config\Cache;

class Memcached extends MemcachedHandler
{
    public function __construct(Cache $config)
    {
        parent::__construct($config);
        $this->initialize();
    }

    public function getInstance()
    {
        return $this->memcached;
    }
}
