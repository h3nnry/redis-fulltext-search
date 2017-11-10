<?php

class RedisClient
{
    /**
     * @var string
     */
    public $redis;

    /**
     * RedisClient constructor.
     * @param string $redis
     * @param string $hostname
     * @param int $port
     * @param int $db
     * @param null $password
     * @throws Exception
     */
    public function __construct($redis = 'Redis', $hostname = '127.0.0.1', $port = 6379, $db = 0, $password = null)
    {
        if ($redis === 'Redis') {
            $this->redis = new $redis;
            $this->redis->connect($hostname, $port);
            $this->redis->select($db);
            $this->redis->auth($password);
        } elseif ($redis === 'Predis\Client') {
            $this->redis = new $redis([
                'scheme' => 'tcp',
                'host' => $hostname,
                'port' => $port,
                'database' => $db,
                'password' => $password,
            ]);
            $this->redis->connect();
        } elseif (is_object($redis) && in_array(get_class($redis), ['Redis', 'Predis\Client'])) {
            $this->redis = $redis;
        } else {
            throw new Exception('Only Predis\\Client and Redis client classes are allowed');
        }
    }

    /**
     * Flush redis database
     */
    public function flushAll()
    {
        $this->redis->flushAll();
    }

    /**
     * @return bool
     */
    public function isPredisClient()
    {
        return get_class($this->redis) === 'Predis\Client';
    }

    /**
     * @return bool
     */
    public function isPhpRedis()
    {
        return get_class($this->redis) === 'Redis';
    }

    /**
     * @param bool $usePipelineForPhpRedis
     * @return mixed
     */
    public function multi(bool $usePipelineForPhpRedis = false)
    {
        return $this->isPredisClient() ?
            $this->redis->pipeline() :
            $this->redis->multi($usePipelineForPhpRedis ? \Redis::PIPELINE : \Redis::MULTI);
    }

    /**
     * @param string $command
     * @param array $arguments
     * @return mixed
     * @throws Exception
     */
    public function rawCommand(string $command, array $arguments)
    {
        foreach ($arguments as $index => $argument) {
            if (!is_scalar($arguments[$index])) {
                $arguments[$index] = (string)$argument;
            }
        }
        array_unshift($arguments, $command);
        if ($this->logger) {
            $this->logger->debug(implode(' ', $arguments));
        }
        $rawResult = $this->isPredisClient() ?
            $this->redis->executeRaw($arguments) :
            call_user_func_array([$this->redis, 'rawCommand'], $arguments);

        if ($rawResult === 'Unknown Index name') {
            throw new Exception('Unknown index name');
        }
        return $rawResult;
    }

    /**
     * Delete redis key
     * @param $keys
     */
    public function delete($keys)
    {
        $this->redis->delete($keys);
    }

    /**
     * Redis get keys
     * @param $index
     * @return mixed
     */
    public function scan($index)
    {
        return $this->redis->scan(null, $index);
    }
}
