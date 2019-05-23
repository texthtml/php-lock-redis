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
     * @param array                $ignoredSAPIs the server apis to ignore the pcntl_signal callback for
     */
    public function __construct($identifier, Client $client, $ttl = 10000, LoggerInterface $logger = null, array $ignoredSAPIs = [])
    {
        $this->identifier = $identifier;
        $this->client     = $client;
        $this->ttl        = $ttl;
        $this->logger     = $logger ?: new NullLogger;
        $this->id         = mt_rand();
        register_shutdown_function($closure = $this->releaseClosure());

        if (!in_array(php_sapi_name(), $ignoredSAPIs)) {
            if (!function_exists('pcntl_signal')) {
                throw new \RuntimeException("pcntl_signal() from the pcntl extension is not availlable, configure `$ignoredSAPIs = ['".php_sapi_name()."']` to skip catching SIGINT signal.");
            }
            pcntl_signal(SIGINT, $closure);
        }
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
        $closure = $this->releaseClosure();
        $closure();
    }

    public function __destruct()
    {
        $this->release();
    }

    private function releaseClosure()
    {
        $client = $this->client;
        $id = $this->id;
        $identifier = $this->identifier;
        $logger = $this->logger;

        $closure = function () use ($client, $identifier, $id, $logger) {
            $script = <<<LUA
    if redis.call("get", KEYS[1]) == ARGV[1] then
        return redis.call("del", KEYS[1])
    end
LUA;
            if ($client->eval($script, 1, $identifier, $id)) {
                $logger->debug("lock released on {identifier}", ["identifier" => $identifier]);
            }
        };
        return $closure->bindTo(null);
    }
}
