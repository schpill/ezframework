<?php
    /* PHP 5.4 */
    abstract class FTV_Thief
    {
        abstract function getTarget();

        public function __call($name, $arguments)
        {
            $reflectionClass = new ReflectionClass($this->getStructure());
            $closure = $reflectionClass->getMethod($name)->getClosure($this->getTarget());
            $closure = $closure->bindTo($this);
            $return = call_user_func_array(
                $closure,
                $arguments
            );
        }
    }
