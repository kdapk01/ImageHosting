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
use think\facade\Route;

Route::get('/', 'Index/index');

Route::post('api/admin/login', 'Admin/login')
    ->middleware(\think\middleware\Throttle::class, [
        'visit_rate' => '5/m',
        'key' => '__IP__',
    ]);

Route::group(function () {
    Route::get('api/admin/me', 'Admin/me');
    Route::get('api/admin/settings', 'Admin/settings');
    Route::post('api/admin/settings', 'Admin/updateSettings');
    Route::post('api/admin/favicon', 'Admin/uploadFavicon');
    Route::post('api/admin/logout', 'Admin/logout');
    Route::get('api/images', 'Img/get_list');
    Route::post('api/images', 'Img/upload');
    Route::delete('api/images/:id', 'Img/delete')->pattern(['id' => '\d+']);
})->middleware('admin_auth');

Route::get('api/images/:id', 'Img/read')->pattern(['id' => '\d+']);

Route::get('i/:year/:month/:file/thumb', 'Img/thumb')
    ->pattern(['year' => '\d{4}', 'month' => '\d{2}']);
Route::get('i/:year/:month/:file/:width/:height', 'Img/show')
    ->pattern(['year' => '\d{4}', 'month' => '\d{2}', 'width' => '\d+', 'height' => '\d+']);
Route::get('i/:year/:month/:file', 'Img/show')
    ->pattern(['year' => '\d{4}', 'month' => '\d{2}']);
