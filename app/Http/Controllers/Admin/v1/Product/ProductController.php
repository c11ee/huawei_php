<?php

namespace App\Http\Controllers\Admin\v1\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id) {}

    /**
     * 删除分类（支持逗号分隔批量删除，连同子孙分类一并删除）
     */
    public function destroy(string $id) {}

    /**
     * 详情
     */
    public function show(string $id) {}

    /**
     * 编辑状态
     */
    public function updateStatus(Request $request) {}

    /**
     * 校验并格式化请求数据
     */
    private function validateData(Request $request): array
    {
        return [];
    }
}
