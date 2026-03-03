<?php

declare(strict_types=1);

namespace App\Http\Requests\LeaveCategory;

use App\Models\LeaveCategory;
use App\Policies\LeaveCategoryPolicy;

class UpdateLeaveCategoryRequest extends StoreLeaveCategoryRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(LeaveCategoryPolicy::PERM_UPDATE, LeaveCategory::class) ?? false;
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
