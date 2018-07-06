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

use think\exception\ClassNotFoundException;

class Loader
{
    /**
     * 类名映射信息
     * @var array
     */
    protected static $classMap = [];

    /**
     * 类库别名
     * @var array
     */
    protected static $classAlias = [];

    /**
     * PSR-4
     * @var array
     */
    private static $prefixLengthsPsr4 = [];
    private static $prefixDirsPsr4    = [];
    private static $fallbackDirsPsr4  = [];

    /**
     * PSR-0
     * @var array
     */
    private static $prefixesPsr0     = [];
    private static $fallbackDirsPsr0 = [];

    /**
     * 需要加载的文件
     * @var array
     */
    private static $files = [];

    /**
     * Composer安装路径
     * @var string
     */
    private static $composerPath;

    // 获取应用根目录
    # OUTPUT: such as : D:\dev\web\tp5.1
    public static function getRootPath()
    {   
        # 命令行模式
        if ('cli' == PHP_SAPI) {
            $scriptName = realpath($_SERVER['argv'][0]);
        # web模式
        } else {
            $scriptName = $_SERVER['SCRIPT_FILENAME'];
        }

        $path = realpath(dirname($scriptName));

        if (!is_file($path . DIRECTORY_SEPARATOR . 'think')) {

            #回退一层目录
            $path = dirname($path);
        }
        return $path . DIRECTORY_SEPARATOR;
    }

    // 注册自动加载机制
    public static function register($autoload = '')
    {
        // 注册系统自动加载
        spl_autoload_register($autoload ?: 'think\\Loader::autoload', true, true);

        $rootPath = self::getRootPath();

        self::$composerPath = $rootPath . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR;

        // Composer自动加载支持
        if (is_dir(self::$composerPath)) {

            # 有autoload_static.php文件的情况：
            if (is_file(self::$composerPath . 'autoload_static.php')) {

                # 加载autoload_static.php文件，类（全局类）的名称为：
                # ComposerStaticInit46edc17b6f3f3b256dffede296fe857d
                require self::$composerPath . 'autoload_static.php';

                # 获取由已定义类的名字所组成的数组
                $declaredClass = get_declared_classes();

                # 返回最后一个已经声明的类：
                # ComposerStaticInit46edc17b6f3f3b256dffede296fe857d
                $composerClass = array_pop($declaredClass);

                foreach (['prefixLengthsPsr4', 'prefixDirsPsr4', 'fallbackDirsPsr4', 'prefixesPsr0', 'fallbackDirsPsr0', 'classMap', 'files'] as $attr) {

                    # 检查该类是否有该属性：
                    if (property_exists($composerClass, $attr)) {

                        # 有该属性则把它复制到当前的Loader类的静态属性
                        self::${$attr} = $composerClass::${$attr};
                    }
                }

            } else {  # 没有autoload_static.php文件的情况
                self::registerComposerLoader(self::$composerPath);
            }
        }

        // 注册命名空间定义
        # example：将'think'映射到目录'__DIR__'（其值保存在相应的静态属性之中）
        # 详细注释见该函数
        self::addNamespace([
            'think'  => __DIR__,
            'traits' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'traits',
        ]);

        // 加载类库映射文件
        # 这里'runtime'下没有该文件！
        if (is_file($rootPath . 'runtime' . DIRECTORY_SEPARATOR . 'classmap.php')) {
            self::addClassMap(__include_file($rootPath . 'runtime' . DIRECTORY_SEPARATOR . 'classmap.php'));
        }

        // 自动加载extend目录
        # 将路径添加到：self::$fallbackDirsPsr4[]
        # 得到： 
        #   [fallbackDirsPsr4] => Array
        #   (
        #       [0] => D:\dev\web\tp5.1\extend
        #   )
        self::addAutoLoadDir($rootPath . 'extend');
    }

    // 自动加载
    # 该函数已经由spl_autoload_register注册为自动加载的实现，当使用未定义的类时，自动include类对应的文件
    # INPUT: example: "think\App"
    # self::$classAlias : example: ["App"] => "think\facade\App"
    public static function autoload($class)
    {
        # 如果类库别名已经注册(在$classAlias中)，则添加类库别名，添加后，例如，App类则等同于think\facade\App类
        if (isset(self::$classAlias[$class])) {
            return class_alias(self::$classAlias[$class], $class);
        }

        # 查找文件并包含文件
        if ($file = self::findFile($class)) {

            // Win环境严格区分大小写
            if (strpos(PHP_OS, 'WIN') !== false && pathinfo($file, PATHINFO_FILENAME) != pathinfo(realpath($file), PATHINFO_FILENAME)) {
                return false;
            }

            __include_file($file);
            return true;
        }
    }

    /**
     * 查找文件
     * @access private
     * @param  string $class
     * @return string|false
     */
    # 排在前面的被加载的优先级别越高
    private static function findFile($class)
    {
        # 如果存在类库映射，直接返回(直接得到类的文件路径)
        if (!empty(self::$classMap[$class])) {
            // 类库映射
            return self::$classMap[$class];
        }

        // 查找 PSR-4
        # example： "think\App.php"
        $logicalPathPsr4 = strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';

        # $first = "t"
        $first = $class[0];
        if (isset(self::$prefixLengthsPsr4[$first])) {
            foreach (self::$prefixLengthsPsr4[$first] as $prefix => $length) {
                # 该前缀(有命名空间限定的)类名开头，如think\
                if (0 === strpos($class, $prefix)) {
                    foreach (self::$prefixDirsPsr4[$prefix] as $dir) {

                        # example : "D:\dev\web\tp5.1\thinkphp\library\think" . "\" . "App.php"
                        if (is_file($file = $dir . DIRECTORY_SEPARATOR . substr($logicalPathPsr4, $length))) {
                            return $file;
                        }
                    }
                }
            }
        }

        // 查找 PSR-4 fallback dirs
        # example： $file = "D:\dev\web\tp5.1\extend" . "\" . "my\test.php"
        foreach (self::$fallbackDirsPsr4 as $dir) {
            if (is_file($file = $dir . DIRECTORY_SEPARATOR . $logicalPathPsr4)) {
                return $file;
            }
        }

        // 查找 PSR-0
        # 如果含有分隔符，分开两部分处理然后合并
        if (false !== $pos = strrpos($class, '\\')) {
            // namespaced class name
            # 取出(分隔符前面部分+分隔符) + 后面部分把 "_"替换为分隔符
            $logicalPathPsr0 = substr($logicalPathPsr4, 0, $pos + 1)
            . strtr(substr($logicalPathPsr4, $pos + 1), '_', DIRECTORY_SEPARATOR);
        } else {
            // PEAR-like class name
            # 转换Psr0风格的类名("_"替换为分隔符)
            $logicalPathPsr0 = strtr($class, '_', DIRECTORY_SEPARATOR) . '.php';
        }

        # 同Psr4的处理：
        if (isset(self::$prefixesPsr0[$first])) {
            foreach (self::$prefixesPsr0[$first] as $prefix => $dirs) {
                if (0 === strpos($class, $prefix)) {
                    foreach ($dirs as $dir) {
                        if (is_file($file = $dir . DIRECTORY_SEPARATOR . $logicalPathPsr0)) {
                            return $file;
                        }
                    }
                }
            }
        }

        // 查找 PSR-0 fallback dirs
        # 同Psr4的处理
        foreach (self::$fallbackDirsPsr0 as $dir) {
            if (is_file($file = $dir . DIRECTORY_SEPARATOR . $logicalPathPsr0)) {
                return $file;
            }
        }

        return self::$classMap[$class] = false;
    }

    // 注册classmap
    public static function addClassMap($class, $map = '')
    {
        if (is_array($class)) {
            self::$classMap = array_merge(self::$classMap, $class);
        } else {
            self::$classMap[$class] = $map;
        }
    }

    // 注册命名空间
    # 假设 INPUT： [
    #           'think'  => __DIR__,
    #           'traits' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'traits',
    #         ]
    # OUTPUT: 相当于向$prefixDirsPsr4添加：
    # [think\] => Array
    #            (
    #               [0] => D:\dev\web\tp5.1\thinkphp\library\think
    #             )
    # 即将命名空间think映射到路径D:\dev\web\tp5.1\thinkphp\library\think
    public static function addNamespace($namespace, $path = '')
    {
        if (is_array($namespace)) {
            # example: $prefix = "think"; $paths = "D:\dev\web\tp5.1\thinkphp\library\think";
            foreach ($namespace as $prefix => $paths) {
                # 第三个参量设为true，则是把值加到队列开头，(array)$paths并入$self::$prefixDirsPsr4[$prefix]
                self::addPsr4($prefix . '\\', rtrim($paths, DIRECTORY_SEPARATOR), true);
            }
        } else {
            self::addPsr4($namespace . '\\', rtrim($path, DIRECTORY_SEPARATOR), true);
        }
    }

    // 添加Ps0空间
    private static function addPsr0($prefix, $paths, $prepend = false)
    {
        if (!$prefix) {
            if ($prepend) {
                self::$fallbackDirsPsr0 = array_merge(
                    (array) $paths,
                    self::$fallbackDirsPsr0
                );
            } else {
                self::$fallbackDirsPsr0 = array_merge(
                    self::$fallbackDirsPsr0,
                    (array) $paths
                );
            }

            return;
        }

        $first = $prefix[0];
        if (!isset(self::$prefixesPsr0[$first][$prefix])) {
            self::$prefixesPsr0[$first][$prefix] = (array) $paths;

            return;
        }

        if ($prepend) {
            self::$prefixesPsr0[$first][$prefix] = array_merge(
                (array) $paths,
                self::$prefixesPsr0[$first][$prefix]
            );
        } else {
            self::$prefixesPsr0[$first][$prefix] = array_merge(
                self::$prefixesPsr0[$first][$prefix],
                (array) $paths
            );
        }
    }

    // 添加Psr4空间
    private static function addPsr4($prefix, $paths, $prepend = false)
    {
        if (!$prefix) {
            # 没有前缀
            // Register directories for the root namespace.
            if ($prepend) {
                self::$fallbackDirsPsr4 = array_merge(
                    (array) $paths,
                    self::$fallbackDirsPsr4
                );
            } else {
                self::$fallbackDirsPsr4 = array_merge(
                    self::$fallbackDirsPsr4,
                    (array) $paths
                );
            }
        } elseif (!isset(self::$prefixDirsPsr4[$prefix])) {
            # 对应前缀没有则添加
            // Register directories for a new namespace.
            $length = strlen($prefix);
            if ('\\' !== $prefix[$length - 1]) {
                throw new \InvalidArgumentException("A non-empty PSR-4 prefix must end with a namespace separator.");
            }

            self::$prefixLengthsPsr4[$prefix[0]][$prefix] = $length;
            self::$prefixDirsPsr4[$prefix]                = (array) $paths;
        } elseif ($prepend) {
            # 已存在时的添加
            // Prepend directories for an already registered namespace.
            self::$prefixDirsPsr4[$prefix] = array_merge(
                (array) $paths,
                self::$prefixDirsPsr4[$prefix]
            );
        } else {
            // Append directories for an already registered namespace.
            self::$prefixDirsPsr4[$prefix] = array_merge(
                self::$prefixDirsPsr4[$prefix],
                (array) $paths
            );
        }
    }

    // 注册自动加载类库目录
    public static function addAutoLoadDir($path)
    {
        self::$fallbackDirsPsr4[] = $path;
    }

    // 注册类别名
    public static function addClassAlias($alias, $class = null)
    {
        if (is_array($alias)) {
            self::$classAlias = array_merge(self::$classAlias, $alias);
        } else {
            self::$classAlias[$alias] = $class;
        }
    }

    // 注册composer自动加载
    # 把Composer的命名空间到文件夹的映射、类库映射、需包含的文件搬到当前Loader类的静态属性中
    public static function registerComposerLoader($composerPath)
    {
        # 加载命名空间到文件夹的映射(Psr0)
        if (is_file($composerPath . 'autoload_namespaces.php')) {
            $map = require $composerPath . 'autoload_namespaces.php';
            foreach ($map as $namespace => $path) {
                self::addPsr0($namespace, $path);
            }
        }

        # 加载命名空间到文件夹的映射(Psr4)
        if (is_file($composerPath . 'autoload_psr4.php')) {
            $map = require $composerPath . 'autoload_psr4.php';
            foreach ($map as $namespace => $path) {
                self::addPsr4($namespace, $path);
            }
        }

        # 将autoload_classmap.php中的类库映射添加到当前Loader类的静态属性$classmap中
        if (is_file($composerPath . 'autoload_classmap.php')) {
            $classMap = require $composerPath . 'autoload_classmap.php';
            if ($classMap) {
                self::addClassMap($classMap);
            }
        }

        # 添加需要包含的文件到当前Loader类的$files静态属性中
        if (is_file($composerPath . 'autoload_files.php')) {
            self::$files = require $composerPath . 'autoload_files.php';
        }
    }

    // 加载composer autofile文件
    public static function loadComposerAutoloadFiles()
    {
        foreach (self::$files as $fileIdentifier => $file) {
            if (empty($GLOBALS['__composer_autoload_files'][$fileIdentifier])) {
                __require_file($file);

                $GLOBALS['__composer_autoload_files'][$fileIdentifier] = true;
            }
        }
    }

    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @access public
     * @param  string  $name 字符串
     * @param  integer $type 转换类型
     * @param  bool    $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    public static function parseName($name, $type = 0, $ucfirst = true)
    {
        if ($type) {
            # 匹配"_[a-zA-Z]"并返回第一个子组转为大写，如"my_test" => "myTest"，这里"_t"=>"T"
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);
            return $ucfirst ? ucfirst($name) : lcfirst($name);
        }

        # $type == 0
        # 这里的"\\0"指的是捕获的整个匹配，匹配[A-Z]，即一个大写字母，preg_replace执行将"_[A-Z]"替换"[A-Z]"
        # example: "MyTest" => "my_test" : "T",替换为"_T","M"替换为"_M",最后去掉首尾的"_"并全部转成小写
        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }

    /**
     * 创建工厂对象实例
     * @access public
     * @param  string $name         工厂类名
     * @param  string $namespace    默认命名空间
     * @return mixed
     */
    public static function factory($name, $namespace = '', ...$args)
    {
        $class = false !== strpos($name, '\\') ? $name : $namespace . ucwords($name);

        if (class_exists($class)) {
            return Container::getInstance()->invokeClass($class, $args);
        } else {
            throw new ClassNotFoundException('class not exists:' . $class, $class);
        }
    }
}

/**
 * 作用范围隔离
 *
 * @param $file
 * @return mixed
 */
# 使$file里面的变量只在其里面起作用，与外部隔离，防止$file里面有使用self，$this而出错
function __include_file($file)
{
    return include $file;
}

function __require_file($file)
{
    return require $file;
}
