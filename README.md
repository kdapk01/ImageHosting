# ImageHosting

基于 `ThinkPHP 8` + `Naive UI` 的图床系统，提供后台登录、图片上传、图片列表、公开访问链接、缩略图和指定尺寸图片输出。

## 项目结构

```text
app/          后端控制器、模型、中间件和语言包
config/       ThinkPHP 配置
database/     数据库初始化 SQL
public/       Web 入口和前端静态资源发布目录
route/        路由定义
upload/       默认本地图片存储目录
view/         后端渲染入口模板
web/          Vite 前端工程
```

## 功能

- 管理员登录、退出和登录态校验
- 图片上传、去重、列表查询、详情查看和删除
- 支持 JPEG、PNG、GIF、WebP、SVG，具体类型由数据库配置控制
- 按 `年/月` 归档存储图片
- 原图链接、缩略图链接和指定尺寸链接
- SVG 安全检查，位图使用 GD 重新编码后保存
- 上传大小限制、分辨率内存预估，避免大图触发 PHP 内存溢出
- 基于 ThinkPHP `Filesystem`，后续可通过扩展接入第三方存储

## 环境要求

- PHP >= 8.0
- MySQL 或兼容数据库
- Composer
- Node.js 和 npm，用于构建 `/web` 前端工程
- PHP 扩展：`gd`、`fileinfo`、`mbstring`、`pdo_mysql`

## 安装

```bash
# 安装后端依赖
composer install

# 安装前端依赖
# /web 目录是 Vite 前端工程，技术栈为 Vue 3、TypeScript、Naive UI、Vue Router。
cd web
npm install
npm run build
```

根据 `.env.example` 内容配置 `.env`，确保数据库连接可用。然后导入数据库结构：

```bash
mysql -u 用户名 -p 数据库名 < database/schema.sql
```

生成管理员密码 hash：

```bash
php -r 'echo password_hash("你的密码", PASSWORD_DEFAULT);'
```

把生成结果写入 `config` 表中的 `admin.password_hash`。

## 运行

开发环境可使用 ThinkPHP 内置服务：

```bash
php think run
```

#### 生产环境请将 `运行目录` 指向当前工程 `public` 目录。

## 前端工程

开发模式：

```bash
npm run dev
```

构建生产资源：

```bash
npm run build
```

当前 Vite 配置会在构建后自动复制产物：

- 静态资源复制到 `public/assets/web`
- `index.html` 复制到 `view/index.html`

后端首页路由 `/` 会渲染 `view/index.html`，因此发布前端更新后需要重新执行 `npm run build`。

构建前请手动删除 `/public/assets/web` 文件夹。

## 配置项

系统配置存储在数据库 `config` 表。

| 配置键                 | 默认值   | 说明                         |
| ---------------------- | -------- | ---------------------------- |
| `admin.username`       | `admin`  | 管理员用户名                 |
| `admin.password_hash`  | 空       | 管理员密码 hash              |
| `upload.allowed_mimes` | JSON     | 允许上传的 MIME 与扩展名映射 |
| `upload.max_size`      | `1M`     | 单个上传文件大小限制         |
| `upload.disk`          | `upload` | ThinkPHP Filesystem 磁盘名称 |
| `upload.path`          | 空       | 磁盘内存储路径前缀           |

Filesystem 磁盘在 `config/filesystem.php` 中配置。默认本地磁盘：

```php
'upload' => [
    'type' => 'local',
    'root' => app()->getRootPath() . 'upload',
    'url' => '/i',
    'visibility' => 'public',
],
```

公开访问 URL 优先由 `Filesystem::disk(...)->url($path)` 生成，因此本地 URL 前缀和第三方存储外链都应在 filesystem 磁盘配置中处理。

## 上传限制说明

请把 PHP 环境的 `upload_max_filesize` 和 `post_max_size` 设置到服务器允许的最大值。

系统会在上传接口中再次读取 `config.upload.max_size` 做业务限制。如果 PHP/FPM/Nginx/Apache 的入口限制小于 `upload.max_size`，请求会在进入业务代码前被拦截，实际仍无法上传更大的文件。

推荐：

```ini
upload_max_filesize = 20M
post_max_size = 20M
memory_limit = 128M
```

然后在 `config` 表中设置实际业务限制，例如：

```sql
UPDATE `config`
SET `config_value` = '5M'
WHERE `config_key` = 'upload.max_size';
```

## 路由

### 页面

| 方法  | 路径 | 说明         |
| ----- | ---- | ------------ |
| `GET` | `/`  | 前端入口页面 |

### 管理接口

| 方法   | 路径                | 说明         |
| ------ | ------------------- | ------------ |
| `POST` | `/api/admin/login`  | 管理员登录   |
| `GET`  | `/api/admin/me`     | 当前登录信息 |
| `POST` | `/api/admin/logout` | 退出登录     |

### 图片接口

需要登录：

| 方法     | 路径              | 说明                                                           |
| -------- | ----------------- | -------------------------------------------------------------- |
| `GET`    | `/api/images`     | 图片列表，支持 `page`、`page_size`、`keyword`、`year`、`month` |
| `POST`   | `/api/images`     | 上传图片，表单字段为 `file`                                    |
| `DELETE` | `/api/images/:id` | 删除图片                                                       |

公开访问：

| 方法  | 路径                                   | 说明         |
| ----- | -------------------------------------- | ------------ |
| `GET` | `/api/images/:id`                      | 图片详情     |
| `GET` | `/i/:year/:month/:file`                | 原图         |
| `GET` | `/i/:year/:month/:file/thumb`          | 缩略图       |
| `GET` | `/i/:year/:month/:file/:width/:height` | 指定尺寸图片 |

## 响应格式

成功：

```json
{
  "code": 0,
  "message": "操作成功",
  "data": {}
}
```

失败：

```json
{
  "code": 1,
  "message": "错误信息",
  "data": null
}
```

未登录接口会返回 HTTP `401`。

## 存储目录

默认本地存储目录：

```text
upload/{year}/{month}/{uid}.{extension}
```

缩略图：

```text
upload/{year}/{month}/{uid}_thumb.{extension}
```

指定尺寸缓存：

```text
upload/_cache/{width}x{height}/{uid}.{extension}
```

## 第三方存储适配

当前项目只内置本地磁盘。后续接入第三方存储时：

1. 安装对应 ThinkPHP Filesystem 扩展或 Flysystem 适配器。
2. 在 `config/filesystem.php` 的 `disks` 中新增磁盘配置。
3. 将数据库配置 `upload.disk` 改为新磁盘名。
4. 如需子目录前缀，设置 `upload.path`。

上传流程统一通过 `think\facade\Filesystem` 写入，公开 URL 通过磁盘 `url()` 生成。

## 安全说明

- 管理接口使用 session 登录态保护。
- 上传文件按 MIME 白名单校验。
- SVG 会拒绝脚本、事件属性、`javascript:`、`foreignObject` 等危险内容。
- 位图会通过 GD 重新编码，减少直接保存原始文件带来的风险。
- 大尺寸图片会在 GD 解码前做内存预估，超过可安全处理范围会返回错误。
