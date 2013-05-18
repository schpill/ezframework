<?php
     /**
     * Blackhole Caching
     *
     * This cache does not save any data at all and exists as the logical pendant
     * to all the other, real systems. It should be used if the caching is disabled,
     * so that code relying on a cache can use the cache instance normally without
     * checking for null.
     *
     * @author  Gerald
     */
    class FTV_CacheBlackhole extends FTV_Cache implements FTV_CacheInterface
    {
            public static function isAvailable()
            {
                return true;
            }

            public function set($namespace, $key, $value)
            {
                return $value;
            }

            public function exists($namespace, $key)
            {
                return false;
            }

            public function get($namespace, $key, $default = null)
            {
                return $default;
            }

            public function lock($namespace, $key, $duration = 1)
            {
                return true;
            }

            public function unlock($namespace, $key)
            {
                return true;
            }

            public function waitForObject($namespace, $key, $default = null, $maxWaitTime = 3, $checkInterval = 50)
            {
                return true;
            }

            public function delete($namespace, $key)
            {
                return true;
            }

            public function flush($namespace, $recursive = false)
            {
                return true;
            }
    }
