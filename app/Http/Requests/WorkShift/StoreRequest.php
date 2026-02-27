<?php

namespace App\Http\Requests\WorkShift;

use App\Models\WorkShift;
use App\Policies\WorkShiftPolicy;
use App\Support\WorkShiftTimeCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(WorkShiftPolicy::PERM_CREATE, WorkShift::class) ?? false;
    }
    
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'breaks' => ['nullable', 'array'],
            'breaks.*.break_start_time' => ['required_with:breaks.*.break_end_time', 'date_format:H:i'],
            'breaks.*.break_end_time' => ['required_with:breaks.*.break_start_time', 'date_format:H:i'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $breaks = $this->input('breaks', []);
        if (! is_array($breaks)) {
            $breaks = [];
        }

        $this->merge([
            'start_time' => $this->normalizeTime($this->input('start_time')),
            'end_time' => $this->normalizeTime($this->input('end_time')),
            'breaks' => array_values(array_map(function (mixed $row): array {
                if (! is_array($row)) {
                    return [
                        'break_start_time' => null,
                        'break_end_time' => null,
                    ];
                }

                return [
                    'break_start_time' => $this->normalizeTime($row['break_start_time'] ?? null),
                    'break_end_time' => $this->normalizeTime($row['break_end_time'] ?? null),
                ];
            }, $breaks)),
            'active' => $this->has('active') ? $this->boolean('active') : true,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $start = $this->input('start_time');
            $end = $this->input('end_time');

            if (! is_string($start) || ! is_string($end)) {
                return;
            }

            try {
                $window = WorkShiftTimeCalculator::shiftWindow($start, $end);
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('end_time', 'A műszak vége nem lehet azonos a kezdéssel.');
                return;
            }

            /** @var array<int, array{break_start_time:string,break_end_time:string}> $breaks */
            $breaks = $this->input('breaks', []);
            if (app()->environment('local')) {
                $firstBreak = is_array($breaks[0] ?? null) ? $breaks[0] : null;
                Log::debug('work_shift.breaks.validation.store', [
                    'start_time' => $start,
                    'end_time' => $end,
                    'first_break' => $firstBreak,
                    'breaks_count' => count($breaks),
                    'shift_window_minutes' => [
                        'start' => $window['start'],
                        'end' => $window['end'],
                        'duration' => $window['duration'],
                    ],
                    'is_overnight' => WorkShiftTimeCalculator::parseToMinutes($end) < WorkShiftTimeCalculator::parseToMinutes($start),
                ]);
            }

            try {
                WorkShiftTimeCalculator::calculateBreaks($breaks, $start, $end);
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add(
                    'breaks',
                    'A szünetek érvénytelenek. A szünetnek a műszak intervallumán belül kell lennie '
                    .'(a műszak vége nem része az intervallumnak). Részlet: '.$exception->getMessage()
                );
            }
        });
    }

    private function normalizeTime(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (strlen($trimmed) === 8) {
            return substr($trimmed, 0, 5);
        }

        return $trimmed;
    }
}
