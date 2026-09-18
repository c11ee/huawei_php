<?php

namespace App\Models\Attachment;

use App\Models\BaseModel;

class Folder extends BaseModel
{
    protected $table = 'attachment_folders';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'parent_id',
        'sort',
        'recycle',
    ];

    public function children()
    {
        return $this->hasMany(Folder::class, 'parent_id', 'id')->where('recycle', 0)->with('children');
    }
}
