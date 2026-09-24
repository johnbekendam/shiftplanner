<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_audit_events', function (Blueprint $table) {
            $table->string('employee_name')->nullable()->after('employee_id');
            $table->string('employee_email')->nullable()->after('employee_name');
        });

        DB::table('employee_audit_events')
            ->orderBy('id')
            ->chunkById(100, function ($events): void {
                $employees = DB::table('employees')
                    ->whereIn('id', $events->pluck('employee_id')->unique())
                    ->get(['id', 'first_name', 'last_name', 'email'])
                    ->keyBy('id');

                foreach ($events as $event) {
                    $employee = $employees->get($event->employee_id);
                    if ($employee === null) {
                        continue;
                    }

                    DB::table('employee_audit_events')->where('id', $event->id)->update([
                        'employee_name' => trim("{$employee->first_name} {$employee->last_name}"),
                        'employee_email' => $employee->email,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('employee_audit_events', function (Blueprint $table) {
            $table->dropColumn(['employee_name', 'employee_email']);
        });
    }
};
