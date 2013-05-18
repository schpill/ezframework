<?php
    /**
     * Auth class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Auth
    {
        /**
         * The user currently being managed by the driver.
         *
         * @var mixed
         */
        public $user;

        /**
         * The current value of the user's token.
         *
         * @var string|null
         */
        public $token;

        public function __construct()
        {
            $this->token = FTV_Session::instance('FTVSession')->getAuthToken();
            if (is_null($this->token)) {
                $this->token = u::token();
                FTV_Session::instance('FTVSession')->setAuthToken($this->token);
            }
        }

        public function check()
        {
            return !is_null($this->user);
        }

        public function gest()
        {
            return is_null($this->user);
        }

        public function login($user)
        {
            $this->user = $user;
            FTV_Session::instance('FTVSession')->setAuthUser(array($user->getId() => $this->token));
            u::run('FTV.auth.login');
        }

        public function logout($redirectRoute = null)
        {
            unset($this->user);
            unset($this->token);
            FTV_Session::instance('FTVSession')->forgetAuthToken();
            FTV_Session::instance('FTVSession')->forgetAuthUser();
            u::run('FTV.auth.logout');
            if (null !== $redirectRoute) {
                u::redirect($route);
            }
        }
    }
