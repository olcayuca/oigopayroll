<?php

namespace App\Actions\Employees;

use App\Enums\AuditEvent;
use App\Enums\CodeList;
use App\Models\Employee;
use App\Models\PayrollCode;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * İşten çıkış: last working day and SGK işten ayrılış kodu (Admin → Bordro Kodları → İşten Çıkış Kodları).
 * The record stays (status "İşten ayrıldı") for payroll history; a mistaken exit can be undone.
 * Authorization is the caller's job (EmployeePolicy::update).
 */
class TerminateEmployee
{
    /**
     * @param  array<string, mixed>  $input  termination_date (Y-m-d), termination_code, termination_note
     */
    public function terminate(Employee $employee, array $input): Employee
    {
        if ($employee->status === Employee::LEFT) {
            throw ValidationException::withMessages(['termination_date' => 'Personelin işten çıkışı zaten yapılmış.']);
        }

        $data = Validator::make($input, [
            'termination_date' => ['required', 'date', 'after_or_equal:'.$employee->hire_date->toDateString()],
            'termination_code' => ['required', Rule::in(self::codes())],
            'termination_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'termination_date.after_or_equal' => 'Çıkış tarihi işe giriş tarihinden ('.$employee->hire_date->format('d.m.Y').') önce olamaz.',
        ], [
            'termination_date' => 'Çıkış tarihi', 'termination_code' => 'İşten çıkış kodu', 'termination_note' => 'Not',
        ])->validate();

        $employee->update([...$data, 'status' => Employee::LEFT, 'termination_note' => filled($data['termination_note'] ?? null) ? $data['termination_note'] : null]);

        $reason = PayrollCode::query()->where('list', CodeList::TerminationReasons)->where('code', $data['termination_code'])->value('name');
        Audit::log(AuditEvent::EmployeeTerminated, "İşten çıkış: {$employee->registry_no} {$employee->fullName()} · ".Carbon::parse($data['termination_date'])->format('d.m.Y')
            ." · kod {$data['termination_code']} {$reason}", $employee, [
                'termination_date' => $data['termination_date'], 'termination_code' => $data['termination_code'],
            ]);

        return $employee;
    }

    /**
     * Undo a mistaken exit: back to active, exit data cleared (kept in the audit log).
     */
    public function cancel(Employee $employee): Employee
    {
        if ($employee->status !== Employee::LEFT) {
            throw ValidationException::withMessages(['termination_date' => 'Personelin işten çıkışı yapılmamış.']);
        }

        $previous = $employee->termination_date?->format('d.m.Y');
        $employee->update(['status' => Employee::ACTIVE, 'termination_date' => null, 'termination_code' => null, 'termination_note' => null]);

        Audit::log(AuditEvent::EmployeeReinstated, "İşten çıkış geri alındı: {$employee->registry_no} {$employee->fullName()} (çıkış tarihi {$previous})", $employee);

        return $employee;
    }

    /**
     * Active SGK işten ayrılış kodları.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_values(array_map('strval', PayrollCode::options(CodeList::TerminationReasons)->pluck('code')->all()));
    }
}
