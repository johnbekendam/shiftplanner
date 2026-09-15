<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();
        $employeeIds = DB::table('employees')->pluck('id');
        $shiftIds = DB::table('shifts')->pluck('id');

        foreach ($employeeIds as $employeeId) {
            foreach ($shiftIds as $shiftId) {
                foreach (range(1, 5) as $weekday) {
                    DB::table('recurring_availabilities')->insertOrIgnore([
                        'employee_id' => $employeeId,
                        'weekday' => $weekday,
                        'shift_id' => $shiftId,
                        'level' => 'available',
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Backfilled rows cannot be distinguished from later explicit values.
    }
};
