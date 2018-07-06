<?php
namespace app\index\controller;

class Index
{
    public function index()
    {
    	$a = \think\Loader::parseName("my_test",1,false);
    	print($a);
    	exit;
    }

    public function hello($name = 'ThinkPHP5')
    {
        return 'hello,' . $name;
    }
}
