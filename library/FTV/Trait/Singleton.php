<?php
    /**
     * Singleton Trait
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */

    trait FTV_Trait_Singleton
    {
        public static function getInstance()
        {
            static $_instance = null;
            $class = __CLASS__;
            return $_instance ?: $_instance = new $class;
        }

        public function __clone()
        {
            trigger_error('Cloning '.__CLASS__.' is not allowed.', E_USER_ERROR);
        }
    }
