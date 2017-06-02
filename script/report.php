<?php
require_once __DIR__ . '/init.php';
class report {use EasyBack;}

$report = new report();
$redis = PRedis::instance();
$redis->select(R_REPORT);
/******************************************************************************/
//会员信息上报
for($i=1;$i<=100;$i++){
    $user = $redis->rPop(PK_REPORT_USERINFO);
    if (!$user){
        break;
    }
    $user = json_decode($user, TRUE);
    $url = "";
    $res = $report->http_post_request($url, $user['list']);
    if ($res['status']){
        continue;
    }
    
    //如果请求失败
    $log = [];
    $log['c_time'] = time();
    $log['err'] = "fasdfs";
    $log['user_id'] = $user_id;
    $log['type'] = 1;  //上报类型 1用户信息上报
    $report->baseInsert('ms_report_log', $log);

    //插入数据库日志
    $user['times'] -= 1;
    if ($user['times'] <= 0){
        continue;
    }
    $redis->lPush(PK_REPORT_USERINFO, json_encode($user));
}
/******************************************************************************/