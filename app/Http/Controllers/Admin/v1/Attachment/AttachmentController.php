<?php

namespace App\Http\Controllers\Admin\v1\Attachment;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\v1\Traits\RecycleFolderTrait;
use App\Http\Responses\ApiResponse;
use App\Models\Attachment\Attachment;
use App\Models\Attachment\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    use RecycleFolderTrait;


    /**
     * 获取文件列表
     */
    public function index(Request $request)
    {
        $folderId = $request->input('folder_id', 0);
        $keyword = $request->input('keyword', '');
        // 仅筛选图片（mime_type 为 image/*）
        $onlyImage = (bool) $request->input('only_image', 0);

        // 回收站：folder_id = -1 时仅展示 recycle = 1 的数据
        $isRecycleBin = (int) $folderId === -1;

        // 获取当前目录下的子文件夹
        $folders = Folder::when(
            $isRecycleBin,
            // 回收站根视图：文件夹本身被回收，且其父文件夹未被回收（或被直接回收在根目录）才在此展示
            fn($query) => $query->where('recycle', 1)->whereNotIn('parent_id', Folder::where('recycle', 1)->pluck('id')),
            fn($query) => $query->where('recycle', 0)->where('parent_id', $folderId)
        )
            ->orderBy('sort')
            ->get()
            ->each(fn($folder) => $folder->type = 'folder');

        // 获取当前目录下的附件
        $attachments = Attachment::when(
            $isRecycleBin,
            // 回收站根视图：附件本身被回收，且其所在文件夹未被回收（或被直接回收在根目录）才在此展示
            fn($query) => $query->where('recycle', 1)->whereNotIn('folder_id', Folder::where('recycle', 1)->pluck('id')),
            fn($query) => $query->where('recycle', 0)->where('folder_id', $folderId)
        )
            ->when($keyword, fn($query) => $query->where('original_name', 'like', "%{$keyword}%"))
            ->when($onlyImage, fn($query) => $query->where('mime_type', 'like', 'image/%'))
            ->get()
            ->each(fn($file) => $file->type = 'file');

        // 合并并按 sort/id 排序（文件夹在前）
        $items = $folders->concat($attachments)->sortByDesc('type')->values();

        return ApiResponse::success($items);
    }

    /**
     * 上传文件
     */
    public function upload(Request $request)
    {
        // 验证参数
        $request->validate([
            'folder_id' => 'required|integer',
            'file'      => 'required|file|max:51200', // 限制最大 50MB
            'user_id'   => 'required|integer',
        ]);

        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $file = $request->file('file');

            // 本地目录结构
            $localFolder = 'attachments/' . date('Y/m');

            // 存储本地 'public' 目录下
            $filePath = $file->store($localFolder, 'public');

            if (!$filePath) {
                return ApiResponse::error('文件上传失败');
            }

            // 完整访问路径
            // $fileUrl = Storage::url($filePath);
            $fileUrl = asset('storage/' . $filePath);

            // 保存到数据库
            $attachment = Attachment::create([
                'user_id'       => $request->input('user_id', 0),
                'folder_id'     => $request->input('folder_id', 0),
                'original_name' => $file->getClientOriginalName(),
                'file_path'     => $filePath,
                'file_url'      => $fileUrl,
                'extension'     => $file->getClientOriginalExtension(),
                'file_size'     => $file->getSize(),
                'mime_type'     => $file->getClientMimeType(),
                'recycle'       => 0,
            ]);

            return ApiResponse::success($attachment, '上传成功');
        }

        return ApiResponse::error('无效的文件');
    }

    /**
     * 删除文件
     * @description
     * 接收文件,附件id(支持逗号分隔批量删除), 另一张表 folder_id(文件夹id) 可同时传入
     * 使用 recycle 字段判断是否删除还是移到回收站
     */
    public function destroy(Request $request)
    {
        $attachment_ids = $this->parseIds($request->input('attachment_ids', ''));
        $folderIds = $this->parseIds($request->input('folder_ids', ''));
        // recycle = 1 移到回收站, recycle = 0 永久删除
        $isRecycle = (bool) $request->input('recycle', 1);

        if ($attachment_ids === [] && $folderIds === []) {
            return ApiResponse::error('参数错误');
        }

        if ($isRecycle) {
            // 附件移入回收站(保留记录和物理文件)
            if ($attachment_ids !== []) {
                Attachment::whereIn('id', $attachment_ids)->update(['recycle' => 1]);
            }
            // 文件夹及其内部文件移入回收站
            if ($folderIds !== []) {
                $this->moveFoldersToRecycle($folderIds);
            }

            return ApiResponse::success([], '已移入回收站');
        }

        // 永久删除附件:先删物理文件,再删数据库记录
        if ($attachment_ids !== []) {
            $attachments = Attachment::whereIn('id', $attachment_ids)->get();
            foreach ($attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }
            Attachment::destroy($attachment_ids);
        }
        // 永久删除文件夹及其内部文件
        if ($folderIds !== []) {
            $this->permanentlyDeleteFolders($folderIds);
        }

        return ApiResponse::success([], '删除成功');
    }

    /**
     * 从回收站还原附件/文件夹
     * @description
     * 接收附件id(支持逗号分隔批量还原), 文件夹id(支持逗号分隔批量还原)
     * 将附件/文件夹及其内部文件的 recycle 改为 0
     */
    public function restore(Request $request)
    {
        $attachmentIds = $this->parseIds($request->input('attachment_ids', ''));
        $folderIds = $this->parseIds($request->input('folder_ids', ''));

        if ($attachmentIds === [] && $folderIds === []) {
            return ApiResponse::error('参数错误');
        }

        // 还原附件
        if ($attachmentIds !== []) {
            Attachment::whereIn('id', $attachmentIds)->update(['recycle' => 0]);
        }
        // 还原文件夹及其内部文件
        if ($folderIds !== []) {
            $this->restoreFolders($folderIds);
        }

        return ApiResponse::success([], '还原成功');
    }
}
