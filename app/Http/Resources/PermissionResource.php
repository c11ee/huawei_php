<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->name,
            'name' => $this->label,
            'path' => $this->path,
            'icon' => $this->icon,
            'type' => $this->type,
            'sort' => $this->sort,
            'remark' => $this->remark,
            'parent_id' => $this->parent_id,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'created_at_ts' => $this->created_at?->timestamp ?? 0,
            'updated_at_ts' => $this->updated_at?->timestamp ?? 0,
        ];
    }
}
