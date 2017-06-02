<?php

class RedisSessionHandler implements SessionHandlerInterface {

    const DB = R_SESSION_DB;
    
    /**
     * @var Redis
     */
    private $lifetime;  
    private $prefix = 'token@';

    public function close(){
        PRedis::instance()->select(self::DB);
        $this->gc($this->lifetime);
        PRedis::instance()->close();
        return true;
    }

    public function destroy($session_id) {
        $handle = PRedis::instance();
        $handle->select(self::DB);
        return $handle->del($this->prefix . $session_id);
    }

    public function gc($maxlifetime) {
        return true;
    }

    public function open($save_path, $name) {
        $this->lifetime = ini_get('session.gc_maxlifetime');  
        return true;
    }

    public function read($session_id){
        $handle = PRedis::instance();
        $session_id = $this->prefix . $session_id;
        $handle->select(self::DB);
        $data = $handle->get($session_id);
        return $data ? $data : '';
    }

    public function write($session_id, $session_data){
        $handle = PRedis::instance();
        $session_id = $this->prefix . $session_id;
        $handle->select(self::DB);
        $handle->set($session_id, $session_data);
        $handle->expire($session_id, $this->lifetime);
        return true;
    }

}
