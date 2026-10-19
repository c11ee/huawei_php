<?php

namespace App\Http\Controllers\Api\v1\Home;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductSpu;
use App\Models\Product\ProductSpuCategory;
use Illuminate\Http\Request;

class HomeController extends Controller
{

    public function index(Request $request)
    {
        // 可选登录：带了有效 token 就返回用户，否则为 null
        $user = $request->user('sanctum'); // 等价于 auth('sanctum')->user()

        // 最新商品：仅在售(status=1)，按创建时间倒序
        $newProduct = ProductSpu::query()
            ->where('status', 1)
            ->orderByDesc('created_at')
            ->limit(10)
            ->select([
                'id',
                'product_name',
                'default_sku_image_url',
                'default_sku_sale_price',
                'default_sku_stock',
            ])->get();

        // 分类楼层：已启用 且 标记为首页，按 sort 排序
        $category = ProductCategory::query()
            ->where('status', 1)
            ->where('is_home', 1)
            ->orderBy('sort')
            ->get();

        // 每个楼层「自身 + 全部子孙分类」的 id 集合
        $floorCategoryIds = ProductCategory::subtreeIds($category->pluck('id')->all());

        // 转一维,去重,重置键,转回PHP数组
        $allCategoryIds = collect($floorCategoryIds)->flatten()->unique()->values()->all();

        // 一次查出所有楼层相关的在售商品
        $floorProducts = ProductSpu::query()
            ->where('status', 1)
            ->whereHas('categories', fn($q) => $q->whereIn('product_spu_category.category_id', $allCategoryIds))
            ->orderBy('sort')
            ->get([
                'id',
                'product_short_name',
                'default_sku_image_url',
                'default_sku_sale_price',
                'default_sku_stock',
            ]);

        // 商品所属分类（仅楼层涉及的部分），用于把商品归入对应楼层
        $productCategoryMap = ProductSpuCategory::query()
            ->whereIn('spu_id', $floorProducts->pluck('id'))
            ->whereIn('category_id', $allCategoryIds)
            ->get(['spu_id', 'category_id'])
            // 每个商品 -> 所属分类 id 集合
            ->groupBy('spu_id')
            ->map(fn($rows) => $rows->pluck('category_id')->all());

        return ApiResponse::success([
            'banner' => [
                [
                    'path'  => '/',
                    'image' => 'https://res.vmallres.com/uomcdn/CN/cms/202511/f38e90cce4514397bca98ed98379d8bb.jpg',
                ],
                [
                    'path'  => '/',
                    'image' => 'https://res.vmallres.com/uomcdn/CN/cms/202511/f6aeae11a74b430583af4bc65a03b63a.jpg',
                ],
                [
                    'path'  => '/',
                    'image' => 'https://res.vmallres.com/uomcdn/CN/cms/202511/f2c668885bca46058f9b6e04bb07b5d8.jpg',
                ],
                [
                    'path'  => '/',
                    'image' => 'https://res.vmallres.com/uomcdn/CN/cms/202511/e1d5a9463b8f4270bb32fda1dfd76bc8.jpg',
                ],
                [
                    'path'  => '/',
                    'image' => 'https://res.vmallres.com/uomcdn/CN/cms/202509/b32fa175ec5a4245be0972e2a9e93ad5.jpg',
                ]
            ],
            'category' => [
                [
                    'imageUrl' => 'https://res.vmallres.com/uomcdn/CN/cms/202511/70963402adad41e8a3af6606626e130b.png.50.webp',
                    'title'    => '华为手机',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/uomcdn/CN/cms/202511/fec791ccd3fb4180a95513fe25538d07.png.50.webp',
                    'title'    => '运动健康',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/uomcdn/CN/cms/202511/a1abaf2ef5cb40b0bfbfeada7bef7fc7.png.50.webp',
                    'title'    => '影音娱乐',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/uomcdn/CN/cms/202507/535589f4a6b6475cb22e72f166f7aa9e.png.50.webp',
                    'title'    => '智慧办公',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/uomcdn/CN/cms/202406/a2a2c15898194124a32457e23b1ca1d6.webp',
                    'title'    => '教育优惠',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/uomcdn/CN/cms/2024-03/c67071804c4145a39308d561228c7b8f.webp',
                    'title'    => '她之选',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/cmscdn/CN/2023-09/651c8ebe3fa447c7ad1a62fb2d8aa161.png.50.webp',
                    'title'    => '企业商用',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/cmscdn/CN/2023-08/eaa13b24fe4140e481876b041d2b949f.png.50.webp',
                    'title'    => '以旧换新',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/cmscdn/CN/2023-08/18f2d81bd1934dfaa7e6847d95a30d90.png.50.webp',
                    'title'    => '众测',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/uomcdn/CN/cms/202509/6a2eeaee101d467eb5cce6519bef470f.png.50.webp',
                    'title'    => '鸿蒙智家',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/uomcdn/CN/cms/202506/7c60959d40a14c35b490473e7e08c1f4.png.50.webp',
                    'title'    => '鸿蒙智行',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/uomcdn/CN/cms/202509/87d231c135e44574901053c0e4d32aca.png.50.webp',
                    'title'    => '乾崑智驾',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/uomcdn/CN/cms/202504/83d690d9775344b39ace6994f3970dd6.png.50.webp',
                    'title'    => '鸿蒙智选',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/uomcdn/CN/cms/202412/963235875b6547308ca65ef1eacbeefd.webp',
                    'title'    => '配件中心',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/uomcdn/CN/cms/2024-04/7ca211983313469fb6570ae70cde52d0.webp',
                    'title'    => '直播间',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/uomcdn/CN/cms/202410/06caf0820e8b48e3a62c6c986bf181e4.png.50.webp',
                    'title'    => '生态周边',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/cmscdn/CN/2023-08/95f5129a66104674a742a19472074db9.png.50.webp',
                    'title'    => '家庭绿电',
                    'path'     => '/',
                ],
                [
                    'imageUrl' => 'https://res.vmallres.com/cmscdn/CN/2023-08/8e21a51f2cbf47ffa3dde67807bb1b3f.png.50.webp',
                    'title'    => '我的积分',
                    'path'     => '/',
                ],
            ],
            'ads' => [
                'imageUrl' => 'https://res.vmallres.com/uomcdn/CN/cms/202610/737554a669c1475c823003fc2fd5f9c3.jpg.50.webp',
                'path'     => '/',
            ],
            'newProduct' => $newProduct,
            'floor' => $category->map(function (ProductCategory $item) use ($floorCategoryIds, $floorProducts, $productCategoryMap) {
                $categoryIds = $floorCategoryIds[$item->id];

                return [
                    'category_name' => $item->category_name,
                    'category_id' => $item->id,
                    'products'  => $floorProducts
                        ->filter(fn($product) => array_intersect($productCategoryMap[$product->id] ?? [], $categoryIds))
                        ->values(),
                ];
            }),
        ]);
    }
}
