<?php

class DB {

    private $_db;
    private $_sql_list = array();

    public function __construct($params) {
        $options = array();

        $options[PDO::ATTR_PERSISTENT] = boolval($params['persistent']) ?? false;
        $dsn = sprintf('mysql:dbname=%s;host=%s;charset=%s', $params['dbname'], $params['host'], $params['charset']);
        try {
            $this->_db = new PDO($dsn, $params['username'], $params['password'], $options);
            $this->_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $ex) {
            $this->_errorlog($ex->getMessage() . "\n" . $ex->getTraceAsString());
            throw $ex;
        }
    }

    /**
     * @return PDO
     */
    public function getResource() {
        return $this->_db;
    }

    public function fetch($sql) {
        $this->addQueryLog($sql);
        $result = false;
        try {
            $stmt = $this->_db->query($sql);
            if ($stmt) {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $ex) {
            $this->_errorlog($ex->getMessage() . "\n" . $ex->getTraceAsString());
            throw $ex;
        }
        return $result;
    }

    public function fetchAll($sql) {
        $this->addQueryLog($sql);
        $result = false;
        try {
            $stmt = $this->_db->query($sql);
            if ($stmt) {
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $ex) {
            $this->_errorlog($ex->getMessage() . "\n" . $ex->getTraceAsString());
            throw $ex;
        }
        return $result;
    }

    public function exec($sql) {
        logMessage($sql);
        $this->addQueryLog($sql);
        $effect_rows = 0;
        try {
            $effect_rows = $this->_db->exec($sql);
        } catch (Exception $ex) {
            $this->_errorlog($ex->getMessage() . "\n" . $ex->getTraceAsString());
            throw $ex;
        }
        return $effect_rows;
    }

    public function query($sql, $params = array()) {
        $this->addQueryLog($sql);
        $result = false;
        try {
            $stmt = $this->_db->prepare($sql);
            if ($stmt) {
                $stmt->execute($params);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $ex) {
            $this->_errorlog($ex->getMessage() . "\n" . $ex->getTraceAsString());
            throw $ex;
        }
        return $result;
    }

    public function query_fetch($sql, $params = array()) {
        $this->addQueryLog($sql);
        $result = false;
        try {
            $stmt = $this->_db->prepare($sql);
            if ($stmt) {
                $stmt->execute($params);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $ex) {
            $this->_errorlog($ex->getMessage() . "\n" . $ex->getTraceAsString());
            throw $ex;
        }
        return $result;
    }

    public function insert($table, $fields) {
        $fields_name = array();
        array_walk($fields, function(&$v, $k) use(&$fields_name) {
            $k = '`' . $k . '`';
            $fields_name[] = $k;
            $v = $this->quote($v);
        });
        $fields_name = implode(',', $fields_name);
        $fields = implode(',', $fields);
        $sql = sprintf('insert into `%s`(%s) values(%s)', $table, $fields_name, $fields);
        return $this->exec($sql);
    }

    public function insertUpdate($table, $fields, $update_fields) {
        //insert key
        array_walk($fields, function(&$v, $k) use(&$fields_name) {
            $k = '`' . $k . '`';
            $fields_name[] = $k;
            $v = $this->quote($v);
        });
        $fields_name = implode(',', $fields_name);
        $insert_fields_str = implode(',', $fields);
        //update key
        array_walk($update_fields, function(&$v, $k) {
            $v = '`' . $k . '`' . '=' . $this->quote($v);
        });
        $update_fields_str = implode(',', $update_fields);
        //sql
        $sql = sprintf('insert into `%s`(%s) values(%s) ON DUPLICATE KEY UPDATE %s', $table, $fields_name, $insert_fields_str, $update_fields_str);
        return $this->exec($sql);
    }

    public function update($table, $fields, $where = '') {
        array_walk($fields, function(&$v, $k) {
            $v = '`' . $k . '`' . '=' . (is_array($v) ? $v[0] : $this->quote($v));
        });
        $fields = implode(',', $fields);
        $sql = sprintf('update `%s` set %s ' . ($where == '' ? '' : 'where %s'), $table, $fields, $where);
        return $this->exec($sql);
    }

    public function delete($table, $where) {
        $sql = sprintf('delete from `%s` where %s', $table, $where);
        return $this->exec($sql);
    }

    public function rowCount($table, $where) {
        $sql = sprintf('select count(1) as `count` from %s ' . ($where == '' ? '' : 'where %s'), $table, $where);
        return $this->fetch($sql);
    }

    public function quote($str) {
        if (is_null($str)) {
            return 'NULL';
        }
        return $this->_db->quote($str);
    }

    protected function _errorlog($errmsg) {
        logMessage(var_export($this->getQueryLog(), true), LOG_ERR);
        logMessage($errmsg, LOG_ERR);
        trigger_error($errmsg, E_USER_ERROR);
    }

    public function close() {
        $this->_db = null;
    }

    private function addQueryLog($sql) {
        $this->_sql_list[] = $sql;
    }

    public function getQueryLog() {
        return $this->_sql_list;
    }

    public function trancation($func) {
        $ret = false;
        if (is_callable($func)) {
            $rb = false;
            $this->beginTransaction();
            try {
                if(is_callable($func)){
                    $ret = $func($this, $rb);
                    $rb ? $this->rollback() : $this->commit();
                }
            } catch (Exception $ex) {
                $this->rollBack();
                $this->_errorlog($ex->getMessage() . "\n" . $ex->getTraceAsString());
            }
        }
        return $ret;
    }

    public function beginTransaction() {
        $this->_db->setAttribute(PDO::ATTR_AUTOCOMMIT, FALSE);
        $this->_db->beginTransaction();
    }

    public function commit() {
        $this->_db->commit();
        $this->_db->setAttribute(PDO::ATTR_AUTOCOMMIT, TRUE);
    }

    public function rollback() {
        $this->_db->rollBack();
        $this->_db->setAttribute(PDO::ATTR_AUTOCOMMIT, TRUE);
    }

    public function lastInsertId($name = NULL) {
        return $this->_db->lastInsertId($name);
    }

}
