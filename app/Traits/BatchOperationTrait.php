<?php

namespace App\Traits;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

trait BatchOperationTrait
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

        $ids = $this->parseIds($request->ids);

        if ($ids === []) {
            return ApiResponse::error('参数错误');
        }

        $data = [
            'status' => (int) $request->status,
        ];

        // 仅当表存在 updated_by 字段时才写入
        if (Schema::hasColumn((new $model)->getTable(), 'updated_by')) {
            $data['updated_by'] = $request->user()->id;
        }

        $updated = $model::whereIn('id', $ids)->update($data);

        if ($updated === 0) {
            return ApiResponse::error('更新失败');
        }

        return ApiResponse::success([], '更新成功');
    }

    /**
     * 批量删除 (ids 支持逗号分隔)
     *
     * @param  callable|null  $expandIds  扩展待删除 id 的回调, 用于级联子级等场景
     */
    protected function batchDelete(Request $request, string $model, ?callable $expandIds = null)
    {
        $request->validate([
            'ids' => 'required|string',
        ], [
            'ids.required' => 'ID不能为空',
        ]);

        $ids = $this->parseIds($request->ids);

        if ($ids === []) {
            return ApiResponse::error('参数错误');
        }

        if ($expandIds !== null) {
            $ids = array_values(array_unique($expandIds($ids)));
        }

        $model::whereIn('id', $ids)->delete();

        return ApiResponse::success([], '删除成功');
    }
}
