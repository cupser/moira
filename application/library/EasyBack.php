<?php

trait EasyBack {

    /**
     * @return \Input
     */
    protected function input(): Input {
        return Yaf\Registry::get('__INPUT__');
    }

    /**
     * 
     * @return \DB
     */
    protected function DB(): DB {
        return Yaf\Registry::get('__DB__')();
    }


    /**
     *
     * @return \Dao\Connection
     */
    protected function DB_GameLog(): DB{
        return Yaf\Registry::get('__DB_GAMELOG__')();
    }

    /**
     * 
     * @return Yaf\Config_Abstract
     */
    protected function config() {
        return Yaf\Application::app()->getConfig();
    }
    
    /**
     * 接口数据数据返回
     * @param int $result
     * @param string $msg
     * @param array $data
     */
    protected function jsonReturn($result = RCODE_SUCC, string $msg = '', array $data = null) {
        if ($result == RCODE_SUCC) {
            $timestamp = time();

            $ret = array(
                'result' => $result,
                'msg' => $msg,
                'data' => $data,
                'timestamp' => $timestamp
            );
        } else {
            //返回数据
            $ret = array(
                'result' => $result,
                'msg' => $msg,
                'data' => null
            );
        }
        //返回数据
        $json = json_encode($ret, JSON_UNESCAPED_UNICODE);
        echo $json . md5($json);
    }

    /**
     * 成功返回数据
     * @param array $data
     * @return boolean
     */
    protected function succ(array $data = array()) {
        $this->jsonReturn(RCODE_SUCC, '', $data);
    }

    /**
     * 失败返回
     * @param int $code
     * @param string $msg
     * @return boolean
     */
    protected function failed(string $msg = '', int $code = RCODE_FAIL) {
        //返回数据
        $this->jsonReturn($code, $msg);
    }

    /**
     * 参数校验
     * @param type $rules
     */
    protected function validation(array $args, array $rules): bool {
        $valid = new Validation($args);
        $valid->rule($rules);
        if (!$valid->run()) {
            $this->failed("参数错误", RCODE_ARG_ILLEGAL);
            logMessage($valid->getErrorMsg());
            logMessage(json_encode($args));
            return false;
        }
        return true;
    }

    /**
     * 检测请求的签名是否正确
     * @return bool
     */
    protected function checkSign(): bool {
        //调试模式下不需要验证签名，方便开发
        if (DEBUG) {
            return true;
        }
        $input = $this->input();
        $sign = $input->get('sign');
        $timestamp = $input->get('timestamp');
        //无签名数据
        if (empty($sign) || empty($timestamp)) {
            $this->failed("非法请求", RCODE_REQ_ILLEGAL);
            logMessage($_SERVER['REQUEST_URI'] . ' missing params sign or timestamp');
            return false;
        }
        //签名错误
        if ($sign !== $this->buildSign($input->json_stream(), $timestamp)) {
            $this->failed("签名错误", RCODE_FAIL_SIGN);
            logMessage($_SERVER['REQUEST_URI'] . ' sign verify failed');
            return false;
        }
        //检测过期
        $cfg = Yaf\Application::app()->getConfig();
        $expire = intval($cfg->get('api.expire'));
        if ($expire > 0 && (time() - $timestamp >= $expire)) {
            $this->failed("非法请求", RCODE_REQ_ILLEGAL);
            logMessage($_SERVER['REQUEST_URI'] . ' request expired');
            return false;
        }

        return true;
    }

    /**
     * 生成签名字符串
     * @param array $data
     * @param type $timestamp
     * @return string
     */
    protected function buildSign(array $data, int $timestamp): string {
        try {
            $key = Yaf\Application::app()->getConfig()->get('api.secret') ?? '';
            array_ksort($data);
            $json = json_encode($data);
            $sign = md5($json . $key . $timestamp);
        } catch (Exception $ex) {
            logMessage(sprintf('%s %s %s', $_SERVER['REQUEST_URI'], var_export($data, true), 'buildSign failed'), LOG_ERR);
        }
        return $sign;
    }

    protected function _getLogTable() {
        $table_name = 'ms_user_logs' . date('Ym');
        //初始化数据表，如果不存在则新建
        $sql = 'create table if not exists ' . $table_name . ' like ms_user_logs';
        $this->DB()->exec($sql);
        return $table_name;
    }

    final protected function _addUserLog(int $user_id, int $action, string $detail = '') {
        $table_name = $this->_getLogTable();

        $fields = array(
            'user_id' => $user_id,
            'created_date' => time(),
            'ip' => $this->input()->ip_address(),
            'action' => $action
        );
        if (!empty($detail)) {
            $fields['detail'] = $detail;
        }
        $this->DB()->insert($table_name, $fields);
    }

    /**
     * 记录登录日志
     * @param int $user_id
     */
    final public function addLoginLog(int $user_id) {
        $this->_addUserLog($user_id, LOG_ACT_LOGIN);
    }

    /**
     * 记录物品获取日志
     * @param int $user_id
     */
    final public function addItemLog(int $user_id, array $detail) {
        $this->_addUserLog($user_id, LOG_ACT_GETITEM, json_encode($detail));
    }
    
    /**
     * 通用insert
     */
    protected function baseInsert(string $table_name, array $data) : bool {
        $db = $this->DB();
        if (!$db->insert("$table_name", $data)) {
            logMessage('ms_user 插入失败;' . var_export($data, true));
            return false;
        }
        return true;
    }
    
    /**
     * 通用update
     */
    protected function baseUpdate(string $table_name, array $data, string $where) : bool {
        $db = $this->DB();
        try{
            $db->update("$table_name", $data, $where);
        } catch (Exception $ex){
            logMessage($ex->getTraceAsString());
            return false;
        }
        return true;
    }
    
    /**
     * 通用find
     */
    protected function baseFind(string $table_name, string $where, array $params, array $fields = ['*']) {
        $fields = empty($fields) ? '*' : implode(',', $fields);
        $sql = <<<SQL
                select $fields from $table_name where $where limit 1
SQL;
        return $this->DB()->query_fetch($sql, $params);
    }
    
    /**
     * 通用删除redis
     */
    protected function baseDelRedis(string $db, string $key) : bool{
        $redis = PRedis::instance();
        $redis->select($db);
        $redis->del($key);
        if ($redis->exists($key)){
            return FALSE;
        }
        return TRUE;
    }
    
    /**
     * 通用url
     */
    protected function baseUrl(string $uri = null) {
        $uri = $uri ?? $_SERVER['REQUEST_URI'];
        $url = 'http://' . $_SERVER['HTTP_HOST']. $uri;
        if (isset($_SERVER['HTTPS'])){
            $url = $_SERVER['HTTPS'] == 'on' ? 'https://'.$_SERVER['HTTP_HOST'] . $uri : $url;
        }
        return $url;
    }
    
    /**
     * 用户通用修改金币
     * @user_id  用户id
     * @count 数量
     * @detail ['item_id' => $this->id, 'item_count' => $count, 'why' => $why]
     */
    protected function onChangeUserGold(int $user_id, int $count) : bool {
        try{
            $userData = new Data\UserModel($user_id);
            if($userData->addGold($count) !== false){
                return true;
            }
        }catch(Exception $e){
            logMessage($e->getMessage(), LOG_ERR);
        }
        return false;
    }
    
    /**
     * 用户通用修改钻石
     * @user_id  用户id
     * @count 数量
     * @detail ['item_id' => $this->id, 'item_count' => $count, 'why' => $why]
     */
    protected function onChangeUserCredit(int $user_id, int $count) : bool {
        try{
            $userData = new Data\UserModel($user_id);
            if($userData->addCredit($count) !== false){
                return true;
            }
        }catch(Exception $e){
            logMessage($e->getMessage(), LOG_ERR);
        }
        return false;
    }
    
    /**
     * 用户通用修改绿宝石
     * @user_id  用户id
     * @count 数量
     * @detail ['item_id' => $this->id, 'item_count' => $count, 'why' => $why]
     */
    protected function onChangeUserEmerald(int $user_id, int $count) : bool {
        try{
            $userData = new Data\UserModel($user_id);
            if($userData->addEmerald($count) !== false){
                return true;
            }
        }catch(Exception $e){
            logMessage($e->getMessage(), LOG_ERR);
        }
        return false;
    }
    
    /**
     * 用户通用修改冰封道具
     * @user_id  用户id
     * @count 数量
     * @detail ['item_id' => $this->id, 'item_count' => $count, 'why' => $why]
     */
    protected function onChangeUserFrozen(int $user_id, int $count) : bool {
        try{
            $userData = new Data\UserModel($user_id);
            if($userData->addFrozen($count) !== false){
                return true;
            }
        }catch(Exception $e){
            logMessage($e->getMessage(), LOG_ERR);
        }
        return false;
    }
    
    /**
     * 用户通用修改鹰眼道具
     * @user_id  用户id
     * @count 数量
     * @detail ['item_id' => $this->id, 'item_count' => $count, 'why' => $why]
     */
    protected function onChangeUserEagleeye(int $user_id, int $count) : bool {
        try{
            $userData = new Data\UserModel($user_id);
            if($userData->addEagleeye($count) !== false){
                return true;
            }
        }catch(Exception $e){
            logMessage($e->getMessage(), LOG_ERR);
        }
        return false;
    }
    
    /**
     * 用户通用修改多种货币
     * @user_id  用户id
     * @params 修改的参数['gold','credit','emerald']
     * @values 修改的数据[100,200,300]
     */
    protected function onChangeUserData(int $user_id, array $params, array $values) : bool{
        try{
            $userData = new Data\UserModel($user_id);
            if(count($params) > 0 && $userData->addData($params, $values)){
                return true;
            }
        }catch(Exception $e){
            logMessage($e->getMessage(), LOG_ERR);
        }
        return false;
    }
    
    /**
     * post请求
     */
    protected function http_post_request($url, $param = NULL, $header = NULL) {
            if(is_array($param)){
                    $param = http_build_query($param);
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            if(is_array($header)) curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $param);

            $res = curl_exec( $ch );
            curl_close( $ch );
            return $res;
    }
    
    /**
     * get请求
     */
    function http_get_request($url, $param = NULL, $header = NULL) {
        if(!empty($param)) {
                if(strpos($url, "?") ===false) $url .= "?".http_build_query($param);
                else $url .= "&".http_build_query($param);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        if(is_array($header)) curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $res = curl_exec( $ch );
        curl_close( $ch );
        return $res;
    }
}
