<?php
    /**
     * Registry class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Registry
    {
        static $instance;
        static $objects = array();

        // singleton
        public static function forge()
        {
            if (!self::$instance instanceof self) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public static function get($key)
        {
            return ake($key, self::$objects) ? self::$objects[$key] : null;
        }

        public static function set($key, $value = null)
        {
            if (is_array($key) || is_object($key)) {
                foreach ($key as $k => $v) {
                    self::$objects[$k] = $v;
                }
            } else {
                self::$objects[$key] = $value;
            }
        }

        public static function delete($key)
        {
            self::$objects[$key] = null;
        }

        public static function has($key)
        {
            return ake($key, self::$objects);
        }
    }
