<?php
    /**
     * Object class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Object extends ArrayObject implements ArrayAccess
    {
        public $_args;
        public $_datas = array();

        public function __construct()
        {
            $args   = func_get_args();
            $nbArgs = func_num_args();
            if (1 == $nbArgs && (is_array($args[0]) || is_object($args[0]))) {
                if (is_array($args[0])) {
                    $this->populate($args[0]);
                } elseif (is_object($args[0])) {
                    $array = (array) $args[0];
                    $this->populate($array);
                }
            }
            $this->_args = $args;
            $this->_nameClass = i::lower(get_class($this));
            return $this;
        }

        public function __call($func, $argv)
        {
            if (substr($func, 0, 3) == 'get') {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($func, 3)));
                $var = i::lower($uncamelizeMethod);
                if (isset($this->$var)) {
                    return $this->$var;
                } else {
                    return null;
                }
            } elseif (substr($func, 0, 3) == 'has') {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($func, 3)));
                $var = i::lower($uncamelizeMethod);
                return isset($this->$var) && !empty($this->$var);
            } elseif (substr($func, 0, 3) == 'set') {
                $value = $argv[0];
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($func, 3)));
                $var = i::lower($uncamelizeMethod);
                if (!empty($var)) {
                    $this->$var = $value;
                }
                return $this;
            }
            if (!is_callable($func) || substr($func, 0, 6) !== 'array_' || substr($func, 0, 3) !== 'set' || substr($func, 0, 3) !== 'get') {
                throw new BadMethodCallException(__class__ . ' => ' . $func);
            }
            return call_user_func_array($func, array_merge(array($this->getArrayCopy()), $argv));
        }

        public static function __callStatic($method, $args)
        {
            return self::run($method, $args);
        }

        public static function factory($functionName, FTV_Closure $function)
        {
            u::event(__class__ . '.' . $functionName, $function);
        }

        public static function run($function, $args)
        {
            $method = __class__ . '.' . $function;
            $output = u::run($method, $args);
            return $output;
        }

        public function __invoke($key, $value)
        {
            $this->$key = $value;
            return $this;
        }

        public function populate(array $datas, $namespace = null)
        {
            if (null !== $namespace) {
                if (!isset($this->$namespace)) {
                    $this->$namespace = array();
                }
                foreach ($datas as $k => $v) {
                    if (is_array($k)) {
                        $this->populate($k, $namespace);
                    } else {
                        $this->$namespace = array_merge($this->$namespace, array($k => $v));
                    }
                }
            } else {
                foreach ($datas as $k => $v) {
                    $this->$k = $v;
                }
            }
            return $this;
        }

        public function save()
        {
            $session = session(sha1($this->_nameClass))->setSession(serialize($this));
            return $this;
        }

        public function remember($key = null)
        {
            $session = session(sha1($this->_nameClass))->getSession();
            if (null === $key) {
                return $session;
            } else {
                $getter = 'get' . i::camelize($key);
                return $session->$getter();
            }
        }

        public function toArray()
        {
            $array = array();
            foreach ($this->_datas['fieldsSave'] as $field) {
                $array[$field] = $this->$field;
            }
            $array = (array) $this;
            unset($array['_nameClass']);
            unset($array['_args']);
            unset($array['_data']);
            return $array;
        }


    }
