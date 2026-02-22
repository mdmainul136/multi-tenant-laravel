<?php

namespace App\Modules\HRM\DTOs;

class StaffDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $employee_id = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?int $department_id = null,
        public readonly ?string $designation = null,
        public readonly ?string $role = null,
        public readonly float $salary = 0.0,
        public readonly string $salary_type = 'monthly',
        public readonly ?string $hire_date = null,
        public readonly ?string $end_date = null,
        public readonly string $status = 'active',
        public readonly ?string $address = null,
        public readonly ?string $emergency_contact = null,
        public readonly ?string $notes = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            employee_id: $data['employee_id'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            department_id: isset($data['department_id']) ? (int) $data['department_id'] : null,
            designation: $data['designation'] ?? null,
            role: $data['role'] ?? 'staff',
            salary: isset($data['salary']) ? (float) $data['salary'] : 0.0,
            salary_type: $data['salary_type'] ?? 'monthly',
            hire_date: $data['hire_date'] ?? null,
            end_date: $data['end_date'] ?? null,
            status: $data['status'] ?? 'active',
            address: $data['address'] ?? null,
            emergency_contact: $data['emergency_contact'] ?? null,
            notes: $data['notes'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'employee_id' => $this->employee_id,
            'email' => $this->email,
            'phone' => $this->phone,
            'department_id' => $this->department_id,
            'designation' => $this->designation,
            'role' => $this->role,
            'salary' => $this->salary,
            'salary_type' => $this->salary_type,
            'hire_date' => $this->hire_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'address' => $this->address,
            'emergency_contact' => $this->emergency_contact,
            'notes' => $this->notes,
        ];
    }
}
