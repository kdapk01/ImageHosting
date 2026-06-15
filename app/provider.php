<?php
use app\ExceptionHandle;
use app\Request;

// 容器Provider定义文件
return [
    think\Request::class => Request::class,
    think\exception\Handle::class => ExceptionHandle::class,
];
