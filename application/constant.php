<?php

//============================================================
// 返回状态吗
//============================================================
define('RCODE_SUCC', 0);              //成功；非0值全部为失败
define('RCODE_FAIL', 1);              //失败
define('RCODE_DENY', 1001);           //无权限
define('RCODE_REQ_ILLEGAL', 1002);    //非法的请求
define('RCODE_FAIL_SIGN', 1003);      //签名错误
define('RCODE_ARG_ILLEGAL', 1004);    //参数错误
define('RCODE_UNSUPPORT', 1005);      //版本不一致
define('RCODE_BUSY', 1006);           //服务器繁忙
define('RCODE_NEED_LOGIN', 2001);     //需要登录
define('RCODE_ACCOUNT_FREEZING', 2002);     //帐号被冻结
define('RCODE_LOGIN_AUTH_EXPIRED', 2003);   //第三方登录过期，需要重新授权
define('RCODE_ORDER_UNSELL', 3001);         //订单商品不存在或已下架

//============================================================
// Redis
//============================================================
define('R_USER_TIMEOUT', 1800);                 //用户数据过期时间
define('R_NOTICE_TIMEOUT', 300);                //公告广播数据过期时间
define('R_GOODS_TIMEOUT', 300);                 //商城商品数据过期时间
define('RK_TOKEN', 'token@');                   //键格式 - token
define('R_GAME_DB', 0);                         //用户数据库
define('R_AGENT_DB', 1);                         //代理数据库
define('R_SESSION_DB', 2);                      //session据库
define('R_SMS_DB', 3);                          //短信数据库
define('R_EMAIL_DB', 4);                        //邮件数据库
define('R_MESSAGE_DB', 5);                      //消息数据库(公告，广播等等)
define('R_BENEFITS_DB', 6);                     //福利数据库(签到，分享，任务)
define('R_REPORT', 7);                          //上报数据
define('RK_USER_INFO', 'info@');                //键格式 - 用户基本数据(hash表)
define('PK_USER_SMS', 'sms@');                  //键格式 - 用户短信数据(有序集合sorted set score保存短信发送次数)
define('PK_USER_EMAIL', 'email@');              //键格式 - 用户邮件数据(有序集合sorted set score保存邮件id号)
define('PK_USER_SYS_EMAIL', 'sys_email');              //键格式 - 系统邮件数据(有序集合sorted set score保存邮件id号)
define('PK_USER_EXLOG', 'exlog@');              //键格式 - 用户兑换记录
define('RK_NOTICE_LIST', 'notice_list');        //键格式 - 公告数据(有序集合sorted set score保存公告id号)
define('RK_CAROUSEL_LIST', 'carousel_list');        //键格式 - 轮播图数据
define('RK_ACTIVITY', 'activity');        //键格式 - 活动(hash表)
define('RK_ROOM_LIST', 'room_list');        //键格式 - 房间列表 string方式存储
define('RK_BROADCAST_LIST', 'broadcast_list');      //键格式 - 广播数据(有序集合sorted set score保存广播开始时间)
define('RK_USER_BROADCAST', 'broadcast@');          //键格式 - 用户广播数据上一次获取的数据记录(hash表，string方式存储， 数据以json方式存储 已id号为键值)
define('RK_BROADCAST_LIST_1', 'broadcast_list_1');
define('RK_USER_BROADCAST_LIST', 'user_broadcast_list'); //键格式 - 用户广播数据(有序集合sorted set score保存广播开始时间)
define('RK_USER_BROADCAST_CODE', 'user_broadcast_code'); //键格式 - 用户广播校验码(无序集合)
define('RK_USER_EXPLOITS_WIN', 'exp_win@');         //键格式 - 用户战绩数据 - 胜场
define('RK_USER_EXPLOITS_LOSE', 'exp_lose@');       //键格式 - 用户战绩数据 - 败场
define('RK_USER_EXPLOITS_TOTAL', 'exp_total@');     //键格式 - 用户战绩数据 - 总场
define('PK_RANK_GOLD_DATE', 'rank_gold_date');      //键格式 - 金币排行榜更新日期
define('PK_RANK_CREDIT_DATE', 'rank_credit_date');        //键格式 - 钻石排行榜更新日期
define('PK_RANK_EMERALD_DATE', 'rank_emerald_date');      //键格式 - 绿宝石排行榜更新日期
define('PK_RANK_WINNER_DATE', 'rank_winner_date');        //键格式 - 赢家排行榜更新日期
define('RK_RANK_GOLD', 'rank_gold');            //键格式 - 金币排行榜   list方式存储（排名从大到小）， 数据以json方式存储，包含用户ID(uid)，昵称(nickname)，金币(amount)
define('RK_RANK_CREDIT', 'rank_credit');            //键格式 - 钻石排行榜   list方式存储（排名从大到小）
define('RK_RANK_EMERALD', 'rank_emerald');            //键格式 - 绿宝石排行榜   list方式存储（排名从大到小）
define('RK_RANK_WINNER', 'rank_winner');        //键格式 - 赢家排行榜   list方式存储（排名从大到小）， 数据以json方式存储，包含用户ID(uid)，昵称(nickname)，金币(amount)
define('RK_GAME_LIST', 'game_list');            //键格式 - 游戏列表     string方式存储， 数据以json方式存储，包含用户游戏ID(gid)，版本号(version)，名称(name)，类型(mode)
#define('RK_NOTICE_LIST', 'notice_list');        //键格式 - 广播公告列表  string方式存储， 数据以json方式存储，包含ID(nid)，内容(content)，类型(type)
define('RK_GOODS_LIST', 'goods_list@');          //键格式 - 商品列表     string方式存储， 数据以json方式存储，包含ID(gid)，名称(name)，图片(img_url)，描述(desc)，价格(price)，版本号(version)
define('PK_USER_SIGN', 'sign@');                //键格式 - 用户每月签到记录，只记录单月记录，删除其他月份记录
define('PK_SIGN_RULE', 'sign_rule');            //键格式 - 签到奖励规则 基本数据(hash表)
define('PK_USER_SHARE', 'share@');              //键格式 - 用户分享记录 基本数据(hash表)
define('PK_USER_SHARE_INVITE', 'share_invite@');       //键格式 - 用户分享临时表 基本数据(string，具有有效期)
define('PK_SHARE_RULE', 'share_rule');          //键格式 - 分享奖励规则 基本数据(hash表)
define('PK_ONLINE_RULE', 'online_rule');        //键格式 - 在想奖励规则 基本数据(string表)
define('PK_USER_ONLINE', 'online@');            //键格式online
define('PK_VIP_RULE', 'vip_rule');              //键格式vip规则
define('PK_FISH_SIGN', 'fish_sign');              //捕鱼签到，(hash表)
define('PK_VIP_DAY', 'vip_day');              //捕鱼vip每日奖励，(hash表)
define('PK_REPORT_USERINFO', 'report_userinfo');   //用户信息上报
define('PK_TASK_RULE', 'task_rule');   //任务规则信息 string类型json
define('PK_TASK_DAY', 'task_day');              //每日任务奖励，(hash表)
define('PK_FISH', 'fish');              //鱼种信息，(hash表)
define('PK_MODEL', 'model');                    //模块信息，(string)
define('PK_SENSITIVE_WORD', 'sensitive_word');  //敏感字，(string)
define('PK_RANK_POPULARITY', 'rank_popularity');  //人气排行
define('PK_GIFT_LIST', 'gift_list');  //礼物，(hash)
define('PK_GIFT_RECORD', 'gift_record');  //礼物记录，(hash)
define('PK_GIFT_NOSEND', 'gift_nosend');  //礼物未发送记录，(hash)
define('RK_USER_AGENT', 'agent@');//用户代理信息(hash)
define('RK_AGENT_PARENT', 'agent_parent@');//上级代理链(string)

//============================================================
// 用户日志
//============================================================
define('LOG_ACT_LOGIN', 0);                 //用户日志action - 登录
define('LOG_ACT_GETITEM', 1);               //用户日志action - 给予道具
define('LOG_ACT_SIGN', 2);                  //用户日志action - 签到
define('LOG_ACT_SHARE', 3);                 //用户日志action - 分享
define('LOG_ACT_SUBSIDY', 4);               //用户日志action - 补助
define('LOG_ACT_ONLINE', 5);               //用户日志action - 在线
define('LOG_ACT_VIP', 6);               //用户日志action - vip
define('LOG_ACT_SHOP', 7);               //用户日志action - 商城
define('LOG_ACT_TASK', 8);               //用户日志action - 任务


//===============================
//货币类型
//====================================
define('CURRENCY_GOLD', 'gold');//金币

//============================================================
// Server
//============================================================
//define('DEF_AVATAR', 'rs/default.png');                 //用户日志action - 登录