<?php
    /**
     * Handler class
     *
     * @todo Add inline documentation.
     *
     * @package     FTV
     * @subpackage  Exception
     * @author      Gerald Plusquellec
     */
    class FTV_Exception_Handler
    {
        /**
         * The exception handlers
         *
         * @var array
         */
        protected $_handlers = array();

        /**
         * Handler statuses
         *
         * @var array
         */
        protected $_handlerStatus = array();

        /**
         * {@inheritdoc}
         */
        public function handle(FTV_Exception $e)
        {
            $trace = $e->getTrace();

            $error = array(
                   'file'    => $e->getFile(),
                   'line'    => $e->getLine(),
                   'message' => $e->getMessage()
            );

            try {

                // Execute each callback
                foreach ($this->_handlers as $handler) {
                    $this->_handlerStatus[] = array(
                        'object'   => get_class($handler),
                        'response' => $handler->handle($e)
                    );
                }

                require(config('tpl.fatal'));
                exit;

            } catch (\Exception $e) {
                require(config('tpl.fatal'));
                exit;
            }

        }

        /**
         * @todo Add inline documentation.
         *
         * @param type $errno
         * @param type $errstr
         * @param type $errfile
         * @param type $errline
         *
         * @return void
         */
        public function handleError($errno = '', $errstr = '', $errfile = '', $errline = '')
        {
            $error = array(
                'message' => $errstr,
                'file'    => $errfile,
                'line'    => $errline
            );

            try {
                throw new FTV_Exception('');
            } catch (FTV_Exception $e) {
                try {
                    // Execute each callback
                    foreach ($this->_handlers as $handler) {
                        $this->_handlerStatus[] = array(
                            'object'   => get_class($handler),
                            'response' => $handler->handle($e)
                        );
                    }
                } catch (FTV_Exception $e) {
                }

                $trace = $e->getTrace();
            }

            require(config('tpl.fatal'));
            exit;
        }

        /**
         * Add an Exception callback
         *
         * @param HandlerInterface $handler
         *
         * @return void
         */
        public function addHandler(FTV_Exception_Handler_Interface $handler)
        {
            $this->_handlers[] = $handler;
        }

    }
