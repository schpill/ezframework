<?php
    /**
     * ORM class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Model
    {
        protected $_table;
        protected $_entity;
        protected $_datas         = array();
        protected $_dbName;
        protected $_tableName;
        protected $_numberOfQueries;
        protected $_totalDuration;
        protected $_bufferTables  = array();
        protected $_buffer        = true;
        protected $_cache         = false;
        protected $_array         = false;
        protected $_session;
        protected $_sleepId;
        protected $_pdoOptions    = array(
                PDO::ATTR_CASE              => PDO::CASE_LOWER,
                PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_EMULATE_PREPARES  => false
        );
        private $_log             = null;
        protected $_newRow        = false;
        protected $_isConnected   = false;

        protected function config()
        {
            if (strstr($this->_table, '_id')) {
                $this->_table = repl('_id', '', $this->_table);
            }
            $configs = u::get('FTVConfig');
            $configModel = u::get('FTVConfigModels');
            if (!ake($this->_entity, $configModel)) {
                throw new FTV_Exception("The config models file can't read $this->_entity entity");
            }
            $DB = $configModel[$this->_entity]['DB'];
            $keyCache = sha1(session_id() . date('dmY') . $this->_table);
            $this->_datas['keyCache'] = $keyCache;
            $this->_datas['config']['resources'] = $configs['resources']['db'][$DB];

            $adapter    = $configs['db'][$DB]['adapter'];
            $username   = $this->_datas['config']['resources']['params']['username'];
            $password   = $this->_datas['config']['resources']['params']['password'];
            $dbName     = $this->_datas['config']['resources']['params']['dbname'];
            $host       = $this->_datas['config']['resources']['params']['host'];

            $this->_datas['config']['connexionInfos'] = array(
                'adapter' => $adapter,
                'username' => $username,
                'password' => $password,
                'dbName' => $dbName,
                'host' => $host
            );

            $this->_dbName = $this->_datas['config']['resources']['params']['dbname'];
            unset($this->_datas['config']['resources']);
            $this->_datas['classCollection'] = $this->_entity . ucfirst($this->_table) . 'ResultModelCollection';
            $this->_datas['classModel'] = 'FTVModel_' . ucfirst($this->_entity) . '_' . ucfirst($this->_table);

            if (!ake($this->_table, $configModel[$this->_entity]['tables'])) {
                if (false === $this->checkTable()) {
                    throw new FTV_Exception("The config models file can't read $this->_table table [$this->_entity].");
                } else {
                    $configModel = array();
                }
            } else {
                $configModel = $configModel[$this->_entity]['tables'][$this->_table];
            }
            $this->_datas['configModel'] = $configModel;
            $this->_datas['salt'] = base_convert(sha1(uniqid(mt_rand(), true)), 16, 36);
            $this->_tableName = (isset($configModel['tableName'])) ? $configModel['tableName'] : $this->_table;

            $nbQueries = u::get('FTVNbQueries');
            $totalDuration = u::get('FTVSQLTotalDuration');
            if (null === $nbQueries) {
                $nbQueries = 0;
            }
            if (null === $totalDuration) {
                $totalDuration = 0;
            }
            $this->_numberOfQueries = $nbQueries;
            $this->_totalDuration = $totalDuration;
            $this->_session = FTV_Session::instance($this->_datas['classModel']);

            $this->_datas['query']['distinct'] = false;
            $this->_isConnected = true;
            return $this;
        }

        private function _getConnexion()
        {
            extract($this->_datas['config']['connexionInfos']);
            $connexions = u::get('ORMConnexions');
            if (null === $connexions) {
                $connexions = array();
            }

            $keyConnexion = sha1(serialize(array("$adapter:dbname=$dbName;host=$host", $username, $password)));
            if (ake($keyConnexion, $connexions)) {
                $db = $connexions[$keyConnexion];
            } else {
                switch ($adapter) {
                    case 'mysql':
                        $db = u::newInstance('PDO', array("$adapter:dbname=$dbName;host=$host", $username, $password));
                        break;
                }
                $connexions[$keyConnexion] = $db;
                u::set('ORMConnexions', $connexions);
            }
            $this->_isConnected = true;
            return $db;
        }

        public function close()
        {
            extract($this->_datas['config']['connexionInfos']);
            $connexions = u::get('ORMConnexions');
            if (null === $connexions) {
                $connexions = array();
            }

            $keyConnexion = sha1(serialize(array("$adapter:dbname=$dbName;host=$host", $username, $password)));
            if (ake($keyConnexion, $connexions)) {
                $connexions[$keyConnexion] = null;
                u::set('ORMConnexions', $connexions);
            }
            $this->_isConnected = false;
            return $this;
        }

        public function checkTable()
        {
            $q = "SHOW TABLES";
            $res = $this->_query($q);
            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            if ($count < 1) {
                return false;
            }
            foreach ($res as $row) {
                $table = current($row);
                if ($table == $this->_table) {
                    return true;
                }
            }
            return false;
        }

        public function between($field, $min, $max)
        {
            return $this->where("$this->_dbName.$this->_tableName.$field BETWEEN $min AND $max")->select();
        }

        public function min($field)
        {
            $q = "SELECT MIN($this->_dbName.$this->_tableName.$field) FROM $this->_dbName.$this->_tableName";
            $res = $this->_query($q);
            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            if ($count < 1) {
                return null;
            } else {
                foreach ($res as $row) {
                    $val = current($row);
                    return $val;
                }
            }
        }

        public function max($field)
        {
            $q = "SELECT MAX($this->_dbName.$this->_tableName.$field) FROM $this->_dbName.$this->_tableName";
            $res = $this->_query($q);
            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            if ($count < 1) {
                return null;
            } else {
                foreach ($res as $row) {
                    $val = current($row);
                    return $val;
                }
            }
        }

        public function avg($field)
        {
            $q = "SELECT AVG($this->_dbName.$this->_tableName.$field) FROM $this->_dbName.$this->_tableName";
            $res = $this->_query($q);
            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            if ($count < 1) {
                return null;
            } else {
                foreach ($res as $row) {
                    $val = current($row);
                    return $val;
                }
            }
        }

        public function sum($field)
        {
            $q = "SELECT SUM($this->_dbName.$this->_tableName.$field) FROM $this->_dbName.$this->_tableName";
            $res = $this->_query($q);
            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            if ($count < 1) {
                return null;
            } else {
                foreach ($res as $row) {
                    $val = current($row);
                    return $val;
                }
            }
        }

        public function count($field)
        {
            $q = "SELECT COUNT($this->_dbName.$this->_tableName.$field) FROM $this->_dbName.$this->_tableName";
            $res = $this->_query($q);
            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            if ($count < 1) {
                return null;
            } else {
                foreach ($res as $row) {
                    $val = current($row);
                    return $val;
                }
            }
        }

        public function Db()
        {
            return $this->_getConnexion();
        }

        public function newInstance($id = null)
        {
            $class = 'FTVModel_' . ucfirst($this->_entity) . '_' . ucfirst($this->_table);
            if (null === $id) {
                $obj = new $class;
                $obj = $obj->map();
                foreach ($obj->fields() as $field) {
                    if (is_array($obj->_datas['keys'])) {
                        if (in_array($field, $obj->_datas['keys'])) {
                            $modelField = repl('_id', '', $field);
                            if (ake($modelField, $obj->_datas['configModel']['relationship'])) {
                                $m = $obj->_datas['configModel']['relationship'][$modelField];
                                if (null !== $m) {
                                    $obj->_datas['foreignFields'][$modelField] = true;
                                }
                            }
                        }
                    }
                }
                return $obj;
            } else {
                $obj = new $class;
                return $obj->find($id);
            }
        }

        public function toArray()
        {
            $array = array();
            foreach ($this->_datas['fieldsSave'] as $field) {
                $array[$field] = $this->$field;
            }
            return $array;
        }

        public function first()
        {
            return $this->select(null, true);
        }

        public function one()
        {
            return $this->first();
        }

        public function populate(array $datas)
        {
            foreach ($datas as $k => $v) {
                $this->$k = $v;
            }
            return $this;
        }

        // populate's method's alias
        public function fill(array $datas)
        {
            return $this->populate($datas);
        }

        public function create(array $datas)
        {
            return $this->fill($datas)->save();
        }

        protected function factory()
        {
            return $this->config()->map();
        }

        protected function foreign()
        {
            if (!ake('keys', $this->_datas)) {
                $this->_datas['keys'] = array();
            }

            foreach ($this->fields() as $field) {
                if (is_array($this->_datas['keys'])) {
                    if (in_array($field, $this->_datas['keys'])) {
                        $modelField = repl('_id', '', $field);
                        if (isset($this->_datas['configModel']['relationship']) && ake($modelField, $this->_datas['configModel']['relationship'])) {
                            $m = $this->_datas['configModel']['relationship'][$modelField];
                            if (null !== $m) {
                                $this->_datas['foreignFields'][$modelField] = true;
                            }
                        }
                    }
                }
            }
        }

        protected function quote($value, $parameterType = PDO::PARAM_STR)
        {
            if(null === $value) {
                return "NULL";
            }
            $db = $this->_getConnexion();
            if (is_string($value)) {
                return $db->quote($value, $parameterType);
            }
            return $value;
        }

        public function find($id = null, array $fields = array(), $fk = true)
        {
            if (null === $id) {
                if (!count($fields)) {
                    return $this->all();
                } else {
                    $obj = $this->all();
                    if($obj instanceof $this->_datas['classCollection']) {
                        $objCollectionClass = $this->_entity . ucfirst($this->_table) . 'ResultModelCollection';
                        $returnCollection = new $objCollectionClass;
                        foreach ($obj as $objCollection) {
                            if ($objCollection instanceof $objCollection->_datas['classModel']) {
                                $return = new FTVModelResult($objCollection);
                                foreach ($fields as $field) {
                                    $return->$field = $objCollection->$field;
                                }
                                $returnCollection[] = $return;
                            }
                        }
                        return $returnCollection;
                    } else {
                        return $obj;
                    }
                }
            }
            $pk = $this->pk();
            if (!count($fields)) {
                return $this->where("$this->_dbName.$this->_tableName.$pk = " . $this->quote($id), true, $fk)->select();
            } else {
                $obj = $this->where("$this->_dbName.$this->_tableName.$pk = " . $this->quote($id), true, $fk)->select();
                if ($obj instanceof $this->_datas['classModel']) {
                    $return = new FTVModelResult($obj);
                    foreach ($fields as $field) {
                        $return->$field = $obj->$field;
                    }
                    return $return;
                } else if($obj instanceof $this->_datas['classCollection']) {
                    $objCollectionClass = $this->_entity . ucfirst($this->_table) . 'ResultModelCollection';
                    $returnCollection = new $objCollectionClass;
                    foreach ($obj as $objCollection) {
                        if ($objCollection instanceof $objCollection->_datas['classModel']) {
                            $return = new FTVModelResult($objCollection);
                            foreach ($fields as $field) {
                                $return->$field = $objCollection->$field;
                            }
                            $objCollectionClass[] = $return;
                        }
                    }
                    return $objCollectionClass;
                } else {
                    return $obj;
                }
            }
        }

        public function load()
        {
            if (!ake('foreignFields', $this->_datas)) {
                $pk = $this->hasPk();
                if (false !== $pk) {
                    return $this->find($pk);
                }
                return $this;
            }
            return $this;
        }

        public function findBy($field, $value, $one = false)
        {
            return $this->select("$this->_dbName.$this->_tableName.$field = " . $this->quote($value), $one);
        }

        public function findByIds(array $ids)
        {
            $collection = array();
            foreach ($ids as $id) {
                $collection[$id] = $this->find($id);
            }
            return $collection;
        }

        public function all()
        {
            return $this->select();
        }

        public function query($q)
        {
            $_start = $this->_getTime();
            $qTab = explode(' ', i::lower($q));
            $qFirst = $qTab[0];
            $rowAffected = ($qFirst == 'insert' || $qFirst == 'update' || $qFirst == 'delete');
            $res = $this->_query($q);
            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            $this->_incQueries($_start);
            if (true === $rowAffected) {
                return $count;
            }
            if (false === $res) {
                return null;
            }
            if ($count == 0) {
                return null;
            } elseif ($count == 1) {
                if (is_array($res)) {
                    return (object) current($res);
                } else {
                    return $res->fetchObject();
                }
            } else {
                $collection = new FTVQueryCollection;
                foreach ($res as $row) {
                    $collection[] = (object) $row;
                }
                return $collection;
            }
        }

        public function queryObject($q)
        {
            $_start = $this->_getTime();
            $qTab = explode(' ', i::lower($q));
            $qFirst = $qTab[0];
            $rowAffected = ($qFirst == 'insert' || $qFirst == 'update' || $qFirst == 'delete');
            $res = $this->_query($q);
            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            $this->_incQueries($_start);
            if (true === $rowAffected) {
                return $count;
            }
            if (false === $res) {
                return null;
            }
            if ($count == 0) {
                return null;
            } else {
                $collection = new FTVQueryCollection;
                foreach ($res as $row) {
                    $obj = new FTVRow;
                    $collection[] = $obj->populate($row);
                }
                return $collection;
            }
        }

        public function where($condition = '', $operator = 'AND')
        {
            if (strlen($condition)) {
                $this->_datas['query']['wheres'][] = array($condition, $operator);
            }
            return $this;
        }

        public function order($field, $direction = 'ASC')
        {
            if (!ake('query', $this->_datas)) {
                $this->_datas['query'] = array();
            }
            if (!ake('order', $this->_datas['query'])) {
                $this->_datas['query']['order'] = array();
            }
            $this->_datas['query']['order'][] = array($field, i::upper($direction));
            return $this;
        }

        public function limit($limit, $offset = 0)
        {
            $this->_datas['query']['limit'] = array($limit, $offset);
            return $this;
        }

        public function groupBy($field)
        {
            $this->_datas['query']['groupBy'] = $field;
            return $this;
        }

        public function distinct()
        {
            $this->_datas['query']['distinct'] = true;
            return $this;
        }

        public function join($model, $type = 'LEFT')
        {
            if (!ake('query', $this->_datas)) {
                $this->_datas['query'] = array();
            }
            if (!ake('join', $this->_datas['query'])) {
                $this->_datas['query']['join'] = array();
            }
            if (!is_object($model)) {
                throw new FTV_Exception("The first argument must be an instance of model.");
            }

            $tableModel = $model->_tableName;
            $pk = $model->pk();

            $fk = ($pk == 'id') ? $model->_tableName . '_id' : $pk;

            $join = $type . ' JOIN ' . $model->_dbName . '.' . $tableModel . ' ON ' . $this->_dbName . '.' . $this->_tableName . '.' . $fk . ' = ' . $model->_dbName . '.' . $tableModel . '.' . $pk . ' ';


            if (!in_array($join, $this->_datas['query']['join'])) {
                $this->_datas['query']['join'][] = $join;
            }
            return $this;
        }

        /* Ajout de la methode fetch pour forcer a retourner un objet collection et non un objet unique si le nombre de resultats de la requete vaut 1 */
        public function fetch($bool = true)
        {
            $this->_array = $bool;
            return $this;
        }

        public function cache($duration = 60)
        {
            $this->_datas['query']['cache'] = $duration;
            $this->_cache = $duration;
            return $this;
        }

        public function selectFields($fields)
        {
            if (ake('fields', $this->_datas['query'])) {
                throw new FTV_Exception("The fields for this query have been ever setted.");
            }
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    if (!in_array($field, $this->_datas['fieldsSave'])) {
                        throw new FTV_Exception("The field '$field' is unknow in $this->_table model.");
                    }
                }
                $this->_datas['query']['fields'] = $fields;
            } else {
                throw new FTV_Exception("You must specify an array argument to set the query's select fields.");
            }
            return $this;
        }

        public function select($where = null, $one = false, $fk = true)
        {
            $_start = $this->_getTime();
            $collection = array();
            $this->runEvent('selecting');
            $order = '';
            $limit = '';
            if (!ake('models', $this->_datas)) {
                $this->_datas['models'] = array();
            }
            if (count($this->_datas['models'])) {
                foreach ($this->_datas['models'] as $ffield => $fobject) {
                    $ffield = $ffield . '_id';
                    if (in_array($ffield, $this->_datas['fieldsSave'])) {
                        $m = u::loadModel(substr($ffield, 0, -3), $fobject->_entity);
                        $this->join($m);
                    }
                }
            }
            $cache = false;
            $join = '';
            $distinct = '';
            $groupBy = '';
            $where = (null === $where) ? '1 = 1' : $where;
            $fields = $this->_dbName . '.' . $this->_tableName . '.' . implode(', ' . $this->_dbName . '.' . $this->_tableName . '.', $this->_datas['fieldsSave']);
            if (ake('query', $this->_datas)) {
                if (ake('wheres', $this->_datas['query'])) {
                    if ($where == '1 = 1') {
                        $where = '';
                    }
                    foreach ($this->_datas['query']['wheres'] as $wq) {
                        list($condition, $operator) = $wq;
                        if (strlen($where)) {
                            $where .= " $operator ";
                        }
                        $where .= "$condition";
                    }
                }
                if (ake('order', $this->_datas['query'])) {
                    $order = 'ORDER BY ';
                    $i = 0;
                    foreach ($this->_datas['query']['order'] as $qo) {
                        list($field, $direction) = $qo;
                        if ($i > 0) {
                            $order .= ', ';
                        }
                        $order .= "$this->_dbName.$this->_tableName.$field $direction";
                        $i++;
                    }
                }
                if (ake('limit', $this->_datas['query'])) {
                    list($max, $offset) = $this->_datas['query']['limit'];
                    $limit = "LIMIT $offset, $max";
                }
                if (ake('fields', $this->_datas['query'])) {
                    $fields = $this->_dbName . '.' . $this->_tableName . '.' . implode(', ' . $this->_dbName . '.' . $this->_tableName . '.', $this->_datas['query']['fields']);
                }
                if (ake('join', $this->_datas['query'])) {
                    $join = implode(' ', $this->_datas['query']['join']);
                }
                if (ake('groupBy', $this->_datas['query'])) {
                    $groupBy = 'GROUP BY ' . $this->_datas['query']['groupBy'];
                }
                if (ake('distinct', $this->_datas['query'])) {
                    $distinct = (true === $this->_datas['query']['distinct']) ? 'DISTINCT' : '';
                }
                if (ake('cache', $this->_datas['query'])) {
                    $cache = $this->_datas['query']['cache'];
                }
            }

            $q = "SELECT $distinct $fields FROM $this->_dbName.$this->_tableName $join WHERE $where $order $limit $groupBy";

            $res = $this->_query($q);
            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            if ($count) {
                $classCollection = $this->_datas['classCollection'];
                $collection = new $classCollection;
                foreach ($res as $row) {
                    $classModel = $this->_datas['classModel'];
                    $obj = new $classModel;
                    foreach ($obj->fields() as $field) {
                        if (isset($row[$field])) {
                            if (is_numeric($row[$field]) && null !== $row[$field]) {
                                $obj->$field = (int) $row[$field];
                            } else {
                                $obj->$field = $row[$field];
                            }
                        }
                    }
                    $collection[] = $obj;
                    if (true === $one) {
                        $this->runEvent('selected');
                        return $obj;
                    }
                }
            }
            $collection = (count($collection) == 0) ? null : $collection;
            if (false === $this->_array) {
                $collection = (count($collection) == 1 && null !== $collection) ? current($collection) : $collection;
            }
            $this->runEvent('selected');
            unset($this->_datas['query']);
            $this->_cache = false;
            $this->_incQueries($_start);
            return $collection;
        }

        public function hasPk()
        {
            $primary = $this->pk();
            $vars = get_object_vars($this);
            foreach ($vars as $key => $value) {
                if ($key == $primary && null !== $value) {
                    return $value;
                }
            }
            return false;
        }

        public function isNew()
        {
            return (false === $this->hasPk()) ? true : false;
        }

        public function attributes()
        {
            $class = $this->_entity . ucfirst($this->_table) . 'Attributes';
            $obj = new $class;
            foreach ($this->_datas['fieldsSave'] as $field) {
                $obj->$field = $this->$field;
            }
            return $obj;
        }

        public function delete($where = null)
        {
            $_start = $this->_getTime();
            $this->runEvent('deleting');
            if (null !== $where) {
                $q = "DELETE FROM $this->_dbName.$this->_tableName WHERE $where";
            } else {
                $pkValue = $this->hasPk();
                if (false === $pkValue) {
                    return false;
                } else {
                    $pk = $this->pk();
                    $q = "DELETE FROM $this->_dbName.$this->_tableName WHERE $pk = " . $this->quote($pkValue);
                }
            }
            $del = $this->query($q);
            $this->runEvent('deleted');
            $this->_incQueries($_start);
            return $del;
        }

        public function debug()
        {
            $array = (array) $this;
            u::dump($array);
            return $this;
        }

        protected function queries()
        {
            return $this->_datas['queries'];
        }

        public function save()
        {
            $this->runEvent('saving');
            $key = $this->_dbName . '.' . $this->_tableName;
            if (!in_array($key, $this->_bufferTables)) {
                array_push($this->_bufferTables, $key);
            }
            $pkValue = $this->hasPk();
            if (false === $pkValue || true === $this->_newRow) {
                return $this->insert();
            } else {
                return $this->update();
            }
            return $this;
        }

        public function isNullable($field)
        {
            $value = $this->_datas['isNullable'][$field];
            if (false === $value) {
                if (null === $this->$field || !strlen($this->$field)) {
                    throw new FTV_Exception("The field '$field' must not be nulled.");
                }
            }
        }

        public function insert()
        {
            $_start = $this->_getTime();
            $pk = $this->pk();
            $this->runEvent('inserting');
            $q = "INSERT INTO $this->_tableName SET ";
            foreach ($this->_datas['fieldsSave'] as $field) {
                if ($field != $pk || true === $this->_newRow) {
                    $isNullable = $this->isNullable($field);
                    $q .= "$field = " . $this->quote($this->$field) . ", ";
                }
            }
            $q = substr($q, 0, -2);
            $this->_query($q);
            $this->runEvent('inserted');
            $this->_incQueries($_start);
            $this->setId($this->lastInsertId());
            $this->_newRow = false;
            return $this;
        }

        public function lastInsertId()
        {
            $db = $this->_getConnexion();
            return $db->lastInsertId();
        }

        public function _needToUpdate()
        {
            $id = $this->getId();
            $originalRow = $this->find($id);
            if (null !== $originalRow) {
                $a1 = md5(serialize($this->_getValues()));
                $a2 = md5(serialize($originalRow->_getValues()));
                return $a1 == $a2 ? false : true;
            }
            return true;
        }

        public function _getValues()
        {
            $return = array();
            foreach ($this->fields() as $field) {
                $return[$field] = $this->$field;
            }
            return $return;
        }

        public function update()
        {
            $_start = $this->_getTime();
            $id = $this->getId();
            if (true === $this->_needToUpdate()) {
                $this->runEvent('updating');
                $pk = $this->pk();
                $q = "UPDATE $this->_tableName SET ";
                foreach ($this->_datas['fieldsSave'] as $field) {
                    if ($field != $pk) {
                        $isNullable = $this->isNullable($field);
                        $q .= "$field = " . $this->quote($this->$field) . ", ";
                    }
                }
                $q = substr($q, 0, -2);
                $q .= " WHERE $pk = " . $this->quote($id);
                $update = $this->_query($q);
                $this->runEvent('updated');
                $this->_incQueries($_start);
            }
            return $this;
        }

        public function describe()
        {
            $desc = array();
            $q = 'DESCRIBE ' . $this->_dbName . '.' . $this->_tableName;
            $res = $this->_query($q);

            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }
            if (0 < $count) {
                $field   = 0;
                $type    = 1;
                $null    = 2;
                $key     = 3;
                $default = 4;
                $extra   = 5;
                $i = 1;
                $p = 1;
                foreach ($res as $row) {
                    list($length, $scale, $precision, $unsigned, $primary, $index, $primaryPosition, $identity) = array(null, null, null, null, false, false, null, false);
                    if (preg_match('/unsigned/', $row[$type])) {
                        $unsigned = true;
                    }
                    if (preg_match('/^((?:var)?char)\((\d+)\)/', $row[$type], $matches)) {
                        $row[$type] = $matches[1];
                        $length = $matches[2];
                    } else if (preg_match('/^decimal\((\d+),(\d+)\)/', $row[$type], $matches)) {
                        $row[$type] = 'decimal';
                        $precision = $matches[1];
                        $scale = $matches[2];
                    } else if (preg_match('/^float\((\d+),(\d+)\)/', $row[$type], $matches)) {
                        $row[$type] = 'float';
                        $precision = $matches[1];
                        $scale = $matches[2];
                    } else if (preg_match('/^((?:big|medium|small|tiny)?int)\((\d+)\)/', $row[$type], $matches)) {
                        $row[$type] = $matches[1];
                        // The optional argument of a MySQL int type is not precision
                        // or length; it is only a hint for display width.
                    }
                    if (strlen($row[$key])) {
                        if (i::upper($row[$key]) == 'PRI') {
                            $primary = true;
                            $primaryPosition = $p;
                            if ($row[$extra] == 'auto_increment') {
                                $identity = true;
                                $index = true;
                            } else {
                                $identity = false;
                            }
                            ++$p;
                        } else {
                            $index = true;
                        }
                    }
                    $desc[$this->foldCase($row[$field])] = array(
                        'ENTITY_NAME'      => $this->foldCase($this->_dbName),
                        'TABLE_NAME'       => $this->foldCase($this->_tableName),
                        'COLUMN_NAME'      => $this->foldCase($row[$field]),
                        'COLUMN_POSITION'  => $i,
                        'DATA_TYPE'        => $row[$type],
                        'DEFAULT'          => $row[$default],
                        'NULLABLE'         => (bool) ($row[$null] == 'YES'),
                        'LENGTH'           => $length,
                        'SCALE'            => $scale,
                        'PRECISION'        => $precision,
                        'UNSIGNED'         => $unsigned,
                        'PRIMARY'          => $primary,
                        'PRIMARY_POSITION' => $primaryPosition,
                        'INDEX'            => $index,
                        'IDENTITY'         => $identity
                    );
                    ++$i;
                }
            }
            return $desc;
        }

        public function foldCase($string)
        {
            $value = (string) $string;
            return $value;
        }

        protected function map()
        {
            $_start = $this->_getTime();
            if (!ake('relationshipEntities', $this->_datas['configModel'])) {
                $this->_datas['configModel']['relationshipEntities'] = array();
            }
            $q = "SHOW COLUMNS FROM $this->_tableName";

            $key = sha1($q . $this->_dbName);
            $maps = u::get('FTVModelsMap');
            $maps = (null === $maps) ? array() : $maps;
            if (ake($key, $maps)) {
                $res = $maps[$key];
                $count = count($res);
            } else {
                $res = $this->_query($q);
                $cols = array();
                if (is_array($res)) {
                    $count = count($res);
                } else {
                    $count = $res->rowCount();
                }
            }
            if (false === $res) {
                throw new FTV_Exception("This table $this->_table doesn't exist in $this->_entity entity.");
            }
            if ($count > 0) {
                foreach ($res as $row) {
                    $cols[] = $row;
                    $field = $row['Field'];
                    $this->_datas['fields'][] = $field;
                    $this->_datas['fieldsSave'][] = $field;
                    $this->_datas['type'][$field] = typeSql($row['Type']);
                    $this->_datas['isNullable'][$field] = ('yes' == i::lower($row['Null'])) ? true : false;
                    $this->$field = null;
                    if ($row['Key'] == 'PRI') {
                        $this->_datas['pk'] = $field;
                        $this->_datas['pks'][] = $field;
                    }
                    if (strlen($row['Key']) && $row['Key'] != 'PRI') {
                        $this->_datas['keys'][] = $field;
                    }
                }
                if (ake('pk', $this->_datas)) {
                    if (null === $this->_datas['pk']) {
                        if (in_array($this->_table . '_id', $this->_datas['fields'])) {
                            $this->_datas['pk'] = $this->_table . '_id';
                            $this->_datas['pks'][] = $this->_table . '_id';
                        }
                    }
                } else {
                    if (in_array($this->_table . '_id', $this->_datas['fields'])) {
                        $this->_datas['pk'] = $this->_table . '_id';
                        $this->_datas['pks'][] = $this->_table . '_id';
                    }
                }
                if (ake('keys', $this->_datas)) {
                    if (!count($this->_datas['keys'])) {
                        foreach ($this->_datas['fields'] as $field) {
                            $isId = '_id' == substr($field, -3);
                            if (true === $isId) {
                                if ($field != $this->_datas['pk']) {
                                    $this->_datas['keys'][] = $field;
                                }
                            }
                        }
                    }
                } else {
                    foreach ($this->_datas['fields'] as $field) {
                        $isId = '_id' == substr($field, -3);
                        if (true === $isId) {
                            if ($field != $this->_datas['pk']) {
                                $this->_datas['keys'][] = $field;
                            }
                        }
                    }
                }

                if (ake('relationship', $this->_datas['configModel'])) {
                    foreach ($this->_datas['configModel']['relationship'] as $field => $relationship) {
                        if (!in_array($field, $this->_datas['fields'])) {
                            $this->_datas['fields'][] = $field . '_id';
                            $this->_datas['keys'][] = $field . '_id';
                            if ($relationship != 'oneToMany' && $relationship != 'manyToMany') {
                                if (ake($field, $this->_datas['configModel']['relationshipEntities'])) {
                                    $entity = $this->_datas['configModel']['relationshipEntities'][$field];
                                } else {
                                    $entity = $this->_entity;
                                }
                                $fieldModel = 'FTVModel_' . ucfirst($entity) . '_' . ucfirst($field);
                                $this->_datas['models'][$field] = new $fieldModel;
                            }
                        }
                    }
                }
            }
            $this->_datas['fields'] = array_unique($this->_datas['fields']);
            if(ake('keys', $this->_datas)) {
                $this->_datas['keys'] = array_unique($this->_datas['keys']);
            }
            $this->_incQueries($_start);
            if (!ake($key, $maps)) {
                $maps[$key] = $cols;
                u::set('FTVModelsMap', $maps);
            }
            return $this;
        }

        public function model($model, $id = null)
        {
            if (ake('models', $this->_datas)) {
                if (ake($model, $this->_datas['models'])) {
                    if (null === $id) {
                        return $this->_datas['models'][$model];
                    } else {
                        $classModel = $this->_datas['models'][$model];
                        $obj = new $classModel;
                        return $obj->find($id);
                    }
                } else {
                    throw new FTV_Exception("This model '$model' has not relationship in '$this->_table' model.");
                }
            } else {
                throw new FTV_Exception("This model '$model' has not relationship in '$this->_table' model.");
            }
        }

        public function getId()
        {
            $pk = $this->_datas['pk'];
            return $this->$pk;
        }

        public function setId($id)
        {
            $pk = $this->_datas['pk'];
            $this->$pk = $id;
            $this->_newRow = true;
            return $this;
        }

        public function pk()
        {
            $pk = $this->_datas['pk'];
            /* On traite le cas des vues */
            if (null === $pk) {
                $pk = $this->_tableName . '_id';
            }
            return $pk;
        }

        public function pks()
        {
            return $this->_datas['pks'];
        }

        public function fields()
        {
            return $this->_datas['fields'];
        }

        public function fieldsSave()
        {
            return $this->_datas['fieldsSave'];
        }

        public function keys()
        {
            return $this->_datas['keys'];
        }

        public function types()
        {
            return $this->_datas['type'];
        }

        /* méthodes transactionnelles */

        public function begin()
        {
            $db = $this->_getConnexion();
            $begin = $db->beginTransaction();
            return $this;
        }

        public function inTransaction()
        {
            $db = $this->_getConnexion();
            return $db->inTransaction();
        }

        public function commit()
        {
            $db = $this->_getConnexion();
            $commit = $db->commit();
            return $this;
        }

        public function rollback()
        {
            $db = $this->_getConnexion();
            $rollback = $db->rollBack();
            return $this;
        }

        /* Alias Rollback */
        public function fail()
        {
            return $this->rollback();
        }

        public function __call($method, $args)
        {
            if (empty($this->_datas['foreignFields'])) {
                $this->_datas['foreignFields'] = array();
            }
            if (!is_array($this->_datas['foreignFields'])) {
                $this->_datas['foreignFields'] = array($this->_datas['foreignFields']);
            }
            if (substr($method, 0, 3) == 'get') {
                $vars = array_values($this->fields());
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 3)));
                $var = i::lower($uncamelizeMethod);
                if (in_array($var, $vars) || ake($var, $this->_datas['foreignFields'])) {
                    if (ake($var, $this->_datas['foreignFields'])) {
                        if(('true' != $this->_datas['foreignFields'][$var])) {
                            return $this->_datas['foreignFields'][$var];
                        }
                        $field = $var . '_id';
                        $classModel = $this->_datas['classModel'];
                        $obj = new $classModel;
                        if (in_array($field, $this->_datas['keys'])) {
                            $modelField = repl('_id', '', $field);
                            if (isset($this->_datas['configModel']['relationship']) && ake($modelField, $this->_datas['configModel']['relationship'])) {
                                $m = $this->_datas['configModel']['relationship'][$modelField];
                                if (ake($modelField, $this->_datas['configModel']['relationshipEntities'])) {
                                    $entity = $this->_datas['configModel']['relationshipEntities'][$modelField];
                                } else {
                                    $entity = $obj->_entity;
                                }
                                if (null !== $m) {
                                    $model = 'FTVModel_' . ucfirst($entity) . '_' . ucfirst($modelField);
                                    switch ($m) {
                                        case 'manyToOne':
                                        case 'oneToOne':
                                            $nObj = new $model;
                                            if (!empty($this->_datas['configModel']['relationshipKeys'][$modelField])) {
                                                $field = $this->_datas['configModel']['relationshipKeys'][$modelField];
                                            }
                                            if (!is_null($this->$field)) {
                                                if (false === $this->_cache) {
                                                    $result = $nObj->find($this->$field);
                                                } else {
                                                    $result = $nObj->cache($this->_cache)->find($this->$field);
                                                    $this->_cache = false;
                                                }
                                                $this->_datas['foreignFields'][$var] = $result;
                                                return $result;
                                            }
                                            break;
                                        case 'manyToMany':
                                        case 'oneToMany':
                                            $model = 'FTVModel_' . ucfirst($entity) . '_' . ucfirst(substr($modelField, 0, -1));
                                            $nObj = new $model;
                                            $getter = $this->pk(); //$obj->_tableName . '_id';
                                            $fk = (!empty($this->_datas['configModel']['relationshipKeys'][$modelField])) ? $this->_datas['configModel']['relationshipKeys'][$modelField] : $obj->pk();
                                            if (false === $this->_cache) {
                                                $result = $nObj->where($nObj->_dbName . '.' . $nObj->_tableName . "." . $fk . " = " . $nObj->quote($this->$getter))->select();
                                            } else {
                                                $result = $nObj->cache($this->_cache)->where($nObj->_dbName . '.' . $nObj->_tableName . "." . $fk . " = " . $nObj->quote($this->$getter))->select();
                                                $this->_cache = false;
                                            }
                                            $this->_datas['foreignFields'][$var] = $result;
                                            return $result;
                                            break;
                                    }
                                }
                            }
                        }
                    }
                    if (isset($this->$var) || is_null($this->$var)) {
                        return $this->$var;
                    } else {
                        throw new FTV_Exception("Unknown field $var in " . get_class($this) . " class.");
                    }
                }
                return null;
            } elseif (substr($method, 0, 3) == 'set') {
                $vars = array_values($this->fields());
                $value = $args[0];
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 3)));
                $var = i::lower($uncamelizeMethod);
                if (in_array($var, $vars) || ake($var, $this->_datas['foreignFields'])) {
                    if (ake($var, $this->_datas['foreignFields'])) {
                        $this->_datas['foreignFields'][$var] = $value;
                        $setField = $var . '_id';
                        if (in_array($setField, $vars) && isset($value->$setField)) {
                            $this->$setField = $value->$setField;
                            return $this;
                        } else {
                            return $this;
                        }
                    } else {
                        $this->$var = $value;
                        return $this;
                    }
                } else {
                    throw new FTV_Exception("Unknown field $var in " . get_class($this) . " class.");
                }
            } elseif (substr($method, 0, 3) == 'min') {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 3)));
                $var = i::lower($uncamelizeMethod);
                if ($var == 'id') {
                    $var = $this->pk();
                }
                $vars = array_values($this->fields());
                if (in_array($var, $vars)) {
                    return $this->min($var);
                }
            } elseif (substr($method, 0, 3) == 'max') {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 3)));
                $var = i::lower($uncamelizeMethod);
                if ($var == 'id') {
                    $var = $this->pk();
                }
                $vars = array_values($this->fields());
                if (in_array($var, $vars)) {
                    return $this->max($var);
                }
            } elseif (substr($method, 0, 3) == 'avg') {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 3)));
                $var = i::lower($uncamelizeMethod);
                if ($var == 'id') {
                    $var = $this->pk();
                }
                $vars = array_values($this->fields());
                if (in_array($var, $vars)) {
                    return $this->avg($var);
                }
            } elseif (substr($method, 0, 3) == 'sum') {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 3)));
                $var = i::lower($uncamelizeMethod);
                if ($var == 'id') {
                    $var = $this->pk();
                }
                $vars = array_values($this->fields());
                if (in_array($var, $vars)) {
                    return $this->sum($var);
                }
            } elseif (substr($method, 0, 5) == 'count') {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 5)));
                $var = i::lower($uncamelizeMethod);
                if ($var == 'id') {
                    $var = $this->pk();
                }
                $vars = array_values($this->fields());
                if (in_array($var, $vars)) {
                    return $this->count($var);
                }
            } elseif (substr($method, 0, 6) == 'findBy') {
                $vars = array_values($this->fields());
                $value = $args[0];
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 6)));
                $var = i::lower($uncamelizeMethod);
                if(is_object($value) && null !== $this->_datas['configModel']['relationship'][$var]) {
                    switch ($this->_datas['configModel']['relationship'][$var]) {
                        case 'manyToOne':
                        case 'oneToOne':
                            $field = $var . '_id';
                            $q = $this->_dbName . '.' . $this->_tableName . "." . $field . " = " . $this->quote($value->$field);
                            if (false === $this->_cache) {
                                return $this->where($q)->select();
                            } else {
                                return $this->cache($this->_cache)->where($q)->select();
                                $this->_cache = false;
                            }
                        case 'oneToMany':
                        case 'manyToMany':
                            $var = substr($var, 0, -1);
                            $field = $var . '_id';
                            $pk = $this->pk();
                            $pkValue = $value->$pk;
                            $q = $this->_dbName . '.' . $this->_tableName . "." . $pk . " = " . $this->quote($pkValue);
                            if (false === $this->_cache) {
                                return $this->where($q)->select();
                            } else {
                                return $this->cache($this->_cache)->where($q)->select();
                                $this->_cache = false;
                            }
                    }
                } else {
                    if (in_array($var, $vars) || $var == 'id') {
                        if ($var != 'id') {
                            return $this->findBy($var, $value);
                        } else {
                            return $this->find($value);
                        }
                    } else {
                        throw new FTV_Exception("Unknown field $var in " . get_class($this) . " class.");
                    }
                }
            }  elseif (substr($method, 0, 5) == 'where' && strlen($method) > 5) {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 5)));
                $var = i::lower($uncamelizeMethod);
                if ($var == 'id') {
                    $var = $this->pk();
                }
                $var = $this->_dbName . '.' . $this->_tableName . '.' . $var;
                $condition = $args[0];
                $operator = (isset($args[1])) ? $args[1] : 'AND';
                return $this->where("$var $condition", $operator);
            }  elseif (substr($method, 0, 7) == 'groupBy' && strlen($method) > 7) {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 7)));
                $var = i::lower($uncamelizeMethod);
                if ($var == 'id') {
                    $var = $this->pk();
                }
                return $this->groupBy($var);
            }  elseif (substr($method, 0, 7) == 'orderBy') {
                $direction = (count($args)) ? $args[0] : 'ASC';
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 7)));
                $var = i::lower($uncamelizeMethod);
                return $this->order($var, $direction);
            }  elseif (substr($method, 0, 9) == 'findOneBy') {
                $vars = array_values($this->fields());
                $value = $args[0];
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 9)));
                $var = i::lower($uncamelizeMethod);
                if (in_array($var, $vars) || $var == 'id') {
                    if ($var != 'id') {
                        return $this->findBy($var, $value, true);
                    } else {
                        return $this->find($value);
                    }
                } else {
                    throw new FTV_Exception("Unknown field $var in " . get_class($this) . " class.");
                }
            } else {
                $vars = array_values($this->fields());
                $uncamelizeMethod = i::uncamelize(lcfirst($method));
                $var = i::lower($uncamelizeMethod);
                if (in_array($var, $vars) || ake($var, $this->_datas['foreignFields'])) {
                    if (ake($var, $this->_datas['foreignFields'])) {
                        return $this->_datas['foreignFields'][$var];
                    }
                    if (isset($this->$var)) {
                        return $this->$var;
                    } else {
                        if (!method_exists($this, $method)) {
                            $this->$method = $args[0];
                        }
                    }
                }
            }
        }

        public function __set($name, $value)
        {
            if (!in_array($name, $this->_datas['fields'])) {
                throw new FTV_Exception("Unknown field $name in " . get_class($this) . " class.");
            } else {
                $this->$name = $value;
                return $this;
            }
        }

        public function __get($name)
        {
            $var = $name . '_id';
            if (!in_array($name, $this->_datas['fields']) && !in_array($var, $this->_datas['fields'])) {
                throw new FTV_Exception("Unknown field $name in " . get_class($this) . " class.");
            } else {
                if (isset($this->$name)) {
                    return $this->$name;
                }
                return null;
            }
        }

        /* since php 5.3.0 */
        public function __invoke()
        {
            $args = func_get_args();
            $nbArgs = count($args);
            if ($nbArgs == 0 || $nbArgs > 2) {
                return $this;
            } elseif ($nbArgs == 1) {
                if (!is_array($args[0])) {
                    return $this->find($args[0]);
                } else {
                    return $this->populate($args[0]);
                }
            } elseif ($nbArgs == 2) {
                list($field, $value) = $args;
                $this->$field = $value;
                return $this;
            }
        }

        protected function runEvent($event)
        {
            $events = array("model.{$event}.done", "model.{$event}: " . get_class($this) . '.done');
            u::run($events, array($this));
        }

        public function view()
        {
            $html = '<table cellpadding="5" cellspacing="0" border="0">';
            foreach ($this->_datas['fieldsSave'] as $field) {
                $html .= '<tr><td style="border: solid 1px;"> '. $field . '</td><td style="border: solid 1px;">' . i::utf8($this->$field) . '</td></tr>';
            }
            $html .= '</table>';
            return $html;
        }

        public function _incQueries($start)
        {
            $this->_numberOfQueries++;
            $this->_totalDuration += $this->_getTime() - $start;
            u::set('FTVNbQueries', $this->_numberOfQueries);
            u::set('FTVSQLTotalDuration', $this->_totalDuration);
        }

        public function _getTime()
        {
            $time = microtime();
            $time = explode(' ', $time, 2);
            return ($time[1] + $time[0]);
        }

        public function eraseCache()
        {
            $this->_session->erase('queries');
            $this->_session->erase('collections');
        }

        public function noBuffer()
        {
            $this->_buffer = false;
            return $this;
        }

        public function _getEntity()
        {
            return $this->_entity;
        }

        public function _getDbName()
        {
            return $this->_dbName;
        }

        public function _getTable()
        {
            return $this->_tableName;
        }

        private function _query($q)
        {
            //*GP* hr($q);
            $db = $this->_getConnexion();
            $key = $this->_dbName . '.' . $this->_tableName;
            /* On evite de refaire n fois les memes requetes select dans la meme instance */
            $cacheIt = (true === $this->_buffer && !in_array($key, $this->_bufferTables) && (i::lower(substr($q, 0, 6)) == 'select' || i::lower(substr($q, 0, 4)) == 'show' || i::lower(substr($q, 0, 8)) == 'describe')) ? true : false;
            if (true === $cacheIt) {
                $key = sha1($q . $this->_dbName);
                $buffer = $this->_buffer($key);
                if (false !== $buffer) {
                    $result = $buffer;
                } else {
                    $result = $db->prepare($q);
                    $result->execute();
                    if (false !== $result) {
                        $cols = array();
                        foreach ($result as $row) {
                            $cols[] = $row;
                        }
                        $result = $cols;
                        //*GP* FTVLog($q, DIR_LOGS . DS . date("Y-m-d") . '_queries.log', null, 'query');
                    }
                    if (true === $this->_buffer) {
                        $this->_buffer($key, $result);
                    }
                }
            } else {
                $result = $db->prepare($q);
                $result->execute();
                //*GP* FTVLog($q, DIR_LOGS . DS . date("Y-m-d") . '_queries.log', null, 'query');
            }
            return $result;
        }

        private function _buffer($key, $data = null)
        {
            if (false === $this->_buffer) {
                return false;
            }
            $timeToBuffer = (false !== $this->_cache) ? $this->_cache * 60 : config::get('database.buffer');
            $ext = (false !== $this->_cache) ? 'cache' : 'buffer';
            $file = APPLICATION_PATH . DS . 'cache' . DS . $key . '_sql.' . $ext;
            if (FTV_File::exists($file)) {
                $age = time() - filemtime($file);
                if ($age > $timeToBuffer) {
                    FTV_File::delete($file);
                } else {
                    return unserialize(fgc($file));
                }
            }
            if (null === $data) {
                return false;
            }
            FTV_File::put($file, serialize($data));
        }

        private function _log($log, $type = 'query')
        {
            $logs = u::get('queriesLogs');
            if (null === $logs) {
                $logs = new FTV_Log(DIR_LOGS . DS . date("Y-m-d") . '_queries.log');
                u::set('queriesLogs', $logs);
            }
            $logs->write($type, $log);
        }

        public function _get($key)
        {
            return $this->$key;
        }

        public function _getEmFromKey($key)
        {
            $classModel = $this->_datas['classModel'];
            $obj = new $classModel;
            if (in_array($key, $this->_datas['keys'])) {
                $modelField = repl('_id', '', $key);
                if (isset($this->_datas['configModel']['relationship']) && ake($modelField, $this->_datas['configModel']['relationship'])) {
                    $m = $this->_datas['configModel']['relationship'][$modelField];
                    if (ake($modelField, $this->_datas['configModel']['relationshipEntities'])) {
                        $entity = $this->_datas['configModel']['relationshipEntities'][$modelField];
                    } else {
                        $entity = $obj->_entity;
                    }
                    if (null !== $m) {
                        $model = 'FTVModel_' . ucfirst($entity) . '_' . ucfirst($modelField);
                        return new $model;
                    }
                }
            }
            return null;
        }

        public function __toString()
        {
            return get_class($this);
        }

        public function backup()
        {
            $newline = NL;
            $tables = $this->_query('SHOW TABLES');
            if (is_array($tables)) {
                $count = count($tables);
            } else {
                $count = $tables->rowCount();
            }
            if (false === $tables) {
                throw new FTV_Exception("This database $this->_entity contains no table.");
            }
            $output = '';
            foreach ($tables as $table) {
                $table = current($table);
                $queryTable = "SHOW CREATE TABLE `" . $this->_dbName . '`.`' . $table . '`';

                $res = $this->_query($queryTable);
                if (is_array($res)) {
                    $count = count($res);
                } else {
                    $count = $res->rowCount();
                }
                if (false === $res || 1 > $count) {
                    continue;
                }
                $res = current($res);

                $create = $res[1];
                $output .= '#' . $newline . '# TABLE STRUCTURE FOR: ' . $table . $newline . '#' . $newline . $newline;
                $output .= 'DROP TABLE IF EXISTS ' . $table . ';' . $newline . $newline;
                $output .= $create . ';' . $newline . $newline;

                $res = $this->_query("SELECT * FROM $table");
                if (is_array($res)) {
                    $count = count($res);
                } else {
                    $count = $res->rowCount();
                }
                if (false === $res || 1 > $count) {
                    continue;
                }

                foreach ($res as $row) {
                    $fields = array();
                    $values = array();
                    foreach ($row as $key => $value) {
                        if (!is_numeric($key)) {
                            $fields[] = $key;
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $output .= 'INSERT INTO ' . $table . ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ');' . $newline;
                }
                $output .= $newline;
            }
            return $output;
        }

        public function col($sql = '')
        {
            if (!empty($sql)) {
                $rows = $this->query($sql);
            } else {
                $rows = $this->select();
            }
            if (is_object($rows)) {
                $rows = (array) $rows;
            }
            $cols = array();
            if ($rows && is_array($rows) && count($rows) > 0) {
                foreach ($rows as $row) {
                    if (is_object($row)) {
                        $row = (array) $row;
                    }
                    $cols[] = array_shift($row);
                }
            }
            return $cols;
        }

        public function cell($sql = '')
        {
            if (!empty($sql)) {
                $rows = $this->query($sql);
            } else {
                $rows = $this->select();
            }
            if (is_object($rows)) {
                $rows = (array) $rows;
            }
            $row1 = array_shift($rows);
            $col1 = array_shift($row1);
            return $col1;
        }

        public function row($sql = '')
        {
            if (!empty($sql)) {
                $rows = $this->query($sql);
            } else {
                $rows = $this->select();
            }
            if (is_object($rows)) {
                $rows = (array) $rows;
            }
            return array_shift($rows);
        }

        protected function bindParams($statement, $data)
        {
            foreach($data as $key => &$value) {
                if (is_integer($key)) {
                    if (is_null($value)) {
                        $statement->bindValue($key + 1, null, PDO::PARAM_NULL);
                    } elseif (true === $this->_isThisValueInt($value) && $value < 2147483648) {
                        $statement->bindParam($key + 1, $value, PDO::PARAM_INT);
                    } else {
                        $statement->bindParam($key + 1, $value, PDO::PARAM_STR);
                    }
                } else {
                    if (is_null($value)) {
                        $statement->bindValue($key, null, PDO::PARAM_NULL);
                    } elseif (true === $this->_isThisValueInt($value) && $value < 2147483648) {
                        $statement->bindParam($key, $value, PDO::PARAM_INT);
                    } else {
                        $statement->bindParam($key, $value, PDO::PARAM_STR);
                    }
                }
            }
        }

        private function _isThisValueInt($value)
        {
            return (boolean) (ctype_digit(strval($value)) && strval($value) === strval(intval($value)));
        }

        public function errorCode()
        {
            $db = $this->_getConnexion();
            return $db->errorCode();
        }

        public function errorInfo()
        {
            $db = $this->_getConnexion();
            return $db->errorInfo();
        }
    }
