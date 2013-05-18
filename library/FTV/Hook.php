<?php
    /**
     * Hook class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Hook
    {
        public function __construct()
        {
            $hooks = u::get('FTVHooks');
            if (null === $hooks) {
                $hooks = array();
                u::set('FTVHooks', $hooks);
            }
        }

        public function before($function, $action)
        {
            $hooks = u::get('FTVHooks');
            if (!ake($function, $hooks)) {
                $hooks[$function] = array();
            }
            $hooks[$function]['before'] = $action;
            u::set('FTVHooks', $hooks);
            return $this;
        }

        public function after($function, $action)
        {
            $hooks = u::get('FTVHooks');
            if (!ake($function, $hooks)) {
                $hooks[$function] = array();
            }
            $hooks[$function]['after'] = $action;
            u::set('after', $hooks);
            return $this;
        }

        public function run($function, array $params = array())
        {
            $hooks = u::get('FTVHooks');
            $res = null;
            if (ake($function, $hooks)) {
                if (ake('before', $hooks[$function])) {
                    $action = $hooks[$function]['before'];
                    if (is_callable($action, true, $before)) {
                        $res = $before();
                    }
                }
                if (null === $res) {
                    $res = '';
                }

                $res .= call_user_func_array($function, $params);

                if (ake('after', $hooks[$function])) {
                    $action = $hooks[$function]['after'];
                    if (is_callable($action, true, $after)) {
                        $res .= $after();
                    }
                }

                return $res;
            } else {
                return call_user_func_array($function, $params);
            }
        }

        public function __call($method, $params)
        {
            return $this->run($method, $params);
        }
    }
