<?php

class WebApp{
    
    const INIT_SESSION = 'INIT_SESSION';
    const AFTER_LOGIN = 'AFTER_LOGIN';
    const AFTER_PAYMENT = 'AFTER_PAYMENT';
    const UPGRADE_VIP = 'UPGRADE_VIP';
    const REPORT_USERINFO = 'REPORT_USERINFO';
    private static $_hooks = [];
    
    public static function hook(string $name, callable $callable){
        if(!is_callable($callable)){
            return false;
        }
        self::$_hooks[$name][] = $callable;
        return true;
    }
    
    public static function inform(string $name, array $params = []){
        if(isset(self::$_hooks[$name])){
            foreach (self::$_hooks[$name] as $callable){
                call_user_func_array($callable, $params);
            }
        }
    }
    
}