<?php
    /**
     * Container class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Container 
    {
        protected $s = array(); 
        
        public function __set($k, $c) 
        { 
            $this->s[$k] = $c; 
        }
        
        public function __get($k) 
        { 
            return $this->s[$k]($this); 
        }
        
        public function factory($method, $args = array())
        {
            if (count($args) < 1) {
                $args['c'] = $this;
            }
            $closure = FTV_Utils::closure($args, $method);
            return $closure->res;
        }
    }
