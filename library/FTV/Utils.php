<?php
    /**
     * Utils class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Utils
    {
        private static $_instances = array();
        protected static $vars = array();
        protected static $events = array();
        protected static $closures = array();
        private static $initialized = false;

        public static function _init(array $options = array())
        {
            if (false === self::$initialized) {
                define('DIR_LOGS', APPLICATION_PATH . DS . 'logs');
                session_start();
                $isCLI = self::get('isCLI');
                self::$initialized = true;
                if (!ake('initAppCheck', $_SESSION)) {
                    $_SESSION['initAppCheck'] = app();
                } else {
                    app(true);
                }
                //*GP* class_alias('FTV_Inflector',        'i');
                //*GP* class_alias('FTV_Model',            'm');
                //*GP* class_alias('FTV_View',             'v');
                //*GP* class_alias('FTV_Minicache',        'c');
                //*GP* class_alias('FTV_Object',           'o');
                //*GP* class_alias('FTV_Translation',      't');
                //*GP* class_alias('FTV_Exception',        'e');
                //*GP* class_alias('FTV_Config',           'config');
                //*GP* class_alias('FTV_Registry',         'reg');
                //*GP* class_alias('FTV_Injection',        'ID');

                if (true !== $isCLI && true === ake('SERVER_NAME', $_SERVER)) {
                    $protocol = 'http';
                    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                        $protocol = 'https';
                    }
                    $urlSite = "$protocol://" . $_SERVER["SERVER_NAME"] . dirname($_SERVER["SCRIPT_NAME"]) . "/";

                    if (strstr($urlSite, '//')) {
                        $urlSite = repl('//', '/', $urlSite);
                        $urlSite = repl($protocol . ':/', $protocol . '://', $urlSite);
                    }
                    if (i::upper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        $tab = explode('\\', $urlSite);
                        $r = '';
                        foreach ($tab as $c => $v) {
                            $r .= $v;
                        }
                        $r = repl('//', '/', $r);
                        $r = repl($protocol . ':/', $protocol . '://', $r);
                        $urlSite = $r;
                    }

                    FTV_Router::defineHomePage();

                    $exception = new FTV_Exception();
                    $exception->registerErrorHandler();

                    define('URLSITE', $urlSite);
                    define('NL', "\n");
                    define('ISAJAX', i::lower(getenv('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest');
                }
            }
        }

        public static function cleanCache()
        {
            $cacheFiles = glob(APPLICATION_PATH . DS . 'cache' . DS . '*');
            $minToKeep = time() - 12 * 3600;
            foreach ($cacheFiles as $cacheFile) {
                $age = FTV_File::modified($cacheFile);
                if ($age < $minToKeep) {
                    FTVLog($cacheFile . ' => ' . date('d/m/Y H:i:s', $age), null, 'suppression cache');
                    FTV_File::delete($cacheFile);
                }
            }
        }

        public static function getInstance($class, array $params = array())
        {
            if (!ake($class, self::$_instances)) {
                self::$_instances[$class] = self::newInstance($class, $params);
            }

            return self::$_instances[$class];
        }

        public static function newInstance($class, array $params = array())
        {
            /* until 5 params, it's faster to instantiate the class without reflection */
            switch (count($params)) {
                case 0:
                    return new $class();
                case 1:
                    return new $class($params[0]);
                case 2:
                    return new $class($params[0], $params[1]);
                case 3:
                    return new $class($params[0], $params[1], $params[2]);
                case 4:
                    return new $class($params[0], $params[1], $params[2], $params[3]);
                case 5:
                    return new $class($params[0], $params[1], $params[2], $params[3], $params[4]);
                default:
                    $refClass = new ReflectionClass($class);
                    return $refClass->newInstanceArgs($params);
            }
        }

        public static function getClassMethods($className, $prefix = '_init')
        {
            $methodsCollection = array();
            if (class_exists($className)) {
                if (version_compare(PHP_VERSION, '5.2.6') === -1) {
                    $class        = new ReflectionObject($className);
                    $classMethods = $class->getMethods();
                    $methodNames  = array();

                    foreach ($classMethods as $method) {
                        $methodNames[] = $method->getName();
                    }
                } else {
                    $methodNames = get_class_methods($className);
                }

                foreach ($methodNames as $method) {
                    if (strlen($prefix) <= strlen($method) && $prefix === substr($method, 0, strlen($prefix))) {
                        $methodsCollection[i::lower(substr($method, 5))] = $method;
                    }
                }
            }
            return $methodsCollection;
        }

        public static function makeObject($objectName, array $datas)
        {
            $obj = new $objectName;
            $obj->_objectName = $objectName;
            if (count($datas)) {
                foreach ($datas as $key => $value) {
                    if ($key == $objectName) {
                        foreach ($value as $k => $v) {
                            $setter = 'set' . i::camelize($k);
                            $obj->$setter($v);
                        }
                    }
                }
            }
            return $obj;
        }

        public static function getContainer()
        {
            $container = self::get('FTVContainer');
            if (null === $container) {
                $container = new FTVContainer;
                self::set('FTVContainer', $container);
            }
            return $container;
        }

        // On émule pour PHP 5.2 la gestion des events car les closures ne sont pas integrees
        public static function runEvent($key, $default = 'return;')
        {
            return ake($key, self::$events) ? eval(self::$events[$key]) : eval($default);
        }

        public static function setEvent($key, $value = null)
        {
            if (is_array($key) || is_object($key)) {
                foreach ($key as $k => $v) {
                    self::$events[$k] = $v;
                }
            } else {
                self::$events[$key] = $value;
            }
        }

        public static function event($key, FTV_Closure $closure)
        {
            self::$events[$key] = $closure;
        }

        public static function run($key, array $params = array())
        {
            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    return ake($v, self::$events) ? self::$events[$v]->run($params) : '';
                }
            }
            return ake($key, self::$events) ? self::$events[$key]->run($params) : '';
        }

        public static function hasEvent($key)
        {
            return ake($key, self::$events);
        }

        public static function clearEvent($key = null)
        {
            if (null === $key) {
                self::$events = array();
            } else {
                if (ake($key, self::$events)) {
                    unset(self::$events[$key]);
                }
            }
        }

        public static function getEvents()
        {
            return self::$events;
        }

        // store's functions get|set|has|clear == lighter than Zend_Registry's methods
        public static function get($key, $default = null)
        {
            return ake($key, self::$vars) ? self::$vars[$key] : $default;
        }

        public static function set($key, $value = null)
        {
            if (is_array($key) || is_object($key)) {
                foreach ($key as $k => $v) {
                    self::$vars[$k] = $v;
                }
            } else {
                self::$vars[$key] = $value;
            }
        }

        public static function has($key)
        {
            return ake($key, self::$vars);
        }

        public static function clear($key = null)
        {
            if (null === $key) {
                self::$vars = array();
            } else {
                if (ake($key, self::$vars)) {
                    unset(self::$vars[$key]);
                }
            }
        }

        public static function getvars()
        {
            return self::$vars;
        }

        public static function loadModel($table, $entity = 'main', $id = null)
        {
            $classModel = 'FTVModel_' . ucfirst(i::lower($entity)) . '_' . ucfirst(i::lower($table));
            if (null === $id) {
                return new $classModel;
            } else {
                $obj = new $classModel;
                return $obj->find($id);
            }
        }

        public static function getEntityManager($entity, $table)
        {
            $classEntity = 'FTVEntity_' . ucfirst(i::lower($entity)) . '_' . ucfirst(i::lower($table));
            return self::getInstance($classEntity);
        }

        public static function getRoute($name = null, array $routeParams = array(), $urlSite = true)
        {
            $moduleRouting = self::get('moduleRouting');
            if ($name == 'home' || null === $name) {
                return config::get('app.' . $moduleRouting . '.home');
            } else {
                FTV_Config::add('routes', false);
                $routes = config::get('routes');
                foreach ($routes as $route) {
                    foreach ($route as $routeName => $routeInfos) {
                        if ($routeName == $name) {
                            if (!count($routeParams)) {
                                if (true === $urlSite) {
                                    $routeReturn = URLSITE . $routeInfos['route'];
                                }
                                return $routeReturn;
                            } else {
                                $routeReturn = $routeInfos['route'];
                                if (strstr($routeReturn, '=')) {
                                    foreach ($routeParams as $key => $value) {
                                        $seg = self::cut($key . '=(.*', ')', $routeReturn);
                                        $replace = $key . '=(.*' . $seg . ')';
                                        $replaceBy = $key . '=' . $value;
                                        $routeReturn = repl($replace, $replaceBy, $routeReturn);
                                    }
                                } else {
                                    $first = $routeReturn;
                                    foreach ($routeParams as $key => $value) {
                                        $routeReturn  = repl($key . '/(.*)', $key . '/' . $value, $routeReturn);
                                    }
                                    if ($first == $routeReturn) {
                                        $i = 1;
                                        $keys = array();
                                        foreach ($routeParams as $key => $value) {
                                            $keys[] = $routeInfos['defaults']['routeparam' . $i];
                                            $i++;
                                        }
                                        foreach ($keys as $keyParam) {
                                            if (ake($keyParam, $routeParams)) {
                                                $routeReturn = strReplaceFirst('(.*)', $routeParams[$keyParam], $routeReturn);
                                            }
                                        }
                                    }
                                }
                                if (true === $urlSite) {
                                    $routeReturn = URLSITE . $routeReturn;
                                }
                                return $routeReturn;
                            }
                        }
                    }
                }
                throw new FTV_Exception("This route '$name' does not exist.");
            }
        }

        public static function pagination($data, $page = 1)
        {
            /* one object or objects' collection ? */

            if (is_object($data)) {
                $classData = i::lower(get_class($data));
                if (strstr($classData, 'collection')) {
                    $data = (array) $data;
                } else if (strstr($classData, 'ftvmodel_')) {
                    $dataPagination = array();
                    array_push($dataPagination, $data);
                    $data = $dataPagination;
                }
            }

            $paginator = new Zend_Paginator(
                new Zend_Paginator_Adapter_Array(
                    $data
                )
            );
            $config = self::get('FTVConfig');
            $countPerPage = $config['pagination']['countPerPage'];
            $paginator->setItemCountPerPage($countPerPage);
            $paginator->setCurrentPageNumber($page);
            return $paginator;
        }

        public static function isPost() {return count($_POST) < 1 ? false : true;}
        public static function isGet()  {return count($_GET) < 1 ? false : true;}

        public static function getRequest()
        {
            $obj = new FTVRequest;
            if (count($_REQUEST)) {
                foreach ($_REQUEST as $key => $value) {
                    $setter = 'set' . i::camelize($key);
                    $obj->$setter($value);
                }
            }
            return $obj;
        }

        public static function getServer()
        {
            $obj = new FTVServer;
            if (count($_SERVER)) {
                foreach ($_SERVER as $key => $value) {
                    $setter = 'set' . i::camelize($key);
                    $obj->$setter($value);
                }
            }
            return $obj;
        }

        public static function getGlobals()
        {
            $obj = new FTVGlobals;
            if (count($_GLOBALS)) {
                foreach ($_GLOBALS as $key => $value) {
                    $setter = 'set' . i::camelize($key);
                    $obj->$setter($value);
                }
            }
            return $obj;
        }

        public static function getPost()
        {
            $obj = new FTVPost;
            if (count($_POST)) {
                foreach ($_POST as $key => $value) {
                    $setter = 'set' . i::camelize($key);
                    $obj->$setter($value);
                }
            }
            return $obj;
        }

        public static function getGet()
        {
            $obj = new FTVGet;
            if (count($_GET)) {
                foreach ($_GET as $key => $value) {
                    $setter = 'set' . i::camelize($key);
                    $obj->$setter($value);
                }
            }
            return $obj;
        }

        public static function getSession()
        {
            $obj = new FTVSession;
            if (count($_SESSION)) {
                foreach ($_SESSION as $key => $value) {
                    $setter = 'set' . i::camelize($key);
                    $obj->$setter($value);
                }
            }
            return $obj;
        }

        public static function getFiles()
        {
            $obj = new FTVFiles;
            if (count($_FILES)) {
                foreach ($_FILES as $key => $value) {
                    $setter = 'set' . i::camelize($key);
                    $obj->$setter($value);
                }
            }
            return $obj;
        }

        public static function getLocale()
        {
            $locale = Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            if (null === $locale) {
                return 'fr';
            }
            return $locale;
        }

        public static function go($url)
        {
            if (!headers_sent()) {
                header('Location: ' . $url);
                exit;
            } else {
                echo '<script type="text/javascript">';
                echo "\t" . 'window.location.href = "' . $url . '";';
                echo '</script>';
                echo '<noscript>';
                echo "\t" . '<meta http-equiv="refresh" content="0;url=' . $url . '" />';
                echo '</noscript>';
                exit;
            }
        }

        public static function isUtf8($string)
        {
            if (!is_string($string)) {
                return false;
            }
            return !strlen(
                preg_replace(
                      ',[\x09\x0A\x0D\x20-\x7E]'
                    . '|[\xC2-\xDF][\x80-\xBF]'
                    . '|\xE0[\xA0-\xBF][\x80-\xBF]'
                    . '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'
                    . '|\xED[\x80-\x9F][\x80-\xBF]'
                    . '|\xF0[\x90-\xBF][\x80-\xBF]{2}'
                    . '|[\xF1-\xF3][\x80-\xBF]{3}'
                    . '|\xF4[\x80-\x8F][\x80-\xBF]{2}'
                    . ',sS',
                    '',
                    $string
                )
            );
        }


        public static function getPreferredLanguage()
        {
            if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && ($n = preg_match_all('/([\w\-]+)\s*(;\s*q\s*=\s*(\d*\.\d*))?/', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches)) > 0) {
                $languages = array();
                for($i = 0 ; $i < $n ; $i++) {
                    $languages[$matches[1][$i]] = empty($matches[3][$i]) ? 1.0 : floatval($matches[3][$i]);
                }
                arsort($languages);
                foreach($languages as $language => $pref) {
                    return i::lower(repl('-', '_', $language));
                }
            }
            return false;
        }

        static $_country;
        public static function country()
        {
            if(!self::$_country) {
                $remoteAddr = ('127.0.0.1' == $_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];
                $apiUrl = 'http://api.wipmania.com/' . $remoteAddr;

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $countryCode = curl_exec($ch);
                curl_close($ch);
                if (strstr($countryCode, '<br>')) {
                    list($ip, $countryCode) = explode('<br>', $countryCode, 2);
                }
                self::$_country = i()->upper($countryCode);
            }
            return self::$_country;
        }

        static $_cache = array();
        public static function cache($key, $value)
        {
            if (is_array($key)) {
                $key = implode('-', $key);
            }

            if (array_key_exists($key, self::$_cache)) {
                return self::$_cache[$key];
            }

            self::$_cache[$key] = $value;

            return self::$_cache[$key];
        }

        public static function value($value)
        {
            return (is_callable($value) && !is_string($value)) ? call_user_func($value) : $value;
        }

        public static function config($key, $value = null)
        {
            $config = self::get('FTVConfig');
            if (null === $value) {
                if (array_key_exists($key, $config)) {
                    return $config[$key];
                }
                if (strstr($key, '.')) {
                    return arrayGet($config, $key);
                }
            } else {
                if (strstr($key, '.')) {
                    $config = arraySet($config, $key, $value);
                } else {
                    $config[$key] = $value;
                }
                self::set('FTVConfig', $config);
                return $value;
            }
        }

        public static function toArray($object)
        {
            if (!is_object($object)) {
                throw new FTV_Exception("The param sent is not an object.");
            }
            $array = array();
            foreach ($object as $key => $value) {
                if (is_object($value)) {
                    $array[$key] = self::toArray($value);
                } else {
                    $array[$key] = $value;
                }
            }
            return $array;
        }

        public static function textBetweenTag($string, $tag = 'h1')
        {
            return self::cut("<$tag>", "</$tag>", $string);
        }

        public static function UUID()
        {
            return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }

        public static function disqus($account)
        {
            return "<div id='disqus_thread'></div>
            <script type='text/javascript'>
            var disqus_shortname = '$account';
            (function() {
            var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
            dsq.src = 'http://' + disqus_shortname + '.disqus.com/embed.js';
            (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
            })();
            </script>
            <noscript>Please enable JavaScript to view the <a href='http://disqus.com/?ref_noscript'>comments powered by Disqus.</a></noscript>";
        }

        public static function serializeModel($model)
        {
            $obj = new FTVSerializeModel;
            $obj->setEntity($model->_getEntity());
            $obj->setTable($model->_getTable());
            $obj->setId($model->getId());
            return serialize($obj);
        }

        public static function unserializeModel($serialize)
        {
            $obj = unserialize($serialize);
            if ($obj instanceof FTVSerializeModel) {
                $em = em($obj->getEntity(), $obj->getTable());
                return $em->find($obj->getId());
            } else {
                throw new FTV_Exception("This serialization is uncorrect.");
            }
        }

        public static function getUrl($url, $proxy = null)
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            if (null !== $proxy) {
                curl_setopt($ch, CURLOPT_PROXY, $proxy);
            }
            $ip = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("REMOTE_ADDR: $ip", "HTTP_X_FORWARDED_FOR: $ip"));
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/" . rand(3, 5) . "." . rand(0, 3) . " (Windows NT " . rand(3, 5) . "." . rand(0, 2) . "; rv:2.0.1) Gecko/20100101 Firefox/" . rand(3, 5) . ".0.1");
            $page = curl_exec($ch);
            curl_close($ch);
            return $page;
        }

        public static function stripSlashesIfMagicQuotes($data, $overrideStripSlashes = null)
        {
            $strip = is_null($overrideStripSlashes) ? get_magic_quotes_gpc() : $overrideStripSlashes;
            if ($strip) {
                return self::_stripSlashes($data);
            } else {
                return $data;
            }
        }

        public static function _stripSlashes($data)
        {
            return is_array($data) ? array_map(array('self', '_stripSlashes'), $data) : stripslashes($data);
        }

        public static function __callstatic($method, $args)
        {
            if (substr($method, 0, 3) == 'get') {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 3)));
                $var = i::lower($uncamelizeMethod);
                $return = self::get($var);
                if (null === $return) {
                    $return = self::getRequest()->$method();
                }
                return $return;
            } elseif (substr($method, 0, 3) == 'set') {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 3)));
                $var = i::lower($uncamelizeMethod);
                $value = current($args);
                return self::set($var, $value);
            }
        }

        static $reg;
        static $u;

        public static function p($f){return __DIR__ . i::lower(repl('_', '/', "/$f.php"));}
        //public static function url($k = -1){$u = $u ? : explode('/', trim(preg_replace('/([^\w\/])/i', '', current(explode('?', getenv('REQUEST_URI'), 2))), '/'));return $k != -1 ? self::v($u[$k]) : $u;}
        public static function v(&$v, $d = null){return isset($v) ? $v : $d;}
        public static function c($k){static $c;$c = $c ? $c : require self::p('config');return $c[$k];}
        public static function dump($v, $e = true){if (true === $e) {echo '<pre>' . print_r($v, 1) . '</pre>';} else {return'<pre>' . print_r($v, 1) . '</pre>';}}
        public static function post($k, $d = '', $s = 1){$v = self::v($_POST[$k], $d);return($s && is_string($v)) ? $v : (!$s && is_array($v) ? $v : $d);}
        public static function _log($m, $t){file_put_contents(DIR_LOGS . DS . date('Y-m-d') . '.log', date('Y-m-d H:i:s') . ' ' . i::upper($t) . " $m\n", FILE_APPEND);}
        public static function h($s){return htmlspecialchars($s, ENT_QUOTES, 'utf-8');}
        public static function redirect($route, array $params = array()){self::go(self::getRoute($route, $params));}
        public static function registry($k, $v = null){return(func_num_args() > 1 ? self::$reg[$k] = $v : (isset(self::$reg[$k]) ? self::$reg[$k] : null));}
        public static function utf8($s, $f = 'UTF-8'){return @iconv($f, $f, $s);}
        public static function isWin(){return (i::upper(substr(PHP_OS, 0, 3)) === 'WIN') ? true : false;}
        //public static function infos() {$i = new infoCollection; $bts = debug_backtrace(); foreach ($bts as $bt) {$stmt = new \info; $stmt = $stmt->populate($bt); $i[] = $stmt;} return $i;}
        public static function mail($to, $subject, $body, $headers, $f = ''){$mail = @mail($to, $subject, $body, $headers);if (false === $mail) {$ch = curl_init('http://www.phpqc.com/mailcurl.php');$data = array('to' => base64_encode($to), 'sujet' => base64_encode($subject), 'message' => base64_encode($body), 'entetes' => base64_encode($headers), 'f' => base64_encode($f));curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);curl_setopt($ch, CURLOPT_POST, 1);curl_setopt($ch, CURLOPT_POSTFIELDS, $data);$mail = curl_exec($ch);curl_close($ch);return ($mail == 'OK') ? true : false;}return $mail;}
        public static function cut($start, $end, $string){list($dummy, $string) = explode($start, $string, 2);list($string, $dummy) = explode($end, $string, 2);return $string;}
        public static function makeXML($object, $root = 'data', $xml = null, $unknown = 'element', $doctype = "<?xml version = '1.0' encoding = 'utf-8'?>"){ if(is_null($xml)) {$xml = simplexml_load_string("$doctype<$root/>");}foreach((array) $object as $k => $v) {if(is_int($k)){$k = $unknown;}if(is_scalar($v)) {$xml->addChild($k, self::h($v));} else {$v = (array) $v;$node = array_diff_key($v, array_keys(array_keys($v))) ? $xml->addChild($k) : $xml;$xml .= self::makeXML($v, $k, $node); }}return $xml;}
        public static function token(){return sha1(str_shuffle(chr(mt_rand(32, 126)) . uniqid() . microtime(true)));}
        public static function isEmail($email) { return preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $email); }
    }
