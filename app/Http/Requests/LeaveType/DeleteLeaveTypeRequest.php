<?php

declare(strict_types=1);

namespace App\Http\Requests\LeaveType;

use App\Http\Requests\LeaveType\Concerns\ResolvesCurrentCompany;
use App\Models\LeaveType;
use App\Policies\LeaveTypePolicy;
use Illuminate\Foundation\Http\FormRequest;

class DeleteLeaveTypeRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(LeaveTypePolicy::PERM_DELETE, LeaveType::class) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
