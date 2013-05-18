<?php
    class FTV_Controller
    {
        public function __call($method, $parameters)
        {
            u::redirect('FTV_404');
        }

        public function __get($key)
        {
            $container = u::get('FTVContainer');
            if (null !== $container) {
                $getter = 'get' . i::camelize($key);
                return $container->$getter();
            }
            return null;
        }

        public function noRender()
        {
            $this->view->noCompiled();
            u::clearEvent('bootstrap.finished');
        }

        public function getRequest()
        {
            $request = request();
            return $request;
        }
    }
