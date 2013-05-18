<?php
    /**
     * Bootstrap class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Bootstrap
    {
        protected $_application;
        protected $_classResources;
        protected $_container;
        protected $_environment;
        protected $_optionKeys = array();
        protected $_options = array();
        protected $_pluginLoader;
        protected $_pluginResources = array();
        protected $_run = array();
        protected $_started = array();

        public function __construct(FTV_Application $application)
        {
            FTV_Timer::start();
            $this->setApplication($application);
            $options = $application->getOptions();
            $this->setOptions($options);
        }

        public function setApplication($application)
        {
            if (($application instanceof FTV_Application) || ($application instanceof FTV_Bootstrap)) {
                if ($application === $this) {
                    throw new FTV_Exception('Cannot set application to same object; creates recursion');
                }
                $this->_application = $application;
            } else {
                throw new FTV_Exception('Invalid application provided to bootstrap constructor (received "' . get_class($application) . '" instance)');
            }
            return $this;
        }

        public function setOptions(array $options)
        {
            $this->_options = $this->mergeOptions($this->_options, $options);

            $options = array_change_key_case($options, CASE_LOWER);
            $this->_optionKeys = array_merge($this->_optionKeys, array_keys($options));

            $methods = get_class_methods($this);
            foreach ($methods as $key => $method) {
                $methods[$key] = i()->lower($method);
            }

            foreach ($options as $key => $value) {
                $method = 'set' . i()->lower($key);

                if (in_array($method, $methods)) {
                    $this->$method($value);
                }
            }
            return $this;
        }

        public function getOptions()
        {
            return $this->_options;
        }

        public function hasOption($key)
        {
            return in_array(i()->lower($key), $this->_optionKeys);
        }

        public function getOption($key)
        {
            if ($this->hasOption($key)) {
                $options = $this->getOptions();
                $options = array_change_key_case($options, CASE_LOWER);
                return $options[i()->lower($key)];
            }
            return null;
        }

        public function mergeOptions(array $array1, $array2 = null)
        {
            if (is_array($array2)) {
                foreach ($array2 as $key => $val) {
                    if (is_array($array2[$key])) {
                        $array1[$key] = (array_key_exists($key, $array1) && is_array($array1[$key]))
                                      ? $this->mergeOptions($array1[$key], $array2[$key])
                                      : $array2[$key];
                    } else {
                        $array1[$key] = $val;
                    }
                }
            }
            return $array1;
        }

        final public function bootstrap($resource = null)
        {
            $this->_bootstrap($resource);
            return $this;
        }

        protected function _bootstrap($resource = null)
        {
            if (null === $resource) {
                foreach ($this->getClassResourceNames() as $resource) {
                    $this->_executeResource($resource);
                }
            } elseif (is_string($resource)) {
                $this->_executeResource($resource);
            } elseif (is_array($resource)) {
                foreach ($resource as $r) {
                    $this->_executeResource($r);
                }
            } else {
                throw new FTV_Exception('Invalid argument passed to ' . __METHOD__);
            }
        }

        public function getClassResources()
        {
            if (null === $this->_classResources) {
                if (version_compare(PHP_VERSION, '5.2.6') === -1) {
                    $class        = new ReflectionObject($this);
                    $classMethods = $class->getMethods();
                    $methodNames  = array();

                    foreach ($classMethods as $method) {
                        $methodNames[] = $method->getName();
                    }
                } else {
                    $methodNames = get_class_methods($this);
                }

                $this->_classResources = array();
                foreach ($methodNames as $method) {
                    if (5 < strlen($method) && '_init' === substr($method, 0, 5)) {
                        $this->_classResources[i()->lower(substr($method, 5))] = $method;
                    }
                }
            }

            return $this->_classResources;
        }

        protected function _executeResource($resource)
        {
            $resourceName = i()->lower($resource);

            if (in_array($resourceName, $this->_run)) {
                return;
            }

            if (isset($this->_started[$resourceName]) && $this->_started[$resourceName]) {
                throw new FTV_Exception('Circular resource dependency detected');
            }

            $classResources = $this->getClassResources();
            if (array_key_exists($resourceName, $classResources)) {
                $this->_started[$resourceName] = true;
                $method = $classResources[$resourceName];
                $return = $this->$method();
                unset($this->_started[$resourceName]);
                $this->_markRun($resourceName);

                if (null !== $return) {
                    $this->getContainer()->{$resourceName} = $return;
                }

                return;
            }

            throw new FTV_Exception('Resource matching "' . $resource . '" not found');
        }

        public function getClassResourceNames()
        {
            $resources = $this->getClassResources();
            return array_keys($resources);
        }

        public function getApplication()
        {
            return $this->_application;
        }

        public function getEnvironment()
        {
            if (null === $this->_environment) {
                $this->_environment = $this->getApplication()->getEnvironment();
            }
            return $this->_environment;
        }

        public function setContainer($container)
        {
            if (!is_object($container)) {
                throw new FTV_Exception('Resource containers must be objects');
            }
            $this->_container = $container;
            return $this;
        }

        public function getContainer()
        {
            if (null === $this->_container) {
                $this->setContainer(new FTVContainer);
            }
            return $this->_container;
        }

        public function hasResource($key)
        {
            $resource  = i()->lower($key);
            $container = $this->getContainer();
            return isset($container->{$resource});
        }

        public function getResource($key)
        {
            $resource  = i()->lower($key);
            $container = $this->getContainer();
            if ($this->hasResource($resource)) {
                return $container->{$resource};
            }
            return null;
        }

        public function run()
        {

        }

        protected function _markRun($resource)
        {
            if (!in_array($resource, $this->_run)) {
                $this->_run[] = $resource;
            }
        }

        public function __call($func, $argv)
        {
            if (9 < strlen($func) && 'bootstrap' === substr($func, 0, 9)) {
                $resource = substr($func, 9);
                return $this->bootstrap($resource);
            }
            if (substr($func, 0, 3) == 'get') {
                $uncamelizeMethod = i()->uncamelize(lcfirst(substr($func, 3)));
                $var = i()->lower($uncamelizeMethod);
                if (isset($this->$var)) {
                    return $this->$var;
                } else {
                    return null;
                }
            } elseif (substr($func, 0, 3) == 'set') {
                $value = $argv[0];
                $uncamelizeMethod = i()->uncamelize(lcfirst(substr($func, 3)));
                $var = i()->lower($uncamelizeMethod);
                $this->$var = $value;
                return $this;
            }
            if (!is_callable($func) || substr($func, 0, 3) !== 'set' || substr($func, 0, 3) !== 'get') {
                throw new BadMethodCallException(__class__ . ' => ' . $func);
            }
            return call_user_func_array($func, array_merge(array($this->getArrayCopy()), $argv));
        }

        public function __set($key, $value)
        {
            $this->$key = $value;
            return $this;
        }

        public function __get($key)
        {
            return $this->getResource($key);
        }

        public function __isset($prop)
        {
            return $this->hasResource($prop);
        }

        public function __destruct()
        {
            FTV_Timer::stop();
            $executionTime = FTV_Timer::get();
            $queries = u::get('FTVNbQueries');
            $totalDuration = u::get('FTVSQLTotalDuration');
            u::run('bootstrap.finished', array('time' => $executionTime, 'queries' => $queries, 'sql_duration' => $totalDuration));
        }
    }
