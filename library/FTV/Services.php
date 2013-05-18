<?php    
    /**
     * Services class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Services implements ArrayAccess
    {
        private $values;
        public static $registry = array();
        public static $singletons = array();

        public function __construct (array $values = array())
        {
            $this->values = $values;
        }
        
        public static function register($name, $resolver = null, $singleton = false)
        {
            if (null === $resolver) {
                $resolver = $name;
            }
            self::$registry[$name] = compact('resolver', 'singleton');
        }
        
        public static function registered($name)
        {
            return array_key_exists($name, self::$registry);
        }
        
        public static function singleton($name, $resolver = null)
        {
            self::register($name, $resolver, true);
        }
        
        public static function instance($name, $instance)
        {
            self::$singletons[$name] = $instance;
        }
        
        public static function resolve($type, $parameters = array())
        {
            if (array_key_exists($type, self::$singletons)) {
                return self::$singletons[$type];
            }

            if (!isset(self::$registry[$type])) {
                $concrete = $type;
            } else {
                $concrete = arrayGet(self::$registry[$type], 'resolver', $type);
            }

            if ($concrete == $type || $concrete instanceof Closure) {
                $object = self::build($concrete, $parameters);
            } else {
                $object = self::resolve($concrete);
            }

            if (array_key_exists('singleton', self::$registry[$type]) && self::$registry[$type]['singleton'] === true) {
                self::$singletons[$type] = $object;
            }

            FTV_Events::run('phpfactorService.done', array($type, $object));

            return $object;
        }

        protected static function build($type, $parameters = array())
        {
            if ($type instanceof Closure) {
                return call_user_func_array($type, $parameters);
            }

            $reflector = new ReflectionClass($type);

            if ( ! $reflector->isInstantiable()) {
                throw new FTV_Exception("Resolution target [$type] is not instantiable.");
            }

            $constructor = $reflector->getConstructor();

            if (is_null($constructor)) {
                return new $type;
            }

            $dependencies = self::dependencies($constructor->getParameters());

            return $reflector->newInstanceArgs($dependencies);
        }

        protected static function dependencies($parameters)
        {
            $dependencies = array();

            foreach ($parameters as $parameter) {
                $dependency = $parameter->getClass();

                if (is_null($dependency))  {
                    throw new Exception("Unresolvable dependency resolving [$parameter].");
                }

                $dependencies[] = self::resolve($dependency->name);
            }

            return (array) $dependencies;
        }
        
        // to fluent setters' method
        public function __call($method, $parameters)
        {
            $this->values[$method] = (count($parameters) > 0) ? $parameters[0] : true;
            return $this;
        }

        public function share(Closure $closure)
        {
            return function ($c) use ($closure) {
                static $object;

                if (null === $object) {
                    $object = $closure($c);
                }

                return $object;
            };
        }

        public function protect(Closure $closure)
        {
            return function ($c) use ($closure) {
                return $closure;
            };
        }

        public function raw($id)
        {
            if (!array_key_exists($id, $this->values)) {
                throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
            }

            return $this->values[$id];
        }
        
        public function getObj()
        {
            if (!array_key_exists('_object', $this->values)) {
                throw new InvalidArgumentException('Identifier _object is not defined.');
            }
            return $this->values['_object']($this);
        }
        
        public function getName()
        {
            if (!array_key_exists('_name', $this->values)) {
                throw new InvalidArgumentException('Identifier _name is not defined.');
            }
            return $this->values['_name'];
        }

        public function extend($service, Closure $closure)
        {
            if (!array_key_exists($service, $this->values)) {
                throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $service));
            }

            $factory = $this->values[$service];

            if (!($factory instanceof Closure)) {
                throw new InvalidArgumentException(sprintf('Identifier "%s" does not contain an object definition.', $service));
            }

            return $this->values[$service] = function ($c) use ($closure, $factory) {
                return $closure($factory($c), $c);
            };
        }

        public function keys()
        {
            return array_keys($this->values);
        }
        
        /* ArrayAccess Methods */
        
        public function offsetSet($id, $value)
        {
            $this->values[$id] = $value;
        }

        public function offsetGet($id)
        {
            if (!array_key_exists($id, $this->values)) {
                throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
            }

            return $this->values[$id] instanceof Closure ? $this->values[$id]($this) : $this->values[$id];
        }

        public function offsetExists($id)
        {
            return array_key_exists($id, $this->values);
        }

        public function offsetUnset($id)
        {
            if (!array_key_exists($id, $this->values)) {
                throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
            }
            unset($this->values[$id]);
        }
    }
