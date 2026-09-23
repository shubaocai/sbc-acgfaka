<?php
/**
 * acg-faka 虚拟主机部署向导
 *
 * MIT License · Copyright (c) 2026 鼠宝财
 * 项目地址：https://github.com/shubaocai/sbc-acgfaka
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_STRICT & ~E_WARNING);
if (function_exists('ini_set')) {
    @ini_set('display_errors', '0');
    @ini_set('memory_limit', '256M');
}
if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}

define('TOOL_VERSION', '1.1.2');
define('APP_NAME', 'acg-faka 虚拟主机部署向导');
define('TOOL_AUTHOR', '鼠宝财');
define('TOOL_AUTHOR_URL', 'https://blog.mopush.cn');
define('UPSTREAM_REPO', 'https://github.com/lizhipay/acg-faka');
define('STATE_FILE', 'acg-deploy-state.json');
define('SRC_ZIP', 'acg-faka-src.zip');
define('PHP_MIN', '8.0.0');

function acg_rand_token()
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(4));
    }
    return substr(md5(uniqid('', true)), 0, 8);
}

function acg_h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function acg_simple_page($title, $bodyHtml, $script = '')
{
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<title>' . acg_h($title) . ' · ' . acg_h(TOOL_AUTHOR) . '</title>'
        . '<style>body{margin:0;padding:32px 16px;background:#f5f6f8;color:#24292f;'
        . 'font:15px/1.75 -apple-system,"Segoe UI","Microsoft YaHei",sans-serif}'
        . '.w{max-width:820px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:22px 24px}'
        . 'h1{font-size:20px;margin:0 0 12px}h2{font-size:16px;margin:20px 0 8px}'
        . 'pre{background:#0f172a;color:#cbd5e1;padding:12px 14px;border-radius:8px;overflow:auto;font:12.5px/1.6 Consolas,monospace}'
        . 'code{background:#f0f2f5;padding:1px 5px;border-radius:4px}'
        . 'input[type=text],input[type=password]{padding:7px 10px;border:1px solid #d0d7de;border-radius:6px;font:inherit;min-width:200px}'
        . 'button{padding:7px 14px;border:1px solid #d0d7de;background:#fff;border-radius:6px;cursor:pointer;font:inherit}'
        . 'button:hover{background:#f3f4f6}'
        . '.bad{background:#fdecec;color:#b3261e;padding:10px 12px;border-radius:6px;margin:12px 0}'
        . '.ok{background:#e8f7ee;color:#137a3f;padding:10px 12px;border-radius:6px;margin:12px 0}'
        . 'table{border-collapse:collapse;width:100%;font-size:13.5px}'
        . 'th,td{text-align:left;padding:6px 8px;border-bottom:1px solid #eef0f3}'
        . 'a{color:#2563eb}details{margin:10px 0}'
        . '.au{display:inline-block;font-size:12px;color:#8a5a00;background:#fff7e6;'
        . 'border:1px solid #ffe0a3;border-radius:999px;padding:1px 9px;vertical-align:2px;margin-left:6px;'
        . 'text-decoration:none}a.au:hover{background:#fff1d6;border-color:#f0c874}'
        . '.foot{margin-top:22px;padding-top:12px;border-top:1px solid #eef0f3;color:#8b949e;font-size:12.5px}'
        . '.foot a{color:#57606a;font-weight:600;text-decoration:none}'
        . '.foot a:hover{color:#2563eb;text-decoration:underline}'
        . '</style></head><body><div class="w">'
        . $bodyHtml
        . '<div class="foot">' . acg_h(APP_NAME) . ' v' . TOOL_VERSION . '　由 '
        . '<a href="' . acg_h(TOOL_AUTHOR_URL) . '" target="_blank" rel="noopener">' . acg_h(TOOL_AUTHOR) . '</a>'
        . ' 制作　MIT License</div>'
        . '</div>' . ($script !== '' ? '<script>' . $script . '</script>' : '') . '</body></html>';
}

if (version_compare(PHP_VERSION, '7.0.0', '<')) {
    acg_simple_page(APP_NAME, '<h1>PHP 版本过低</h1>'
        . '<div class="bad">当前 PHP 版本：<b>' . acg_h(PHP_VERSION) . '</b>，本程序与本部署工具都需要 <b>PHP 8.0 及以上</b>。</div>'
        . '<h2>怎么解决</h2>'
        . '<p>到主机面板里把站点 PHP 版本切到 8.0 / 8.1 / 8.2 后，刷新本页即可：</p>'
        . '<table><tr><th>面板</th><th>位置</th></tr>'
        . '<tr><td>卡乐 / Kangle</td><td>网站管理 → 该站点 → PHP 版本（或「PHP 设置」）</td></tr>'
        . '<tr><td>宝塔面板</td><td>网站 → 站点设置 → PHP 版本</td></tr>'
        . '<tr><td>cPanel / DirectAdmin</td><td>MultiPHP Manager / Select PHP Version</td></tr>'
        . '<tr><td>其他虚拟主机</td><td>控制面板里找「PHP 版本」「PHP 切换」类选项，或直接问客服</td></tr>'
        . '</table>'
        . '<h2>当前环境</h2><pre>' . acg_h('PHP ' . PHP_VERSION . '  SAPI ' . php_sapi_name() . '  ' . PHP_OS) . '</pre>');
    exit;
}

session_start();

register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        return;
    }
    if (headers_sent() === false) {
        header('Content-Type: text/html; charset=utf-8', true, 500);
    }
    $hint = '';
    if (strpos($e['message'], 'disabled') !== false || strpos($e['message'], 'disable_functions') !== false) {
        $hint = '看起来是主机禁用了某个函数。请在面板里查看 disable_functions 设置，或联系主机商放开。';
    } elseif (strpos($e['message'], 'Allowed memory') !== false) {
        $hint = '内存不足。请在面板里把 PHP memory_limit 调到 256M 以上。';
    } elseif (strpos($e['message'], 'Maximum execution time') !== false) {
        $hint = '执行超时。请在面板里把 max_execution_time 调大（或设为 0），然后重试。';
    } elseif (strpos($e['message'], 'No such file') !== false || strpos($e['message'], 'Permission denied') !== false) {
        $hint = '目录不可写或文件缺失。请确认网站根目录可写（755），并检查是否把所有文件都上传了。';
    }
    acg_simple_page(APP_NAME . ' - 出错了', '<h1>部署工具遇到错误</h1>'
        . '<div class="bad">' . acg_h($e['message']) . '</div>'
        . '<h2>位置</h2><pre>' . acg_h($e['file'] . ':' . $e['line']) . '</pre>'
        . ($hint !== '' ? '<h2>可能的原因</h2><p>' . acg_h($hint) . '</p>' : '')
        . '<h2>环境信息（反馈时请一并提供）</h2><pre>'
        . acg_h('PHP ' . PHP_VERSION . '  SAPI ' . php_sapi_name() . "\n"
            . 'memory_limit=' . ini_get('memory_limit') . '  max_execution_time=' . ini_get('max_execution_time')
            . "\n" . 'upload_max_filesize=' . ini_get('upload_max_filesize') . '  post_max_size=' . ini_get('post_max_size')
            . "\n" . 'disable_functions=' . ini_get('disable_functions') . "\n"
            . 'curl=' . (function_exists('curl_init') ? 'yes' : 'no') . '  allow_url_fopen=' . ini_get('allow_url_fopen')
            . '  zip=' . (class_exists('ZipArchive') ? 'yes' : 'no'))
        . '</pre>');
});

define('SOURCE_MIRRORS_SERIALIZED', implode("\n", array(
    'https://ghfast.top/https://github.com/lizhipay/acg-faka/archive/refs/heads/main.zip',
    'https://gh-proxy.com/https://github.com/lizhipay/acg-faka/archive/refs/heads/main.zip',
    'https://ghproxy.net/https://github.com/lizhipay/acg-faka/archive/refs/heads/main.zip',
    'https://codeload.github.com/lizhipay/acg-faka/zip/refs/heads/main',
    'https://github.com/lizhipay/acg-faka/archive/refs/heads/main.zip',
)));

function acg_mirrors()
{
    return array_values(array_filter(explode("\n", SOURCE_MIRRORS_SERIALIZED)));
}

function acg_allow_hosts()
{
    return array(
        'github.com', 'codeload.github.com', 'raw.githubusercontent.com',
        'ghfast.top', 'gh-proxy.com', 'ghproxy.net', 'gitclone.com',
        'objects.githubusercontent.com',
    );
}

function acg_need_ext()
{
    return array(
        'pdo_mysql' => '数据库连接（必需）',
        'gd' => '图片处理：验证码、缩略图（必需）',
        'zip' => '解压安装包（必需）',
        'bcmath' => '金额计算（必需）',
        'mbstring' => '中文处理（必需）',
        'curl' => '对外请求：支付回调、云更新（必需）',
        'json' => '接口数据（必需）',
        'session' => '登录会话（必需）',
        'openssl' => '加密与 HTTPS（必需）',
        'fileinfo' => '上传文件类型识别（必需）',
    );
}

function acg_soft_ext()
{
    return array(
        'opcache' => 'PHP 加速，强烈建议开启',
        'redis' => '会话/缓存放到 Redis（没有则用文件，可正常运行）',
    );
}

function acg_need_dirs()
{
    return array(
        'runtime', 'runtime/log', 'runtime/plugin', 'runtime/request', 'runtime/tmp',
        'runtime/view', 'runtime/waf', 'runtime/session',
        'assets/cache', 'app/Plugin',
    );
}

function acg_nginx_rules()
{
    return "location ~* ^/(runtime|kernel|config|vendor)/                { return 404; }\n"
        . "location ~  /\\.(?!well-known)                                { return 404; }\n"
        . "location ~* \\.(log|sql|sqlite|db|db-wal|db-shm|bak|old|save|orig|swp|swo|tmp|ini|lock)$  { return 404; }\n"
        . "location ~* (~|composer\\.(json|lock)|package(-lock)?\\.json)$ { return 404; }\n"
        . "location / {\n"
        . "    try_files \$uri \$uri/ /index.php?s=\$uri&\$args;\n"
        . "}";
}

function acg_can($fn)
{
    return function_exists($fn);
}

function acg_work_dir()
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    $tmp = @sys_get_temp_dir();
    if ($tmp && is_dir($tmp) && is_writable($tmp)) {
        $sub = $tmp . '/acg-deploy-' . substr(md5(__DIR__), 0, 12);
        if (!is_dir($sub)) {
            @mkdir($sub, 0700, true);
        }
        if (is_dir($sub) && is_writable($sub)) {
            $dir = $sub;
            return $dir;
        }
    }
    $dir = __DIR__;
    return $dir;
}

function acg_state_path()
{
    return acg_work_dir() . '/' . STATE_FILE;
}

function acg_state_read()
{
    $f = acg_state_path();
    if (!is_file($f)) {
        return array();
    }
    $data = json_decode((string)@file_get_contents($f), true);
    return is_array($data) ? $data : array();
}

function acg_state_write($data)
{
    @file_put_contents(acg_state_path(), json_encode($data, JSON_UNESCAPED_UNICODE));
}

function acg_json($data)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function acg_site_base()
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $dir = str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/'));
    $dir = rtrim($dir, '/');
    return ($https ? 'https://' : 'http://') . $host . $dir;
}

function acg_target_path()
{
    $st = acg_state_read();
    $sub = isset($st['subdir']) ? trim((string)$st['subdir'], '/') : '';
    return $sub === '' ? __DIR__ : __DIR__ . '/' . $sub;
}

function acg_password_set()
{
    $st = acg_state_read();
    return !empty($st['pass']);
}

function acg_logged_in()
{
    return !empty($_SESSION['acg_ok']);
}

function acg_writable($dir)
{
    if (!is_dir($dir)) {
        return false;
    }
    $probe = $dir . '/.acg-w-' . acg_rand_token();
    if (@file_put_contents($probe, 'ok') === false) {
        return false;
    }
    @unlink($probe);
    return true;
}

function acg_rrmdir($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    $items = @scandir($dir);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path) && !is_link($path)) {
            acg_rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function acg_http_chunk($url, $offset, $limit, $saveTo)
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!in_array($host, acg_allow_hosts(), true)) {
        return array('error' => '不允许的下载地址：' . $host);
    }

    $fh = @fopen($saveTo, $offset > 0 ? 'ab' : 'wb');
    if (!$fh) {
        return array('error' => '无法写入临时文件，请检查目录权限');
    }

    $headers = array();
    $total = 0;
    $code = 0;
    $short = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opts = array(
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'sbc-acgfaka-deploy/' . TOOL_VERSION,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
                $headers[] = trim($line);
                return strlen($line);
            },
        );
        if ($offset > 0) {
            $opts[CURLOPT_HTTPHEADER] = array('Range: bytes=' . $offset . '-' . ($offset + $limit - 1));
        }
        curl_setopt_array($ch, $opts);
        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($ok === false) {
            fclose($fh);
            return array('error' => '下载失败：' . $err);
        }
    } else {
        if (!ini_get('allow_url_fopen')) {
            fclose($fh);
            return array('error' => '主机既没有 curl 扩展也关闭了 allow_url_fopen，请在下面改用「上传安装包」');
        }
        $header = $offset > 0 ? ('Range: bytes=' . $offset . '-' . ($offset + $limit - 1) . "\r\n") : '';
        $ctx = stream_context_create(array(
            'http' => array(
                'method' => 'GET', 'timeout' => 120, 'follow_location' => 1,
                'user_agent' => 'sbc-acgfaka-deploy/' . TOOL_VERSION, 'header' => $header,
            ),
            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false),
        ));
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            fclose($fh);
            return array('error' => '下载失败（allow_url_fopen 方式），请在下面改用「上传安装包」');
        }
        fwrite($fh, $body);
        $code = 200;
        $short = strlen($body) < $limit;
    }

    fclose($fh);
    clearstatcache(true, $saveTo);
    $size = is_file($saveTo) ? (int)filesize($saveTo) : 0;

    foreach ($headers as $line) {
        if (stripos($line, 'content-range:') === 0 && preg_match('#/(\d+)#', $line, $m)) {
            $total = (int)$m[1];
        } elseif (stripos($line, 'content-length:') === 0 && $total === 0) {
            $total = $offset + (int)trim(substr($line, 15));
        }
    }

    if ($code === 416) {
        return array('offset' => $size, 'total' => $size, 'done' => true, 'code' => $code);
    }
    if ($offset > 0 && $code === 200) {
        @unlink($saveTo);
        return array('restart' => true, 'offset' => 0, 'total' => 0, 'done' => false, 'code' => $code,
            'msg' => '该源不支持断点续传，改为整包下载');
    }

    $done = $short || ($total > 0 && $size >= $total) || ($offset === 0 && $size > 0 && $code === 200);
    return array('offset' => $size, 'total' => $total > 0 ? $total : $size, 'done' => $done, 'code' => $code);
}

function acg_extract_zip($zipFile, $dest)
{
    if (!class_exists('ZipArchive')) {
        return array('error' => '没有 zip 扩展，无法解压。请在主机面板给当前 PHP 版本开启 zip 扩展');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        return array('error' => '压缩包打不开，可能下载不完整，请重新下载或改用「上传安装包」');
    }

    $tmp = $dest . '/.acg-x-' . acg_rand_token();
    @mkdir($tmp, 0755, true);
    if (!$zip->extractTo($tmp)) {
        $zip->close();
        acg_rrmdir($tmp);
        return array('error' => '解压失败：可能磁盘空间不足，或主机限制了文件数');
    }
    $zip->close();

    $entries = @scandir($tmp);
    $entries = is_array($entries) ? $entries : array();
    $root = null;
    foreach ($entries as $e) {
        if ($e === '.' || $e === '..') {
            continue;
        }
        $root = ($root === null) ? $e : false;
        break;
    }
    $src = (is_string($root) && is_dir($tmp . '/' . $root)) ? $tmp . '/' . $root : $tmp;

    $items = @scandir($src);
    $items = is_array($items) ? $items : array();
    $count = 0;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (!@rename($src . '/' . $item, $dest . '/' . $item)) {
            if (is_dir($dest . '/' . $item)) {
                acg_rrmdir($dest . '/' . $item);
            }
            @rename($src . '/' . $item, $dest . '/' . $item);
        }
        $count++;
    }
    acg_rrmdir($tmp);
    return array('ok' => true, 'items' => $count);
}

function acg_env_report()
{
    $target = acg_target_path();
    $ext = array();
    foreach (acg_need_ext() as $name => $why) {
        $ext[] = array('name' => $name, 'why' => $why, 'ok' => extension_loaded($name), 'soft' => false);
    }
    foreach (acg_soft_ext() as $name => $why) {
        $ext[] = array('name' => $name, 'why' => $why, 'ok' => extension_loaded($name), 'soft' => true);
    }
    $missing = array();
    foreach ($ext as $e) {
        if (!$e['ok'] && !$e['soft']) {
            $missing[] = $e['name'];
        }
    }
    $space = acg_can('disk_free_space') ? @disk_free_space(__DIR__) : false;
    $disabled = (string)ini_get('disable_functions');
    $needFns = array('disk_free_space', 'chmod', 'rename', 'unlink', 'mkdir', 'move_uploaded_file');
    $missingFns = array();
    foreach ($needFns as $fn) {
        if (!function_exists($fn)) {
            $missingFns[] = $fn;
        }
    }
    $net = function_exists('curl_init') || ini_get('allow_url_fopen');

    return array(
        'php_version' => PHP_VERSION,
        'php_ok' => version_compare(PHP_VERSION, PHP_MIN, '>='),
        'php_min' => PHP_MIN,
        'sapi' => php_sapi_name(),
        'ext' => $ext,
        'missing' => $missing,
        'net_ok' => (bool)$net,
        'curl' => function_exists('curl_init'),
        'write_ok' => acg_writable(__DIR__),
        'space' => $space,
        'space_ok' => ($space === false) || ($space > 200 * 1024 * 1024),
        'zip' => class_exists('ZipArchive'),
        'target' => $target,
        'has_app' => is_file($target . '/index.php') && is_dir($target . '/kernel'),
        'has_vendor' => is_file($target . '/vendor/autoload.php'),
        'max_exec' => (string)ini_get('max_execution_time'),
        'memory' => (string)ini_get('memory_limit'),
        'upload_max' => (string)ini_get('upload_max_filesize'),
        'post_max' => (string)ini_get('post_max_size'),
        'disabled' => $disabled,
        'missing_fns' => $missingFns,
    );
}

function acg_prepare_dirs($target)
{
    $log = array();
    foreach (acg_need_dirs() as $d) {
        $path = $target . '/' . $d;
        if (!is_dir($path)) {
            $log[] = @mkdir($path, 0755, true) ? ('创建目录 ' . $d) : ('!! 创建目录失败 ' . $d);
        }
        if (is_dir($path) && !is_writable($path)) {
            if (acg_can('chmod')) {
                @chmod($path, 0777);
                $log[] = (is_writable($path) ? '已放开写入权限 ' : '!! 仍不可写（请在面板里手动设为 777）') . $d;
            } else {
                $log[] = '!! 不可写且主机禁用了 chmod，请在面板文件管理里把 ' . $d . ' 设为可写';
            }
        }
    }
    foreach (array('config', 'kernel/Install', 'app/Pay', 'app/View/User/Theme') as $d) {
        $path = $target . '/' . $d;
        if (is_dir($path) && !is_writable($path)) {
            if (acg_can('chmod')) {
                @chmod($path, 0777);
                $log[] = (is_writable($path) ? '已放开写入权限 ' : '!! 仍不可写（请在面板里手动设为 777）') . $d;
            } else {
                $log[] = '!! 不可写且主机禁用了 chmod，请在面板文件管理里把 ' . $d . ' 设为可写';
            }
        }
    }
    return $log;
}

function acg_fix_rewrite_base($target, $subdir)
{
    $ht = $target . '/.htaccess';
    if (!is_file($ht)) {
        return array('未找到 .htaccess：Apache 主机需要手动配置伪静态');
    }
    if ($subdir === '') {
        return array('.htaccess 位于网站根目录，无需调整（Apache / LiteSpeed 主机零配置）');
    }
    $src = (string)@file_get_contents($ht);
    $new = preg_replace('/RewriteBase\s+\S+/', 'RewriteBase /' . trim($subdir, '/') . '/', $src, 1, $n);
    if ($n && $new !== null && @file_put_contents($ht, $new) !== false) {
        return array('已把 .htaccess 的 RewriteBase 改为 /' . trim($subdir, '/') . '/');
    }
    return array('!! 子目录安装需要手动把 .htaccess 的 RewriteBase 改成 /' . trim($subdir, '/') . '/');
}

function acg_check_rewrite($base)
{
    if (!function_exists('curl_init')) {
        return array('ok' => null, 'msg' => '本机没有 curl 扩展，跳过伪静态自检（不影响安装）');
    }
    $ch = curl_init($base . '/install/rewrite');
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'sbc-acgfaka-deploy/' . TOOL_VERSION,
    ));
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && is_string($body) && strpos($body, '"code":200') !== false) {
        return array('ok' => true, 'msg' => '伪静态规则生效（应用自己的检测接口返回成功）');
    }
    return array('ok' => false, 'msg' => '伪静态检测未通过（HTTP ' . $code . '）：Apache 请确认主机允许 .htaccess（AllowOverride FileInfo）；Nginx 请按下方规则配置');
}

$action = isset($_GET['action']) ? (string)$_GET['action'] : '';

if ($action === 'dl') {
    if (!acg_logged_in()) {
        acg_json(array('error' => '会话已失效，请刷新页面重新登录'));
    }
    $mirrors = acg_mirrors();
    $idx = isset($_GET['m']) ? (int)$_GET['m'] : 0;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    if (!isset($mirrors[$idx])) {
        acg_json(array('error' => '下载源序号无效'));
    }
    $res = acg_http_chunk($mirrors[$idx], $offset, 4 * 1024 * 1024, acg_work_dir() . '/' . SRC_ZIP);
    $res['mirror'] = $idx;
    acg_json($res);
}

if ($action === 'upload') {
    if (!acg_logged_in()) {
        acg_json(array('error' => '会话已失效，请刷新页面'));
    }
    if (!isset($_FILES['zip'])) {
        acg_json(array('error' => '没有收到文件。若文件较大，请确认主机 upload_max_filesize / post_max_size 是否够大（当前 ' . ini_get('upload_max_filesize') . ' / ' . ini_get('post_max_size') . '），或改用在线下载'));
    }
    $f = $_FILES['zip'];
    if (!empty($f['error'])) {
        $map = array(
            UPLOAD_ERR_INI_SIZE => '文件超过主机限制 upload_max_filesize=' . ini_get('upload_max_filesize'),
            UPLOAD_ERR_FORM_SIZE => '文件超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件只上传了一部分，请重试',
            UPLOAD_ERR_NO_FILE => '没有选择文件',
            UPLOAD_ERR_NO_TMP_DIR => '主机没有临时目录',
            UPLOAD_ERR_CANT_WRITE => '主机无法写入临时文件',
            UPLOAD_ERR_EXTENSION => '被主机扩展拦截',
        );
        acg_json(array('error' => isset($map[$f['error']]) ? $map[$f['error']] : ('上传失败（错误码 ' . $f['error'] . '）')));
    }
    $name = isset($f['name']) ? (string)$f['name'] : '';
    if (strtolower(substr($name, -4)) !== '.zip') {
        acg_json(array('error' => '请上传 .zip 格式的安装包'));
    }
    if (!is_file($f['tmp_name'])) {
        acg_json(array('error' => '上传的临时文件已不存在（主机可能清理了临时目录），请重新上传'));
    }
    $fsize = @filesize($f['tmp_name']);
    if ($fsize === false || $fsize < 1024 * 1024) {
        $shown = $fsize === false ? '大小未知' : ($fsize < 1024 ? $fsize . ' 字节' : round($fsize / 1024) . ' KB');
        acg_json(array('error' => '安装包不完整（' . $shown . '，完整包约 15 MB），可能上传中断，请重新上传；反复失败请改用在线下载'));
    }
    $dest = acg_work_dir() . '/' . SRC_ZIP;
    $moved = false;
    if (acg_can('move_uploaded_file')) {
        $moved = @move_uploaded_file($f['tmp_name'], $dest);
    }
    if (!$moved) {
        $moved = @rename($f['tmp_name'], $dest);
    }
    if (!$moved) {
        $moved = @copy($f['tmp_name'], $dest);
    }
    if (!$moved) {
        acg_json(array('error' => '无法把上传的文件保存到临时目录（主机可能禁用了相关函数），请改用在线下载'));
    }
    acg_json(array('ok' => true, 'msg' => '安装包已就位：' . round(filesize($dest) / 1048576, 1) . ' MB，可以解压了'));
}

if ($action === 'extract') {
    if (!acg_logged_in()) {
        acg_json(array('error' => '会话已失效，请刷新页面'));
    }
    $zip = acg_work_dir() . '/' . SRC_ZIP;
    if (!is_file($zip) || filesize($zip) < 1024 * 1024) {
        acg_json(array('error' => '安装包不存在或不完整，请先下载或上传'));
    }
    $target = acg_target_path();
    if (!is_dir($target) && !@mkdir($target, 0755, true)) {
        acg_json(array('error' => '安装目录创建失败：' . $target));
    }
    $res = acg_extract_zip($zip, $target);
    if (isset($res['error'])) {
        acg_json($res);
    }
    @unlink($zip);
    $st = acg_state_read();
    $st['extracted'] = true;
    acg_state_write($st);
    acg_json(array('ok' => true, 'msg' => '解压完成，已展开 ' . $res['items'] . ' 个顶层条目'));
}

if ($action === 'prepare') {
    if (!acg_logged_in()) {
        acg_json(array('error' => '会话已失效，请刷新页面'));
    }
    $target = acg_target_path();
    $st = acg_state_read();
    $log = acg_prepare_dirs($target);
    foreach (acg_fix_rewrite_base($target, isset($st['subdir']) ? trim((string)$st['subdir'], '/') : '') as $l) {
        $log[] = $l;
    }
    $log[] = is_file($target . '/vendor/autoload.php')
        ? '依赖完整（vendor/autoload.php 已就位）'
        : '!! 缺少 vendor/autoload.php：请确认下载的是完整包，或在主机上执行 composer install --no-dev';
    $log[] = is_file($target . '/index.php') ? '应用入口 index.php 已就位' : '!! 没有找到 index.php，解压步骤可能没完成';
    acg_json(array('ok' => true, 'log' => $log));
}

if ($action === 'rewritecheck') {
    if (!acg_logged_in()) {
        acg_json(array('error' => '会话已失效，请刷新页面'));
    }
    acg_json(acg_check_rewrite(acg_site_base()));
}

if ($action === 'savepw') {
    $pw = isset($_POST['pw']) ? (string)$_POST['pw'] : '';
    if (strlen($pw) < 6) {
        acg_json(array('error' => '访问密码至少 6 位'));
    }
    $st = acg_state_read();
    $st['pass'] = password_hash($pw, PASSWORD_DEFAULT);
    acg_state_write($st);
    $_SESSION['acg_ok'] = true;
    acg_json(array('ok' => true));
}

if ($action === 'login') {
    $pw = isset($_POST['pw']) ? (string)$_POST['pw'] : '';
    $st = acg_state_read();
    if (!empty($st['pass']) && password_verify($pw, (string)$st['pass'])) {
        $_SESSION['acg_ok'] = true;
        acg_json(array('ok' => true));
    }
    acg_json(array('error' => '访问密码不正确'));
}

if ($action === 'logout') {
    session_destroy();
    header('Location: ' . acg_site_base() . '/');
    exit;
}

if ($action === 'selfdelete') {
    if (!acg_logged_in()) {
        acg_json(array('error' => '会话已失效'));
    }
    @unlink(acg_state_path());
    @unlink(__FILE__);
    acg_json(array('ok' => true, 'msg' => file_exists(__FILE__) ? '自动删除失败（主机权限限制），请手动删除本文件' : '本文件已删除'));
}

if ($action === 'setsubdir') {
    if (!acg_logged_in()) {
        acg_json(array('error' => '会话已失效'));
    }
    $sub = trim(isset($_POST['subdir']) ? (string)$_POST['subdir'] : '', '/ ');
    if ($sub !== '' && !preg_match('/^[A-Za-z0-9_\-\/]{1,64}$/', $sub)) {
        acg_json(array('error' => '子目录只允许字母、数字、下划线、短横线'));
    }
    if ($sub !== '' && (strpos($sub, '..') !== false || is_file(__DIR__ . '/' . $sub))) {
        acg_json(array('error' => '子目录名不合法'));
    }
    $st = acg_state_read();
    $st['subdir'] = $sub;
    acg_state_write($st);
    acg_json(array('ok' => true));
}

$env = acg_env_report();
$needSetup = !acg_password_set();

if ($needSetup || !acg_logged_in()) {
    $body = '<h1>' . acg_h(APP_NAME) . '</h1>'
        . '<h2>' . ($needSetup ? '设置访问密码' : '请输入访问密码') . '</h2>'
        . '<p>本工具会下载并解压程序、修改目录权限，请先设置一个访问密码，避免被他人打开使用。</p>'
        . '<p><input type="password" id="pw" placeholder="访问密码（至少 6 位）"'
        . ($needSetup ? '> <input type="password" id="pw2" placeholder="再输一次" style="margin-left:6px">' : ' style="margin-left:0">')
        . ' <button id="pwbtn">' . ($needSetup ? '设置并进入' : '进入') . '</button></p>'
        . '<div id="msg"></div>'
        . '<p style="color:#6b7280;font-size:13px">环境：PHP ' . acg_h(PHP_VERSION) . ' / ' . acg_h(php_sapi_name())
        . '　装完请删除 <code>' . acg_h(basename(__FILE__)) . '</code></p>';
    $script = 'var btn=document.getElementById("pwbtn");'
        . 'btn.onclick=function(){'
        . 'var pw=document.getElementById("pw").value;var pw2=document.getElementById("pw2");'
        . 'if(pw2&&pw!==pw2.value){return show("两次输入的密码不一致");}'
        . 'var fd=new FormData();fd.append("pw",pw);'
        . 'fetch("?action="+(pw2?"savepw":"login"),{method:"POST",body:fd})'
        . '.then(function(r){return r.json();}).then(function(j){'
        . 'if(j.error){return show(j.error);}location.reload();})'
        . '.catch(function(e){show("请求失败："+e.message);});};'
        . 'function show(t){document.getElementById("msg").innerHTML="<div class=\'bad\'>"+t+"</div>";}'
        . 'document.getElementById("pw").addEventListener("keydown",function(e){if(e.key==="Enter")btn.click();});';
    acg_simple_page(APP_NAME, $body, $script);
    exit;
}

$st = acg_state_read();
$subdir = isset($st['subdir']) ? trim((string)$st['subdir'], '/') : '';
$appUrl = acg_site_base() . ($subdir === '' ? '' : '/' . $subdir);
$mirrorCount = count(acg_mirrors());

$h = '<h1>' . acg_h(APP_NAME) . ' <span style="font-size:13px;color:#6b7280">v' . TOOL_VERSION . '</span>'
    . '<a class="au" href="' . acg_h(TOOL_AUTHOR_URL) . '" target="_blank" rel="noopener" title="'
    . acg_h(TOOL_AUTHOR) . ' 的博客">' . acg_h(TOOL_AUTHOR) . '</a></h1>'
    . '<p style="color:#6b7280;font-size:13px">PHP ' . acg_h($env['php_version']) . ' / ' . acg_h($env['sapi'])
    . '　<a href="' . acg_h(UPSTREAM_REPO) . '" target="_blank" rel="noopener">上游项目</a>'
    . '　<a href="?action=logout">退出</a></p>';

$h .= '<h2>1. 环境检测</h2><table>'
    . '<tr><th>项目</th><th>当前</th><th>要求</th><th>结果</th></tr>'
    . acg_row('PHP 版本', $env['php_version'], '≥ ' . $env['php_min'], $env['php_ok'])
    . acg_row('在线下载能力', $env['curl'] ? 'curl 扩展' : ($env['net_ok'] ? 'allow_url_fopen' : '都不可用'), '至少一种', $env['net_ok'])
    . acg_row('安装目录可写', $env['write_ok'] ? '可写' : '不可写', '必需', $env['write_ok'])
    . acg_row('解压能力', $env['zip'] ? 'ZipArchive' : '缺失', '必需', $env['zip'])
    . acg_row('剩余空间', $env['space'] === false ? '主机未开放查询' : round($env['space'] / 1048576) . ' MB',
        '≥ 200 MB', $env['space_ok'])
    . acg_row('max_execution_time', $env['max_exec'] . ' 秒', '越大越好', true)
    . acg_row('upload_max_filesize', $env['upload_max'], '上传离线包时需 ≥ 20M', true)
    . '</table><h2>PHP 扩展</h2><p>';
foreach ($env['ext'] as $e) {
    $color = $e['ok'] ? '#137a3f' : ($e['soft'] ? '#92620a' : '#b3261e');
    $tag = $e['ok'] ? '已装' : ($e['soft'] ? '建议装' : '缺失');
    $h .= '<code style="color:' . $color . ';margin-right:6px" title="' . acg_h($e['why']) . '">'
        . acg_h($e['name']) . ' · ' . $tag . '</code>';
}
$h .= '</p>'
    . (count($env['missing']) ? '<div class="bad">缺少必需扩展：' . acg_h(implode('、', $env['missing']))
        . '　请在主机面板里给当前 PHP 版本安装这些扩展后再继续。</div>'
        : '<div class="ok">必需扩展齐全，可以继续。</div>')
    . (count($env['missing_fns']) ? '<div class="ok">主机禁用了一部分函数（'
        . acg_h(implode('、', $env['missing_fns'])) . '），部署工具已自动跳过，不影响安装。</div>' : '');

$h .= '<h2>2. 选择安装位置</h2>'
    . '<p>安装到：<input type="text" id="subdir" value="' . acg_h($subdir) . '" placeholder="留空＝当前目录（推荐）"> '
    . '<button id="savedir">保存</button></p>'
    . '<p style="font-size:13px;color:#6b7280">目标目录：<code>' . acg_h($env['target']) . '</code>　'
    . ($env['has_app'] ? '已检测到已安装的程序　' : '') . ($env['has_vendor'] ? '依赖完整' : '还没有 vendor') . '</p>'
    . '<p style="font-size:13px;color:#6b7280">想装到子目录（如 <code>shop</code>）就填 <code>shop</code>，装完访问地址是 <code>'
    . acg_h($appUrl) . '</code>；子目录安装会自动改 .htaccess 的 RewriteBase。</p>';

$h .= '<h2>3. 下载并解压程序</h2>'
    . '<p>源码来自上游仓库 <a href="' . acg_h(UPSTREAM_REPO) . '" target="_blank" rel="noopener">' . acg_h(UPSTREAM_REPO)
    . '</a>，<b>离线包自带 vendor 依赖，不需要 composer</b>。</p>'
    . '<p><button id="startdl">开始下载并解压</button> <span style="font-size:13px;color:#6b7280">约 15 MB，分块下载不会超时</span></p>'
    . '<div style="background:#eef0f3;height:8px;border-radius:6px;overflow:hidden;margin:10px 0">'
    . '<i id="bar" style="display:block;height:100%;width:0;background:#2563eb;transition:width .2s"></i></div>'
    . '<pre id="log">等待开始…</pre>'
    . '<details><summary style="cursor:pointer;color:#2563eb">下载不通？本地上传离线包</summary>'
    . '<p>在能上网的机器上下载 <code>https://github.com/lizhipay/acg-faka/archive/refs/heads/main.zip</code>'
    . '（或本仓库提供的离线包），然后在这里上传：</p>'
    . '<p><input type="file" id="zipfile" accept=".zip"> <button id="doUpload">上传</button></p>'
    . '<p style="font-size:13px;color:#6b7280">主机限制：upload_max_filesize ' . acg_h($env['upload_max'])
    . '，post_max_size ' . acg_h($env['post_max']) . '；超过限制请先在面板调大。</p></details>';

$h .= '<h2>4. 目录与权限</h2>'
    . '<p><button id="prep">创建运行目录并检查依赖</button></p><pre id="log2">等待开始…</pre>';

$h .= '<h2>5. 伪静态</h2>'
    . '<p><button id="rwcheck">检测伪静态是否生效</button>'
    . ' <span style="font-size:13px;color:#6b7280">装完程序、进过安装向导后再点</span></p>'
    . '<pre id="log3">等待检测…</pre>'
    . '<p style="font-size:13px;color:#6b7280">Apache / LiteSpeed / kangle 主机：程序自带 <code>.htaccess</code>，通常无需配置。</p>'
    . '<details><summary style="cursor:pointer;color:#2563eb">Nginx 用户请把下面的规则加到站点配置</summary><pre>'
    . acg_h(acg_nginx_rules()) . '</pre></details>';

$h .= '<h2>6. 完成</h2>'
    . '<p><a href="' . acg_h($appUrl) . '/install/step" target="_blank" rel="noopener"><b>打开安装向导</b></a>'
    . '　<a href="' . acg_h($appUrl) . '/admin" target="_blank" rel="noopener">后台（装完可用）</a></p>'
    . '<p style="font-size:13px;color:#6b7280">装完请删除本文件 <code>' . acg_h(basename(__FILE__))
    . '</code> 与状态文件（状态文件在系统临时目录，不在网站目录）。</p>'
    . '<p style="font-size:13px;color:#6b7280">本部署工具由 <a href="' . acg_h(TOOL_AUTHOR_URL)
    . '" target="_blank" rel="noopener">' . acg_h(TOOL_AUTHOR) . '</a>'
    . ' 制作；程序本体的安装与配置问题见项目 README 的「常见问题」。</p>'
    . '<p><button id="selfdel">删除本部署文件</button></p><div id="msg6"></div>';

acg_simple_page(APP_NAME, $h, 'var ENV=' . json_encode($env, JSON_UNESCAPED_UNICODE) . ';'
    . 'var MIRRORS=' . $mirrorCount . ';'
    . 'var mirror=0;'
    . 'function log(el,t,cls){var b=document.getElementById(el);var d=document.createElement("div");'
    . 'if(cls){d.style.color=cls;}d.textContent=t;b.appendChild(d);b.scrollTop=b.scrollHeight;}'
    . 'document.getElementById("savedir").onclick=function(){var fd=new FormData();'
    . 'fd.append("subdir",document.getElementById("subdir").value.trim());'
    . 'fetch("?action=setsubdir",{method:"POST",body:fd}).then(function(r){return r.json();})'
    . '.then(function(j){if(j.error){return alert(j.error);}location.reload();});};'
    . 'document.getElementById("startdl").onclick=function(){'
    . 'var btn=this;btn.disabled=true;var offset=0,total=0,stall=0,restarts=0;'
    . 'function step(){'
    . 'fetch("?action=dl&m="+mirror+"&offset="+offset).then(function(r){return r.json();}).then(function(j){'
    . 'if(j.error){log("log","下载源 "+(mirror+1)+" 失败："+j.error,"#ff9a9a");mirror++;offset=0;stall=0;'
    . 'if(mirror>=MIRRORS){log("log","所有下载源都失败了，请改用下面的「本地上传离线包」","#ff9a9a");btn.disabled=false;return;}'
    . 'log("log","切换下一个下载源…");return step();}'
    . 'if(j.restart){restarts++;log("log",j.msg||"改为整包下载","#ffd479");'
    . 'if(restarts>1){log("log","反复重启，已中止","#ff9a9a");btn.disabled=false;return;}'
    . 'offset=0;total=0;return step();}'
    . 'if(j.offset===offset&&offset>0){stall++;if(stall>=3){log("log","进度停滞，已中止，请刷新重试","#ff9a9a");btn.disabled=false;return;}}else{stall=0;}'
    . 'offset=j.offset||offset;total=j.total||total;'
    . 'if(total>0){document.getElementById("bar").style.width=Math.min(100,Math.round(offset*100/total))+"%";'
    . 'log("log","已下载 "+(offset/1048576).toFixed(1)+" / "+(total/1048576).toFixed(1)+" MB");}'
    . 'if(j.done||(total>0&&offset>=total)){log("log","下载完成","#7ee2a8");return doExtract();}'
    . 'step();}).catch(function(e){log("log","请求失败："+e.message,"#ff9a9a");btn.disabled=false;});}'
    . 'step();'
    . 'function doExtract(){log("log","开始解压，文件较多请稍候…");'
    . 'fetch("?action=extract").then(function(r){return r.json();}).then(function(j){'
    . 'if(j.error){log("log",j.error,"#ff9a9a");btn.disabled=false;return;}'
    . 'log("log",j.msg,"#7ee2a8");log("log","可以继续第 4 步了。","#7ee2a8");btn.disabled=false;});}'
    . '};'
    . 'document.getElementById("doUpload").onclick=function(){'
    . 'var f=document.getElementById("zipfile").files[0];'
    . 'if(!f){return alert("请先选择 .zip 文件");}'
    . 'var fd=new FormData();fd.append("zip",f);'
    . 'log("log","正在上传 "+f.name+"（"+(f.size/1048576).toFixed(1)+" MB）…");'
    . 'fetch("?action=upload",{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(j){'
    . 'if(j.error){log("log",j.error,"#ff9a9a");return;}log("log",j.msg,"#7ee2a8");'
    . 'log("log","接下来点上面的「开始下载并解压」即可（会直接用这个包）。");});};'
    . 'document.getElementById("prep").onclick=function(){var btn=this;btn.disabled=true;'
    . 'fetch("?action=prepare").then(function(r){return r.json();}).then(function(j){'
    . 'var l=j.log||[];for(var i=0;i<l.length;i++){log("log2",l[i],l[i].indexOf("!!")===0?"#ff9a9a":"#7ee2a8");}'
    . 'if(j.error){log("log2",j.error,"#ff9a9a");}btn.disabled=false;});};'
    . 'document.getElementById("rwcheck").onclick=function(){var btn=this;btn.disabled=true;'
    . 'log("log3","正在请求应用的检测接口…");'
    . 'fetch("?action=rewritecheck").then(function(r){return r.json();}).then(function(j){'
    . 'log("log3",j.msg||j.error,j.ok?"#7ee2a8":"#ff9a9a");btn.disabled=false;});};'
    . 'document.getElementById("selfdel").onclick=function(){'
    . 'if(!confirm("确定删除部署工具？删除后需要重新上传才能再次部署。")){return;}'
    . 'fetch("?action=selfdelete").then(function(r){return r.json();}).then(function(j){'
    . 'document.getElementById("msg6").innerHTML="<div class=\'ok\'>"+(j.msg||j.error)+"</div>";});};');

function acg_row($name, $cur, $need, $ok)
{
    return '<tr><td>' . acg_h($name) . '</td><td>' . acg_h($cur) . '</td><td>' . acg_h($need) . '</td><td>'
        . ($ok ? '<span style="color:#137a3f">通过</span>' : '<span style="color:#b3261e">不通过</span>') . '</td></tr>';
}
