<?php
    function FTVAutoloader($className)
    {
        static $classes = array();
        if (strstr($className, 'AJF')) {
            $replace = str_replace('AJF', 'FTV', $className);
            class_alias($replace, $className);
        }
        if (!array_key_exists($className, $classes)) {
            $classes[$className] = true;
            if (substr($className, 0, 4) == 'Zend') {
            } elseif (substr($className, 0, 7) == 'PHPUnit') {
            } elseif (substr($className, 0, 8) == 'Doctrine') {
            } elseif (substr($className, 0, 18) == 'Services_Zencoder_') {
                $file = str_replace('_', '/', $className);
                $file = str_replace('Services/Zencoder/', '', $file);
                return include LIBRARIES_PATH . DS . 'Zencoder' . DS . $file . '.php';
            } elseif (substr($className, 0, 17) == 'Services_Zencoder') {
                $file = str_replace('_', '/', $className);
                $file = str_replace('Services/', '', $file);
                return include LIBRARIES_PATH . DS . 'Zencoder' . DS . $file . '.php';
            } elseif (substr($className, 0, strlen('FTVTrait_')) == 'FTVTrait_') {
                list($ns, $class) = explode('FTVTrait_', $className, 2);
                $file = LIBRARIES_PATH . DS . 'FTV' . DS . 'trait.' . lcfirst($class) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                } else {
                    throw new Exception("This class '$className' does not exist.");
                }
            } elseif (substr($className, 0, 4) == 'FTV_') {
                list($ns, $class) = explode('FTV_', $className, 2);
                $file = LIBRARIES_PATH . DS . 'FTV' . DS . 'class.' . lcfirst($class) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                } else {
                    $classDir = str_replace('_', DS, $class);
                    $file = LIBRARIES_PATH . DS . 'FTV' . DS . $classDir . '.php';
                    if (file_exists($file)) {
                        require_once $file;
                    } else {
                        throw new Exception("This class '$className' does not exist.");
                    }
                }
            } elseif (substr($className, 0, strlen('FTVService_')) == 'FTVService_') {
                list($ns, $class) = explode('FTVService_', $className, 2);
                $classDir = str_replace('_', DS, $class);
                $file = APPLICATION_PATH . DS . 'services' . DS . $classDir . '.php';
                if (file_exists($file)) {
                    require_once $file;
                } else {
                    throw new Exception("This service class '$file' does not exist.");
                }
            } elseif (substr($className, 0, strlen('FTVHelper_')) == 'FTVHelper_') {
                list($ns, $class) = explode('FTVHelper_', $className, 2);
                $classDir = str_replace('_', DS, $class);
                $file = APPLICATION_PATH . DS . 'helpers' . DS . $classDir . '.php';
                if (file_exists($file)) {
                    require_once $file;
                } else {
                    throw new Exception("This helper class '$className' does not exist.");
                }
            } elseif (substr($className, 0, strlen('FTVPlugin_')) == 'FTVPlugin_') {
                list($ns, $class) = explode('FTVPlugin_', $className, 2);
                $classDir = str_replace('_', DS, $class);
                $file = APPLICATION_PATH . DS . 'plugins' . DS . $classDir . '.php';
                if (file_exists($file)) {
                    require_once $file;
                } else {
                    throw new Exception("This plugin class '$className' does not exist.");
                }
            } elseif (substr($className, 0, strlen('FTVForm_')) == 'FTVForm_') {
                list($ns, $class) = explode('FTVForm_', $className, 2);
                $classDir = str_replace('_', DS, $class);
                $file = APPLICATION_PATH . DS . 'forms' . DS . $classDir . '.php';
                if (file_exists($file)) {
                    require_once $file;
                } else {
                    throw new Exception("This plugin class '$className' does not exist.");
                }
            } elseif (substr($className, 0, strlen('FTVEntity_')) == 'FTVEntity_') {
                list($ns, $class) = explode('FTVEntity_', $className, 2);
                $classDir = str_replace('_', DS, $class);
                $file = APPLICATION_PATH . DS . 'entities' . DS . $classDir . '.php';
                if (file_exists($file)) {
                    require_once $file;
                } else {
                    $modelClass = str_replace('Entity_', 'Model_', $className);
                    eval("class $className extends $modelClass {}");
                }
            } elseif (substr($className, 0, strlen('FTVModelOracle_')) == 'FTVModelOracle_') {
                if (!class_exists($className)) {
                    eval("class $className extends FTV_Oracle {public function __construct(\$id = null) { list(\$dummy, \$this->_entity, \$this->_table) = explode('_', strtolower(get_class(\$this)), 3); \$this->factory(); if (null === \$id) {return \$this;} else {return \$this->find(\$id);}}}");
                }
            } elseif (substr($className, 0, strlen('FTVModel_')) == 'FTVModel_') {
                if (!class_exists($className)) {
                    eval("class $className extends FTV_Model {public function __construct(\$id = null) { list(\$dummy, \$this->_entity, \$this->_table) = explode('_', strtolower(get_class(\$this)), 3); \$this->factory(); if (null === \$id) {\$this->foreign(); return \$this;} else {return \$this->find(\$id);}}}");
                }
            }  else {
                if (!class_exists($className) && !strstr(strtolower($className), 'doctrine') && !strstr(strtolower($className), 'symfony') && !strstr(strtolower($className), 'zend')) {
                    if (strstr($className, '_')) {
                        $classDir = str_replace('_', DS, $className);
                        $file = LIBRARIES_PATH . DS . $classDir . '.php';
                        if (file_exists($file)) {
                            require_once $file;
                            return;
                        }
                    }
                    $addLoadMethod = '';
                    if (strstr($className, 'ResultModelCollection')) {
                        $addLoadMethod = 'public function first() {return $this->cursor(1);} public function last() {return $this->cursor(count($this));} public function cursor($key) {$val = $key - 1; return $this[$val];} public function load(){$coll = $this->_args[0][0];$pk = $coll->pk();$objId = $coll->$pk;return $coll->find($objId);}';
                    }
                    eval("class $className extends FTV_Object {public static function getNew() {return new self(func_get_args());}public static function getInstance() {return FTV_Utils::getInstance($className, func_get_args());} public function getArg(\$key){if (isset(\$this->_args[0][\$key])) {return \$this->_args[0][\$key];} return null;}$addLoadMethod}");
                }
            }
        }
    }
    spl_autoload_register('FTVAutoloader');
    require_once 'Helper.php';

    class_alias('FTV_Utils', 'u');
