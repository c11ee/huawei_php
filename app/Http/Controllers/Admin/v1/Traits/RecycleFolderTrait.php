<?php

namespace App\Http\Controllers\Admin\v1\Traits;

use App\Models\Attachment;
use App\Models\Folder;
use Illuminate\Support\Facades\Storage;

trait RecycleFolderTrait
{
    /**
     * 解析逗号分隔的 id 列表
     */
    protected function parseIds(?string $value): array
    {
        return array_values(array_filter(explode(',', (string) $value), fn($v) => $v !== ''));
    }

    /**
     * 获取文件夹及其所有子孙文件夹 id
     */
    protected function folderIdsWithDescendants(array $ids): array
    {
        $result = $ids;
        $current = $ids;

        while ($current !== []) {
            $children = Folder::whereIn('parent_id', $current)
                ->whereNotIn('id', $result)
                ->pluck('id')
                ->toArray();
            $result = array_merge($result, $children);
            $current = $children;
        }

        return $result;
    }

    /**
     * 将文件夹及其子孙文件夹、内部附件移入回收站
     */
    protected function moveFoldersToRecycle(array $folderIds): void
    {
        $allFolderIds = $this->folderIdsWithDescendants($folderIds);

        Folder::whereIn('id', $allFolderIds)->update(['recycle' => 1]);
        Attachment::whereIn('folder_id', $allFolderIds)->update(['recycle' => 1]);
    }

    /**
     * 将文件夹及其子孙文件夹、内部附件从回收站还原
     */
    protected function restoreFolders(array $folderIds): void
    {
        $allFolderIds = $this->folderIdsWithDescendants($folderIds);

        Folder::whereIn('id', $allFolderIds)->update(['recycle' => 0]);
        Attachment::whereIn('folder_id', $allFolderIds)->update(['recycle' => 0]);
    }

    /**
     * 永久删除文件夹及其子孙文件夹、内部附件(含物理文件)
     */
    protected function permanentlyDeleteFolders(array $folderIds): void
    {
        $allFolderIds = $this->folderIdsWithDescendants($folderIds);

        $attachments = Attachment::whereIn('folder_id', $allFolderIds)->get();
        foreach ($attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }
        Attachment::whereIn('folder_id', $allFolderIds)->delete();
        Folder::whereIn('id', $allFolderIds)->delete();
    }
}
