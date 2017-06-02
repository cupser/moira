<?php

class ModelModel extends BaseModel {

    const DB = R_GAME_DB;
    
    /**
     * 获取信息
     */
    public function info(){
        $info = $this->_getInfo();
        if (!$info){
            if ($list){
                $info = json_encode($list);
                $redis = PRedis::instance();
                $redis->select(self::DB);
                $key = PK_MODEL;
                $redis->set($key, json_encode($list));
                
                $info = $list;
            }
        }
        
        return $info ? $info : [];
    }
    
    /**
     * redis获取列表
     */
    private function _getInfo(){
        $redis = PRedis::instance();
        $redis->select(self::DB);
        $key = PK_MODEL;
        //判断是否存在
        if (!$redis->exists($key)){
            return [];
        }
        $info = $redis->get($key);
        return $info ? json_decode($info, TRUE) : [];
    }
    
    /**
     * 获取数据
     */
    public function getList(){
        $sql = <<<SQL
            select id,m_type,name,status from ms_model where status != -1
SQL;
        $data = $this->DB()->query($sql);
        return $data;
    }

}
