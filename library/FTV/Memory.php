<?php
    /**
     * Memory class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Memory
    {
        public $_entity;
        public $_table;
        protected $_new = false;
        protected $_isWriting = false;
        protected $_file;
        public $_memoryId;
        protected $_offest;
        protected $_limit;
        protected $_operator = 'and';
        protected $_results = array();
        protected $_order = array();
        public $_record = array();

        public function __construct($entity, $table)
        {
            if (empty($entity)) {
                throw new FTV_Exception('You must provide an entity.');
            }

            if (empty($table)) {
                throw new FTV_Exception('You must provide a table.');
            }
            $this->_file    = config('memory.path') . DS . $entity . '_' . $table . '.memory';

            if (!file_exists(config('memory.path') . DS . $entity . '_' . $table . '.memory')) {
                touch(config('memory.path') . DS . $entity . '_' . $table . '.memory', 0777);
                $this->_new = true;
            } else {
                $this->_new = (strlen(file_get_contents($this->_file)) > 0) ? false : true;
            }
            $this->_table   = $table;
            $this->_entity  = $entity;
        }

        public function _getDatas()
        {
            if (true === $this->_new) {
                return array();
            }
            return json_decode(file_get_contents($this->_file), 1);
        }

        public function drop()
        {
            if (file_exists(config('memory.path') . DS . $entity . '_' . $table . '.memory')) {
                unlink(config('memory.path') . DS . $entity . '_' . $table . '.memory');
            } else {
                throw new FTV_Exception('You try to drop an unknown memory.');
            }
        }

        public function first()
        {
            $res = $this->select(array(), true);
            if (count($res)) {
                return current($res);
            }
            return $res;
        }

        public function fill(array $data, $memoryId = null)
        {
            if (!count($data)) {
                throw new FTV_Exception('You must provide a valid array to fill a row in memory.');
            }
            if (!is_null($memoryId)) {
                $this->_memoryId = $memoryId;
            } else {
                $this->_memoryId = $this->getId();
            }
            foreach ($data as $k => $v) {
                $this->_record[$this->_memoryId][$k] = $v;
            }
            return $this;
        }

        public function save()
        {
            if (false === $this->_isWriting) {
                $this->_isWriting = true;
                $datas = $this->_getDatas();
                if (!isset($this->_memoryId)) {
                    return $this->insert();
                } else {            
                    $datas[$this->_memoryId] = current($this->_record);
                    return $this->_write($datas);
                }
            } else {
                return $this->save();
            }
        }

        public function insert()
        {
            $datas = $this->_getDatas();
            $this->_memoryId = sha1(u::UUID() . $this->_table . $this->_entity . session_id() . time());
            $datas[$this->_memoryId] = $this->_record;
            return $this->_write($datas);
        }

        public function update($updates, $condition)
        {
            $collection = $this->where($condition)->results();
            if (is_array($updates)) {
                foreach ($collection as $row) {
                    foreach ($updates as $update) {
                        $obj = $this->find($row->_memoryId);
                        list($field, $value) = $update;
                        $setter = 'set' . i::camelize($field);
                        $obj->$setter($value);
                        $obj->save();
                    }
                }
            }
            return new self($this->_entity, $this->_table);
        }

        public function find($id)
        {
            $datas = $this->_getDatas();
            if (array_key_exists($id, $datas)) {
                $obj = new self($this->_entity, $this->_table);
                return $obj->fill($datas[$id], $id);
            }
            return null;
        }

        public function findBy($field, $value, $one = false)
        {
            $res = $this->where("$field = $value");
            if (null === $res) {
                return $res;
            }
            $res = $res->results();
            if (true === $one) {
                if (count($res)) {
                    return current($res);
                }
            }
            $res = (count($res) < 1) ? null : $res;
            return $res;
        }

        public function select(array $where = array(), $one = false)
        {
            $datas = $this->_getDatas();
            if (!count($datas)) {
                throw new FTV_Exception("This memory is empty.");
            }
            $class = 'FTVMemory_' . ucfirst(i::lower($this->_entity)) . '_' . ucfirst(i::lower($this->_table));
            $collection = new $class;
            if (!count($where)) {
                foreach ($datas as $id => $row) {
                    $obj = new self($this->_entity, $this->_table);
                    $obj->fill($row, $id);
                    $collection[] = $obj;
                    if (true === $one) {
                        break;
                    }
                }
            } else {
                foreach ($datas as $id => $row) {
                    $continue = true;
                    foreach ($where as $fieldWhere => $valueWhere) {
                        if (!array_key_exists($fieldWhere, $row)) {
                            throw new FTV_Exception("The field $fieldWhere does not exist in this model.");
                        }
                        $continue = ($row[$fieldWhere] == $valueWhere) ? true : false;
                    }
                    if (true === $continue) {
                        $obj = new self($this->_entity, $this->_table);
                        $obj->fill($row, $id);
                        $collection[] = $obj;
                        if (true === $one) {
                            break;
                        }
                    }
                }
            }
            return $collection;
        }

        public function whereAnd($condition)
        {
            $this->_operator = 'and';
            return $this->where($condition);
        }

        public function whereOr($condition)
        {
            $this->_operator = 'or';
            return $this->where($condition);
        }

        public function whereXor($condition)
        {
            $this->_operator = 'xor';
            return $this->where($condition);
        }

        public function limit($offset, $limit)
        {
            $this->_offset  = $offset;
            $this->_limit   = $limit;
            return $this;
        }

        public function order($field, $direction = 'ASC')
        {
            $this->_order = array_push($this->_order, array($field => $direction));
            return $this;
        }

        public function results()
        {
            $datas = $this->_getDatas();
            $class = 'FTVMemory_' . ucfirst(i::lower($this->_entity)) . '_' . ucfirst(i::lower($this->_table));
            $collection = new $class;
            if (count($this->_results)) {
                foreach ($this->_results as $id) {
                    if (array_key_exists($id, $datas)) {
                        $obj = new self($this->_entity, $this->_table);
                        $collection[] = $obj->fill($datas[$id], $id);
                    }
                }
            } else {
                return null;
            }

            // if limit
            if (isset($this->_limit) && isset($this->_offset)) {
                $max = count($collection);
                $number = $this->_limit - $this->_offset;
                if ($number > $max) {
                    $this->_offset = $max - $this->_limit;
                    if (0 > $this->_offset) {
                        $this->_offset = 0;
                    }
                    $this->_limit = $max;
                }
                $collection = array_slice($collection, $this->_offset, $this->_limit); 
            }
            
            // if order
            if (count($this->_order)) {
                $sort = array();
                foreach($collection as $row) {
                    $record = $row->_record;
                    foreach ($record as $id => $datasRow) {
                        break;
                    }
                    $sort['_id'][] = $id;
                    foreach ($datasRow as $key => $value) {
                        $sort[$key][] = $value;
                    }
                }
                $asort = array();
                foreach ($sort as $key => $rows) {
                    for ($i = 0 ; $i < count($rows) ; $i++) {
                        if (!is_array($$key)) {
                            $$key = array();
                        }
                        $asort[$i][$key] = $rows[$i];
                        array_push($$key, $rows[$i]);
                    }
                }
                foreach ($this->_order as $order) {
                    foreach ($order as $field => $direction) {
                        break;
                    }

                    if ('ASC' == i::upper($direction)) {
                        array_multisort($$field, SORT_ASC, $asort);
                    } else {
                        array_multisort($$field, SORT_DESC, $asort);
                    }
                }
                $collection = new $class;
                foreach ($asort as $key => $row) {
                    $tmpId = $row['_id'];
                    if (array_key_exists($tmpId, $datas)) {
                        $obj = new self($this->_entity, $this->_table);
                        $collection[] = $obj->fill($datas[$tmpId], $tmpId);
                    }
                }
            }

            return $collection;
        }

        public function where($condition)
        {
            $datas = $this->_getDatas();
            if(!count($datas)) {
                return null;
            }
            $condition = repl('NOT LIKE', 'NOTLIKE', $condition);
            $condition = repl('NOT IN', 'NOTIN', $condition);
            list($field, $op, $value) = explode(' ', $condition, 3);
            $results = array();
            foreach ($datas as $id => $row) {
                $continue = true;
                if (array_key_exists($field, $row)) {
                    $continue = $this->analyze($row[$field], $op, $value);
                } else {
                    $continue = false;
                }
                if (true === $continue) {
                    $results[] = $id;
                }
            }
            if (count($this->_results)) {
                if (count($results)) {
                    if ($this->_operator == 'and') {
                        $this->_results = array_intersect($this->_results, $results);
                    } else if ($this->_operator == 'or') {
                        $this->_results = array_merge($this->_results, $results);
                    } else if ($this->_operator == 'xor') {
                        $this->_results = array_merge(array_diff($this->_results, $results), array_diff($results, $this->_results));
                    }
                } else {
                    if ($this->_operator != 'or') {
                        $this->_results = $results;
                    }
                }
            } else {
                if (count($results)) {
                    $this->_results = $results;
                }
            }
            return $this;
        }

        protected function analyze($comp, $op, $value)
        {
            if (isset($comp)) {
                $comp = i::lower($comp);
                $value = i::lower($value);
                switch ($op) {
                    case '=':
                        if ($comp == $value) {
                            return true;
                        }
                        break;
                    case '>=':
                        if ($comp >= $value) {
                            return true;
                        }
                        break;
                    case '>':
                        if ($comp > $value) {
                            return true;
                        }
                        break;
                    case '<':
                        if ($comp < $value) {
                            return true;
                        }
                        break;
                    case '<=':
                        if ($comp <= $value) {
                            return true;
                        }
                        break;
                    case '<>':
                    case '!=':
                        if ($comp <> $value) {
                            return true;
                        }
                        break;
                    case 'LIKE':
                        $value = repl('"', '', $value);
                        $value = repl('%', '', $value);
                        if (strstr($comp, $value)) {
                            return true;
                        }
                        break;
                    case 'NOTLIKE':
                        $value = repl('"', '', $value);
                        $value = repl('%', '', $value);
                        if (!strstr($comp, $value)) {
                            return true;
                        }
                        break;
                    case 'IN':
                        $value = repl('(', '', $value);
                        $value = repl(')', '', $value);
                        $tabValues = explode(',', $value);
                        if (in_array($comp, $tabValues)) {
                            return true;
                        }
                        break;
                    case 'NOTIN':
                        $value = repl('(', '', $value);
                        $value = repl(')', '', $value);
                        $tabValues = explode(',', $value);
                        if (!in_array($comp, $tabValues)) {
                            return true;
                        }
                        break;
                }
            }
            return false;
        }

        protected function _write($datas)
        {
            unlink($this->_file);
            file_put_contents($this->_file, json_encode($datas));
            chmod($this->_file, 0777);
            $this->_isWriting = false;
            return $this;
        }

        public function getId()
        {
            if (isset($this->_memoryId)) {
                return $this->_memoryId;
            } else {
                $this->_memoryId = sha1(u::UUID() . $this->_table . $this->_entity . session_id() . time());
                return $this->_memoryId;
            }
            return null;
        }

        public function delete()
        {
            $datas = $this->_getDatas();
            if (null !== $this->getId()) {
                if (array_key_exists($this->getId(), $datas)) {
                    unset($datas[$this->getId()]);
                    return $this->_write($datas);
                }
            } else {
                throw new FTV_Exception('You cannot delete this record beacause it does not exist yet.');
            }
        }

        public function _getResults()
        {
            return $this->results();
        }

        public function __call($method, $args)
        {
            if (substr($method, 0, 3) == 'set') {
                $value = $args[0];
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 3)));
                $var = i::lower($uncamelizeMethod);
                $this->_record[$this->getId()][$var] = $value;
                return $this;
            } elseif (substr($method, 0, 3) == 'get') {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 3)));
                $var = i::lower($uncamelizeMethod);
                if (array_key_exists($var, $this->_record[$this->getId()])) {
                    return $this->_record[$this->getId()][$var];
                } else {
                    $newId = $var . '_id';
                    if (array_key_exists($newId, $this->_record[$this->getId()])) {
                        $obj = new self($this->_entity, $var);
                        return $obj->find($this->_record[$this->getId()][$newId]);
                    } else {
                        $newId = substr($var, 0, -1) . '_id';
                        if (file_exists(config('memory.path') . DS . $this->_entity . '_' . substr($var, 0, -1) . '.memory')) {
                            $obj = new self($this->_entity, substr($var, 0, -1));
                            return $obj->where($this->_table . '_id = ' . $this->getId())->results();
                        } else {
                            throw new FTV_Exception("Unknown field $var in " . get_class($this) . " class.");
                        }
                    }
                }
            } elseif (substr($method, 0, 6) == 'findBy') {
                $value = $args[0];
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 6)));
                $var = i::lower($uncamelizeMethod);
                return $this->findBy($var, $value);
            } elseif (substr($method, 0, 9) == 'findOneBy') {
                $value = $args[0];
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($method, 9)));
                $var = i::lower($uncamelizeMethod);
                return $this->findBy($var, $value, true);
            }
        }
    }
