<?php
    /**
     * ORM Oracle class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     * @author      Jeremy Cerri
     */

    class FTV_Oracle
    {
        protected $_db;
        protected $_table;
        protected $_entity;
        protected $_datas = array();
        protected $_dbName;
        protected $_tableName;

        protected function config()
        {
            if (strstr($this->_table, '_id')) {
                $this->_table = str_replace('_id', '', $this->_table);
            }
            $configs = FTV_Utils::get('FTVConfig');
            $configModel = FTV_Utils::get('FTVConfigModels');
            if (!array_key_exists($this->_entity, $configModel)) {
                throw new FTV_Exception("The config models file can't read $this->_entity entity");
            }
            //$DB = $configModel[$this->_entity]['DB'];
            $keyCache = sha1(session_id() . date('dmY') . $this->_table);
            $this->_datas['keyCache'] = $keyCache;

           // $this->_datas['config']['db'] = $configs[$DB];
            //$this->_datas['config']['resources'] = $configs['resources']['db'][$DB];

            $username = $configs['db']['oracle']['username'];
            $password = $configs['db']['oracle']['password'];
            $dbName = $configs['db']['oracle']['dbname'];

            $connexions = FTV_Utils::get('ORMConnexions');
            if (null === $connexions) {
                $connexions = array();
            }

            $keyConnexion = md5(serialize(array("oci:dbname=$dbName", $username, $password)));
            if (array_key_exists($keyConnexion, $connexions)) {
                $this->_db = $connexions[$keyConnexion];
            } else {
                $this->_db = oci_connect($username, $password, $dbName); //FTV_Utils::newInstance('PDO', array("$adapter:dbname=$dbName;host=$host", $username, $password);

                $connexions[$keyConnexion] = $this->_db;
                FTV_Utils::set('ORMConnexions', $connexions);
            }

            $this->_dbName = $dbName; //$this->_datas['config']['resources']['params']['dbname'];
            //unset($this->_datas['config']['resources']);
            $this->_datas['classCollection'] = $this->_entity . ucfirst($this->_table) . 'ResultModelCollection';
            $this->_datas['classModel'] = 'FTVModelOracle_' . ucfirst($this->_entity) . '_' . ucfirst($this->_table);

            if (!array_key_exists($this->_table, $configModel[$this->_entity]['tables'])) {
                throw new FTV_Exception("The config models file can't read $this->_table table [$this->_entity].");
            }
            $configModel = $configModel[$this->_entity]['tables'][$this->_table];
            $this->_datas['configModel'] = $configModel;
            $this->_datas['salt'] = base_convert(sha1(uniqid(mt_rand(), true)), 16, 36);
            $this->_tableName = (isset($configModel['tableName'])) ? $configModel['tableName'] : $this->_table;
            return $this;
        }

        public function Db()
        {
            return $this->_db;
        }

        public function newInstance($id = null)
        {
            $class = 'FTVModelOracle_' . ucfirst($this->_entity) . '_' . ucfirst($this->_table);
            if (null === $id) {
                $obj = new $class;
                $obj = $obj->map();
                foreach ($obj->fields() as $field) {
                    if (is_array($obj->_datas['keys'])) {
                        if (in_array($field, $obj->_datas['keys'])) {
                            $modelField = str_replace('_id', '', $field);
                            if (array_key_exists($modelField, $obj->_datas['configModel']['relationship'])) {
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
            $this->config()->map();
        }

        protected function quote($value)
        {
            if (is_int($value) || is_float($value)) {
                return $value;
            }
            $value = str_replace("'", "''", $value);
            return "'" . addcslashes($value, "\000\n\r\\\032") . "'";
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
            $pks = $this->pk();
            if (count($pks) > 1) {
                throw new FTV_Exception("Multiple primary key ! Not implemented yet.");
            }
            $where = array();
            foreach ($pks as $pk) {
                $where[] = "$this->_tableName.$pk = " . $this->quote($id);
            }
            if (!count($fields)) {
                return $this->select(implode(" AND ", $where), true, $fk);
            } else {
                $obj = $this->select(implode(" AND ", $where), true, $fk);
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
            if (!array_key_exists('foreignFields', $this->_datas)) {
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
            return $this->select("$this->_tableName.$field = " . $this->quote($value), $one);
        }

        public function all()
        {
            return $this->select();
        }

        public function query($q)
        {
            $this->_datas['queries'][] = $q;
            $qTab = explode(' ', FTV_Inflector::lower($q));
            $qFirst = $qTab[0];
            $rowAffected = ($qFirst == 'insert' || $qFirst == 'update' || $qFirst == 'delete');

            $statement = oci_parse($this->_db, $q);
            if (!$statement) {
                return null;
            }
            if (!oci_execute($statement)) {
                return null;
            }

            if (true === $rowAffected) {
                return oci_num_rows($statement);
            }

            $nrows = oci_fetch_all($statement, $results, null, null, OCI_FETCHSTATEMENT_BY_ROW);

            oci_free_statement($statement);

            return $results;
        }

        public function where($condition, $operator = 'AND')
        {
            $this->_datas['query']['wheres'][] = array($condition, $operator);
            return $this;
        }

        public function order($field, $direction = 'ASC')
        {
            $this->_datas['query']['order'] = array($field, $direction);
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

        public function join($model, $type = 'LEFT')
        {
            if (!array_key_exists('query', $this->_datas)) {
                $this->_datas['query'] = array();
            }
            if (!array_key_exists('join', $this->_datas['query'])) {
                $this->_datas['query']['join'] = array();
            }
            if (!is_object($model)) {
                throw new FTV_Exception("The first argument must be an instance of model.");
            }

            $tableModel = $model->_tableName;
            $pk = $model->pk();
            $fk = ($pk == 'id') ? $model->_tableName . '_id' : $pk;

            $join = $type . ' JOIN ' . $model->_dbName . '.' . $tableModel . ' ON ' . $this->_tableName . '.' . $fk . ' = ' . $model->_dbName . '.' . $tableModel . '.' . $pk . ' ';


            if (!in_array($join, $this->_datas['query']['join'])) {
                $this->_datas['query']['join'][] = $join;
            }
            return $this;
        }

        public function selectFields($fields)
        {
            if (array_key_exists('fields', $this->_datas['query'])) {
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
            $collection = array();
            $this->runEvent('selecting');
            $order = '';
            $limit = '';
            if (!array_key_exists('models', $this->_datas)) {
                $this->_datas['models'] = array();
            }
            if (count($this->_datas['models'])) {
                foreach ($this->_datas['models'] as $ffield => $fobject) {
                    $ffield = $ffield . '_id';
                    if (in_array($ffield, $this->_datas['fieldsSave'])) {
                        $m = FTV_Utils::loadModel(substr($ffield, 0, -3), $fobject->_entity);
                        $this->join($m);
                    }
                }
            }
            $join = '';
            $groupBy = '';
            $where = (null === $where) ? '1 = 1' : $where;
            $fields = $this->_tableName . '.' . implode(', ' . $this->_tableName . '.', $this->_datas['fieldsSave']);
            if (array_key_exists('query', $this->_datas)) {
                if ($where == '1 = 1' && array_key_exists('wheres', $this->_datas['query'])) {
                    $where = '';
                    foreach ($this->_datas['query']['wheres'] as $wq) {
                        list($condition, $operator) = $wq;
                        if (strlen($where)) {
                            $where .= " $operator ";
                        }
                        $where .= "$condition";
                    }
                }
                if (array_key_exists('order', $this->_datas['query'])) {
                    list($field, $direction) = $this->_datas['query']['order'];
                    $order = "ORDER BY $this->_tableName.$field $direction";
                }
                if (array_key_exists('limit', $this->_datas['query'])) {
                    list($max, $offset) = $this->_datas['query']['limit'];
                    $limit = "LIMIT $offset, $max";
                }
                if (array_key_exists('fields', $this->_datas['query'])) {
                    $fields = $this->_tableName . '.' . implode(', ' . $this->_tableName . '.', $this->_datas['query']['fields']);
                }
                if (array_key_exists('join', $this->_datas['query'])) {
                    $join = implode(' ', $this->_datas['query']['join']);
                }
                if (array_key_exists('groupBy', $this->_datas['query'])) {
                    $groupBy = 'GROUP BY ' . $this->_datas['query']['groupBy'];
                }
            }
            $q = "SELECT $fields FROM $this->_tableName $join WHERE $where $order $limit $groupBy";
            $this->_datas['queries'][] = $q;
            $res = $this->query($q);
            if (count($res)) {
                $classCollection = $this->_datas['classCollection'];
                $collection = new $classCollection;
                foreach ($res as $row) {
                    $classModel = $this->_datas['classModel'];
                    $obj = new $classModel;
                    foreach ($obj->fields() as $field) {
                        $rowField = strtoupper($field);
                        if (isset($row[$rowField])) {
                            if (strstr($this->_datas['type'][$field], 'NUMBER') && null !== $row[$rowField]) {
                                $obj->$field = (int) $row[$rowField];
                            } else {
                                $obj->$field = $row[$rowField];
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
            $collection = (count($collection) == 1 && null !== $collection) ? current($collection) : $collection;
            $this->runEvent('selected');
            return $collection;
        }

        public function hasPk()
        {
            $primary = $this->pk();
            $vars = get_object_vars($this);
            foreach ($vars as $key => $value) {
                if (in_array($key, $primary) && null !== $value) {
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
            $this->runEvent('deleting');
            if (null !== $where) {
                $q = "DELETE FROM $this->_tableName WHERE $where";
            } else {
                $pkValue = $this->hasPk();
                if (false === $pkValue) {
                    return false;
                } else {
                    $pks = $this->pk();
                    $where = array();
                    foreach ($pks as $pk) {
                        $where[] = "$this->_tableName.$pk = " . $this->quote($this->$pk);
                    }
                    $q = "DELETE FROM $this->_tableName WHERE " . implode(" AND ", $where);
                }
            }
            $del = $this->query($q);
            $this->_datas['queries'][] = $q;
            $this->runEvent('deleted');
            return $del;
        }

        public function debug()
        {
            $array = (array) $this;
            FTV_Utils::dump($array);
            return $this;
        }

        protected function queries()
        {
            return $this->_datas['queries'];
        }

        public function save()
        {
            $this->runEvent('saving');
            $pkValue = $this->hasPk();
            if ($pkValue !== false) {
                if (isset($this->_datas['configModel']['autoIncrement'])) {
                    return $this->update();
                } else {
                    $pks = $this->pk();
                    $where = array();
                    foreach ($pks as $pk) {
                        $where[] = "$this->_tableName.$pk = " . $this->quote($this->$pk);
                    }
                    $potentialRow = $this->select(implode(" AND ", $where));
                    if (null === $potentialRow) {
                        return $this->insert();
                    } else {
                        return $this->update();
                    }
                }
            } else {
                if (!empty($this->_datas['configModel']['uniqueConstraint'])) {
                    $fields = $this->_datas['configModel']['uniqueConstraint'];
                    $where = array();
                    foreach ($fields as $field) {
                        $where[] = "$this->_tableName.$field = " . $this->quote($this->$field);
                    }
                    $potentialRow = $this->select(implode(" AND ", $where));
                    if (null === $potentialRow) {
                        return $this->insert();
                    } else {
                        $pks = $this->pk();
                        foreach ($pks as $pk) {
                            $this->$pk = $potentialRow->$pk;
                        }
                        return $this->update();
                    }
                } else {
                    return $this->insert();
                }
            }
            return $this;
        }

        public function isNullable($field)
        {
            $value = $this->_datas['isNullable'][$field];
            if (false === $value) {
                if (null === $this->$field || !strlen($this->$field)) {
                    exit;
                    throw new FTV_Exception("The field '$field' must not be nulled.");
                }
            }
        }

        public function insert()
        {
            $pks = $this->pk();
            $this->runEvent('inserting');
            $fields = array();
            $values = array();
            foreach ($this->_datas['fieldsSave'] as $field) {
                if (
                    in_array($field, $pks)
                    && !empty($this->_datas['configModel']['autoIncrement'])
                    && !empty($this->_datas['configModel']['sequence'])
                ) {
                    $fields[] = $field;
                    $values[] = $this->_datas['configModel']['sequence'] . '.nextval';
                } else {
                    $isNullable = $this->isNullable($field);
                    $fields[] = $field;
                    $values[] = $this->quote($this->$field);
                }
            }
            $q = "INSERT INTO $this->_tableName (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            $this->query($q);
            $this->runEvent('inserted');
            $this->_datas['queries'][] = $q;
            //return $this->find($this->_db->lastInsertId());
            return $this;
        }

        public function _compare()
        {
            $id = $this->getId();
            $pks = $this->pk();
            if (count($pks) > 1) {
                $where = array();
                foreach ($pks as $pk) {
                    $where[] = "$this->_tableName.$pk = " . $this->quote($this->$pk);
                }
                $originalRow = $this->select(implode(" AND ", $where));
            } else {
                $pk = current($pks);
                $originalRow = $this->find($this->$pk);
            }
            $a1 = md5(serialize($this->_getValues()));
            $a2 = md5(serialize($originalRow->_getValues()));
            return $a1 == $a2 ? false : true;
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
            if (true === $this->_compare()) {
                $this->runEvent('updating');
                $pks = $this->pk();
                $q = "UPDATE $this->_tableName SET ";
                foreach ($this->_datas['fieldsSave'] as $field) {
                    if (!in_array($field, $pks)) {
                        $isNullable = $this->isNullable($field);
                        $q .= "$field = " . $this->quote($this->$field) . ", ";
                    }
                }
                $q = substr($q, 0, -2);
                $q .= " WHERE ";
                $where = array();
                foreach ($pks as $pk) {
                    $where[] = "$pk = " . $this->quote($this->$pk);
                }
                $q .= implode(" AND ", $where);
                $update = $this->query($q);
                $this->runEvent('updated');
                $this->_datas['queries'][] = $q;
            }
            return $this;
        }

        protected function map()
        {
            $q = "SELECT column_name, data_type, nullable
            FROM all_tab_columns
            WHERE table_name = '" . strtoupper($this->_tableName) . "'";
            $this->_datas['queries'][] = $q;
            $results = $this->query($q);
            if (!count($results)) {
                throw new FTV_Exception("This table $this->_table doesn't exist in $this->_entity entity.");
            } else {
                foreach ($results as $row) {
                    $field = strtolower($row['COLUMN_NAME']);
                    $this->_datas['fields'][] = $field;
                    $this->_datas['fieldsSave'][] = $field;
                    $this->_datas['type'][$field] = $row['DATA_TYPE'];
                    $this->_datas['isNullable'][$field] = ('y' == FTV_Inflector::lower($row['NULLABLE'])) ? true : false;
                    $this->$field = null;
                    /*if (strlen($row['Key']) && $row['Key'] != 'PRI') {
                        $this->_datas['keys'][] = $field;
                    }*/
                }
                $q = "SELECT cols.column_name
                FROM all_constraints cons, all_cons_columns cols
                WHERE cols.table_name = '" . strtoupper($this->_tableName) . "'
                AND cons.constraint_type = 'P'
                AND cons.constraint_name = cols.constraint_name
                AND cons.owner = cols.owner";
                $primaryKeys = $this->query($q);
                $this->_datas['pk'] = array();
                if (count($primaryKeys)) {
                    foreach ($primaryKeys as $pk) {
                        $this->_datas['pk'][] = strtolower($pk['COLUMN_NAME']);
                    }
                }
            }
            $this->_datas['fields'] = array_unique($this->_datas['fields']);
            if(array_key_exists('keys', $this->_datas)) {
                $this->_datas['keys'] = array_unique($this->_datas['keys']);
            }
            return $this;
        }

        public function model($model, $id = null)
        {
            if (array_key_exists('models', $this->_datas)) {
                if (array_key_exists($model, $this->_datas['models'])) {
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

        /*public function getId()
        {
            $pk = current($this->_datas['pk']);
            return $this->$pk;
        }

        public function setId($id)
        {
            $pk = current($this->_datas['pk']);
            $this->$pk = $id;
            return $this;
        }*/

        public function pk()
        {
            return $this->_datas['pk'];
        }

        public function fields()
        {
            return $this->_datas['fields'];
        }

        public function keys()
        {
            return $this->_datas['keys'];
        }

        public function __call($method, $args)
        {
            if (substr($method, 0, 3) == 'get') {
                $vars = array_values($this->fields());
                $uncamelizeMethod = FTV_Inflector::uncamelize(lcfirst(substr($method, 3)));
                $var = FTV_Inflector::lower($uncamelizeMethod);
                if (in_array($var, $vars)) {
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
                $uncamelizeMethod = FTV_Inflector::uncamelize(lcfirst(substr($method, 3)));
                $var = FTV_Inflector::lower($uncamelizeMethod);
                if (in_array($var, $vars)) {
                    $this->$var = $value;
                    return $this;
                } else {
                    throw new FTV_Exception("Unknown field $var in " . get_class($this) . " class.");
                }
            } elseif (substr($method, 0, 6) == 'findBy') {
                $vars = array_values($this->fields());
                $value = $args[0];
                $uncamelizeMethod = FTV_Inflector::uncamelize(lcfirst(substr($method, 6)));
                $var = FTV_Inflector::lower($uncamelizeMethod);
                if (in_array($var, $vars)) {
                    return $this->findBy($var, $value);
                } else {
                    throw new FTV_Exception("Unknown field $var in " . get_class($this) . " class.");
                }
            }  elseif (substr($method, 0, 9) == 'findOneBy') {
                $vars = array_values($this->fields());
                $value = $args[0];
                $uncamelizeMethod = FTV_Inflector::uncamelize(lcfirst(substr($method, 9)));
                $var = FTV_Inflector::lower($uncamelizeMethod);
                if (in_array($var, $vars)) {
                    return $this->findBy($var, $value, true);
                } else {
                    throw new FTV_Exception("Unknown field $var in " . get_class($this) . " class.");
                }
            } else {
                $vars = array_values($this->fields());
                $uncamelizeMethod = FTV_Inflector::uncamelize(lcfirst($method));
                $var = FTV_Inflector::lower($uncamelizeMethod);
                if (in_array($var, $vars)) {
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

        public function __invoke()
        {
            $args = func_get_args();
            $nbArgs = count($args);
            if ($nbArgs == 0 || $nbArgs > 2) {
                return $this;
            } elseif ($nbArgs == 1) {
                return $this->find($args[0]);
            } elseif ($nbArgs == 2) {
                list($field, $value) = $args;
                $this->$field = $value;
                return $this;
            }
        }

        protected function runEvent($event)
        {
            $events = array("model.{$event}.done", "model.{$event}: " . get_class($this) . '.done');
            FTV_Events::run($events, array($this));
        }

        public function view()
        {
            $html = '<table cellpadding="5" cellspacing="0" border="0">';
            foreach ($this->_datas['fieldsSave'] as $field) {
                $html .= '<tr><td style="border: solid 1px;"> '. $field . '</td><td style="border: solid 1px;">' . utf8_encode($this->$field) . '</td></tr>';
            }
            $html .= '</table>';
            return $html;
        }
    }
