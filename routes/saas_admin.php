<?php

use App\Http\Controllers\SaaSAdmin\AlertController;
use App\Http\Controllers\SaaSAdmin\ChannelFormController;
use App\Http\Controllers\SaaSAdmin\DashboardController;
use App\Http\Controllers\SaaSAdmin\MaintenanceController;
use App\Http\Controllers\SaaSAdmin\OrganizationController;
use App\Http\Controllers\SaaSAdmin\SubscriptionController;
use App\Http\Controllers\SaaSAdmin\UsageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('saas-admin.dashboard');

// Organizations
Route::get('organizations', [OrganizationController::class, 'index'])->name('saas-admin.organizations.index');
Route::get('organizations/{organization}', [OrganizationController::class, 'show'])->name('saas-admin.organizations.show');
Route::get('organizations/{organization}/edit', [OrganizationController::class, 'edit'])->name('saas-admin.organizations.edit');
Route::put('organizations/{organization}', [OrganizationController::class, 'update'])->name('saas-admin.organizations.update');
Route::post('organizations/{organization}/suspend', [OrganizationController::class, 'suspend'])->name('saas-admin.organizations.suspend');
Route::post('organizations/{organization}/activate', [OrganizationController::class, 'activate'])->name('saas-admin.organizations.activate');

// Subscriptions
Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('saas-admin.subscriptions.index');
Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('saas-admin.subscriptions.show');
Route::put('subscriptions/{subscription}', [SubscriptionController::class, 'update'])->name('saas-admin.subscriptions.update');

// Usage
Route::get('usage', [UsageController::class, 'index'])->name('saas-admin.usage.index');

// Alerts
Route::get('alerts', [AlertController::class, 'index'])->name('saas-admin.alerts.index');
Route::get('alerts/create', [AlertController::class, 'create'])->name('saas-admin.alerts.create');
Route::post('alerts', [AlertController::class, 'store'])->name('saas-admin.alerts.store');
Route::get('alerts/{alert}/edit', [AlertController::class, 'edit'])->name('saas-admin.alerts.edit');
Route::put('alerts/{alert}', [AlertController::class, 'update'])->name('saas-admin.alerts.update');
Route::post('alerts/{alert}/resolve', [AlertController::class, 'resolve'])->name('saas-admin.alerts.resolve');
Route::delete('alerts/{alert}', [AlertController::class, 'destroy'])->name('saas-admin.alerts.destroy');

// Channel Forms
Route::get('channel-forms', [ChannelFormController::class, 'index'])->name('saas-admin.channel-forms.index');
Route::get('channel-forms/create', [ChannelFormController::class, 'create'])->name('saas-admin.channel-forms.create');
Route::post('channel-forms', [ChannelFormController::class, 'store'])->name('saas-admin.channel-forms.store');
Route::get('channel-forms/{channelForm}', [ChannelFormController::class, 'show'])->name('saas-admin.channel-forms.show');
Route::post('channel-forms/{channelForm}/toggle', [ChannelFormController::class, 'toggleActive'])->name('saas-admin.channel-forms.toggle');
Route::delete('channel-forms/{channelForm}', [ChannelFormController::class, 'destroy'])->name('saas-admin.channel-forms.destroy');

// Maintenance
Route::get('maintenance', [MaintenanceController::class, 'index'])->name('saas-admin.maintenance.index');
Route::post('maintenance/{organization}/toggle', [MaintenanceController::class, 'toggle'])->name('saas-admin.maintenance.toggle');
