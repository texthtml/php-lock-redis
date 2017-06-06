<?php

namespace TH\RedisLock;

use Predis\Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TH\Lock\TtlFactory;

/**
 * Class RedisSimpleLockFactory
 *
 * @package TH\RedisLock
 */
class RedisSimpleLockFactory implements TtlFactory
{
    private $client;
    private $defaultTtl;
    private $logger;

    public function __construct(Client $client, $defaultTtl = 10000, LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->defaultTtl = $defaultTtl;
        $this->logger = $logger ?: new NullLogger;
    }

    /**
     * Create a new RedisSimpleLock
     *
     * @param string  $identifier the redis lock key
     * @param integer $ttl lock time-to-live in milliseconds
     *
     * @return RedisSimpleLock
     */
    public function create($identifier, $ttl = null)
    {
        return new RedisSimpleLock($identifier, $this->client, $ttl ?: $this->defaultTtl, $this->logger);
    }
}
