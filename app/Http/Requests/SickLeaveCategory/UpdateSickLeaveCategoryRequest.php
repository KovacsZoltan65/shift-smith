<?php

declare(strict_types=1);

namespace App\Http\Requests\SickLeaveCategory;

use App\Models\SickLeaveCategory;
use App\Policies\SickLeaveCategoryPolicy;

class UpdateSickLeaveCategoryRequest extends StoreSickLeaveCategoryRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(SickLeaveCategoryPolicy::PERM_UPDATE, SickLeaveCategory::class) ?? false;
    }

    public function validatedPayload(): array
    {
        $payload = $this->validated();

        if ($this->has('code')) {
            $payload['code'] = $this->input('code');
        }

        return $payload;
    }
}
