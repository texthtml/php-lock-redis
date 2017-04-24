<?php

namespace TH\RedisLock;

use Predis\Client;
use Predis\Response\Status;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TH\Lock\Lock;

/**
 * Class RedisSimpleLock
 *
 * @package TH\RedisLock
 */
class RedisSimpleLock implements Lock
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var int
     */
    private $id;

    /**
     * Create new RedisSimpleLock
     *
     * @param string               $key the redis lock key
     * @param Client               $client the Predis client
     * @param integer              $ttl lock time-to-live in milliseconds
     * @param LoggerInterface|null $logger
     */
    public function __construct($key, Client $client, $ttl = 10000, LoggerInterface $logger = null)
    {
        $this->key = $key;
        $this->client = $client;
        $this->ttl = $ttl;
        $this->logger = $logger ?: new NullLogger;
        $this->id = mt_rand();

        register_shutdown_function($closure = $this->releaseClosure());

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, $closure);
        }
    }

    /**
     * @return \Closure
     */
    private function releaseClosure()
    {
        $client = $this->client;
        $id = $this->id;
        $key = $this->key;
        $logger = $this->logger;

        $closure = function () use ($client, $key, $id, $logger) {
            $script = <<<LUA
    if redis.call("get", KEYS[1]) == ARGV[1] then
        return redis.call("del", KEYS[1])
    end
LUA;
            if ($client->eval($script, 1, $key, $id)) {
                $logger->debug('Lock released on {key}', ['key' => $key]);
            }
        };

        return $closure->bindTo(null);
    }

    public function acquire()
    {
        $logData = ['key' => $this->key];

        $response = $this->client->set($this->key, $this->id, 'PX', $this->ttl, 'NX');

        if (!$response instanceof Status || $response->getPayload() !== 'OK') {
            $this->logger->debug('Could not acquire lock on {key}', $logData);

            throw new \RuntimeException('Could not acquire lock on ' . $this->key);
        }

        $this->logger->debug('Lock acquired on {key}', $logData);
    }

    public function __destruct()
    {
        $this->release();
    }

    public function release()
    {
        $this->releaseClosure()();
    }
}
