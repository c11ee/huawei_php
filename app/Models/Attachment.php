<?php

namespace App\Models;


class Attachment extends BaseModel
{
    protected $table = 'attachments';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'folder_id',
        'original_name',
        'file_path',
        'file_url',
        'extension',
        'file_size',
        'mime_type',
        'recycle',
    ];
}
