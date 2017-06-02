<?php

class PRedis extends Redis{
    
    //实例1
    static private $_instance = null;
    //实例2
    static private $_instance2 = null;
    
    /**
     * 
     * @return PRedis
     */
    public static function instance(){
        if (self::$_instance == null || !self::$_instance->isConnected()) {
            try {
                self::$_instance = new self();
                $bPConnect = (bool) config_item('redis.pconnect', false);
                $host = config_item('redis.host');
                $port = config_item('redis.port');
                $database = config_item('redis.database');
                $password = config_item('redis.password');
                if ($bPConnect) {
                    self::$_instance->pconnect($host, $port);
                } else {
                    self::$_instance->connect($host, $port);
                }
                if (!empty($password)) {
                    self::$_instance->auth($password);
                }
                if (!empty($database)) {
                    self::$_instance->select($database);
                }
            } catch (RedisException $ex) {
                $errMsg = $ex->getMessage() . "\n" . $ex->getTraceAsString();
                logMessage($errMsg, LOG_ERR);
                trigger_error($errMsg, E_USER_ERROR);
                return false;
            }
        }
        return self::$_instance;
    }

    /**
     *
     * @return PRedis
     */
    public static function instance2(){
        if (self::$_instance2 == null || !self::$_instance2->isConnected()) {
            try {
                self::$_instance2 = new self();
                $bPConnect = (bool) config_item('redis2.pconnect', false);
                $host = config_item('redis2.host');
                $port = config_item('redis2.port');
                $database = config_item('redis2.database');
                $password = config_item('redis2.password');
                if ($bPConnect) {
                    self::$_instance2->pconnect($host, $port);
                } else {
                    self::$_instance2->connect($host, $port);
                }
                if (!empty($password)) {
                    self::$_instance2->auth($password);
                }
                if (!empty($database)) {
                    self::$_instance2->select($database);
                }
            } catch (RedisException $ex) {
                $errMsg = $ex->getMessage() . "\n" . $ex->getTraceAsString();
                logMessage($errMsg, LOG_ERR);
                trigger_error($errMsg, E_USER_ERROR);
                return false;
            }
        }
        return self::$_instance2;
    }

    
    public function get($key, $def = false){
        $data = parent::get($key);
        return false === $data ? $def : $data;
    }
    
    public function mGet($keys, $def = false){
        $data = parent::mGet($keys);
        array_walk($data, function(&$item) use($def){
            if(false === $item) {
                $item = $def;
            }
        });
        return $data;
    }
    
}