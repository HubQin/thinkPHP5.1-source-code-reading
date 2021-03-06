<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace think;

// 载入Loader类
require __DIR__ . '/library/think/Loader.php';

// 注册自动加载
Loader::register();

# 提取注册后Loader的静态属性值
# echo "<pre>";
# $a = new \ReflectionClass('think\Loader');
# print_r($a->getStaticProperties());
# echo "</pre>";
# exit();
/*
Array
(
    [classMap] => Array
        (
            [Psr\Log\LoggerInterface] => 
        )

    [classAlias] => Array
        (
            [App] => think\facade\App
            [Build] => think\facade\Build
            [Cache] => think\facade\Cache
            [Config] => think\facade\Config
            [Cookie] => think\facade\Cookie
            [Db] => think\Db
            [Debug] => think\facade\Debug
            [Env] => think\facade\Env
            [Facade] => think\Facade
            [Hook] => think\facade\Hook
            [Lang] => think\facade\Lang
            [Log] => think\facade\Log
            [Request] => think\facade\Request
            [Response] => think\facade\Response
            [Route] => think\facade\Route
            [Session] => think\facade\Session
            [Url] => think\facade\Url
            [Validate] => think\facade\Validate
            [View] => think\facade\View
        )

    [prefixLengthsPsr4] => Array
        (
            [t] => Array
                (
                    [think\composer\] => 15
                    [think\] => 6
                    [traits\] => 7
                )

            [a] => Array
                (
                    [app\] => 4
                )

        )

    [prefixDirsPsr4] => Array
        (
            [think\composer\] => Array
                (
                    [0] => D:\dev\web\tp5.1\vendor\composer/../topthink/think-installer/src
                )

            [think\] => Array
                (
                    [0] => D:\dev\web\tp5.1\thinkphp\library\think
                    [1] => D:\dev\web\tp5.1\vendor\composer/../topthink/think-image/src
                )

            [app\] => Array
                (
                    [0] => D:\dev\web\tp5.1\application
                    [1] => D:\dev\web\tp5.1\vendor\composer/../../application
                )

            [traits\] => Array
                (
                    [0] => D:\dev\web\tp5.1\thinkphp\library\traits
                )

        )

    [fallbackDirsPsr4] => Array
        (
            [0] => D:\dev\web\tp5.1\extend
        )

    [prefixesPsr0] => Array
        (
        )

    [fallbackDirsPsr0] => Array
        (
        )

    [files] => Array
        (
        )

    [composerPath] => D:\dev\web\tp5.1\vendor\composer\
)
*/
// 注册错误和异常处理机制
Error::register();

// 实现日志接口
if (interface_exists('Psr\Log\LoggerInterface')) {
    interface LoggerInterface extends \Psr\Log\LoggerInterface
    {}
} else {
    interface LoggerInterface
    {}
}
// 注册核心类的静态代理
# App::class 获取带有命名空间的类名，这里App::class == 'think\App', facade\App::class == 'think\facade\App'
Facade::bind([
    facade\App::class        => App::class,
    facade\Build::class      => Build::class,
    facade\Cache::class      => Cache::class,
    facade\Config::class     => Config::class,
    facade\Cookie::class     => Cookie::class,
    facade\Debug::class      => Debug::class,
    facade\Env::class        => Env::class,
    facade\Hook::class       => Hook::class,
    facade\Lang::class       => Lang::class,
    facade\Log::class        => Log::class,
    facade\Middleware::class => Middleware::class,
    facade\Request::class    => Request::class,
    facade\Response::class   => Response::class,
    facade\Route::class      => Route::class,
    facade\Session::class    => Session::class,
    facade\Url::class        => Url::class,
    facade\Validate::class   => Validate::class,
    facade\View::class       => View::class,
]);

// 注册类库别名
# 对应关系: 'App' => facade\App::class => App::class
Loader::addClassAlias([
    'App'      => facade\App::class,
    'Build'    => facade\Build::class,
    'Cache'    => facade\Cache::class,
    'Config'   => facade\Config::class,
    'Cookie'   => facade\Cookie::class,
    'Db'       => Db::class,
    'Debug'    => facade\Debug::class,
    'Env'      => facade\Env::class,
    'Facade'   => Facade::class,
    'Hook'     => facade\Hook::class,
    'Lang'     => facade\Lang::class,
    'Log'      => facade\Log::class,
    'Request'  => facade\Request::class,
    'Response' => facade\Response::class,
    'Route'    => facade\Route::class,
    'Session'  => facade\Session::class,
    'Url'      => facade\Url::class,
    'Validate' => facade\Validate::class,
    'View'     => facade\View::class,
]);
