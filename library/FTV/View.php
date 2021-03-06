<?php
    class FTV_View
    {
        public $_viewFile;
        public $_module;
        public $_cache;
        protected $_grammar = array();
        public $_compiled = true;
        /**
         * Callback for escaping.
         *
         * @var string
         */
        private $_escape = 'htmlspecialchars';

        /**
         * Encoding to use in escaping mechanisms; defaults to utf-8
         * @var string
         */
        private $_encoding = 'UTF-8';

        /**
         * Stack of View_Filter names to apply as filters.
         * @var array
         */
        private $_filter = array();

        /**
         * Stack of View_Filter objects that have been loaded
         * @var array
         */
        private $_filterClass = array();

        /**
         * Map of filter => class pairs to help in determining filter class from
         * name
         * @var array
         */
        private $_filterLoaded = array();

        /**
         * Map of filter => classfile pairs to aid in determining filter classfile
         * @var array
         */
        private $_filterLoadedDir = array();

        public function __construct($viewFile = null)
        {
            if (null !== $viewFile) {
                if (strstr($viewFile, DS)) {
                    $this->_viewFile = $viewFile;
                } else {
                    $file = APPLICATION_PATH . DS . 'cache' . DS . md5($this->_viewFile . time() . u::UUID()) . '.fake';
                    @unlink($file);
                    @touch($file);
                    $fp = fopen($file, 'a');
                    fwrite($fp, '<?php echo $this->content; ?>');
                    $this->_viewFile = $file;
                }
            } else {
                $module     = u::get('FTVModuleName');
                $controller = u::get('FTVControllerName');
                $action     = u::get('FTVActionName');

                $this->_module   = $module;
                $this->_viewFile   = APPLICATION_PATH . DS . 'modules' . DS . $module . DS . 'views' . DS . 'scripts' . DS . i::lower($controller) . DS . i::lower($action) . '.phtml';
            }

            u::set('FTVView', $this);
        }

        public function partial($partial, array $params = array(), $cache = false, $echo = true, $module = null)
        {
            if (count($params)) {
                foreach ($params as $k => $v) {
                    $this->$k = $v;
                }
            }

            if (false !== $cache) {
                $this->setCache($cache);
            }

            if (file_exists($partial)) {
                $viewFile = $partial;
            } else {
                $module = (null === $module) ? $this->_module : $module;
                $viewFile = APPLICATION_PATH . DS . 'modules' . DS . $module . DS . 'views' . DS . 'scripts' . DS . $partial;
            }

            if (file_exists($viewFile)) {
                $this->render($viewFile, $echo);
            } else {
                throw new FTV_Exception("This partial '$viewFile' does not exist.");
            }
        }

        public function partialLoop($partial, array $data, $iterator, $cache = false, $echo = true, $module = null)
        {
            $result = '';

            if (count($data)) {
                foreach ($data as $key => $value) {
                    $with = array('key' => $key, $iterator => $value);
                    $result .= $this->partial($partial, $with, $cache, false, $module);
                }
            }
            if (true === $echo) {
                return $result;
            } else {
                echo $result;
            }
        }

        public function render($partial = null, $echo = true)
        {
            $file = (null === $partial) ? $this->_viewFile : $partial;
            $this->_viewFile = $file;
            $this->_run($echo);
        }

        public static function display(FTVDisplay $page)
        {
            $tpl = $page->getTpl();
            if (file_exists($tpl)) {
                $content = fgc($tpl);
                $content = repl('$this->', '$page->', $content);
                $file = APPLICATION_PATH . DS . 'cache' . DS . sha1($content) . '.display';
                FTV_File::put($file, $content);
                ob_start();
                include $file;
                $html = ob_get_contents();
                ob_end_clean();
                FTV_File::delete($file);
                return $html;
            }
            return '';
        }

        protected function _run()
        {
            $echo = func_get_arg(0);
            $file = $this->_viewFile;
            $isExpired = $this->expired();
            if (false === $this->_compiled) {
                $isExpired = true;
            }
            if (false === $isExpired) {
                $file = $this->compiled();
            } else {
                if (is_numeric($this->_cache)) {
                    $cacheInst = new FTV_Minicache(APPLICATION_PATH . DS . 'cache' . DS);
                    $hash = sha1($this->compiled() . $this->_cache . _serialize((array)$this)) . '.cache';
                    $cacheInst->forget($hash);
                }
                $file = $this->compiled($file);
            }
            if (null !== $this->_cache) {
                $isCached = isCached($file, $this->_cache, (array)$this);
                if (false === $isCached) {
                    ob_start();
                    include $file;
                    $content = ob_get_contents();
                    ob_end_clean();
                    if (true === $echo) {
                        echo cache($file, $content, $this->_cache, (array)$this);
                    } else {
                        return cache($file, $content, $this->_cache, (array)$this);
                    }
                } else {
                    $content = cache($file, null, $this->_cache, (array)$this);
                    if (true === $echo) {
                        echo $content;
                    } else {
                        return $content;
                    }
                }
            } else {
                if (true === $echo) {
                    include $file;
                } else {
                    ob_start();
                    include $file;
                    $content = ob_get_contents();
                    ob_end_clean();
                    $hash = sha1($this->_viewFile);
                    u::set($hash, $content);
                    return true;
                }
            }
        }

        protected function expired()
        {
            if (!file_exists($this->compiled())) {
                return true;
            }
            return filemtime($this->_viewFile) > filemtime($this->compiled());
        }

        protected function compiled($compile = false)
        {
            $file = APPLICATION_PATH . DS . 'cache' . DS . md5($this->_viewFile) . '.compiled';
            if (false !== $compile) {
                @unlink($file);
                file_put_contents($file, $this->makeCompile($compile));
            }
            return $file;
        }

        protected function makeCompile($file)
        {
            $content = file_get_contents($file);

            $content = repl('{{=', '<?php echo ', $content);
            $content = repl('{{', '<?php ', $content);
            $content = repl('}}', '?>', $content);
            $content = repl('<?=', '<?php echo ', $content);
            $content = repl('<? ', '<?php ', $content);
            $content = repl('<?[', '<?php [', $content);
            $content = repl('[if]', 'if ', $content);
            $content = repl('[elseif]', 'elseif ', $content);
            $content = repl('[else if]', 'else if ', $content);
            $content = repl('[else]', 'else:', $content);
            $content = repl('[/if]', 'endif;', $content);
            $content = repl('[for]', 'for ', $content);
            $content = repl('[foreach]', 'foreach ', $content);
            $content = repl('[while]', 'while ', $content);
            $content = repl('[switch]', 'switch ', $content);
            $content = repl('[/endfor]', 'endfor;', $content);
            $content = repl('[/endforeach]', 'endforeach;', $content);
            $content = repl('[/endwhile]', 'endwhile;', $content);
            $content = repl('[/endswitch]', 'endswitch;', $content);
            $content = repl('includes(', 'echo $this->partial(', $content);
            if (count($this->_grammar)) {
                foreach ($this->_grammar as $grammar => $replace) {
                    $content = repl($grammar, $replace, $content);
                }
            }

            return $content;
        }

        public function setGrammar($grammar, $replace)
        {
            if (!array_key_exists($grammar, $this->_grammar)) {
                $this->_grammar[$grammar] = $replace;
            }
            return $this;
        }

        public function setCache($duration)
        {
            if (is_numeric($duration)) {
                if (0 < $duration) {
                    $this->_cache = $duration;
                }
            }
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
            } elseif (substr($func, 0, 3) == 'set') {
                $value = $argv[0];
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($func, 3)));
                $var = i::lower($uncamelizeMethod);
                $this->$var = $value;
                return $this;
            }
            if (!is_callable($func) || substr($func, 0, 3) !== 'set' || substr($func, 0, 3) !== 'get') {
                throw new BadMethodCallException(__class__ . ' => ' . $func);
            }
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

        public function noCompiled()
        {
            $this->_compiled = false;
        }

        public function addAsset($asset, array $configAsset = array(), $ext = null)
        {
            $configs = u::get('FTVConfig');

            $versionJs = $configs['app']['js']['version'];
            $versionCss = $configs['app']['css']['version'];

            if(null === $ext) {
                $tabString = explode('.', i::lower($asset));
                $ext = end($tabString);
            }
            if ($ext == 'css') {
                $assetHtml = '<link href="' . $asset . '?v=' . $versionCss . '"';
                if (count($configAsset)) {
                    foreach ($configAsset as $key => $value) {
                        $assetHtml .= " $key=\"$value\" ";
                    }
                    $assetHtml = i::substr($assetHtml, 0, -1);
                }
                $assetHtml .= ' />' . "\n";
                return $assetHtml;
            } else if ($ext == 'ico') {
                return '<link rel="shortcut icon" href="' . $asset . '" type="image/x-icon" />' . "\n";
            } else if ($ext == 'js') {
                return '<script type="text/javascript" src="' . $asset . '?v=' . $versionJs . '"></script>' . "\n";
            } elseif (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'bmp'))) {
                $assetHtml = '<img src="' . $asset . '"';
                if (count($configAsset)) {
                    foreach ($configAsset as $key => $value) {
                        $assetHtml .= " $key=\"$value\" ";
                    }
                    $assetHtml = i::substr($assetHtml, 0, -1);
                }
                $assetHtml .= ' />' . "\n";
                return $assetHtml;
            } else {
                return $asset;
            }
        }

        public function setEscape($spec)
        {
            $this->_escape = $spec;
            return $this;
        }

        public function escape($var)
        {
            if (in_array($this->_escape, array('htmlspecialchars', 'htmlentities'))) {
                return call_user_func($this->_escape, $var, ENT_COMPAT, $this->_encoding);
            }

            if (1 == func_num_args()) {
                return call_user_func($this->_escape, $var);
            }
            $args = func_get_args();
            return call_user_func_array($this->_escape, $args);
        }

        /**
         * Set encoding to use with htmlentities() and htmlspecialchars()
         *
         * @param string $encoding
         * @return Zend_View_Abstract
         */
        public function setEncoding($encoding)
        {
            $this->_encoding = $encoding;
            return $this;
        }

        /**
         * Return current escape encoding
         *
         * @return string
         */
        public function getEncoding()
        {
            return $this->_encoding;
        }
    }
