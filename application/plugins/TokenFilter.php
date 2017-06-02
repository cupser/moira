<?php

class TokenFilterPlugin extends Yaf\Plugin_Abstract {

    use EasyBack;

    public function routerShutdown(\Yaf\Request_Abstract $request, \Yaf\Response_Abstract $response) {

        $exclude = ['wxpaynotify', 'sendsms', 'register', 'validsms', 'reset', 'invite', 'test', 'feedback','pay', 'porder',
            'preturn', 'pnotice', 'prouser'];

        if(config_item('application.debug') == 1){
            error_log("[".date('Y-m-d H:i:s')."]"
                .$request->getModuleName() . '/'
                .$request->getControllerName() . '/'
                .$request->getActionName() . '/'
                .file_get_contents("php://input")."\n", 3,  '/data/www/GameAPI/var/api.log');
        }

        $action = $request->getActionName();
        //不检测
        if(in_array($action, $exclude)){
            return;
        }
        //验证签名
        if (!$this->checkSign()) {
            exit;
        }
        
        WebApp::inform(WebApp::INIT_SESSION);
        //检测登录
        if($request->getActionName() !== 'login'){
            $user = new UserModel();
            if (!$user->isLogin()) {
                $this->failed('服务器繁忙', RCODE_NEED_LOGIN);
                exit;
            }
        }
    }

}
