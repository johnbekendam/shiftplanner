<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LoginLinkController;
use App\Http\Controllers\BusinessLineController;
use App\Http\Controllers\CompetenceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EligibleEmployeeController;
use App\Http\Controllers\EmployeeAuditController;
use App\Http\Controllers\EmployeeBackupController;
use App\Http\Controllers\EmployeeCompetenceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeHolidayController;
use App\Http\Controllers\EmployeeQuestionController;
use App\Http\Controllers\EmployeeWorkcenterController;
use App\Http\Controllers\LivePlanningController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\PersonalCompetenceController;
use App\Http\Controllers\PersonalHolidayController;
use App\Http\Controllers\PersonalLinkController;
use App\Http\Controllers\PersonalPageController;
use App\Http\Controllers\PersonalQuestionController;
use App\Http\Controllers\PersonalRecurringAvailabilityController;
use App\Http\Controllers\PlanGenerationController;
use App\Http\Controllers\PlannerOpenWeekController;
use App\Http\Controllers\PlanningRuleController;
use App\Http\Controllers\PlanNotificationController;
use App\Http\Controllers\PublishedWeekController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\RecurringAvailabilityController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleSpotController;
use App\Http\Controllers\SchedulingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShiftAssignmentController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SignupController;
use App\Http\Controllers\ThemeBuilderController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkcenterController;
use App\Http\Controllers\WorkcenterShiftAssignmentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/login', [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1');
Route::post('/login/link', [LoginLinkController::class, 'request'])->middleware('throttle:login-link')->name('login.link.request');
Route::get('/login/link/{token}', [LoginLinkController::class, 'show'])->name('login.link.show');
Route::post('/login/link/{token}', [LoginLinkController::class, 'confirm'])->name('login.link.confirm');
Route::post('/login/link/{token}/skip', [LoginLinkController::class, 'skip'])->name('login.link.skip');
Route::post('/personal-link', [PersonalLinkController::class, 'request'])->middleware('throttle:5,1')->name('personal-link.request');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Public employee self-signup — features/employee-self-signup/.
Route::get('/signup', [SignupController::class, 'show'])->name('signup.show');
Route::post('/signup', [SignupController::class, 'store'])->middleware('throttle:5,1')->name('signup.store');

// Workcenter wall screen — token-only, no auth. See features/workcenter-live-planning/.
Route::get('/live/{token}', [LivePlanningController::class, 'show'])->name('live.show');

Route::middleware('auth')->group(function () {
    // Admin-only: everything except the employee list/editor.
    Route::middleware('admin')->group(function () {
        // Theme builder now renders inside the app chrome (AppLayout sidebar).
        Route::get('/theme-builder', [ThemeBuilderController::class, 'index'])->name('theme-builder.index');
        Route::post('/theme-builder/save', [ThemeBuilderController::class, 'save'])->name('theme-builder.save');
        Route::delete('/theme-builder/save', [ThemeBuilderController::class, 'reset'])->name('theme-builder.reset');
        Route::post('/theme-builder/logo', [ThemeBuilderController::class, 'uploadLogo'])->name('theme-builder.logo.upload');
        Route::delete('/theme-builder/logo', [ThemeBuilderController::class, 'deleteLogo'])->name('theme-builder.logo.delete');

        Route::get('/employee-backup', [EmployeeBackupController::class, 'index'])->name('employee-backup.index');
        Route::get('/employee-backup/export', [EmployeeBackupController::class, 'export'])->name('employee-backup.export');
        Route::post('/employee-backup/import', [EmployeeBackupController::class, 'import'])->name('employee-backup.import');
        Route::get('/employee-audit', [EmployeeAuditController::class, 'index'])->name('employee-audit.index');
        Route::post('/employees/{employee}/restore', [EmployeeController::class, 'restore'])->name('employees.restore');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/competences', [CompetenceController::class, 'store'])->name('settings.competences.store');
        Route::put('/settings/competences/reorder', [CompetenceController::class, 'reorder'])->name('settings.competences.reorder');
        Route::put('/settings/competences/{competence}', [CompetenceController::class, 'update'])->name('settings.competences.update');
        Route::delete('/settings/competences/{competence}', [CompetenceController::class, 'destroy'])->name('settings.competences.destroy');
        Route::post('/settings/business-lines', [BusinessLineController::class, 'store'])->name('settings.business-lines.store');
        Route::put('/settings/business-lines/reorder', [BusinessLineController::class, 'reorder'])->name('settings.business-lines.reorder');
        Route::put('/settings/business-lines/{businessLine}', [BusinessLineController::class, 'update'])->name('settings.business-lines.update');
        Route::delete('/settings/business-lines/{businessLine}', [BusinessLineController::class, 'destroy'])->name('settings.business-lines.destroy');
        Route::post('/settings/shifts', [ShiftController::class, 'store'])->name('settings.shifts.store');
        Route::put('/settings/shifts/note', [ShiftController::class, 'updateNote'])->name('settings.shifts.note');
        Route::put('/settings/shifts/schedule-note', [ShiftController::class, 'updateScheduleNote'])->name('settings.shifts.schedule-note');
        Route::put('/settings/shifts/{shift}', [ShiftController::class, 'update'])->name('settings.shifts.update');
        Route::delete('/settings/shifts/{shift}', [ShiftController::class, 'destroy'])->name('settings.shifts.destroy');
        Route::post('/settings/questions', [QuestionController::class, 'store'])->name('settings.questions.store');
        Route::put('/settings/questions/reorder', [QuestionController::class, 'reorder'])->name('settings.questions.reorder');
        Route::put('/settings/questions/{question}', [QuestionController::class, 'update'])->name('settings.questions.update');
        Route::delete('/settings/questions/{question}', [QuestionController::class, 'destroy'])->name('settings.questions.destroy');
        Route::put('/settings/period', [PeriodController::class, 'update'])->name('settings.period.update');
        Route::post('/settings/workcenters', [WorkcenterController::class, 'store'])->name('settings.workcenters.store');
        Route::put('/settings/workcenters/reorder', [WorkcenterController::class, 'reorder'])->name('settings.workcenters.reorder');
        Route::put('/settings/workcenters/{workcenter}', [WorkcenterController::class, 'update'])->name('settings.workcenters.update');
        Route::post('/settings/workcenters/{workcenter}/live-token', [WorkcenterController::class, 'regenerateLiveToken'])->name('settings.workcenters.live-token');
        Route::delete('/settings/workcenters/{workcenter}', [WorkcenterController::class, 'destroy'])->name('settings.workcenters.destroy');

        Route::get('/planning-rules', [PlanningRuleController::class, 'index'])->name('planning-rules.index');
        Route::post('/planning-rules', [PlanningRuleController::class, 'store'])->name('planning-rules.store');
        Route::put('/planning-rules/{planningRule}', [PlanningRuleController::class, 'update'])->name('planning-rules.update');
        Route::delete('/planning-rules/{planningRule}', [PlanningRuleController::class, 'destroy'])->name('planning-rules.destroy');

        Route::get('/schedule', [WorkcenterShiftAssignmentController::class, 'index'])->name('schedule.index');
        Route::post('/schedule', [WorkcenterShiftAssignmentController::class, 'store'])->name('schedule.store');
        Route::put('/schedule/{workcenter}/{shift}', [WorkcenterShiftAssignmentController::class, 'update'])->name('schedule.update');
        Route::delete('/schedule/{workcenter}/{shift}', [WorkcenterShiftAssignmentController::class, 'destroy'])->name('schedule.destroy');

        Route::get('/planning', [SchedulingController::class, 'index'])->name('planning.index');
        Route::post('/planning/assignments', [ShiftAssignmentController::class, 'store'])->name('planning.assignments.store');
        Route::put('/planning/assignments/{shiftAssignment}', [ShiftAssignmentController::class, 'updateFixed'])->name('planning.assignments.update');
        Route::delete('/planning/assignments/{shiftAssignment}', [ShiftAssignmentController::class, 'destroy'])->name('planning.assignments.destroy');
        Route::put('/planning/spots/{workcenter}/{shift}/{date}', [ScheduleSpotController::class, 'update'])
            ->where('date', '\d{4}-\d{2}-\d{2}')->name('planning.spots.update');
        Route::delete('/planning/spots/{workcenter}/{shift}/{date}', [ScheduleSpotController::class, 'destroy'])
            ->where('date', '\d{4}-\d{2}-\d{2}')->name('planning.spots.destroy');
        Route::get('/planning/eligible-employees', [EligibleEmployeeController::class, 'index'])->name('planning.eligible-employees');
        Route::post('/planning/weeks/{weekStart}/workcenters/{workcenter}/publish', [PublishedWeekController::class, 'store'])
            ->where('weekStart', '\d{4}-\d{2}-\d{2}')->name('planning.weeks.workcenters.publish');
        Route::delete('/planning/weeks/{weekStart}/workcenters/{workcenter}/publish', [PublishedWeekController::class, 'destroy'])
            ->where('weekStart', '\d{4}-\d{2}-\d{2}')->name('planning.weeks.workcenters.unpublish');
        Route::put('/planning/weeks/{weekStart}/workcenters/{workcenter}/planner-open', [PlannerOpenWeekController::class, 'update'])
            ->where('weekStart', '\d{4}-\d{2}-\d{2}')->name('planning.weeks.workcenters.planner-open');
        Route::post('/planning/generate', [PlanGenerationController::class, 'store'])->name('planning.generate');
        Route::post('/planning/send', [PlanNotificationController::class, 'store'])->name('planning.send');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/planned-hours/export', [ReportController::class, 'exportPlannedHours'])->name('reports.planned-hours.export');

        Route::get('/mailbox', [MailboxController::class, 'index'])->name('mailbox.index');
        Route::post('/mailbox/compose', [MailboxController::class, 'store'])->name('mailbox.compose');
        Route::post('/mailbox/compose/preview', [MailboxController::class, 'preview'])->name('mailbox.preview');
        Route::put('/mailbox/templates/{type}', [MailboxController::class, 'updateTemplate'])->name('mailbox.templates.update');
        Route::post('/mailbox/{message}/send', [MailboxController::class, 'send'])->name('mailbox.send');
        Route::post('/mailbox/bulk-send', [MailboxController::class, 'bulkSend'])->name('mailbox.bulk-send');
        Route::post('/mailbox/bulk-delete', [MailboxController::class, 'bulkDelete'])->name('mailbox.bulk-delete');
        Route::delete('/mailbox/{message}', [MailboxController::class, 'destroy'])->name('mailbox.destroy');

        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/resend-invite', [UserController::class, 'resendInvite'])->name('users.resend-invite');
    }); // end admin group

    // Read-only for a manager; the admin group above covers create/edit/resend.
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/account/employee', [AccountController::class, 'linkEmployee'])->name('account.employee');
    Route::put('/account/business-line', [AccountController::class, 'updateBusinessLine'])->name('account.business-line');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::post('/employees/bulk-delete', [EmployeeController::class, 'bulkDelete'])->name('employees.bulk-delete');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->middleware('employee.active')->name('employees.update');
    Route::put('/employees/{employee}/confirmed', [EmployeeController::class, 'updateConfirmed'])->middleware('employee.active')->name('employees.confirmed.update');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::get('/employees/{employee}/personal-page', [EmployeeController::class, 'personalPage'])->middleware('employee.active')->name('employees.personal-page');
    // Queues the personal-page-link message straight to the outbox — no Compose UI.
    Route::post('/employees/{employee}/send-link', [EmployeeController::class, 'sendLink'])->middleware('employee.active')->name('employees.send-link');
    Route::post('/employees/{employee}/holidays', [EmployeeHolidayController::class, 'store'])->middleware('employee.active')->name('employees.holidays.store');
    Route::delete('/employees/{employee}/holidays/{holiday}', [EmployeeHolidayController::class, 'destroy'])->middleware('employee.active')->name('employees.holidays.destroy');
    Route::put('/employees/{employee}/availability/{weekday}/{shift}', [RecurringAvailabilityController::class, 'update'])
        ->where(['weekday' => '[1-5]', 'shift' => '[0-9]+'])
        ->middleware('employee.active')->name('employees.availability.update');
    Route::put('/employees/{employee}/competences/{competence}', [EmployeeCompetenceController::class, 'update'])->middleware('employee.active')->name('employees.competences.update');
    Route::delete('/employees/{employee}/competences/{competence}', [EmployeeCompetenceController::class, 'destroy'])->middleware('employee.active')->name('employees.competences.destroy');
    Route::put('/employees/{employee}/workcenters/{workcenter}', [EmployeeWorkcenterController::class, 'update'])->middleware('employee.active')->name('employees.workcenters.update');
    Route::delete('/employees/{employee}/workcenters/{workcenter}', [EmployeeWorkcenterController::class, 'destroy'])->middleware('employee.active')->name('employees.workcenters.destroy');
    Route::put('/employees/{employee}/questions/{question}', [EmployeeQuestionController::class, 'update'])->middleware('employee.active')->name('employees.questions.update');
});

// Employee personal page — token-only, no auth. Prototype preview links.
// See doc/features/employee-admin/spec.md and roadmap phase 2.
Route::get('/personal/{token}', [PersonalPageController::class, 'show'])->name('personal.show');

// Employee-side writes: blocked when a manager turns off
// `allow_employee_changes` (features/employee-change-lock/). The show route
// above is deliberately outside this group so the page stays viewable.
Route::middleware(['employee.changes', 'employee.active'])->group(function () {
    Route::put('/personal/{token}', [PersonalPageController::class, 'update'])->name('personal.update');
    Route::post('/personal/{token}/holidays', [PersonalHolidayController::class, 'store'])->name('personal.holidays.store');
    Route::delete('/personal/{token}/holidays/{holiday}', [PersonalHolidayController::class, 'destroy'])->name('personal.holidays.destroy');
    Route::put('/personal/{token}/availability/{weekday}/{shift}', [PersonalRecurringAvailabilityController::class, 'update'])
        ->where(['weekday' => '[1-5]', 'shift' => '[0-9]+'])
        ->name('personal.availability.update');
    Route::put('/personal/{token}/competences/{competence}', [PersonalCompetenceController::class, 'update'])->name('personal.competences.update');
    Route::delete('/personal/{token}/competences/{competence}', [PersonalCompetenceController::class, 'destroy'])->name('personal.competences.destroy');
    Route::put('/personal/{token}/questions/{question}', [PersonalQuestionController::class, 'update'])->name('personal.questions.update');
});
