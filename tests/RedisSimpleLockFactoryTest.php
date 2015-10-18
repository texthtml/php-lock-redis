<?php

use TH\RedisLock\RedisSimpleLock;
use TH\RedisLock\RedisSimpleLockFactory;

class RedisSimpleLockFactoryTest extends PHPUnit_Framework_TestCase
{
    private $redisClient;

    protected function setUp()
    {
        $this->redisClient = new \Predis\Client(getenv('REDIS_URI'));
        $this->redisClient->flushdb();
    }

    public function testCreateLock()
    {
        $factory = new RedisSimpleLockFactory($this->redisClient, 50);
        $lock = $factory->create('lock identifier');
        $this->assertInstanceOf(RedisSimpleLock::class, $lock);
    }
}
