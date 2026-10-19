<?php

namespace App\Http\Controllers\Api\v1\Search;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /** 热门搜索 */
    public function index()
    {
        return ApiResponse::success([
            "MatePad",
            "华为移动路由 5",
            "华为智慧屏 V5",
            "华为内胆包",
            "Mate 80"
        ]);
    }
}
