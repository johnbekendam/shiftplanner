<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginCodeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BusinessLineController;
use App\Http\Controllers\CompetenceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeCompetenceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeHolidayController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\EmployeeQuestionController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\PersonalCompetenceController;
use App\Http\Controllers\PersonalHolidayController;
use App\Http\Controllers\PersonalPageController;
use App\Http\Controllers\PersonalQuestionController;
use App\Http\Controllers\PersonalRecurringAvailabilityController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\RecurringAvailabilityController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SignupController;
use App\Http\Controllers\ThemeBuilderController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/login', [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1');
Route::post('/login/code', [LoginCodeController::class, 'request'])->middleware('throttle:login-code')->name('login.code.request');
Route::post('/login/code/verify', [LoginCodeController::class, 'verify'])->middleware('throttle:login-code')->name('login.code.verify');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Public employee self-signup — features/employee-self-signup/.
Route::get('/signup', [SignupController::class, 'show'])->name('signup.show');
Route::post('/signup', [SignupController::class, 'store'])->middleware('throttle:5,1')->name('signup.store');

Route::middleware('auth')->group(function () {
    // Admin-only: everything except the employee list/editor.
    Route::middleware('admin')->group(function () {
        // Theme builder now renders inside the app chrome (AppLayout sidebar).
        Route::get('/theme-builder', [ThemeBuilderController::class, 'index'])->name('theme-builder.index');
        Route::post('/theme-builder/save', [ThemeBuilderController::class, 'save'])->name('theme-builder.save');
        Route::delete('/theme-builder/save', [ThemeBuilderController::class, 'reset'])->name('theme-builder.reset');
        Route::post('/theme-builder/logo', [ThemeBuilderController::class, 'uploadLogo'])->name('theme-builder.logo.upload');
        Route::delete('/theme-builder/logo', [ThemeBuilderController::class, 'deleteLogo'])->name('theme-builder.logo.delete');

        Route::get('/import', [EmployeeImportController::class, 'index'])->name('import.index');
        Route::post('/import', [EmployeeImportController::class, 'store'])->name('import.store');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/competences', [CompetenceController::class, 'store'])->name('settings.competences.store');
        Route::put('/settings/competences/{competence}/move', [CompetenceController::class, 'move'])->name('settings.competences.move');
        Route::put('/settings/competences/{competence}', [CompetenceController::class, 'update'])->name('settings.competences.update');
        Route::delete('/settings/competences/{competence}', [CompetenceController::class, 'destroy'])->name('settings.competences.destroy');
        Route::post('/settings/business-lines', [BusinessLineController::class, 'store'])->name('settings.business-lines.store');
        Route::put('/settings/business-lines/{businessLine}/move', [BusinessLineController::class, 'move'])->name('settings.business-lines.move');
        Route::put('/settings/business-lines/{businessLine}', [BusinessLineController::class, 'update'])->name('settings.business-lines.update');
        Route::delete('/settings/business-lines/{businessLine}', [BusinessLineController::class, 'destroy'])->name('settings.business-lines.destroy');
        Route::post('/settings/shifts', [ShiftController::class, 'store'])->name('settings.shifts.store');
        Route::put('/settings/shifts/note', [ShiftController::class, 'updateNote'])->name('settings.shifts.note');
        Route::put('/settings/shifts/{shift}', [ShiftController::class, 'update'])->name('settings.shifts.update');
        Route::delete('/settings/shifts/{shift}', [ShiftController::class, 'destroy'])->name('settings.shifts.destroy');
        Route::post('/settings/questions', [QuestionController::class, 'store'])->name('settings.questions.store');
        Route::put('/settings/questions/{question}/move', [QuestionController::class, 'move'])->name('settings.questions.move');
        Route::put('/settings/questions/{question}', [QuestionController::class, 'update'])->name('settings.questions.update');
        Route::delete('/settings/questions/{question}', [QuestionController::class, 'destroy'])->name('settings.questions.destroy');
        Route::put('/settings/period', [PeriodController::class, 'update'])->name('settings.period.update');

        Route::get('/mailbox', [MailboxController::class, 'index'])->name('mailbox.index');
        Route::post('/mailbox/compose', [MailboxController::class, 'store'])->name('mailbox.compose');
        Route::post('/mailbox/compose/preview', [MailboxController::class, 'preview'])->name('mailbox.preview');
        Route::put('/mailbox/templates/{type}', [MailboxController::class, 'updateTemplate'])->name('mailbox.templates.update');
        Route::post('/mailbox/{message}/send', [MailboxController::class, 'send'])->name('mailbox.send');
        Route::post('/mailbox/bulk-delete', [MailboxController::class, 'bulkDelete'])->name('mailbox.bulk-delete');
        Route::delete('/mailbox/{message}', [MailboxController::class, 'destroy'])->name('mailbox.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    }); // end admin group

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/account/employee', [AccountController::class, 'linkEmployee'])->name('account.employee');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::post('/employees/bulk-delete', [EmployeeController::class, 'bulkDelete'])->name('employees.bulk-delete');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::get('/employees/{employee}/personal-page', [EmployeeController::class, 'personalPage'])->name('employees.personal-page');
    Route::post('/employees/{employee}/holidays', [EmployeeHolidayController::class, 'store'])->name('employees.holidays.store');
    Route::delete('/employees/{employee}/holidays/{holiday}', [EmployeeHolidayController::class, 'destroy'])->name('employees.holidays.destroy');
    Route::put('/employees/{employee}/availability/{weekday}/{shift}', [RecurringAvailabilityController::class, 'update'])
        ->where(['weekday' => '[1-5]', 'shift' => '[0-9]+'])
        ->name('employees.availability.update');
    Route::put('/employees/{employee}/competences/{competence}', [EmployeeCompetenceController::class, 'update'])->name('employees.competences.update');
    Route::delete('/employees/{employee}/competences/{competence}', [EmployeeCompetenceController::class, 'destroy'])->name('employees.competences.destroy');
    Route::put('/employees/{employee}/questions/{question}', [EmployeeQuestionController::class, 'update'])->name('employees.questions.update');
});

// Employee personal page — token-only, no auth. Prototype preview links.
// See doc/features/employee-admin/spec.md and roadmap phase 2.
Route::get('/personal/{token}', [PersonalPageController::class, 'show'])->name('personal.show');

// Employee-side writes: blocked when a manager turns off
// `allow_employee_changes` (features/employee-change-lock/). The show route
// above is deliberately outside this group so the page stays viewable.
Route::middleware('employee.changes')->group(function () {
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
