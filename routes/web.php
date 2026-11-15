<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AppointmentCancellationController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppointmentRescheduleController;
use App\Http\Controllers\AutomationSettingsController;
use App\Http\Controllers\AvailabilityBlockController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationReplyController;
use App\Http\Controllers\ConversationTakeoverController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoogleCalendarOAuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReadConversationController;
use App\Http\Controllers\ServiceAvailabilityController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\WhatsAppConnectionController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\WorkspaceSettingsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->middleware('throttle:60,1')
    ->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.whatsapp.handle');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/analytics', AnalyticsController::class)->name('analytics.index');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');

    Route::get('/automations', [AutomationSettingsController::class, 'edit'])->name('automations.edit');
    Route::put('/automations', [AutomationSettingsController::class, 'update'])->name('automations.update');

    Route::get('/availability', [AvailabilityController::class, 'edit'])->name('availability.edit');
    Route::put('/availability', [AvailabilityController::class, 'update'])->name('availability.update');
    Route::post('/availability/blocks', [AvailabilityBlockController::class, 'store'])->name('availability-blocks.store');
    Route::delete('/availability/blocks/{availabilityBlock}', [AvailabilityBlockController::class, 'destroy'])->name('availability-blocks.destroy');
    Route::put('/availability/services/{service}', [ServiceAvailabilityController::class, 'update'])->name('service-availability.update');

    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
    Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
    Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');

    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('/conversations/{conversation}/reply', ConversationReplyController::class)->name('conversations.reply');
    Route::post('/conversations/{conversation}/takeover', [ConversationTakeoverController::class, 'store'])->name('conversations.takeover.store');
    Route::delete('/conversations/{conversation}/takeover', [ConversationTakeoverController::class, 'destroy'])->name('conversations.takeover.destroy');
    Route::post('/conversations/{conversation}/read', ReadConversationController::class)->name('conversations.read');

    Route::get('/settings/whatsapp', [WhatsAppConnectionController::class, 'edit'])->name('whatsapp.edit');
    Route::put('/settings/whatsapp', [WhatsAppConnectionController::class, 'update'])->name('whatsapp.update');
    Route::delete('/settings/whatsapp', [WhatsAppConnectionController::class, 'destroy'])->name('whatsapp.destroy');

    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::put('/appointments/{appointment}/reschedule', [AppointmentRescheduleController::class, 'update'])->name('appointments.reschedule');
    Route::delete('/appointments/{appointment}', [AppointmentCancellationController::class, 'destroy'])->name('appointments.cancel');

    Route::get('/settings/workspace', [WorkspaceSettingsController::class, 'edit'])->name('workspace.edit');
    Route::put('/settings/workspace', [WorkspaceSettingsController::class, 'update'])->name('workspace.update');

    Route::get('/integrations/google-calendar', [GoogleCalendarOAuthController::class, 'redirect'])->name('google-calendar.redirect');
    Route::get('/integrations/google-calendar/callback', [GoogleCalendarOAuthController::class, 'callback'])->name('google-calendar.callback');
    Route::patch('/integrations/google-calendar', [GoogleCalendarOAuthController::class, 'update'])->name('google-calendar.update');
    Route::delete('/integrations/google-calendar', [GoogleCalendarOAuthController::class, 'destroy'])->name('google-calendar.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
