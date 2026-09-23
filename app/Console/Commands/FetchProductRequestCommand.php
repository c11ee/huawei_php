<?php

namespace App\Console\Commands;

use App\Services\ProductService;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class FetchProductRequestCommand extends Command
{
    protected $signature = 'product:fetch-request';

    protected $description = '抓取商品页 __NEXT_DATA__，转换为商品数据并直接入库';

    /**
     * 商品分类
     */
    private const CATEGORY_IDS = [12];

    /**
     * 待抓取的商品页 URL 列表
     *
     * @var array<int, string>
     */
    private const TARGET_URLS = [
        'https://m.vmall.com/product/comdetail/index.html?prdId=10086309795952&sbomCode=3104030003803',
        'https://m.vmall.com/product/comdetail/index.html?prdId=10086569257010&sbomCode=3102010032801'
    ];

    public function handle(ProductService $productService): int
    {
        $failed = 0;

        foreach (self::TARGET_URLS as $url) {
            try {
                $requestData = $this->fetchRequestData($url);
                $spu = $productService->saveProduct($requestData);

                $this->info(sprintf(
                    '商品添加成功 [%s]，SPU ID：%d，SKU 数量：%d',
                    $url,
                    $spu->id,
                    $spu->skus->count()
                ));
            } catch (Throwable $e) {
                $failed++;
                $this->error(sprintf('商品添加失败 [%s]：%s', $url, $e->getMessage()));
            }
        }

        if ($failed > 0) {
            $this->warn(sprintf('执行完成，共 %d 个失败。', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * 从网络 URL 提取 HTML 中的 __NEXT_DATA__，并格式化为商品保存所需的数据结构
     *
     * @return array<string, mixed>
     */
    private function fetchRequestData(string $url): array
    {
        $htmlContent = $this->fetchHtml($url);

        // 1. 解析 HTML 中的 __NEXT_DATA__ 脚本内容
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $htmlContent);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//script[@id="__NEXT_DATA__"]');

        if ($nodes->length === 0) {
            throw new RuntimeException('未找到 id="__NEXT_DATA__" 的脚本标签');
        }

        $nextData = json_decode($nodes->item(0)->nodeValue, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON 解析错误: ' . json_last_error_msg());
        }

        // 2. 定位页面数据：SKU 列表在 current.base（以 sbomCode 为键），详情在 pageProps.extData
        $pageProps = $nextData['props']['pageProps'] ?? [];
        $current = $pageProps['mainData']['current'] ?? [];

        if (empty($current['base'])) {
            throw new RuntimeException('未在 __NEXT_DATA__ 中提取到商品 SKU 数据');
        }

        // 3. 映射组装保存所需的数据结构
        return [
            'category_ids' => self::CATEGORY_IDS,
            'detail'       => $this->detail($pageProps),
            'skus'         => $this->skus($current),
            'specs'        => $this->specs($current),
            'spu'          => $this->spu($current),
        ];
    }

    /**
     * 抓取页面 HTML。
     *
     * vmall 会下发 WAF 会话 Cookie（HWWAFSESID / HWWAFSESTIME）并多次 302，
     * 必须保持 Cookie 跟随跳转，否则会陷入无限重定向而拿不到内容。
     */
    private function fetchHtml(string $url): string
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'vmall_cookie_');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: zh-CN,zh;q=0.9',
            ],
            // 当前 PHP 未配置 CA 证书（php.ini 的 curl.cainfo），开启校验会报 unable to get local issuer certificate
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $html = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        @unlink($cookieFile);

        if ($html === false || $httpCode !== 200) {
            throw new RuntimeException(sprintf('请求失败（HTTP %d）：%s %s', $httpCode, $url, $error));
        }

        return (string) $html;
    }

    /**
     * 商品详情富文本位于 extData.detailsData.data
     *
     * @param  array<string, mixed>  $pageProps
     * @return array{detail_html: string, mobile_detail_html: string}
     */
    private function detail(array $pageProps): array
    {
        $html = (string) ($pageProps['extData']['detailsData']['data'] ?? '');

        return [
            'detail_html'        => $html,
            'mobile_detail_html' => $html,
        ];
    }

    /**
     * 规格项：汇总所有 SKU 的 gbomAttrList，按出现顺序得到「规格名 => 去重后的规格值」
     *
     * @param  array<string, mixed>  $current
     * @return array<int, array<string, mixed>>
     */
    private function specs(array $current): array
    {
        $nameOrder = [];
        $values = [];

        foreach ($current['base'] ?? [] as $sku) {
            foreach ($sku['gbomAttrList'] ?? [] as $attr) {
                $name = $attr['attrName'] ?? '';
                $value = $attr['attrValue'] ?? '';
                if ($name === '' || $value === '') {
                    continue;
                }

                $nameOrder[$name] = true;
                $values[$name][$value] = true;
            }
        }

        $specs = [];
        foreach (array_keys($nameOrder) as $nameIndex => $name) {
            $valueRows = [];
            foreach (array_keys($values[$name]) as $valueIndex => $value) {
                $valueRows[] = [
                    'image_url' => '',
                    'sort'      => $valueIndex + 1,
                    'value'     => $value,
                ];
            }

            $specs[] = [
                'is_image_required' => 0,
                'name'              => $name,
                'sort'              => $nameIndex + 1,
                'values'            => $valueRows,
            ];
        }

        return $specs;
    }

    /**
     * SKU 列表：current.base 的每一项即一个 SKU（键为 sbomCode）
     *
     * @param  array<string, mixed>  $current
     * @return array<int, array<string, mixed>>
     */
    private function skus(array $current): array
    {
        $currentSbomCode = (string) ($current['currentSbomCode'] ?? '');
        $skus = [];

        foreach ($current['base'] ?? [] as $sbomCode => $sku) {
            $specValues = [];
            foreach ($sku['gbomAttrList'] ?? [] as $attr) {
                $name = $attr['attrName'] ?? '';
                if ($name !== '') {
                    $specValues[$name] = $attr['attrValue'] ?? '';
                }
            }

            $price = (float) ($sku['price'] ?? 0);

            $skus[] = [
                'cost_price'   => 0.0,
                'image_url'    => $this->photoUrl($sku['photoPath'] ?? '', $sku['photoName'] ?? ''),
                'is_default'   => (string) $sbomCode === $currentSbomCode ? 1 : 0,
                'name'         => $sku['name'] ?? '',
                'sale_price'   => $price,
                'sku_code'     => (string) ($sku['sbomCode'] ?? $sbomCode),
                'sort'         => count($skus) + 1,
                'spec_values'  => (object) $specValues,
                'status'       => 1,
                'stock'        => (int) ($sku['inventory'] ?? 0),
                'strike_price' => $price,
                'volume'       => 0.0,
                'weight'       => 0.0,
            ];
        }

        return $skus;
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function spu(array $current): array
    {
        $base = $current['base'] ?? [];
        $sbom = $base[$current['currentSbomCode'] ?? ''] ?? ($base[array_key_first($base)] ?? []);

        $sliderImages = [];
        foreach ($sbom['groupPhotoList'] ?? [] as $photo) {
            $url = $this->photoUrl($photo['photoPath'] ?? '', $photo['photoName'] ?? '');
            if ($url !== '') {
                $sliderImages[] = $url;
            }
        }

        return [
            'brand_id'            => 1,
            'product_description' => $current['briefName'] ?? '',
            'product_name'        => $current['name'] ?? '',
            'slider_images'       => $sliderImages,
            'sort'                => 1,
            'spec_template_id'    => 0,
            'spu_code'            => (string) ($current['disPrdId'] ?? ''),
            'status'              => 1,
            'video_cover_url'     => '',
            'video_url'           => '',
        ];
    }

    /**
     * 拼接 vmall 图片地址：https://res.vmallres.com/pimages + 路径 + 800_800_ + 文件名
     */
    private function photoUrl(string $path, string $name): string
    {
        if ($name === '') {
            return '';
        }

        return 'https://res.vmallres.com/pimages' . $path . '800_800_' . $name;
    }
}
