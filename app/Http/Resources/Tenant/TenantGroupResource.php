<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TenantGroup
 */
final class TenantGroupResource extends JsonResource
{
    /**
     * @return array{
     *   id:int,
     *   name:string,
     *   code:string,
     *   slug:string,
     *   status:?string,
     *   active:bool,
     *   notes:?string,
     *   database_name:?string,
     *   created_at:?string,
     *   updated_at:?string,
     *   deleted_at:?string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'code' => (string) $this->code,
            'slug' => (string) $this->slug,
            'status' => $this->status !== null ? (string) $this->status : null,
            'active' => (bool) $this->active,
            'notes' => $this->notes !== null ? (string) $this->notes : null,
            'database_name' => $this->database_name !== null ? (string) $this->database_name : null,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),
        ];
    }
}
