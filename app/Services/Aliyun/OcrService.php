<?php

namespace App\Services\Aliyun;

use AlibabaCloud\SDK\Ocrapi\V20210707\Ocrapi;
use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeIdcardRequest;
use AlibabaCloud\Dara\Models\RuntimeOptions;
use AlibabaCloud\Dara\Exception\DaraRespException;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeBusinessLicenseRequest;
use Illuminate\Support\Facades\Log;

class OcrService
{
    protected Ocrapi $client;

    public function __construct()
    {
        $config = new Config([
            'accessKeyId' => config('services.aliyun.access_key_id'),
            'accessKeySecret' => config('services.aliyun.access_key_secret'),
        ]);
        // 访问的域名
        $config->endpoint = 'ocr-api.cn-hangzhou.aliyuncs.com';
        $this->client = new Ocrapi($config);
    }

    /**
     * 身份证识别：从图片中提取身份证信息
     */
    public function recognizeIdcard(string $image): ?array
    {
        $request = new RecognizeIdcardRequest([
            'url' => $image,
        ]);
        $runtime = new RuntimeOptions([
            'maxIdleConns' => 3,
            'connectTimeout' => 10000,
            'readTimeout' => 10000,
        ]);
        try {
            $response = $this->client->recognizeIdcardWithOptions($request, $runtime);
            // 字符串转 JSON
            $data = json_decode($response->toArray()['body']['Data'], true);
            $info = $data['data']['face']['data'] ?? null;
            return $info ? [
                'name'      => $info['name'] ?? '',
                'sex'       => $info['sex'] ?? '',
                'ethnicity' => $info['ethnicity'] ?? '',
                'birthDate' => $info['birthDate'] ?? '',
                'address'   => $info['address'] ?? '',
                'idNumber'  => $info['idNumber'] ?? '',
            ] : null;
        } catch (DaraRespException $e) {
            Log::error('身份证识别失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 营业执照识别：从图片中提取营业执照信息
     */
    public function recognizeBusinessLicense(string $image): ?array
    {
        $request = new RecognizeBusinessLicenseRequest([
            'url' => $image,
        ]);
        $runtime = new RuntimeOptions([
            'maxIdleConns' => 3,
            'connectTimeout' => 10000,
            'readTimeout' => 10000,
        ]);
        try {
            $response = $this->client->recognizeBusinessLicenseWithOptions($request, $runtime);
            // 字符串转 JSON
            $data = json_decode($response->toArray()['body']['Data'], true);
            $info = $data['data'] ?? null;
            return $info ? [
                'RegistrationDate'  => $info['RegistrationDate'] ?? '',
                'businessAddress'   => $info['businessAddress'] ?? '',
                'businessScope'     => $info['businessScope'] ?? '',
                'companyName'       => $info['companyName'] ?? '',
                'companyType'       => $info['companyType'] ?? '',
                'creditCode'        => $info['creditCode'] ?? '',
                'issueDate'         => $info['issueDate'] ?? '',
                'legalPerson'       => $info['legalPerson'] ?? '',
                'registeredCapital' => $info['registeredCapital'] ?? '',
                'title'             => $info['title'] ?? '',
                'validFromDate'     => $info['validFromDate'] ?? '',
            ] : null;
        } catch (DaraRespException $e) {
            Log::error('营业执照识别失败: ' . $e->getMessage());
            return null;
        }
    }
}
