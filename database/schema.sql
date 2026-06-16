CREATE TABLE IF NOT EXISTS `config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `config` (`config_key`, `config_value`, `description`)
VALUES ('admin.username', 'admin', '管理员用户名')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

-- 使用 php -r "echo password_hash('你的密码', PASSWORD_DEFAULT);" 生成密码后，替换下面的 config_value。
INSERT INTO `config` (`config_key`, `config_value`, `description`)
VALUES ('admin.password_hash', '', '管理员密码 hash，使用 PHP password_hash 生成')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

INSERT INTO `config` (`config_key`, `config_value`, `description`)
VALUES ('site.title', 'ImageHosting', '网站标题')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

INSERT INTO `config` (`config_key`, `config_value`, `description`)
VALUES ('upload.allowed_mimes', '{"image/jpeg":"jpg","image/png":"png","image/gif":"gif","image/webp":"webp","image/svg+xml":"svg"}', '允许上传的图片 MIME 与扩展名映射 JSON')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

INSERT INTO `config` (`config_key`, `config_value`, `description`)
VALUES ('upload.max_size', '1M', '允许上传的单个文件大小，支持 PHP 容量写法如 1M、512K')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

INSERT INTO `config` (`config_key`, `config_value`, `description`)
VALUES ('upload.disk', 'upload', '上传文件使用的 ThinkPHP filesystem 磁盘名称')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

INSERT INTO `config` (`config_key`, `config_value`, `description`)
VALUES ('upload.path', '', '上传文件在磁盘内的存储路径前缀，留空表示磁盘根目录')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

INSERT INTO `config` (`config_key`, `config_value`, `description`)
VALUES ('hotlink.enabled', '0', '是否开启图片防盗链，1开启，0关闭')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

INSERT INTO `config` (`config_key`, `config_value`, `description`)
VALUES ('hotlink.allow_empty_referer', '1', '是否允许空HTTP_REFERER请求，1允许，0拒绝')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

INSERT INTO `config` (`config_key`, `config_value`, `description`)
VALUES ('hotlink.extensions', 'jpg,jpeg,png,gif,webp,svg', '启用防盗链的URL后缀，支持逗号/换行分隔或JSON数组')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

INSERT INTO `config` (`config_key`, `config_value`, `description`)
VALUES ('hotlink.allowed_domains', '', '防盗链许可域名，支持逗号/换行分隔或JSON数组，子域名可写*.example.com')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

INSERT INTO `config` (`config_key`, `config_value`, `description`)
VALUES ('hotlink.deny_status', '403', '防盗链拒绝请求时返回的HTTP状态码，如403或404')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

CREATE TABLE IF NOT EXISTS `images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(16) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `extension` varchar(10) NOT NULL,
  `mime` varchar(100) NOT NULL,
  `size_kb` decimal(12,2) NOT NULL,
  `width` int unsigned DEFAULT NULL,
  `height` int unsigned DEFAULT NULL,
  `year` char(4) NOT NULL,
  `month` char(2) NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_images_uid` (`uid`),
  KEY `idx_images_archive` (`year`, `month`),
  KEY `idx_images_original_name` (`original_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
