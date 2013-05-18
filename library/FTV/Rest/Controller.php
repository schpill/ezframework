<?php 
    /**
     * Controller class
     *
     * @package     FTV Rest
     * @author      Gerald Plusquellec
     */
    abstract class FTV_Rest_Controller
    {
        protected $request;
        protected $response;
        protected $responseStatus;

        public function __construct($request)
        {
            $this->request = $request;      
        }

        final public function getResponseStatus()
        {
            return $this->responseStatus;
        }

        final public function getResponse()
         {
            return $this->response;
        }

        public function checkAuth()
        {
            return true;
        }

        // @codeCoverageIgnoreStart
        abstract public function get();
        abstract public function post();
        abstract public function put();
        abstract public function delete();
        // @codeCoverageIgnoreEnd
        
    }
