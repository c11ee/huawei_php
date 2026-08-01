<?php

namespace App\Http\Controllers\Admin\v1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\v1\Traits\RecycleFolderTrait;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Folder;

class FolderController extends Controller
{
    use RecycleFolderTrait;

    /**
     * 获取文件夹树
     */
    public function tree()
    {
        $tree = Folder::where('parent_id', 0)->where('recycle', 0)->with('children')->orderBy('sort')->get();
        return ApiResponse::success($tree);
    }

    /** 
     * 创建文件夹
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:attachment_folders,name',
            'parent_id' => 'nullable|integer',
            'sort' => 'nullable|integer',
        ], [
            'name.required' => '文件夹名称不能为空',
            'name.unique' => '文件夹名称已存在',
            'parent_id.exists' => '父文件夹不存在',
            'sort.between' => '排序值必须在0到255之间',
        ]);

        $user = $request->user();
        $data = [
            'user_id' => $user->id,
            'name' => $request->name,
            'parent_id' => $request->parent_id ?? 0,
            'sort' => $request->sort ?? 0,
        ];
        $folder = Folder::create($data);
        return ApiResponse::success($folder, '新建成功');
    }

    /**
     * 删除文件夹
     * @description
     * 接收文件夹id(支持逗号分隔批量删除)
     * 使用 recycle 字段判断是否删除还是移到回收站
     */
    public function destroy(Request $request, string $id)
    {
        $ids = $this->parseIds($id);
        if ($ids === []) {
            return ApiResponse::error('参数错误');
        }

        // recycle = 1 移到回收站, recycle = 0 永久删除
        $isRecycle = (bool) $request->input('recycle', 1);

        if ($isRecycle) {
            // 文件夹及其子孙文件夹、内部附件移入回收站
            $this->moveFoldersToRecycle($ids);

            return ApiResponse::success([], '已移入回收站');
        }

        // 永久删除:文件夹及其子孙文件夹、内部附件(含物理文件)
        $this->permanentlyDeleteFolders($ids);

        return ApiResponse::success([], '删除成功');
    }
}
