<?php

/**
 * 配置信息快捷获取
 * @param string $key
 * @param string $default
 * @return string
 */
function config_item(string $key, $default = null) {
    static $cfg = NULL;
    if (is_null($cfg)) {
        $cfg = Yaf\Application::app()->getConfig();
    }
    return $cfg->get($key) ?? $default;
}

/**
 * 系统日志记录
 * @param string $msg 日志内容
 * @param int $level 记录等级
 */
function logMessage($msg, $level = LOG_DEBUG) {
    $priority = config_item('log.priority');
    $log_file = config_item('log.logfile');
    if ($level > $priority) {
        return;
    }
    $logmsg = sprintf("[%s] %s\r\n", date('Y/m/d H:i:s'), $msg);
    error_log($logmsg, 3, $log_file);
}

/**
 * 短信发送函数
 * @param array $params 一个包含短信接口相关参数的数组
 * @return mixed 因网络问题发送失败的返回false，其他情况返回一个数组对象
 */
function sms($params) {
    $apikey = config_item('sms.apikey');
    list($mobile, $content) = $params;
    $ch = curl_init();
    $url = 'http://apis.baidu.com/kingtto_media/106sms/106sms';
    $query_string = http_build_query(array(
        'mobile' => $mobile,
        'content' => $content,
        'tag' => 2
    ));
    $header = array(
        "apikey: {$apikey}",
    );
    try {
        // 添加apikey到header
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);   //超时时间为1秒
        // 执行HTTP请求
        curl_setopt($ch, CURLOPT_URL, $url . '?' . $query_string);
        $res = curl_exec($ch);
    } catch (Exception $ex) {
        logMessage($ex->getMessage(), LOG_ERR);
        return false;
    }
    return $res;
}

/**
 * 取出数组中的某些列组成一个新的数组
 * @param array $array
 * @param mixed $keys
 * @return array
 */
function array_fetch(array $array, ...$keys): array {
    is_array($keys[0]) && ($keys = $keys[0]);
    return array_intersect_key($array, array_flip($keys));
}

/**
 * 更换数组键名
 * @param array $array
 * @param array $keys
 * @param bool $bAll
 * @return array
 */
function array_change_keys(array $array, array $keys, bool $bAll = false) {
    if (!$bAll) {
        $array = array_fetch($array, array_keys($keys));
    }
    $ret = [];
    foreach ($array as $k => $v) {
        if (isset($keys[$k])) {
            $ret[$keys[$k]] = $v;
        } else if ($bAll) {
            $ret[$k] = $v;
        }
    }
    return $ret;
}

/**
 * 生成一个唯一标识
 */
function unique_id() {
    return md5(getenv('PATH') . microtime() . uniqid());
}

/**
 * 判断json解析是否正常
 * @return boolean
 */
function json_error() {
    if (json_last_error() == JSON_ERROR_NONE) {
        //json 解析错误
        return false;
    }
    return true;
}

/**
 * 多维数组按键排序
 * @param array $array
 * @param int $sort_flags
 * @return boolean
 */
function array_ksort(array &$array, int $sort_flags = SORT_REGULAR) {
    $status = true;
    if (ksort($array, $sort_flags)) {
        array_walk($array, function(&$item) use($status) {
            if (is_array($item)) {
                $status &= array_ksort($item);
            }
        });
        return true;
    }
    return false;
}

/**
 * 构造url
 * @staticvar type $baseUrl
 * @param string $uri
 * @return string
 */
function toUrl(string $uri = ''): string {
    static $baseUrl = null;
    if (!$baseUrl) {
        $baseUrl = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
        $baseUrl .= isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : getenv('HTTP_HOST');
        //$baseUrl .= isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : dirname(getenv('SCRIPT_NAME'));
    }
    $dir = dirname($uri);
    if ($dir === '.') {
        $dir = '';
    } else {
        $dir .= '/';
    }
    return $baseUrl . '/' . $dir . rawurlencode(basename($uri));
}

/**
 * 验证手机号
 * @param string $phone
 * @return bool
 */
function checkPhone($phone): bool {
    if (preg_match('/^((?:13[0-9]{1}|15[0-9]{1}|18[0-9]{1})+\d{8})$/', $phone)) {
        return true;
    }
    return false;
}

/**
 * 验证邮箱
 * @param string $email
 * @return bool
 */
function checkEmail($email): bool {
    if (preg_match('/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i', $email)) {
        return true;
    }
    return false;
}

/**
 * 验证密码6-10位数字字母组合
 * @param string $password
 * @return bool
 */
function checkPassword($password) {
    if (preg_match('/^[a-zA-Z0-9]{6,10}$/', $password)) {
        return true;
    }
    return false;
}

function GetIp(){
    $realip = '';
    $unknown = 'unknown';
    if (isset($_SERVER)){
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']) && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], $unknown)){
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach($arr as $ip){
                $ip = trim($ip);
                if ($ip != 'unknown'){
                    $realip = $ip;
                    break;
                }
            }
        }else if(isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP']) && strcasecmp($_SERVER['HTTP_CLIENT_IP'], $unknown)){
            $realip = $_SERVER['HTTP_CLIENT_IP'];
        }else if(isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR']) && strcasecmp($_SERVER['REMOTE_ADDR'], $unknown)){
            $realip = $_SERVER['REMOTE_ADDR'];
        }else{
            $realip = $unknown;
        }
    }else{
        if(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), $unknown)){
            $realip = getenv("HTTP_X_FORWARDED_FOR");
        }else if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), $unknown)){
            $realip = getenv("HTTP_CLIENT_IP");
        }else if(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), $unknown)){
            $realip = getenv("REMOTE_ADDR");
        }else{
            $realip = $unknown;
        }
    }
    $realip = preg_match("/[\d\.]{7,15}/", $realip, $matches) ? $matches[0] : $unknown;
    return $realip;
}

function GetIpLookup($ip = ''){
    if(empty($ip)){
        $ip = GetIp();
    }
    $res = @file_get_contents('http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=js&ip=' . $ip);
    if(empty($res)){ return false; }
    $jsonMatches = array();
    preg_match('#\{.+?\}#', $res, $jsonMatches);
    if(!isset($jsonMatches[0])){ return false; }
    $json = json_decode($jsonMatches[0], true);
    if(isset($json['ret']) && $json['ret'] == 1){
        $json['ip'] = $ip;
        unset($json['ret']);
    }else{
        return '未知';
    }
    return $json['city'] ? $json['city'] : '未知';
}

/**
 * 获取平台版本
 */
function getPlatVer(){
    $redis = PRedis::instance();
    $redis->select(R_GAME_DB);
    return intval(str_replace('.', '', $redis->hget(RK_SYS_CONFIG, 'login_plat_ver')));
}

/**
 * http get
 * @param $url
 * @return array
 */
function curl_get($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);

    $output = curl_exec($ch);
    $error = curl_error($ch);

    if($error){
        return ['code'=>1, 'msg'=>$error];
    }else{
        return ['code'=>0, 'data'=>$output];
    }
}