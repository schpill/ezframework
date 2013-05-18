<?php
    /**
     * FTV_Exception_Handler_Interface interface
     *
     * @todo Add inline documentation.
     *
     * @package     FTV
     * @subpackage  Exception
     * @author      Gerald Plusquellec
     */
    interface FTV_Exception_Handler_Interface
    {
        /**
         * Handle an exception.
         *
         * @param FTV_Exception $e FTV_Exception object.
         *
         * @return void
         */
        public function handle(FTV_Exception $e);

    }
