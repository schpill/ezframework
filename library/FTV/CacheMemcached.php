<?php
     /**
     * Memcached wrapper
     *
     * This class wraps the memcached extension of PHP. Don't mix it up with the
     * memcache (without d!) extension, for which you have to use
     * FTV_CacheMemcache.
     *
     * @author  Gerald
     * @see     http://www.php.net/manual/fr/book.memcached.php
     */
    class FTV_CacheMemcached extends FTV_CacheMemcache
    {
            /**
             * Checks whether a caching system is avilable
             *
             * @return boolean  true if php_memcached is available, else false
             */
            public static function isAvailable()
            {
                return class_exists('Memcached');
            }

            public function addServerEx($host, $port = 11211, $weight = 0, $persistent = true, $timeout = 1, $retryInterval = 15, $status = true, $failureCallback = null)
            {
                throw new FTV_Exception('Extended server configuration is only available in php_memcache.');
            }

            public function addServer($host, $port = 11211, $weight = 0)
            {
                    return $this->memcached->addServer($host, $port, $weight);
            }

            public function getMemcachedVersion()
            {
                $result = $this->memcached->getVersion();
                return empty($result) ? false : reset($result);
            }

            public function getStats()
            {
                $result = $this->memcached->getStats();
                return empty($result) ? false : reset($result);
            }

            public function __construct($host = 'localhost', $port = 11211)
            {
                $this->memcached = new Memcached();

                if (!$this->addServer($host, $port)) {
                    throw new FTV_CacheException('Could not connect to Memcached @ '.$host.':'.$port.'!');
                }
            }

            protected function _setRaw($key, $value, $expiration)
            {
                return $this->memcached->set($key, $value, $expiration);
            }

            protected function _set($key, $value, $expiration)
            {
                return $this->memcached->set($key, serialize($value), $expiration);
            }

            protected function _isset($key)
            {
                $this->memcached->get($key);
                return $this->memcached->getResultCode() != Memcached::RES_NOTFOUND;
            }
    }

