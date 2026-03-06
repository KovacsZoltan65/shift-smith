<?php

declare(strict_types=1);

namespace App\Http\Requests\OrgHierarchy;

use App\Policies\OrgHierarchyPolicy;

final class MoveRequest extends MovePreviewRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(OrgHierarchyPolicy::PERM_UPDATE) ?? false;
    }
}
