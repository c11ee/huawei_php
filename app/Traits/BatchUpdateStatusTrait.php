<?php

namespace App\Traits;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

trait BatchUpdateStatusTrait
{
    /**
     * 批量更新状态 (ids 支持逗号分隔)
     */
    protected function batchUpdateStatus(Request $request, string $model)
    {
        $request->validate([
            'ids' => 'required|string',
            'status' => 'required|integer',
        ], [
            'ids.required' => 'ID不能为空',
            'status.required' => '状态不能为空',
            'status.integer' => '状态必须为整数',
        ]);

        $ids = array_values(array_filter(
            array_map('intval', explode(',', $request->ids)),
            fn($v) => $v > 0
        ));

        if ($ids === []) {
            return ApiResponse::error('参数错误');
        }

        $updated = $model::whereIn('id', $ids)->update([
            'status' => (int) $request->status,
        ]);

        if ($updated === 0) {
            return ApiResponse::error('更新失败');
        }

        return ApiResponse::success([], '更新成功');
    }
}
