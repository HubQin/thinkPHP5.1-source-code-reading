<?php
namespace app\index\controller;

use think\Request;
use think\Route;

class Index
{
    public function __construct($value='')
    {
        # code...
    }
    public function index()
    {
        
    	return "test";
    }

    public function hello($name,$city = 'ThinkPHP5')
    {
        dump(request()->param('name'));
        return 'hello,' . $name . '-' . $city;
    }
}
