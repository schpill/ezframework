<?php
    /**
     * Application class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Application
    {
        protected $_autoloader;

        protected $_bootstrap;

        protected $_environment;

        protected $_optionKeys = array();

        protected $_options = array();

        public function __construct($environment, $options = null)
        {
            $this->_environment = (string) $environment;

            require_once 'Zend/Loader/Autoloader.php';
            $this->_autoloader = Zend_Loader_Autoloader::getInstance();

            if (null !== $options) {
                if (is_string($options)) {
                    $options = $this->_loadConfig($options);
                } elseif ($options instanceof Zend_Config) {
                    $options = $options->toArray();
                } elseif (!is_array($options)) {
                    throw new FTV_Exception('Invalid options provided; must be location of config file, a config object, or an array');
                }

                $this->setOptions($options);
            }
        }


        public function getEnvironment()
        {
            return $this->_environment;
        }


        public function getAutoloader()
        {
            return $this->_autoloader;
        }

        public function setOptions(array $options)
        {
            if (!empty($options['config'])) {
                if (is_array($options['config'])) {
                    $_options = array();
                    foreach ($options['config'] as $tmp) {
                        $_options = $this->mergeOptions($_options, $this->_loadConfig($tmp));
                    }
                    $options = $this->mergeOptions($_options, $options);
                } else {
                    $options = $this->mergeOptions($this->_loadConfig($options['config']), $options);
                }
            }

            $this->_options = $options;

            $options = array_change_key_case($options, CASE_LOWER);

            $this->_optionKeys = array_keys($options);

            if (!empty($options['phpsettings'])) {
                $this->setPhpSettings($options['phpsettings']);
            }

            if (!empty($options['includepaths'])) {
                $this->setIncludePaths($options['includepaths']);
            }

            if (!empty($options['autoloadernamespaces'])) {
                $this->setAutoloaderNamespaces($options['autoloadernamespaces']);
            }

            if (!empty($options['autoloaderzfpath'])) {
                $autoloader = $this->getAutoloader();
                if (method_exists($autoloader, 'setZfPath')) {
                    $zfPath    = $options['autoloaderzfpath'];
                    $zfVersion = !empty($options['autoloaderzfversion'])
                               ? $options['autoloaderzfversion']
                               : 'latest';
                    $autoloader->setZfPath($zfPath, $zfVersion);
                }
            }

            if (!empty($options['bootstrap'])) {
                $bootstrap = $options['bootstrap'];

                if (is_string($bootstrap)) {
                    $this->setBootstrap($bootstrap);
                } elseif (is_array($bootstrap)) {
                    if (empty($bootstrap['path'])) {
                        throw new FTV_Exception('No bootstrap path provided');
                    }

                    $path  = $bootstrap['path'];
                    $class = null;

                    if (!empty($bootstrap['class'])) {
                        $class = $bootstrap['class'];
                    }

                    $this->setBootstrap($path, $class);
                } else {
                    throw new FTV_Exception('Invalid bootstrap information provided');
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
            return in_array(strtolower($key), $this->_optionKeys);
        }

        public function getOption($key)
        {
            if ($this->hasOption($key)) {
                $options = $this->getOptions();
                $options = array_change_key_case($options, CASE_LOWER);
                return $options[strtolower($key)];
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


        public function setPhpSettings(array $settings, $prefix = '')
        {
            foreach ($settings as $key => $value) {
                $key = empty($prefix) ? $key : $prefix . $key;
                if (is_scalar($value)) {
                    ini_set($key, $value);
                } elseif (is_array($value)) {
                    $this->setPhpSettings($value, $key . '.');
                }
            }

            return $this;
        }

        public function setIncludePaths(array $paths)
        {
            $path = implode(PS, $paths);
            set_include_path($path . PATH_SEPARATOR . get_include_path());
            return $this;
        }

        public function setAutoloaderNamespaces(array $namespaces)
        {
            $autoloader = $this->getAutoloader();

            foreach ($namespaces as $namespace) {
                $autoloader->registerNamespace($namespace);
            }

            return $this;
        }

        public function setBootstrap($path, $class = null)
        {
            if (null === $class) {
                $class = 'FTV_Bootstrap';
            }

            if (!class_exists($class, false)) {
                require_once $path;
                if (!class_exists($class, false)) {
                    throw new FTV_Exception('Bootstrap class not found');
                }
            }
            $this->_bootstrap = new $class($this);

            if (!$this->_bootstrap instanceof FTV_Bootstrap) {
                throw new FTV_Exception('Bootstrap class does not implement FTV_Bootstrap');
            }

            return $this;
        }

        public function getBootstrap()
        {
            if (null === $this->_bootstrap) {
                $this->_bootstrap = new FTV_Bootstrap($this);
            }
            return $this->_bootstrap;
        }

        public function bootstrap($resource = null)
        {
            $this->getBootstrap()->bootstrap($resource);
            return $this;
        }

        public function run()
        {
            $this->getBootstrap()->run();
        }


        protected function _loadConfig($file)
        {
            $environment = $this->getEnvironment();
            $suffix      = pathinfo($file, PATHINFO_EXTENSION);
            $suffix      = ($suffix === 'dist')
                         ? pathinfo(basename($file, ".$suffix"), PATHINFO_EXTENSION)
                         : $suffix;

            switch (i()->lower($suffix)) {
                case 'ini':
                    $config = new Zend_Config_Ini($file, $environment);
                    break;

                case 'xml':
                    $config = new Zend_Config_Xml($file, $environment);
                    break;

                case 'json':
                    $config = new Zend_Config_Json($file, $environment);
                    break;

                case 'yaml':
                case 'yml':
                    $config = new Zend_Config_Yaml($file, $environment);
                    break;

                case 'php':
                case 'inc':
                    $config = include $file;
                    if (!is_array($config)) {
                        throw new FTV_Exception('Invalid configuration file provided; PHP file does not return array value');
                    }
                    return $config;
                    break;

                default:
                    throw new FTV_Exception('Invalid configuration file provided; unknown config type');
            }

            return $config->toArray();
        }

        public function __call($func, $argv)
        {
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

        public function __set($name, $value)
        {
            $this->$name = $value;
            return $this;
        }

        public function __get($name)
        {
            if (isset($this->$name)) {
                return $this->$name;
            }
            return null;
        }
    }
