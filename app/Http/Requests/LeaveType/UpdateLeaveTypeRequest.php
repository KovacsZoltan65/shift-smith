<?php

declare(strict_types=1);

namespace App\Http\Requests\LeaveType;

use App\Models\LeaveType;
use App\Policies\LeaveTypePolicy;
use Illuminate\Validation\Rule;

class UpdateLeaveTypeRequest extends StoreLeaveTypeRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(LeaveTypePolicy::PERM_UPDATE, LeaveType::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category' => [
                'required',
                'string',
                Rule::exists('leave_categories', 'code')->where(fn ($query) => $query
                    ->where('company_id', $this->currentCompanyId())
                    ->where('active', true)
                    ->whereNull('deleted_at')),
            ],
            'affects_leave_balance' => ['required', 'boolean'],
            'requires_approval' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
        ];
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
