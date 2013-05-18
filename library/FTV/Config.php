<?php
    class FTV_Config
    {
        public static function get($key, $default = null)
        {
            $value = config($key);
            return (!empty($value)) ? $value : $default;
        }

        public static function set($key, $value = null)
        {
            return config($key, $value);
        }

        public static function all()
        {
            return config();
        }

        public static function add($conf, $environment = true)
        {
            if (null === config::get($conf)) {
                $file = APPLICATION_PATH . DS . 'configs' . DS . $conf . '.php';
                if (file_exists($file)) {
                    $config = include($file);
                    if (is_array($config)) {
                        foreach ($config as $k => $v) {
                            self::set($conf . '.' . $k, $v);
                        }
                    }
                } else {
                    $file = APPLICATION_PATH . DS . 'configs' . DS . $conf . '.ini';
                    if (file_exists($file)) {
                        if (true === $environment) {
                            $config = new Zend_Config_Ini($file, APPLICATION_ENV);
                        } else {
                            $config = new Zend_Config_Ini($file);
                        }
                        $config = $config->toArray();
                        foreach ($config as $k => $v) {
                            self::set($conf . '.' . $k, $v);
                        }
                    }
                }
            }
        }

        public static function moduleRoutes()
        {
            $config = u::get('FTVConfig');
            if (!ake('routes', $config)) {
                return;
            }
            $modules = glob(APPLICATION_PATH . DS . 'modules' . DS . '*');

            /* On scanne tous les modules pour recuperer les routes */

            foreach ($modules as $module) {
                $config = u::get('FTVConfig');
                $routes = $config['routes'];
                $module = repl(APPLICATION_PATH . DS . 'modules' . DS, '', $module);
                $file   = APPLICATION_PATH . DS . 'modules' . DS . ucfirst(i::lower($module)) . DS . 'configs' . DS . 'routes.ini';
                if (file_exists($file)) {
                    $length = strlen(fgc($file));
                    if (0 < $length) {
                        $newRoutes = array();
                        $moduleRoutes = new Zend_Config_Ini($file);

                        if (0 < $moduleRoutes->count()) {
                            $tab = $moduleRoutes->toArray();

                            if (is_array($tab) && is_array($routes)) {
                                $newRoutes = array('routes' => current($routes) + current($tab));
                                $config['routes'] = $newRoutes;
                                u::set('FTVConfig', $config);
                            }
                        }
                    }
                }
            }
        }

        public static function moduleConfig()
        {
            self::moduleRoutes();
            $module         = u::get('FTVModuleName');
            $file           = APPLICATION_PATH . DS . 'modules' . DS . ucfirst(i::lower($module)) . DS . 'configs' . DS . 'application.ini';
            if (file_exists($file)) {
                $config         = u::get('FTVConfig');
                $moduleConfig = new Zend_Config_Ini($file, APPLICATION_ENV);
                $tab          = $moduleConfig->toArray();
                $newConfig    = array('module' => array(ucfirst(i::lower($module)) => $tab));
                $config       = $config + $newConfig;
                u::set('FTVConfig', $config);
            }
        }

        public static function defined($var = null)
        {
            $defined = new FTVDefined;
            $defines = get_defined_constants();
            $defined->populate($defines, 'defined');
            if (null !== $var) {
                return (true === ake($var, $defines)) ? $defines[$var] : null;
            }
            return $defined;
        }

        public static function env()
        {
            return APPLICATION_ENV;
        }

        public static function __callStatic($method, $args)
        {
            if (substr($method, 0, 3) == 'set') {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 3)));
                $var = repl('_', '.', i::lower($uncamelizeMethod));
                return self::set($var, $args[0]);
            } elseif (substr($method, 0, 3) == 'get') {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 3)));
                $var = repl('_', '.', i::lower($uncamelizeMethod));
                return self::get($var);
            }
        }
    }
