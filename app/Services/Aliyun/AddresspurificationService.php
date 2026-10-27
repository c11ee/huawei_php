<?php

namespace App\Services\Aliyun;

use AlibabaCloud\OpenApiUtil\OpenApiUtilClient;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use AlibabaCloud\SDK\Addresspurification\V20191118\Addresspurification;
use App\Models\Address\SysArea;
use Darabonba\OpenApi\Models\Config;
use Darabonba\OpenApi\Models\OpenApiRequest;
use Darabonba\OpenApi\Models\Params;
use Exception;
use Illuminate\Support\Facades\Log;

class AddresspurificationService
{
    protected Addresspurification $client;

    public function __construct()
    {
        $config = new Config([
            'accessKeyId' => config('services.aliyun.access_key_id'),
            'accessKeySecret' => config('services.aliyun.access_key_secret'),
        ]);
        // 访问的域名
        $config->endpoint = 'address-purification.cn-hangzhou.aliyuncs.com';
        $this->client = new Addresspurification($config);
    }

    /**
     * 物流面单信息提取：一次拿到姓名、电话、地址
     */
    public function extractExpress(string $text): array
    {
        $params = new Params([
            'action' => 'ExtractExpress',
            'version' => '2019-11-18',
            'protocol' => 'HTTPS',
            'pathname' => '/',
            'method' => 'POST',
            'authType' => 'AK',
            'style' => 'RPC',
            'reqBodyType' => 'formData',
            'bodyType' => 'json',
        ]);

        $body = [
            'AppKey' => config('services.aliyun.address_purification_project_id'),
            'ServiceCode' => 'addrp',
            'Text' => $text,
        ];

        $req = new OpenApiRequest([
            'body' => OpenApiUtilClient::parseToMap($body),
        ]);

        $runtime = new RuntimeOptions([
            'maxIdleConns' => 3,
            'connectTimeout' => 10000,
            'readTimeout' => 10000,
        ]);

        try {
            $map = $this->client->callApi($params, $req, $runtime);
            // 返回的 Data 是 JSON 字符串，需要解析
            $data = json_decode($map['body']['Data'] ?? '{}', true);
            $expressExtract = $data['express_extract'] ?? [];

            $prov = $expressExtract['prov'] ?? '';
            $city = $expressExtract['city'] ?? '';
            $district = $expressExtract['district'] ?? '';
            $town = $expressExtract['town'] ?? '';

            // 逐级按名称匹配行政区划，用上级 id 限定范围，避免同名区县（如朝阳区）命中错误数据
            $province = $prov !== '' ? SysArea::findAreaByExtName($prov, 0) : null;
            $cityArea = $city !== '' ? SysArea::findAreaByExtName($city, $province?->id) : null;
            $districtArea = $district !== '' ? SysArea::findAreaByExtName($district, $cityArea?->id) : null;
            $townArea = $town !== '' ? SysArea::findAreaByExtName($town, $districtArea?->id) : null;

            return array_merge($expressExtract, [
                'prov_id'     => $province?->id ?? 0,
                'city_id'     => $cityArea?->id ?? 0,
                'district_id' => $districtArea?->id ?? 0,
                'town_id'     => $townArea?->id ?? 0,
            ]);
        } catch (Exception $e) {
            Log::error('物流面单信息提取失败: ' . $e->getMessage());
            return [];
        }
    }
}
