<?php    
    /**
     * Events class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Events 
    {
        public static $events = array();
        public static $queued = array();
        public static $flushers = array();
        
        public static function listeners($event)
        {
            return array_key_exists($event, self::$events);
        }

        public static function set($event, $callback, $once = false)
        {
            self::$events[$event][] = array($callback, $once);
        }

        public static function override($event, $callback)
        {
            self::clear($event);
            self::set($event, $callback);
        }

        public static function queue($queue, $key, $data = array())
        {
            self::$queued[$queue][$key] = $data;
        }

        public static function flusher($queue, $callback)
        {
            self::$flushers[$queue][] = $callback;
        }

        public static function clear($event)
        {
            unset(self::$events[$event]);
        }

        public static function first($event, $parameters = array())
        {
            return head(self::run($event, $parameters));
        }

        public static function until($event, $parameters = array())
        {
            return self::run($event, $parameters, true);
        }

        public static function flush($queue)
        {
            foreach (self::$flushers[$queue] as $flusher)
            {
                if (!array_key_exists($queue, self::$queued)) {continue;}

                foreach (self::$queued[$queue] as $key => $payload) {
                    array_unshift($payload, $key);
                    call_user_func_array($flusher, $payload);
                }
            }
        }
        
        public static function run($events, $parameters = array(), $halt = false)
        {
            $responses = array();

            $parameters = (array) $parameters;
            foreach ((array) $events as $event) {
                if (self::listeners($event)) {
                    foreach (self::$events[$event] as $callbackPack) {
                        list($callback, $once) = $callbackPack;
                        $response = call_user_func_array($callback, $parameters);
                        if ($halt && !is_null($response)) {
                            return $response;
                        }
                        $responses[] = $response;
                        if (true === $once) {
                            if (false !== $index = array_search($callbackPack, self::$events[$event], true)) {
                                unset(self::$events[$event][$index]);
                            }
                        }
                    }
                } else {
                    $error = (strstr($event, '.init') || strstr($event, '.start') || strstr($event, '.done') || strstr($event, '.stop')) ? false : true;
                    if (true === $error) {
                        throw new FTV_Exception("The event $event doesn't exist.");
                    }
                }
            }
            if (count($responses) == 1) {
                $responses = current($responses);
            }
            return $halt ? null : $responses;
        }
    }
