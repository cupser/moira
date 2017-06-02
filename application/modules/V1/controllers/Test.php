<?php
/**
 * vip
 */

class TestController extends \BaseController {

    /**
     * 获取vip信息
     */
    public function testAction() {
        switch ($_GET['type']){
            case 1:
                //添加广播,正对所有人
                $insert = [];
                $insert['content'] = $_GET['content'] ?? "今天你购买了么？";
                $insert['source'] = 0;
                $insert['times'] = 0;
                $insert['intervals'] = 0;
                $insert['start_time'] = time();
                $insert['end_time'] = time() + 3600*24*30;
                $common = new CommonModel();
                if ($common->sendBroadcast($insert)){
                    echo "发布成功";
                }
                break;
            case 2:
                //添加公告,正对所有人
                $insert = [];
                $insert['title'] = $_GET['title'] ?? "公告来啦";
                $insert['content'] = $_GET['content'] ?? "公告来啦，大家准备接受啦";
                $common = new CommonModel();
                if ($common->sendNotice($insert)){
                    echo "发布成功";
                }
                break;
            case 3:
                //添加邮件，正对单人
                $user_id = $_GET['uid'];
                if (!$user_id){
                    echo "请输入uid";exit;
                }
                $data = [];
                $data['title'] = $_GET['title'] ?? "感谢邮件";
                $data['content'] = $_GET['content'] ?? "这是一个测试默认邮件哈，么么哒";
                $data['from_id'] = 0;
                $common = new CommonModel();
                if ($common->sendEmail($user_id, $data)){
                    echo "发布成功";
                }
        }
    }
}
