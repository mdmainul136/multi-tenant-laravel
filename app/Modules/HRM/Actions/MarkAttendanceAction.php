<?php

namespace App\Modules\HRM\Actions;

use App\Models\HRM\Attendance;

class MarkAttendanceAction
{
    public function execute(array $records): array
    {
        $results = [];
        foreach ($records as $record) {
            $attendance = Attendance::updateOrCreate(
                ['staff_id' => $record['staff_id'], 'date' => $record['date']],
                [
                    'status'     => $record['status'],
                    'check_in'   => $record['check_in'] ?? null,
                    'check_out'  => $record['check_out'] ?? null,
                    'note'       => $record['note'] ?? null,
                ]
            );
            
            if ($attendance->check_in && $attendance->check_out) {
                $attendance->update(['hours_worked' => $attendance->calculateHours()]);
            }
            $results[] = $attendance;
        }

        return $results;
    }
}
