<?php
    class FTV_Eav
    {
        protected $_db;

        public function __construct()
        {
            $this->_db = u::newInstance('FTV_Memory', array('FTV', 'EAV'));
        }

        public function save()
        {
            $this->_db->save();
            $this->_db = u::newInstance('FTV_Memory', array('FTV', 'EAV'));
            return $this;
        }

        public function select($entity)
        {
            $this->_db->where('entity = ' . $entity);
            return $this;
        }

        public function results()
        {
            return $this->_db->_getResults();
        }

        public function __call($method, $args)
        {
            $this->_db->$method(current($args));
            return $this;
        }
    }
