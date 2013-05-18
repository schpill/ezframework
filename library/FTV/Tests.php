<?php
    /**
     * Tests class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Tests extends PHPUnit_Framework_TestCase
    {
        public $view;
        public $_test;

        public function runTests()
        {
            $this->view = new FTVTestsView;
            $this->view->_status = array();
            $this->view->_exceptions = array();
            $request = u()->getRequest();
            $this->view->_type = $controller = $request->getType();
            $action = $request->getAction();
            if (null === $action) {
                $action = 'index';
            }
            $this->view->_action = $action;
            $controllerFile = APPLICATION_PATH . DS . 'modules' . DS . 'Tests' . DS . 'controllers' . DS . ucfirst(i()->lower($controller)) . 'Controller.php';
            $viewFile = APPLICATION_PATH . DS . 'modules' . DS . 'Tests' . DS . 'views' . DS . 'scripts' . DS . i()->lower($controller) . DS . i()->lower($action) . '.phtml';
            if (file_exists($controllerFile)) {
                require_once $controllerFile;
                $controllerName = ucfirst(i()->lower($controller)) . 'Controller';
                $testController = new $controllerName;
                $actionName = $action . 'Action';
                $testController->init($this);
                $testController->$actionName();
            } else {
                throw new FTV_Exception("The controller for $controller does not exist.");
            }

            /* si on veut gerer un affichage particulier d une serie de tests on peut creer une vue dediee */
            if (file_exists($viewFile)) {
                $this->_render($viewFile);
            } else {
                /* sinon on utilise le layout */
                $this->_render(str_replace($action . '.phtml', 'tpl.phtml', str_replace($controller, 'layout', $viewFile)));
            }
            exit;
        }

        protected function _render($viewFile)
        {
            /* Emulation basique de Zend_View pour les tests unitaires */
            $content = file_get_contents($viewFile);
            $content = str_replace('this->partial', 'partial->', $content);
            $content = str_replace('this->', 'this->view->', $content);
            $content = str_replace('partial->', 'this->_partial', $content);
            $file = APPLICATION_PATH . DS . 'cache' . DS . md5($viewFile . serialize($_SESSION)) . '.phtml';
            FTV_File::create($file, $content);
            $this->_run($file);
            @unlink($file);
        }

        protected function _partial($partial)
        {
            $viewFile = APPLICATION_PATH . DS . 'modules' . DS . 'Tests' . DS . 'views' . DS . 'scripts' . DS . $partial;
            if (file_exists($viewFile)) {
                $this->_render($viewFile);
            }
        }

        protected function _run()
        {
            include func_get_arg(0);
        }

        protected function _exploreException(PHPUnit_Framework_AssertionFailedError $e)
        {
            $exception = new FTVTestsException;
            $message = $e->getMessage();
            if (strstr($message, '###')) {
                list($message, $dummy) = explode('###', $message, 2);
            }
            $exception->setMessage(utf8_decode($message));
            if (null !== $e->getComparisonFailure()) {
                $exception->setExpected($e->getExpected());
                $exception->setActual($e->getActual());
                $exception->setIdentical($e->getIdentical());
            }
            $this->view->_status[$this->_test] = 'error';
            $this->view->_exceptions[$this->_test] = $exception;
        }

        protected function _setupTest($test)
        {
            $actionName = $this->view->_action . 'Action';
            $this->setName($actionName);
            $this->view->_status[$test] = 'success';
            $this->_test = $test;
            $staticTests = u()->get('staticTests');
            if (null === $staticTests) {
                $staticTests = array();
                u()->set('staticTests', $staticTests);
            }
            u()->set('actualTest', $test);
        }

        public function __call($method, $args)
        {
            if (substr($method, 0, strlen('_assert')) == '_assert') {
                $goodMethod = str_replace('_', '', $method);
                if (count($args) == 3) {
                    $args[2] = $args[2] . '###';
                }
                call_user_func_array(array('PHPUnit_Framework_Assert', $goodMethod), $args);
            }
        }
    }
