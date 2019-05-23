<?php

use TH\RedisLock\RedisSimpleLock;

class RedisSimpleLockTest extends PHPUnit_Framework_TestCase
{
    private $redisClient;

    protected function setUp()
    {
        $this->redisClient = new \Predis\Client(getenv("REDIS_URI"));
        $this->redisClient->flushdb();
    }

    public function testSAPIIgnore()
    {
        $lock1 = new RedisSimpleLock("lock identifier", $this->redisClient, 50, null, [php_sapi_name()]);
        $handler = pcntl_signal_get_handler(SIGINT);
        $this->assertEmpty($handler);

        $lock2 = new RedisSimpleLock("lock identifier", $this->redisClient, 50, null);
        $handler = pcntl_signal_get_handler(SIGINT);
        $this->assertInstanceOf(Closure::class, $handler);
    }

    public function testLock()
    {
        $lock1 = new RedisSimpleLock("lock identifier", $this->redisClient, 50);
        $lock2 = new RedisSimpleLock("lock identifier", $this->redisClient);

        $lock1->acquire();

        // Only the second acquire is supposed to fail
        $this->setExpectedException("Exception");
        $lock2->acquire();
    }

    public function testLockTtl()
    {
        $lock1 = new RedisSimpleLock("lock identifier", $this->redisClient, 50);
        $lock2 = new RedisSimpleLock("lock identifier", $this->redisClient);

        $lock1->acquire();
        usleep(100000);

        // first lock sould have been released
        $lock2->acquire();
    }

    public function testLockSafeRelease()
    {
        $lock1 = new RedisSimpleLock("lock identifier", $this->redisClient, 50);
        $lock2 = new RedisSimpleLock("lock identifier", $this->redisClient);

        $lock1->acquire();
        usleep(100000);
        $lock2->acquire();
        $lock1->release();

        // lock should still exists
        $this->assertTrue($this->redisClient->exists("lock identifier") === 1, "Lock should not have been released");
    }

    public function testLockRelease()
    {
        $lock1 = new RedisSimpleLock("lock identifier", $this->redisClient, 50);
        $lock2 = new RedisSimpleLock("lock identifier", $this->redisClient);

        $lock1->acquire();
        $lock1->release();

        // first lock sould have been released
        $lock2->acquire();
    }

    public function testLockAutoRelease()
    {
        $lock1 = new RedisSimpleLock("lock identifier", $this->redisClient, 50);
        $lock2 = new RedisSimpleLock("lock identifier", $this->redisClient);

        $lock1->acquire();
        unset($lock1);

        // first lock sould have been released
        $lock2->acquire();
    }
}
