<?php

namespace App\Http\Controllers;

use App\Console\Commands\FetchProductRequestCommand;
use App\Http\Responses\ApiResponse;
use App\Models\Product\ProductSpu;

class TestController extends Controller
{
    public function index()
    {
        $URL = 'https://m.vmall.com/product/comdetail/index.html';
        $products = ProductSpu::query()->get();

        foreach ($products as $product) {
            try {
                $requestData = (new FetchProductRequestCommand())->fetchRequestData($URL . '?prdId=' . $product->spu_code);
                $product->product_name = $requestData['spu']['product_name'];
                $product->product_short_name = $requestData['spu']['product_short_name'];
                $product->product_description = $requestData['spu']['product_description'];
                $product->save();
            } catch (\Exception $e) {
                continue;
            }
        }

        return ApiResponse::success([]);
    }
}
