<?php

namespace TH\RedisLock;

use Predis\Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TH\Lock\TtlFactory;

class RedisSimpleLockFactory implements TtlFactory
{
    private $client;
    private $defaultTtl;
    private $logger;
    private $ignoredSAPIs;

    public function __construct(Client $client, $defaultTtl = 10000, LoggerInterface $logger = null, array $ignoredSAPIs = [])
    {
        $this->client       = $client;
        $this->defaultTtl   = $defaultTtl;
        $this->logger       = $logger ?: new NullLogger;
        $this->ignoredSAPIs = $ignoredSAPIs;
    }

    /**
     * Create a new RedisSimpleLock
     *
     * @param string  $identifier the redis lock key
     * @param integer $ttl        lock time-to-live in milliseconds
     *
     * @return RedisSimpleLock
     */
    public function create($identifier, $ttl = null)
    {
        return new RedisSimpleLock($identifier, $this->client, $ttl ?: $this->defaultTtl, $this->logger, $this->ignoredSAPIs);
    }
}
