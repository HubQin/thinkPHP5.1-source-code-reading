<?php
/**
*
* 工具类，使用该类来实现自动依赖注入。
*
*/
# 转载自：https://blog.csdn.net/qq_20678155/article/details/70158374
class Ioc {

    // 获得类的对象实例
    public static function getInstance($className) {

        $paramArr = self::getMethodParams($className); #  得到A(C)实例的参数

        return (new ReflectionClass($className))->newInstanceArgs($paramArr); # 最终得到B的实例(同时已经实例化整条依赖链B(A(C))，通过递归的方式，历遍所有不同层级依赖的类)
    }

    /**
     * 执行类的方法
     * @param  [type] $className  [类名]
     * @param  [type] $methodName [方法名称]
     * @param  [type] $params     [额外的参数]
     * @return [type]             [description]
     */
    public static function make($className, $methodName, $params = []) {

        // 获取类的实例
        $instance = self::getInstance($className);

        // 获取该方法所需要依赖注入的参数
        $paramArr = self::getMethodParams($className, $methodName);

        return $instance->{$methodName}(...array_merge($paramArr, $params)); # ...将数组转为参数列表：[a,b,c]=>a,b,c 参考http://php.net/manual/zh/functions.arguments.php
    }

    /**
     * 获得类的方法参数，只获得有类型的参数
     * @param  [type] $className   [description]
     * @param  [type] $methodsName [description]
     * @return [type]              [description]
     */
    protected static function getMethodParams($className, $methodsName = '__construct') {

        // 通过反射获得该类
        $class = new ReflectionClass($className);
        $paramArr = []; // 记录参数，和参数类型

        // 判断该类是否有构造函数
        if ($class->hasMethod($methodsName)) {
            // 获得构造函数
            $construct = $class->getMethod($methodsName);
            // 判断构造函数是否有参数
            $params = $construct->getParameters();

            if (count($params) > 0) {

                // 判断参数类型
                foreach ($params as $key => $param) {

                    if ($paramClass = $param->getClass()) {

                        // 获得参数类型名称
                        $paramClassName = $paramClass->getName(); #A C

                        // 获得参数类型
                        # var_dump('这里是'.$paramClassName);
                        $args = self::getMethodParams($paramClassName); #经历B->A->C，C完成实例化后，C这一层结束，继续执行上一层A的，这时从C那一层返回C的实例，用来作为A实例化的参数。最终函数返回A的实例(A的实例又包含C的实例(作为参数注入)。)
                        # var_dump('这里是'.$paramClassName);
                        $paramArr[] = (new ReflectionClass($paramClassName))->newInstanceArgs($args);//实例化C=>实例化A(传入获得的C实例)
                    } 
                }
            }
        }
        // var_dump($paramArr);
        return $paramArr;
    }
}


class A {

    protected $cObj;

    /**
     * 用于测试多级依赖注入 B依赖A，A依赖C
     * @param C $c [description]
     */
    public function __construct(C $c) {

        $this->cObj = $c;
    }

    public function aa() {

        echo 'this is A->test';
    }

    public function aac() {

        $this->cObj->cc();
    }
}

class B {

    protected $aObj;

    /**
     * 测试构造函数依赖注入
     * @param A $a [使用引来注入A]
     */
    public function __construct(A $a) {

        $this->aObj = $a;
    }

    /**
     * [测试方法调用依赖注入]
     * @param  C      $c [依赖注入C]
     * @param  string $b [这个是自己手动填写的参数]
     * @return [type]    [description]
     */
    public function bb(C $c, $b) {

        $c->cc();
        echo "\r\n";

        echo 'params:' . $b;
    }

    /**
     * 验证依赖注入是否成功
     * @return [type] [description]
     */
    public function bbb() {

        $this->aObj->aac();
    }
}

class C {

    public function cc() {

        echo 'this is C->cc';
    }
}



// 使用Ioc来创建B类的实例，B的构造函数依赖A类，A的构造函数依赖C类。
$bObj = Ioc::getInstance('B');
$bObj->bbb(); // 输出：this is C->cc ， 说明依赖注入成功。

// 打印$bObj
// var_dump($bObj);

// 打印结果，可以看出B中有A实例，A中有C实例，说明依赖注入成功。
/*object(B)#3 (1) {
  ["aObj":protected]=>
  object(A)#7 (1) {
    ["cObj":protected]=>
    object(C)#10 (0) {
    }
  }
}*/

// 测试方法依赖注入
Ioc::make('B', 'bb', ['this is param b']);

// 输出结果，可以看出依赖注入成功。
// this is C->cc
// params:this is param b