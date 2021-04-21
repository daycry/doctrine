<?php namespace Daycry\Doctrine\Libraries;

use CodeIgniter\Cache\Handlers\RedisHandler;
use Config\Cache;

class Redis extends MemcachedHandler
{
    public function __construct(Cache $config)
	{
		parent::__construct( $config );
	}

    public function getInstance()
    {
		return $this->redis;
	}
}