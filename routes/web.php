<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignSequenceStepController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HotLeadController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadSalesInsightController;
use App\Http\Controllers\LeadImportController;
use App\Http\Controllers\OutboundEmailController;
use App\Http\Controllers\PowerDialerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SendingAccountController;
use App\Http\Controllers\SendingAccountTestController;
use App\Http\Controllers\SuppressionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\ZoomWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::get('/unsubscribe', UnsubscribeController::class)->middleware('throttle:30,1')->name('unsubscribe');
Route::post('/webhooks/zoom-phone', ZoomWebhookController::class)->middleware('throttle:120,1')->name('zoom.webhook');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('leads', LeadController::class)->except(['show']);
    Route::get('/leads/{lead}/sales-insight', LeadSalesInsightController::class)->middleware('throttle:20,1')->name('leads.sales-insight');
    Route::get('/dialer', [PowerDialerController::class, 'index'])->name('dialer.index');
    Route::post('/dialer/start', [PowerDialerController::class, 'start'])->name('dialer.start');
    Route::get('/dialer/{dialerSession}/state', [PowerDialerController::class, 'state'])->name('dialer.state');
    Route::post('/dialer/{dialerSession}/dial', [PowerDialerController::class, 'dial'])->name('dialer.dial');
    Route::post('/dialer/{dialerSession}/control', [PowerDialerController::class, 'control'])->name('dialer.control');
    Route::post('/calls/{callRecord}/disposition', [PowerDialerController::class, 'disposition'])->name('dialer.disposition');
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/{inboundMessage}', [InboxController::class, 'show'])->name('inbox.show');
    Route::post('/inbox/{inboundMessage}/action', [InboxController::class, 'action'])->name('inbox.action');
    Route::post('/inbox/{inboundMessage}/task', [InboxController::class, 'task'])->name('inbox.task');
    Route::get('/hot-leads', [HotLeadController::class, 'index'])->name('hot-leads.index');
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::resource('sending-accounts', SendingAccountController::class);
    Route::post('/sending-accounts/{sendingAccount}/test-smtp', [SendingAccountTestController::class, 'smtp'])->middleware('throttle:10,1')->name('sending-accounts.test-smtp');
    Route::post('/sending-accounts/{sendingAccount}/test-imap', [SendingAccountTestController::class, 'imap'])->middleware('throttle:10,1')->name('sending-accounts.test-imap');
    Route::post('/sending-accounts/{sendingAccount}/send-test', [SendingAccountTestController::class, 'send'])->middleware('throttle:5,1')->name('sending-accounts.send-test');
    Route::get('/outbound-emails', [OutboundEmailController::class, 'index'])->name('outbound-emails.index');
    Route::get('/suppressions', [SuppressionController::class, 'index'])->name('suppressions.index');
    Route::post('/suppressions', [SuppressionController::class, 'store'])->name('suppressions.store');
    Route::delete('/suppressions/{suppression}', [SuppressionController::class, 'destroy'])->name('suppressions.destroy');
    Route::resource('campaigns', CampaignController::class);
    Route::post('/campaigns/audience-preview', [CampaignController::class, 'audiencePreview'])->name('campaigns.audience-preview');
    Route::post('/campaigns/{campaign}/rebuild-audience', [CampaignController::class, 'rebuildAudience'])->name('campaigns.rebuild-audience');
    Route::post('/campaigns/{campaign}/steps', [CampaignSequenceStepController::class, 'store'])->name('campaigns.steps.store');
    Route::put('/campaigns/{campaign}/steps/{step}', [CampaignSequenceStepController::class, 'update'])->name('campaigns.steps.update');
    Route::delete('/campaigns/{campaign}/steps/{step}', [CampaignSequenceStepController::class, 'destroy'])->name('campaigns.steps.destroy');
    Route::get('/imports', [LeadImportController::class, 'index'])->name('imports.index');
    Route::post('/imports/preview', [LeadImportController::class, 'preview'])->name('imports.preview');
    Route::post('/imports', [LeadImportController::class, 'store'])->name('imports.store');
});

require __DIR__.'/auth.php';
