<?php
    class FTV_Service_Manager
    {
        protected $_name;
        protected $_methods = array();
        
        public function __construct($name = 'service')
        {
            $this->_name = $name;
        }

        public function add($name, $method)
        {
            if (!ake($name, $this->_methods)) {
                $this->_methods[$name] = $method;
                return $this;
            } else {
                throw new FTV_Exception("This method $name already exists in this service $this->_name.");
            }
        }

        public function run($method, $parameters = array())
        {
            $class = 'FTV_Service_' . ucfirst(i::lower($this->_name)) . '_' . ucfirst(i::lower($method));
            $code = '<?php' . "\nclass " . $class . "\n{\n";
            foreach ($this->_methods as $name => $methodCode) {
                $code .= "\t" . $methodCode . "\n";
            }
            $code .= '}';

            $file = config('cache.dir') . DS . sha1($this->_name . $method . time()) . '.php';
            file_put_contents($file, $code);
            require_once $file;
            $return = call_user_func_array(array($class, $method), $parameters);
            unlink($file);
            return $return;
        }
    }
