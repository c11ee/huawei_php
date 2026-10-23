<?php

namespace App\Services;

use AlibabaCloud\Dara\Dara;
use AlibabaCloud\SDK\Dypnsapi\V20170525\Dypnsapi;
use AlibabaCloud\SDK\Dypnsapi\V20170525\Models\CheckSmsVerifyCodeRequest;
use AlibabaCloud\SDK\Dypnsapi\V20170525\Models\SendSmsVerifyCodeRequest;
use Darabonba\OpenApi\Models\Config;
use Exception;
use Illuminate\Support\Facades\Log;

class AliyunAuthService
{
    protected Dypnsapi $client;

    public function __construct()
    {
        // 本地 PHP 未配置 curl.cainfo，显式指定 CA 证书，避免 cURL error 60
        $caBundle = base_path('apifox/cacert.pem');
        if (is_file($caBundle)) {
            Dara::config(['verify' => $caBundle]);
        }

        $config = new Config([
            'accessKeyId' => config('services.aliyun_dypns.access_key_id'),
            'accessKeySecret' => config('services.aliyun_dypns.access_key_secret'),
        ]);
        // 访问的域名
        $config->endpoint = 'dypnsapi.aliyuncs.com';
        $this->client = new Dypnsapi($config);
    }

    /**
     * 仅 local 环境返回固定验证码；其他环境或未配置时返回 null，走真实短信
     */
    private function testCode(): ?string
    {
        if (!app()->environment('local')) {
            return null;
        }

        $code = config('services.aliyun_dypns.test_code');

        return $code ? (string) $code : null;
    }

    /**
     * 发送登录验证码
     * 注意: 签名和模板必须使用阿里云配置的方案Code
     * https://dypns.console.aliyun.com/smsCertParamsConfig/sign
     */
    public function sendLoginCode(string $phone): bool
    {
        // local 环境不真实发送短信，直接返回成功
        if ($testCode = $this->testCode()) {
            Log::info("local 环境未真实发送短信: phone={$phone}, code={$testCode}");
            return true;
        }

        $request = new SendSmsVerifyCodeRequest([
            'phoneNumber' => $phone,
            'signName' => '恒创联众',
            'templateCode' => '100001',
            'templateParam' => json_encode(['code' => '##code##', 'min' => '5']),
            'codeLength' => 6,
            'validTime' => 300,
        ]);

        try {
            $response = $this->client->sendSmsVerifyCode($request);
            return (bool) $response->body->success;
        } catch (Exception $e) {
            Log::error('阿里云登录短信验证码发送失败:' . $e->getMessage());
            return false;
        }
    }

    /**
     * 核验验证码
     */
    public function checkCode(string $phone, string $code): bool
    {
        // local 环境直接比对固定验证码
        if ($testCode = $this->testCode()) {
            return $code === $testCode;
        }

        $request = new CheckSmsVerifyCodeRequest([
            'phoneNumber' => $phone,
            'verifyCode' => $code,
        ]);

        try {
            $response = $this->client->checkSmsVerifyCode($request);
            return $response->body->model?->verifyResult === 'PASS';
        } catch (Exception $e) {
            Log::error('阿里云登录短信验证码核验失败:' . $e->getMessage());
            return false;
        }
    }
}
