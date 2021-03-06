<?php
    /**
     * Memcache wrapper
     *
     * This class wraps the memcache extension of PHP. Don't mix it up with the
     * memcached (with d!) extension, for which you have to use FTV_CacheMemcached.
     *
     * @author  Gerald
     */
    class FTV_CacheMemcache extends FTV_CacheAbstract
    {
            protected $memcached = null;  ///< Memcache  the current Memcache instance

            /**
             * Constructor
             *
             * Opens the connection to memcached.
             *
             * @throws FTV_Exception  if the connection could not be established
             * @param  string $host          the host
             * @param  int    $port          the port
             */
            public function __construct($host = 'localhost', $port = 11211)
            {
                    $this->memcached = new Memcache();

                    if (!$this->memcached->connect($host, $port)) {
                            throw new FTV_Exception('Could not connect to Memcache @ ' . $host . ':' . $port . '!');
                    }
            }

            public function getMemcached()
            {
                    return $this->memcached;
            }

            public function addServer($host, $port = 11211, $weight = 0)
            {
                    return $this->memcached->addServer($host, $port, true, $weight);
            }

            public function addServerEx($host, $port = 11211, $weight = 0, $persistent = true, $timeout = 1, $retryInterval = 15, $status = true, $failureCallback = null)
            {
                    return $this->memcached->addServer($host, $port, $persistent, $weight, $timeout, $retryInterval, $status, $failureCallback);
            }

            public function getMemcachedVersion()
            {
                    return $this->memcached->getVersion();
            }

            public function getStats()
            {
                    return $this->memcached->getStats();
            }

            public function getMaxKeyLength()
            {
                    return 200; // unbekannt -> Schätzwert
            }

            public function hasLocking()
            {
                    return false;
            }

            /**
             * Checks whether a caching system is avilable
             *
             * @return boolean  true if php_memcache is available, else false
             */
            public static function isAvailable()
            {
                    return class_exists('Memcache');
            }

            protected function _getRaw($key)
            {
                    return $this->memcached->get($key);
            }

            protected function _get($key)
            {
                    return unserialize($this->memcached->get($key));
            }

            protected function _setRaw($key, $value, $expiration)
            {
                    return $this->memcached->set($key, $value, 0, $expiration);
            }

            protected function _set($key, $value, $expiration)
            {
                    return $this->memcached->set($key, serialize($value), 0, $expiration);
            }

            protected function _delete($key)
            {
                    return $this->memcached->delete($key);
            }

            protected function _isset($key)
            {
                    return $this->memcached->get($key) !== false;
            }

            protected function _increment($key)
            {
                    return $this->memcached->increment($key) !== false;
            }
    }
