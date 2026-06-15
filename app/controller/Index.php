<?php

namespace app\controller;

use think\facade\View;

class Index
{
    /**
     * 渲染前端入口页面
     * @return string
     */
    public function index()
    {
        return View::engine('php')->fetch('index.html');
    }
}
