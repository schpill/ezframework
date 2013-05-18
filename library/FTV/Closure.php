<?php
    /**
     * Base class for simulate closure for php versions < 5.3 && > 5.0
     *
     * This class only contains three little methods. A setter, a getter and a simili globals.
     *
     * @author  Gerald
     */

    class FTV_Closure
    {
        public $_closure;
        public $_fcn;

        /**
         * Create closure key
         *
         *
         * @param  string $method  method's name
         * @param  string $closure  method's code
         * @return void
         */
        public function __construct($closure)
        {
            $this->_closure = $closure;
            $closure = str_replace('##hash##', md5($closure), $closure);
            $this->_fcn = create_function('', $closure);
            return $this;
        }

        public function run(array $params = array())
        {
            $closureObject = new FTVClosureParams;

            if (count($params)) {
                foreach ($params as $key => $value) {
                    $setter = 'set' . FTV_Inflector::camelize($key);
                    $closureObject->$setter($value);
                }
            }
            u()->set('closure_' . md5($this->_closure), $closureObject);

            $fcn = (string) $this->_fcn;
            $res = $fcn();

            return $res;
        }

        public function __invoke(array $params = array())
        {
            return $this->run($params);
        }
    }
