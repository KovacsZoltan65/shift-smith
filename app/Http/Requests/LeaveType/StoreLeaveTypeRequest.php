<?php

declare(strict_types=1);

namespace App\Http\Requests\LeaveType;

use App\Http\Requests\LeaveType\Concerns\ResolvesCurrentCompany;
use App\Models\LeaveType;
use App\Policies\LeaveTypePolicy;
use Illuminate\Foundation\Http\FormRequest;
class StoreLeaveTypeRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(LeaveTypePolicy::PERM_CREATE, LeaveType::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'in:'.implode(',', LeaveType::getCategories())],
            'affects_leave_balance' => ['required', 'boolean'],
            'requires_approval' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function validatedPayload(): array
    {
        return $this->validated();
    }
}
