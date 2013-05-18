<?php
    class FTV_Router
    {
        private $_routes;
        private $_uri = null;

        public function __construct()
        {
            FTV_Config::add('routes', false);
            FTV_Config::moduleRoutes();
            $this->_routes = FTV_Config::get('routes');
            $this->getUri();
        }

        public function getUri()
        {
            if (null === $this->_uri) {
                $this->_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

                if (strlen(dirname($_SERVER["SCRIPT_NAME"])) > 1) {
                    $this->_uri = repl(dirname($_SERVER["SCRIPT_NAME"]), '', $this->_uri);
                }

                if ($this->_uri[0] == '/') {
                    $this->_uri = substr($this->_uri, 1);
                }

                if (!strlen($this->_uri)) {
                    $moduleRouting = u::get('moduleRouting');
                    $this->_uri = FTV_Config::get('app.' . $moduleRouting . '.home');
                 }
            }
            return $this->_uri;
        }

        public function dispatch()
        {
            foreach ($this->_routes as $route) {
                foreach ($route as $routeName => $routeInfos) {
                    $path = $routeInfos['route'];

                    $routeFind = $path;
                    if (strstr($path, '(.*)') && !strstr($this->_uri, '?p=')) {
                        if (strstr($path, '/') && strstr($this->_uri, '/')) {
                            $tabCountParams = explode('(.*)', $path);
                            $tabCountPath   = explode('/', $path);
                            $tabCountUri    = explode('/', $this->_uri);

                            $firstWord      = current($tabCountUri);
                            $lastWord       = end($tabCountUri);
                            $firstWordPath  = current($tabCountPath);
                            $lastWordPath   = end($tabCountPath);

                            if (ake('routeparams', $routeInfos['defaults']) && $firstWord == $firstWordPath) {
                                if ($routeInfos['defaults']['routeparams'] == 'all') {
                                    for ($k = 1 ; $k < count($tabCountUri) ; $k += 2) {
                                        $tmpParam = $tabCountUri[$k];
                                        $_REQUEST[$tmpParam] = $valParam = $tabCountUri[$k + 1];
                                    }
                                    return $this->make($routeInfos);
                                }
                            }

                            /**
                             * Correction
                             * Par : AGE et GPL
                             * Le  : 18/04/2012
                             * Correction du routing pour les url contenant un querystring du type
                             *  exemple : embed/getUrl?urlembed=(.*)&referer=(.*)
                             */
                            //$mustMatchLastWord = ('(.*)' != $lastWordPath) ? true : false;
                            $mustMatchLastWord = ('(.*)' != $lastWordPath && !strstr($lastWordPath, '?')) ? true : false;
                            /** FIN Correction */


                            $matchAll = $this->matchAll($path);

                            $matching = count($tabCountPath) === count($tabCountUri) && strstr($this->_uri, current($tabCountParams)) && true === $matchAll;
                            if (true === $matching) {
                                if (true === $mustMatchLastWord) {
                                    if ($lastWordPath != $lastWord) {
                                        continue;
                                    }
                                }
                                $assign = $this->assign($routeInfos);
                                if (false === $assign) {
                                    break;
                                }
                                return $assign;
                            }
                        }
                    } elseif (strstr($path, '(.*)') && strstr($this->_uri, '?p=')) {
                        $tabPath = explode('(.*)', $path);
                        $numParam = 1;
                        for ($i = 0 ; $i < count($tabPath) ; $i++) {
                            $seg = trim($tabPath[$i]);
                            $indexParam = 'routeparam' . ($numParam);
                            $indexParamRegexp = 'routeparam' . ($numParam) . 'Regexp';
                            $param = (ake($indexParam, $routeInfos['defaults'])) ? $routeInfos['defaults'][$indexParam] : null;

                            $indexParamFunc = 'routeparam' . ($numParam) . 'Function';
                            $paramFunc = '';
                            if(ake($indexParamFunc, $routeInfos['defaults'])){
                                $paramFunc = $routeInfos['defaults'][$indexParamFunc];
                            }

                            if (ake($param, $_REQUEST)) {
                                $continue = true;
                                if (ake($indexParamRegexp, $routeInfos['defaults'])) {
                                    $regexp = $routeInfos['defaults'][$indexParamRegexp];
                                    $continue = preg_match('#^' . $regexp . '#i', $_REQUEST[$param], $matches);
                                    if (false === $continue) {
                                        $routeFind = false;
                                        break;
                                    }
                                }
                                if (true == $continue) {
                                    $routeFind = repl($seg . '(.*)', $seg . ((!empty($paramFunc)) ? call_user_func($paramFunc, $_REQUEST[$param]) : $_REQUEST[$param]), $routeFind);
                                }
                            }
                            $numParam++;
                        }
                        if ($routeFind == $this->_uri) {
                            return $this->make($routeInfos);
                        }
                    } else {
                        if ($path == $this->_uri) {
                            return $this->make($routeInfos);
                        }
                    }
                }
            }
        }

        public function matchAll($path)
        {
            $tab = explode('(.*)', $path);
            foreach ($tab as $seg) {
                if (!empty($seg)) {
                    if (!strstr($this->_uri, $seg)) {
                        return false;
                    }
                }
            }
            return true;
        }

        public function assign($routeInfos)
        {
            $r = repl('.*', '', $routeInfos['route']);
            $uri = $this->_uri;

            $tabUri = explode('()', $r);
            foreach ($tabUri as $elem) {
                $uri = repl($elem, '##', $uri);
            }

            /* un peu de cleaning de l'uri */

            while ($uri[0] == '#') {
                $uri = substr($uri, 1, strlen($uri));
            }

            $tabUri = explode('##', $uri);
            for ($i = 0 ; $i < count($tabUri) ; $i++) {
                $indexParamRegexp = 'routeparam' . ($i + 1) . 'Regexp';
                $indexParamName = 'routeparam' . ($i + 1);
                $paramName = (ake($indexParamName, $routeInfos['defaults'])) ? $routeInfos['defaults'][$indexParamName] : null;
                $_REQUEST[$paramName] = $valParam = $this->cleanVar($tabUri[$i]);
                if (ake($indexParamRegexp, $routeInfos['defaults'])) {
                    $regexp = $routeInfos['defaults'][$indexParamRegexp];
                    $continue = preg_match('#^' . $regexp . '#i', $valParam, $matches);
                    if (false === $continue) {
                        return false;
                    }
                }
            }
            return $this->make($routeInfos);
        }

        public function cleanVar($str)
        {
            if (!strlen($str)) {
                $str = null;
            }
            $str = repl('.html', '', $str);
            $str = repl('.mb', '', $str);
            return $str;
        }

        public function make($routeInfos)
        {
            $module      = $routeInfos['defaults']['module'];
            $controller  = $routeInfos['defaults']['controller'];
            $action      = $routeInfos['defaults']['action'];

            if (strstr($module, 'routeparam')) {
                if (ake($module, $_REQUEST)) {
                    $module = $_REQUEST[$module];
                } else {
                    $module = $_REQUEST[$routeInfos['defaults'][$module]];
                }
            }

            if (strstr($controller, 'routeparam')) {
                if (ake($controller, $_REQUEST)) {
                    $controller = $_REQUEST[$controller];
                } else {
                    $controller = $_REQUEST[$routeInfos['defaults'][$controller]];
                }
            }

            if (strstr($action, 'routeparam')) {
                if (ake($action, $_REQUEST)) {
                    $action = $_REQUEST[$action];
                } else {
                    $action = $_REQUEST[$routeInfos['defaults'][$action]];
                }
            }

            u::set('FTVModuleName', $module);
            u::set('FTVControllerName', $controller);
            u::set('FTVActionName', $action);

            return $routeInfos;
        }

        public function compileRoute($route)
        {
            if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $route, $matches, PREG_SET_ORDER)) {echo 'OK';
                $match_types = array(
                    'i' => '[0-9]++',
                    'a' => '[0-9A-Za-z]++',
                    'h' => '[0-9A-Fa-f]++',
                    '*' => '.+?',
                    '**' => '.++',
                    '' => '[^/]+?'
                );
                foreach ($matches as $match) {
                    list($block, $pre, $type, $param, $optional) = $match;

                    if (isset($match_types[$type])) {
                        $type = $match_types[$type];
                    }
                    if ($pre === '.') {
                        $pre = '\.';
                    }

                    $pattern = '(?:' . ($pre !== '' ? $pre : null) . '(' . ($param !== '' ? "?P<$param>" : null) . $type . '))' . ($optional !== '' ? '?' : null);

                    $route = repl($block, $pattern, $route);
                }
            }
            return "`^$route$`";
        }

        public static function defineHomePage()
        {
            $script        = $_SERVER["SCRIPT_NAME"];
            if(preg_match('/services/', $_SERVER["SCRIPT_NAME"])){
                $moduleRouting = u::cut('/services/', '/', $script);
                if (empty($moduleRouting)) {
                    $moduleRouting = 'appli';
                }
                u::set('moduleRouting', $moduleRouting);
            } else {
                u::set('moduleRouting', 'appli');
            }
        }
    }
