<?php
    /**
     * Query class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Query
    {
        private static $_em;
        
        public function __construct($em)
        {
            self::$_em = $em;
        }
        
        public static function find()
        {
            $table      = self::$_em->_get('_tableName');
            $entity     = self::$_em->_get('_dbName');
            $pk         = self::$_em->pk();
            
            $query = 'SELECT * FROM ' . $entity . '.' . $table . ' WHERE ' . $entity . '.' . $table . '.' . $pk . ' = :' . $pk;
            return $query;
        }
        
        public static function delete()
        {
            $table      = self::$_em->_get('_tableName');
            $entity     = self::$_em->_get('_dbName');
            $pk         = self::$_em->pk();
            
            $query = 'DELETE FROM ' . $entity . '.' . $table . ' WHERE ' . $entity . '.' . $table . '.' . $pk . ' = :' . $pk;
            return $query;
        }
        
        public static function insert()
        {
            $table  = self::$_em->_get('_tableName');
            $datas  = self::$_em->_get('_datas');
            $fields = $datas['fieldsSave'];
            
            $query = 'INSERT INTO ' . $table . ' (';
            $query .= implode(', ', array_values($fields));
            $query .= ') value (:';
            $query .= implode(', :', array_values($fields));
            $query .= ')';
            return $query;
        }
        
        public static function update()
        {
            $table      = self::$_em->_get('_tableName');
            $entity     = self::$_em->_get('_dbName');
            $datas      = self::$_em->_get('_datas');
            $fields     = $datas['fieldsSave'];
            $pk         = self::$_em->pk();
            
            $query = 'UPDATE ' . $entity . '.' . $table . ' SET ';
            $set = array();

            foreach ($fields as $idKey => $key) {
                if($key == $pk) {
                    continue;
                }
                $set[] = $entity . '.' . $table . '.' . $key . ' = :' . $key;
            }

            $query .= implode(', ', $set);
            $query .= ' WHERE ' . $entity . '.' . $table . '.' . $pk . ' = :' . $pk;

            return $query;
        }
    }
