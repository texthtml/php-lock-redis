<?php

use PHPUnit\Framework\TestCase;
use TH\RedisLock\RedisSimpleLock;
use TH\RedisLock\RedisSimpleLockFactory;

class RedisSimpleLockFactoryTest extends TestCase
{
    private $redisClient;

    public function setUp(): void
    {
        $uri = getenv("REDIS_URI");
        $this->redisClient = is_string($uri) ? new \Predis\Client($uri) : new \Predis\Client();
        $this->redisClient->flushdb();
    }

    public function testCreateIgnoredSAPIsLock()
    {
        $factory = new RedisSimpleLockFactory($this->redisClient, 50, null, [php_sapi_name()]);
        $lock = $factory->create('lock identifier');
        $this->assertInstanceOf(RedisSimpleLock::class, $lock);

        if (function_exists('pcntl_signal_get_handler')) {
            $handler = pcntl_signal_get_handler(SIGINT);
            $this->assertEmpty($handler);
        }
    }

    public function testCreateLock()
    {
        $factory = new RedisSimpleLockFactory($this->redisClient, 50);
        $lock = $factory->create('lock identifier');
        $this->assertInstanceOf(RedisSimpleLock::class, $lock);

        if (function_exists('pcntl_signal_get_handler')) {
            $handler = pcntl_signal_get_handler(SIGINT);
            $this->assertInstanceOf(Closure::class, $handler);
        }
    }
}
