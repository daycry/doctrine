<?php namespace Daycry\Doctrine\Libraries;

use CodeIgniter\Cache\Handlers\RedisHandler;
use Config\Cache;

class Redis extends RedisHandler
{
    public function __construct(Cache $config)
	{
		parent::__construct( $config );
        $this->initialize();
	}

    public function getClass()
    {
		return $this->redis;
	}
}