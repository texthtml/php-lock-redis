<?php

namespace TH\RedisLock;

use Exception;
use Predis\Client;
use Predis\Response\Status;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TH\Lock\Lock;

class RedisSimpleLock implements Lock
{
    private $identifier;
    private $client;
    private $ttl;
    private $logger;
    private $id;

    /**
     * Create new RedisSimpleLock
     *
     * @param string               $identifier the redis lock key
     * @param Client               $client     the Predis client
     * @param integer              $ttl        lock time-to-live in milliseconds
     * @param LoggerInterface|null $logger
     */
    public function __construct($identifier, Client $client, $ttl = 10000, LoggerInterface $logger = null)
    {
        $this->identifier = $identifier;
        $this->client     = $client;
        $this->ttl        = $ttl;
        $this->logger     = $logger ?: new NullLogger;
        $this->id         = mt_rand();
    }

    public function acquire()
    {
        $log_data = ["identifier" => $this->identifier];
        $response = $this->client->set($this->identifier, $this->id, "PX", $this->ttl, "NX");
        if (!$response instanceof Status || $response->getPayload() !== "OK") {
            $this->logger->debug("could not acquire lock on {identifier}", $log_data);

            throw new Exception("Could not acquire lock on " . $this->identifier);
        }
        $this->logger->debug("lock acquired on {identifier}", $log_data);
    }

    public function release()
    {
        $script = <<<LUA
    if redis.call("get", KEYS[1]) == ARGV[1] then
        return redis.call("del", KEYS[1])
    end
LUA;
        if ($this->client->eval($script, 1, $this->identifier, $this->id)) {
            $this->logger->debug("lock released on {identifier}", ["identifier" => $this->identifier]);
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
