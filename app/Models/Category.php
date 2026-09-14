<?php

namespace App\Models;

class Category extends BaseModel
{
    protected $table = 'category';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_name',
        'parent_id',
        'sort',
        'status',
        'icon',
    ];

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id', 'id')
            ->orderBy('sort')
            ->with('children');
    }
}
