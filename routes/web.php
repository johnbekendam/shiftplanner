<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompetenceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeHolidayController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\PersonalHolidayController;
use App\Http\Controllers\PersonalPageController;
use App\Http\Controllers\PersonalRecurringAvailabilityController;
use App\Http\Controllers\RecurringAvailabilityController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ThemeBuilderController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/employees');

Route::get('/login', [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware('auth')->group(function () {
    // Theme builder now renders inside the app chrome (AppLayout sidebar).
    Route::get('/theme-builder', [ThemeBuilderController::class, 'index'])->name('theme-builder.index');
    Route::post('/theme-builder/save', [ThemeBuilderController::class, 'save'])->name('theme-builder.save');
    Route::delete('/theme-builder/save', [ThemeBuilderController::class, 'reset'])->name('theme-builder.reset');
    Route::post('/theme-builder/logo', [ThemeBuilderController::class, 'uploadLogo'])->name('theme-builder.logo.upload');
    Route::delete('/theme-builder/logo', [ThemeBuilderController::class, 'deleteLogo'])->name('theme-builder.logo.delete');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/competences', [CompetenceController::class, 'store'])->name('settings.competences.store');
    Route::put('/settings/competences/{competence}/move', [CompetenceController::class, 'move'])->name('settings.competences.move');
    Route::put('/settings/competences/{competence}', [CompetenceController::class, 'update'])->name('settings.competences.update');
    Route::delete('/settings/competences/{competence}', [CompetenceController::class, 'destroy'])->name('settings.competences.destroy');

    Route::get('/mailbox', [MailboxController::class, 'index'])->name('mailbox.index');
    Route::post('/mailbox/compose', [MailboxController::class, 'store'])->name('mailbox.compose');
    Route::post('/mailbox/compose/preview', [MailboxController::class, 'preview'])->name('mailbox.preview');
    Route::post('/mailbox/{message}/send', [MailboxController::class, 'send'])->name('mailbox.send');
    Route::post('/mailbox/bulk-delete', [MailboxController::class, 'bulkDelete'])->name('mailbox.bulk-delete');
    Route::delete('/mailbox/{message}', [MailboxController::class, 'destroy'])->name('mailbox.destroy');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::get('/employees/{employee}/personal-page', [EmployeeController::class, 'personalPage'])->name('employees.personal-page');
    Route::post('/employees/{employee}/holidays', [EmployeeHolidayController::class, 'store'])->name('employees.holidays.store');
    Route::delete('/employees/{employee}/holidays/{holiday}', [EmployeeHolidayController::class, 'destroy'])->name('employees.holidays.destroy');
    Route::put('/employees/{employee}/availability/{weekday}/{daypart}', [RecurringAvailabilityController::class, 'update'])
        ->where(['weekday' => '[1-7]', 'daypart' => 'morning|afternoon|evening'])
        ->name('employees.availability.update');
});

// Employee personal page — token-only, no auth. Prototype preview links.
// See doc/features/employee-admin/spec.md and roadmap phase 2.
Route::get('/personal/{token}', [PersonalPageController::class, 'show'])->name('personal.show');
Route::put('/personal/{token}', [PersonalPageController::class, 'update'])->name('personal.update');
Route::post('/personal/{token}/holidays', [PersonalHolidayController::class, 'store'])->name('personal.holidays.store');
Route::delete('/personal/{token}/holidays/{holiday}', [PersonalHolidayController::class, 'destroy'])->name('personal.holidays.destroy');
Route::put('/personal/{token}/availability/{weekday}/{daypart}', [PersonalRecurringAvailabilityController::class, 'update'])
    ->where(['weekday' => '[1-7]', 'daypart' => 'morning|afternoon|evening'])
    ->name('personal.availability.update');
