# php-lock-redis

[![Build Status](https://travis-ci.org/texthtml/php-lock-redis.svg?branch=master)](https://travis-ci.org/texthtml/php-lock-redis)
[![Latest Stable Version](https://poser.pugx.org/texthtml/php-lock-redis/v/stable.svg)](https://packagist.org/packages/texthtml/php-lock-redis)
[![License](https://poser.pugx.org/texthtml/php-lock-redis/license.svg)](http://www.gnu.org/licenses/agpl-3.0.html)
[![Total Downloads](https://poser.pugx.org/texthtml/php-lock-redis/downloads.svg)](https://packagist.org/packages/texthtml/php-lock-redis)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/texthtml/php-lock-redis/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/texthtml/php-lock-redis/?branch=master)

[php-lock-redis](https://packagist.org/packages/texthtml/php-lock-redis) is an extension for [php-lock](https://packagist.org/packages/texthtml/php-lock) that makes locking on resources easy on distributed system using Redis. It can be used instead of file base locking to lock operations on a distributed system. 

## Installation

With Composer :

```bash
composer require texthtml/php-lock-redis
```

## Usage

You can create an object that represent a lock on a resource. You can then try to acquire that lock by calling `$lock->acquire()`. If the lock fail it will throw an `Exception` (useful for CLI tools built with [Symfony Console Components documentation](http://symfony.com/doc/current/components/console/introduction.html)). If the lock is acquired the program can continue.

### Locking a ressource

```php
use TH\RedisLock\RedisSimpleLockFactory;

$redisClient = new \Predis\Client;
$factory = new RedisSimpleLockFactory($redisClient);
$lock = $factory->create('lock identifier');

$lock->acquire();

// other processes that try to acquire a lock on 'lock identifier' will fail

// do some stuff

$lock->release();

// other processes can now acquire a lock on 'lock identifier'
```

### Auto release

`$lock->release()` is called automatically when the lock is destroyed so you don't need to manually release it at the end of a script or if it goes out of scope.

```php
use TH\RedisLock\RedisSimpleLockFactory;

function batch() {
    $redisClient = new \Predis\Client;
    $factory = new RedisSimpleLockFactory($redisClient);
    $lock = $factory->create('lock identifier');
    $lock->acquire();

    // lot of stuff
}

batch();

// the lock will be released here even if $lock->release() is not called in batch()
```

## Limitations

### Validity time

If a client crashes before releasing the lock (or forget to release it), no other clients would be able to acquire the lock again. To avoid Deadlock, `RedisSimpleLock` locks have a validity time at witch the lock key will expire. But be careful, if the operation is too long, another client might acquire the lock too.

### Mutual exclusion

Because `RedisSimpleLock` does not implements the [RedLock algorithm](http://redis.io/topics/distlock), it have a limitation : with a master slave replication, a race condition can occurs when the master crashes before the lock key is transmitted to the slave. In this case a second client could acquire the same lock.
