#!/usr/bin/env php
<?php

/**
 * 将现成的 OpenAPI JSON 上传到 Apifox
 *
 * 用法：
 *   php scripts/upload-apifox.php [apifox/openapi.json] [选项]
 *
 * 选项：
 *   --token=        Apifox 访问令牌，默认读取 .env 的 APIFOX_ACCESS_TOKEN
 *   --project=      Apifox 项目 ID，默认读取 .env 的 APIFOX_PROJECT_ID
 *   --overwrite=    覆盖策略：OVERWRITE_EXISTING / AUTO_MERGE / KEEP_EXISTING / CREATE_NEW（默认 AUTO_MERGE）
 *   --ca-bundle=    CA 证书路径，默认 apifox/cacert.pem
 *   --folder=       导入到指定接口目录 ID（不传则导入根目录）
 *   --locale=       语言，默认 zh-CN
 *   --dry-run       只校验 JSON，不上传
 *   --help, -h      显示帮助
 */

declare(strict_types=1);

$basePath = dirname(__DIR__);

$options = [];
$positional = [];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        printUsage();
        exit(0);
    }

    if (str_starts_with($arg, '--')) {
        $arg = substr($arg, 2);
        if (str_contains($arg, '=')) {
            [$key, $value] = explode('=', $arg, 2);
            $options[$key] = $value;
        } else {
            $options[$arg] = true;
        }
        continue;
    }

    $positional[] = $arg;
}

$file = $positional[0] ?? ($options['file'] ?? $basePath . '/apifox/openapi.json');
$file = is_string($file) ? $file : '';

if ($file === '' || !is_file($file)) {
    fail("OpenAPI JSON 文件不存在：{$file}");
}

$raw = file_get_contents($file);
if ($raw === false) {
    fail("读取文件失败：{$file}");
}

// 去掉 UTF-8 BOM
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

$spec = json_decode($raw, true);
if (!is_array($spec)) {
    fail('JSON 解析失败：' . json_last_error_msg());
}

if (!isset($spec['openapi']) && !isset($spec['swagger'])) {
    fail('不是合法的 OpenAPI/Swagger 文档：缺少 openapi 或 swagger 字段');
}

if (!isset($spec['paths']) || !is_array($spec['paths']) || $spec['paths'] === []) {
    fail('OpenAPI 文档中没有 paths');
}

$operationCount = 0;
foreach ($spec['paths'] as $operations) {
    if (!is_array($operations)) {
        continue;
    }
    foreach (array_keys($operations) as $method) {
        if (in_array(strtolower((string) $method), ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'], true)) {
            $operationCount++;
        }
    }
}

$title = (string) ($spec['info']['title'] ?? '未命名文档');
$version = (string) ($spec['info']['version'] ?? '-');
$format = isset($spec['swagger']) ? 'Swagger ' . $spec['swagger'] : 'OpenAPI ' . $spec['openapi'];

echo "文件：{$file}\n";
echo "标题：{$title}\n";
echo "版本：{$version}（{$format}）\n";
echo "路径： " . count($spec['paths']) . " 个，接口：{$operationCount} 个\n";

if (isset($options['dry-run'])) {
    echo "dry-run：校验通过，未上传。\n";
    exit(0);
}

$env = loadEnv($basePath . '/.env');

$token = (string) ($options['token'] ?? getenv('APIFOX_ACCESS_TOKEN') ?: ($env['APIFOX_ACCESS_TOKEN'] ?? ''));
$projectId = (string) ($options['project'] ?? getenv('APIFOX_PROJECT_ID') ?: ($env['APIFOX_PROJECT_ID'] ?? ''));
$locale = (string) ($options['locale'] ?? 'zh-CN');
$overwrite = strtoupper((string) ($options['overwrite'] ?? 'AUTO_MERGE'));

$allowedOverwrite = ['OVERWRITE_EXISTING', 'AUTO_MERGE', 'KEEP_EXISTING', 'CREATE_NEW'];
if (!in_array($overwrite, $allowedOverwrite, true)) {
    fail('--overwrite 只能是：' . implode(' / ', $allowedOverwrite));
}

if ($token === '') {
    fail('缺少 Apifox 访问令牌：请设置 .env 的 APIFOX_ACCESS_TOKEN 或传 --token=');
}

if ($projectId === '') {
    fail('缺少 Apifox 项目 ID：请设置 .env 的 APIFOX_PROJECT_ID 或传 --project=');
}

$caBundle = (string) ($options['ca-bundle'] ?? getenv('APIFOX_CA_BUNDLE') ?: ($env['APIFOX_CA_BUNDLE'] ?? $basePath . '/apifox/cacert.pem'));

$importOptions = [
    'endpointOverwriteBehavior' => $overwrite,
    'schemaOverwriteBehavior' => $overwrite,
    'updateFolderOfChangedEndpoint' => true,
    'prependBasePath' => false,
];

if (isset($options['folder']) && is_numeric($options['folder'])) {
    $importOptions['targetEndpointFolderId'] = (int) $options['folder'];
}

$payload = json_encode([
    'input' => $raw,
    'options' => $importOptions,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($payload === false) {
    fail('请求体编码失败：' . json_last_error_msg());
}

$url = 'https://api.apifox.com/v1/projects/' . rawurlencode($projectId) . '/import-openapi?locale=' . rawurlencode($locale);

echo "正在上传到 Apifox 项目 {$projectId} ...\n";

$ch = curl_init($url);
if ($ch === false) {
    fail('初始化 cURL 失败');
}

$curlOptions = [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'X-Apifox-Api-Version: 2024-03-28',
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
];

// phpstudy 等环境通常没有配置 CA 证书，存在本地证书时显式使用
if ($caBundle !== '' && is_file($caBundle)) {
    $curlOptions[CURLOPT_CAINFO] = $caBundle;
}

curl_setopt_array($ch, $curlOptions);

$body = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($body === false) {
    fail('请求失败：' . ($error !== '' ? $error : '未知 cURL 错误'));
}

$response = json_decode((string) $body, true);

if ($status < 200 || $status >= 300) {
    fail("上传失败：HTTP {$status}\n" . (string) $body);
}

if (!is_array($response)) {
    echo "上传完成，但响应不是 JSON：\n{$body}\n";
    exit(0);
}

if (($response['success'] ?? true) === false) {
    fail("Apifox 返回失败：\n" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$counters = $response['data']['counters'] ?? [];
if (is_array($counters) && $counters !== []) {
    echo "\n导入统计：\n";
    foreach ($counters as $key => $value) {
        echo '  ' . str_pad((string) $key, 32) . $value . "\n";
    }
}

$errors = $response['data']['errors'] ?? [];
if (is_array($errors) && $errors !== []) {
    echo "\n部分错误：\n" . json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n同步完成。\n";

/**
 * 读取 .env 配置（只取 KEY=VALUE 形式）
 */
function loadEnv(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $vars = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($value !== '' && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }

        $vars[$key] = $value;
    }

    return $vars;
}

function printUsage(): void
{
    echo <<<TXT
将现成的 OpenAPI JSON 上传到 Apifox

用法：
  php scripts/upload-apifox.php [apifox/openapi.json] [选项]

选项：
  --token=        Apifox 访问令牌，默认读取 .env 的 APIFOX_ACCESS_TOKEN
  --project=      Apifox 项目 ID，默认读取 .env 的 APIFOX_PROJECT_ID
  --overwrite=    覆盖策略：OVERWRITE_EXISTING / AUTO_MERGE / KEEP_EXISTING / CREATE_NEW（默认 AUTO_MERGE）
  --ca-bundle=    CA 证书路径，默认 apifox/cacert.pem
  --folder=       导入到指定接口目录 ID（不传则导入根目录）
  --locale=       语言，默认 zh-CN
  --dry-run       只校验 JSON，不上传
  --help, -h      显示帮助

示例：
  php scripts/upload-apifox.php apifox/openapi.json
  php scripts/upload-apifox.php D:\\docs\\openapi.json --dry-run
  php scripts/upload-apifox.php apifox/openapi.json --overwrite=OVERWRITE_EXISTING

TXT;
}

function fail(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}
