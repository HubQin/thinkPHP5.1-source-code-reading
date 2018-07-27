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

class Hook
{
    /**
     * 钩子行为定义
     * @var array
     */
    private $tags = [];

    /**
     * 绑定行为列表
     * @var array
     */
    protected $bind = [];

    /**
     * 入口方法名称
     * @var string
     */
    private static $portal = 'run';

    /**
     * 应用对象
     * @var App
     */
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 指定入口方法名称
     * @access public
     * @param  string  $name     方法名
     * @return $this
     */
    public function portal($name)
    {
        self::$portal = $name;
        return $this;
    }

    /**
     * 指定行为标识 便于调用
     * @access public
     * @param  string|array  $name     行为标识
     * @param  mixed         $behavior 行为
     * @return $this
     */
    public function alias($name, $behavior = null)
    {
        if (is_array($name)) {
            $this->bind = array_merge($this->bind, $name);
        } else {
            $this->bind[$name] = $behavior;
        }

        return $this;
    }

    /**
     * 动态添加行为扩展到某个标签
     * @access public
     * @param  string    $tag 标签名称
     * @param  mixed     $behavior 行为名称
     * @param  bool      $first 是否放到开头执行
     * @return void
     */
    public function add($tag, $behavior, $first = false)
    {
        # A || B 假如A为false,则需判断下一个语句(即执行了下一个语句)
        # A && B 假如A为false,则整个语句为false,无需再进一步判断;A为true，则需执行下一个语句
        # 此句含义：不存在$tag这个键值，这初始化它为一个空数组
        isset($this->tags[$tag]) || $this->tags[$tag] = [];

        if (is_array($behavior) && !is_callable($behavior)) {
            #传入的参数$behavior是数组且不是闭包函数时
            if (!array_key_exists('_overlay', $behavior)) {
                # 没有'_overlay'这个键值时,使用非覆盖模式
                $this->tags[$tag] = array_merge($this->tags[$tag], $behavior);
            } else {
                # 覆盖模式：覆盖之前的相同标签下面的行为定义
                unset($behavior['_overlay']);
                $this->tags[$tag] = $behavior;
            }
        } elseif ($first) {
            # 插入单个行为到相应标签的开头
            array_unshift($this->tags[$tag], $behavior);
        } else {
            # 添加单个行为到相应标签下
            $this->tags[$tag][] = $behavior;
        }
    }

    /**
     * 批量导入插件
     * @access public
     * @param  array     $tags 插件信息
     * @param  bool      $recursive 是否递归合并
     * @return void
     */
    public function import(array $tags, $recursive = true)
    {
        if ($recursive) {
            foreach ($tags as $tag => $behavior) {
                $this->add($tag, $behavior);
            }
        } else {
            # 两个数组相加，如有相同的键值，会保留第一个的
            $this->tags = $tags + $this->tags;
        }
    }

    /**
     * 获取插件信息
     * @access public
     * @param  string $tag 插件位置 留空获取全部
     * @return array
     */
    public function get($tag = '')
    {
        if (empty($tag)) {
            //获取全部的插件信息
            return $this->tags;
        }
        # 存在则返回相应标签，没有则返回空
        return array_key_exists($tag, $this->tags) ? $this->tags[$tag] : [];
    }

    /**
     * 监听标签的行为
     * @access public
     * @param  string $tag    标签名称
     * @param  mixed  $params 传入参数
     * @param  bool   $once   只获取一个有效返回值
     * @return mixed
     */
    public function listen($tag, $params = null, $once = false)
    {   
        $results = [];
        $tags    = $this->get($tag);
        var_dump($tags);
        foreach ($tags as $key => $name) {
            $results[$key] = $this->execTag($name, $tag, $params);

            if (false === $results[$key] || (!is_null($results[$key]) && $once)) {
                break;
            }
        }

        return $once ? end($results) : $results;
    }

    /**
     * 执行行为
     * @access public
     * @param  mixed     $class  行为
     * @param  mixed     $params 参数
     * @return mixed
     */
    public function exec($class, $params = null)
    {
        if ($class instanceof \Closure || is_array($class)) {
            $method = $class;
        } else {
            if (isset($this->bind[$class])) {
                $class = $this->bind[$class];
            }
            $method = [$class, self::$portal];
        }

        return $this->app->invoke($method, [$params]);
    }

    /**
     * 执行某个标签的行为
     * @access protected
     * @param  mixed     $class  要执行的行为
     * @param  string    $tag    方法名（标签名）
     * @param  mixed     $params 参数
     * @return mixed
     */
    protected function execTag($class, $tag = '', $params = null)
    {
        $this->app->isDebug() && $this->app['debug']->remark('behavior_start', 'time');

        $method = Loader::parseName($tag, 1, false);

        if ($class instanceof \Closure) {
            $call  = $class;
            $class = 'Closure';
        } elseif (strpos($class, '::')) {
            $call = $class;
        } else {
            $obj = Container::get($class);

            if (!is_callable([$obj, $method])) {
                $method = self::$portal;
            }

            $call  = [$class, $method];
            $class = $class . '->' . $method;
        }

        $result = $this->app->invoke($call, [$params]);

        if ($this->app->isDebug()) {
            $debug = $this->app['debug'];
            $debug->remark('behavior_end', 'time');
            $this->app->log('[ BEHAVIOR ] Run ' . $class . ' @' . $tag . ' [ RunTime:' . $debug->getRangeTime('behavior_start', 'behavior_end') . 's ]');
        }

        return $result;
    }

}
