<?php

use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\ConversationsController;
use App\Http\Controllers\Tenant\DealBoardController;
use App\Http\Controllers\Tenant\DealController;
use App\Http\Controllers\Tenant\InboxController;
use App\Http\Controllers\Tenant\AiConfigController;
use App\Http\Controllers\Tenant\AnalyticsController;
use App\Http\Controllers\Tenant\AutomationController;
use App\Http\Controllers\Tenant\CampaignController;
use App\Http\Controllers\Tenant\ContactController;
use App\Http\Controllers\Tenant\DripController;
use App\Http\Controllers\Tenant\KbArticleController;
use App\Http\Controllers\Tenant\KbCategoryController;
use App\Http\Controllers\Tenant\MessageController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\BrandController;
use App\Http\Controllers\Tenant\WhatsAppTemplateController;
use App\Http\Controllers\Tenant\WhatsAppDirectoController;
use App\Http\Controllers\Tenant\ChannelController;
use App\Http\Controllers\Tenant\OrganizationSwitchController;
use App\Http\Controllers\Tenant\PipelineController;
use App\Http\Controllers\Tenant\TagController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Controllers\Tenant\TeamController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landing Domain (chatme.com.mx)
|--------------------------------------------------------------------------
| Public landing page. No auth, no tenant middleware.
*/

Route::domain(config('app.base_domain'))->group(function () {
    Route::get('/', function () {
        return view('landing');
    })->name('landing');

    Route::get('/legal/privacy', function () {
        return view('legal.privacy');
    })->name('legal.privacy');

    Route::get('/legal/terms', function () {
        return view('legal.terms');
    })->name('legal.terms');

    Route::get('/legal/data-deletion', function () {
        return view('legal.data-deletion');
    })->name('legal.data-deletion');
});

/*
|--------------------------------------------------------------------------
| App Domain (app.chatme.com.mx)
|--------------------------------------------------------------------------
| SaaS application. Auth routes provided by Fortify (scoped via config).
| Tenant middleware applied per-route as needed.
*/

Route::domain('app.' . config('app.base_domain'))->group(function () {
    Route::get('/', function () {
        return redirect()->route('login');
    });

    Route::prefix('health')->group(function () {
        Route::get('/app', [HealthCheckController::class, 'app']);
        Route::get('/db', [HealthCheckController::class, 'db']);
        Route::get('/redis', [HealthCheckController::class, 'redis']);
        Route::get('/queue', [HealthCheckController::class, 'queue']);
    });

    // Access request (registration disabled)
    Route::get('/register', function () {
        return view('auth.register');
    })->middleware('guest')->name('register');
    Route::post('/access-request', [\App\Http\Controllers\AccessRequestController::class, 'store'])
        ->middleware(['guest', 'throttle:3,10'])
        ->name('access.request');

    Route::middleware(['auth'])->group(function () {
        Broadcast::routes();

        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('dashboard');

        // Cambiar de negocio activo (login multi-cuenta). Fuera de ResolveUserTenant
        // a propósito: este endpoint cambia cuál es el tenant del usuario.
        Route::post('/organizations/switch/{organization}', [OrganizationSwitchController::class, 'switch'])
            ->name('organizations.switch');

        Route::middleware([\App\Http\Middleware\ResolveUserTenant::class, 'throttle:tenant-api'])->group(function () {
            Route::get('/notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
            Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
            Route::get('/inbox/conversations/{conversation}', [ConversationsController::class, 'show'])->name('inbox.conversations.show');
            Route::post('/inbox/conversations/{conversation}/read', [ConversationsController::class, 'markAsRead'])->name('inbox.conversations.read');
            Route::post('/inbox/conversations/{conversation}/close', [ConversationsController::class, 'close'])->name('inbox.conversations.close');
            Route::post('/inbox/conversations/{conversation}/reopen', [ConversationsController::class, 'reopen'])->name('inbox.conversations.reopen');
            Route::post('/inbox/conversations/{conversation}/assign', [ConversationsController::class, 'assign'])->name('inbox.conversations.assign');
            Route::post('/inbox/conversations/{conversation}/transfer', [ConversationsController::class, 'transfer'])->name('inbox.conversations.transfer');
            Route::post('/inbox/conversations/{conversation}/convert-to-deal', [ConversationsController::class, 'convertToDeal'])->name('inbox.conversations.convert-to-deal');
            Route::delete('/inbox/conversations/{conversation}', [ConversationsController::class, 'destroy'])->name('inbox.conversations.destroy');
            Route::post('/inbox/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('inbox.conversations.messages.store');
            Route::get('/inbox/conversations/{conversation}/messages/poll', [MessageController::class, 'poll'])->name('inbox.conversations.messages.poll');
            Route::post('/inbox/conversations/{conversation}/messages/forward', [MessageController::class, 'forward'])->name('inbox.conversations.messages.forward');
            Route::post('/inbox/conversations/{conversation}/send-template', [MessageController::class, 'sendTemplate'])->name('inbox.conversations.send-template');
            Route::get('/contacts/search', [ContactController::class, 'searchJson'])->name('contacts.search');

            // WhatsApp Directo (Phase 22 — sección aislada del inbox, usa bridge Baileys vía Redis)
            Route::get('/whatsapp-directo', [WhatsAppDirectoController::class, 'index'])->name('whatsapp-directo.index');
            Route::post('/whatsapp-directo', [WhatsAppDirectoController::class, 'store'])->name('whatsapp-directo.store');
            Route::get('/whatsapp-directo/{channel}/pair', [WhatsAppDirectoController::class, 'pair'])->name('whatsapp-directo.pair');
            Route::post('/whatsapp-directo/{channel}/pair', [WhatsAppDirectoController::class, 'repair'])->name('whatsapp-directo.repair');
            Route::get('/whatsapp-directo/{channel}/status', [WhatsAppDirectoController::class, 'status'])->name('whatsapp-directo.status');
            Route::post('/whatsapp-directo/{channel}/logout', [WhatsAppDirectoController::class, 'logout'])->name('whatsapp-directo.logout');
            Route::get('/whatsapp-directo/{channel}', [WhatsAppDirectoController::class, 'show'])->name('whatsapp-directo.show');
            Route::get('/whatsapp-directo/{channel}/conversations', [WhatsAppDirectoController::class, 'conversations'])->name('whatsapp-directo.conversations');
            Route::get('/whatsapp-directo/{channel}/conversations/{conversation}', [WhatsAppDirectoController::class, 'conversation'])->name('whatsapp-directo.conversation.show');
            Route::post('/whatsapp-directo/{channel}/conversations/{conversation}/send', [WhatsAppDirectoController::class, 'send'])->name('whatsapp-directo.send');
            Route::post('/whatsapp-directo/{channel}/conversations/{conversation}/send-media', [WhatsAppDirectoController::class, 'sendMedia'])->name('whatsapp-directo.send-media');
            Route::post('/whatsapp-directo/{channel}/conversations/{conversation}/read', [WhatsAppDirectoController::class, 'markAsRead'])->name('whatsapp-directo.read');
            Route::get('/whatsapp-directo/{channel}/forward-targets', [WhatsAppDirectoController::class, 'forwardTargets'])->name('whatsapp-directo.forward-targets');
            Route::get('/whatsapp-directo/{channel}/conversations/{conversation}/info', [WhatsAppDirectoController::class, 'info'])->name('whatsapp-directo.conversation.info');
            Route::post('/whatsapp-directo/{channel}/conversations/{conversation}/convert-to-deal', [WhatsAppDirectoController::class, 'convertToDeal'])->name('whatsapp-directo.conversation.convert-to-deal');
            Route::post('/whatsapp-directo/{channel}/conversations/{conversation}/close', [WhatsAppDirectoController::class, 'close'])->name('whatsapp-directo.conversation.close');
            Route::post('/whatsapp-directo/{channel}/conversations/{conversation}/reopen', [WhatsAppDirectoController::class, 'reopen'])->name('whatsapp-directo.conversation.reopen');
            Route::post('/whatsapp-directo/{channel}/conversations/{conversation}/assign', [WhatsAppDirectoController::class, 'assign'])->name('whatsapp-directo.conversation.assign');
            Route::post('/whatsapp-directo/{channel}/conversations/{conversation}/messages/delete', [WhatsAppDirectoController::class, 'deleteMessages'])->name('whatsapp-directo.messages.delete');
            Route::post('/whatsapp-directo/{channel}/conversations/{conversation}/messages/forward', [WhatsAppDirectoController::class, 'forwardMessages'])->name('whatsapp-directo.messages.forward');
            // Importador de historial (ZIP de WhatsApp Business, Fase 22.6)
            Route::post('/whatsapp-directo/{channel}/import/analyze', [WhatsAppDirectoController::class, 'importAnalyze'])->name('whatsapp-directo.import.analyze');
            Route::post('/whatsapp-directo/{channel}/import', [WhatsAppDirectoController::class, 'import'])->name('whatsapp-directo.import');

            // CRM Kanban
            Route::get('/deals', [DealBoardController::class, 'index'])->name('deals.board');
            Route::get('/deals/{deal}', [DealBoardController::class, 'show'])->name('deals.show');
            Route::post('/deals', [DealController::class, 'store'])->name('deals.store');
            Route::put('/deals/{deal}', [DealController::class, 'update'])->name('deals.update');
            Route::post('/deals/{deal}/move', [DealController::class, 'move'])->name('deals.move');
            Route::post('/deals/{deal}/assign', [DealController::class, 'assign'])->name('deals.assign');
            Route::post('/deals/{deal}/notes', [DealController::class, 'addNote'])->name('deals.notes.store');
            Route::post('/deals/{deal}/attachments', [DealController::class, 'addAttachment'])->name('deals.attachments.store');
            Route::delete('/deals/{deal}/attachments/{attachment}', [DealController::class, 'deleteAttachment'])->name('deals.attachments.destroy');
            Route::post('/deals/{deal}/commissions', [DealController::class, 'addCommission'])->name('deals.commissions.store');
            Route::post('/deals/{deal}/commissions/{commission}/status', [DealController::class, 'updateCommissionStatus'])->name('deals.commissions.status');
            Route::delete('/deals/{deal}/commissions/{commission}', [DealController::class, 'deleteCommission'])->name('deals.commissions.destroy');
            Route::post('/deals/{deal}/tags', [DealController::class, 'syncTags'])->name('deals.tags.sync');
            Route::delete('/deals/{deal}', [DealController::class, 'destroy'])->name('deals.destroy');

            // Pipelines
            Route::get('/pipelines', [PipelineController::class, 'index'])->name('pipelines.index');
            Route::get('/pipelines/create', [PipelineController::class, 'create'])->name('pipelines.create');
            Route::post('/pipelines', [PipelineController::class, 'store'])->name('pipelines.store');
            Route::get('/pipelines/{pipeline}/edit', [PipelineController::class, 'edit'])->name('pipelines.edit');
            Route::put('/pipelines/{pipeline}', [PipelineController::class, 'update'])->name('pipelines.update');
            Route::delete('/pipelines/{pipeline}', [PipelineController::class, 'destroy'])->name('pipelines.destroy');
            Route::post('/pipelines/{pipeline}/default', [PipelineController::class, 'setDefault'])->name('pipelines.set-default');

            // Tags
            Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
            Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
            Route::put('/tags/{tag}', [TagController::class, 'update'])->name('tags.update');
            Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

            // Brands
            Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
            Route::get('/brands/create', [BrandController::class, 'create'])->name('brands.create');
            Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');
            Route::get('/brands/{brand}', [BrandController::class, 'show'])->name('brands.show');
            Route::get('/brands/{brand}/edit', [BrandController::class, 'edit'])->name('brands.edit');
            Route::put('/brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
            Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

            // Tenant Settings
            Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
            Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::get('/settings/team', [TeamController::class, 'index'])->name('settings.team');
            Route::post('/settings/team', [TeamController::class, 'store'])->name('settings.team.store');
            Route::post('/settings/team/{user}/role', [TeamController::class, 'updateRole'])->name('settings.team.role');
            Route::post('/settings/team/{user}/toggle', [TeamController::class, 'toggleActive'])->name('settings.team.toggle');
            Route::delete('/settings/team/{user}', [TeamController::class, 'destroy'])->name('settings.team.destroy');

            // Billing
            Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
            Route::get('/billing/plans', [BillingController::class, 'plans'])->name('billing.plans');
            Route::post('/billing/change-plan', [BillingController::class, 'changePlan'])->name('billing.change-plan');
            Route::post('/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
            Route::get('/billing/checkout/success', [BillingController::class, 'checkoutSuccess'])->name('billing.checkout.success');
            Route::post('/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
            Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');

            // Knowledge Base
            Route::get('/kb/categories', [KbCategoryController::class, 'index'])->name('kb.categories');
            Route::post('/kb/categories', [KbCategoryController::class, 'store'])->name('kb.categories.store');
            Route::post('/kb/categories/{category}/update', [KbCategoryController::class, 'update'])->name('kb.categories.update');
            Route::post('/kb/categories/{category}/delete', [KbCategoryController::class, 'destroy'])->name('kb.categories.destroy');

            Route::get('/kb/articles', [KbArticleController::class, 'index'])->name('kb.articles');
            Route::get('/kb/articles/create', [KbArticleController::class, 'create'])->name('kb.articles.create');
            Route::post('/kb/articles', [KbArticleController::class, 'store'])->name('kb.articles.store');
            Route::get('/kb/articles/{article}', [KbArticleController::class, 'show'])->name('kb.articles.show');
            Route::get('/kb/articles/{article}/edit', [KbArticleController::class, 'edit'])->name('kb.articles.edit');
            Route::post('/kb/articles/{article}/update', [KbArticleController::class, 'update'])->name('kb.articles.update');
            Route::post('/kb/articles/{article}/publish', [KbArticleController::class, 'publish'])->name('kb.articles.publish');
            Route::post('/kb/articles/{article}/archive', [KbArticleController::class, 'archive'])->name('kb.articles.archive');
            Route::post('/kb/articles/{article}/delete', [KbArticleController::class, 'destroy'])->name('kb.articles.destroy');

            // Channels
            Route::get('/settings/channels', [ChannelController::class, 'index'])->name('settings.channels');
            Route::get('/settings/channels/wizard', [ChannelController::class, 'wizard'])->name('settings.channels.wizard');
            Route::post('/settings/channels/wizard/validate', [ChannelController::class, 'wizardValidate'])->name('settings.channels.wizard.validate');
            Route::post('/settings/channels/wizard/store', [ChannelController::class, 'wizardStore'])->name('settings.channels.wizard.store');
            Route::get('/settings/channels/create', [ChannelController::class, 'create'])->name('settings.channels.create');
            Route::post('/settings/channels', [ChannelController::class, 'store'])->name('settings.channels.store');
            Route::get('/settings/channels/{channel}', [ChannelController::class, 'show'])->name('settings.channels.show');
            Route::get('/settings/channels/{channel}/edit', [ChannelController::class, 'edit'])->name('settings.channels.edit');
            Route::post('/settings/channels/{channel}/update', [ChannelController::class, 'update'])->name('settings.channels.update');
            Route::post('/settings/channels/{channel}/toggle', [ChannelController::class, 'toggleActive'])->name('settings.channels.toggle');
            Route::post('/settings/channels/{channel}/delete', [ChannelController::class, 'destroy'])->name('settings.channels.delete');

            // AI Configuration
            Route::get('/settings/ai', [AiConfigController::class, 'show'])->name('settings.ai');
            Route::post('/settings/ai', [AiConfigController::class, 'update'])->name('settings.ai.update');

            // Contacts
            Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
            Route::get('/contacts/create', [ContactController::class, 'create'])->name('contacts.create');
            Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
            Route::get('/contacts/import', [ContactController::class, 'importForm'])->name('contacts.import.form');
            Route::post('/contacts/import', [ContactController::class, 'import'])->name('contacts.import');
            Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
            Route::get('/contacts/{contact}/edit', [ContactController::class, 'edit'])->name('contacts.edit');
            Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
            Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

            // Campaigns
            Route::middleware(['feature:campaigns_enabled'])->group(function () {
                Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
                Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
                Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
                Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
                Route::get('/campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
                Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
                Route::post('/campaigns/{campaign}/send', [CampaignController::class, 'send'])->name('campaigns.send');
                Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
                Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
                Route::get('/campaigns/{campaign}/recipients/select', [CampaignController::class, 'selectRecipients'])->name('campaigns.recipients.select');
                Route::post('/campaigns/{campaign}/recipients', [CampaignController::class, 'addRecipients'])->name('campaigns.recipients.add');
                Route::delete('/campaigns/{campaign}/recipients/{contactId}', [CampaignController::class, 'removeRecipient'])->name('campaigns.recipients.remove');
            });

            // WhatsApp Templates
            Route::middleware(['feature:whatsapp_templates_enabled'])->group(function () {
                Route::get('/whatsapp-templates', [WhatsAppTemplateController::class, 'index'])->name('whatsapp-templates.index');
                Route::get('/whatsapp-templates/create', [WhatsAppTemplateController::class, 'create'])->name('whatsapp-templates.create');
                Route::post('/whatsapp-templates', [WhatsAppTemplateController::class, 'store'])->name('whatsapp-templates.store');
                Route::get('/whatsapp-templates/approved', [WhatsAppTemplateController::class, 'approvedForChannel'])->name('whatsapp-templates.approved');
                Route::get('/whatsapp-templates/{template}/edit', [WhatsAppTemplateController::class, 'edit'])->name('whatsapp-templates.edit');
                Route::put('/whatsapp-templates/{template}', [WhatsAppTemplateController::class, 'update'])->name('whatsapp-templates.update');
                Route::delete('/whatsapp-templates/{template}', [WhatsAppTemplateController::class, 'destroy'])->name('whatsapp-templates.destroy');
                Route::post('/whatsapp-templates/sync', [WhatsAppTemplateController::class, 'sync'])->name('whatsapp-templates.sync');
                Route::post('/whatsapp-templates/upload-media', [WhatsAppTemplateController::class, 'uploadMedia'])->name('whatsapp-templates.upload-media');
            });

            // Drip Sequences
            Route::middleware(['feature:drip_sequences_enabled'])->group(function () {
                Route::get('/drip', [DripController::class, 'index'])->name('drip.index');
                Route::get('/drip/create', [DripController::class, 'create'])->name('drip.create');
                Route::post('/drip', [DripController::class, 'store'])->name('drip.store');
                Route::get('/drip/{sequence}', [DripController::class, 'show'])->name('drip.show');
                Route::get('/drip/{sequence}/edit', [DripController::class, 'edit'])->name('drip.edit');
                Route::put('/drip/{sequence}', [DripController::class, 'update'])->name('drip.update');
                Route::post('/drip/{sequence}/activate', [DripController::class, 'activate'])->name('drip.activate');
                Route::post('/drip/{sequence}/pause', [DripController::class, 'pause'])->name('drip.pause');
                Route::delete('/drip/{sequence}', [DripController::class, 'destroy'])->name('drip.destroy');
                Route::post('/drip/{sequence}/steps', [DripController::class, 'addStep'])->name('drip.steps.add');
                Route::put('/drip/{sequence}/steps/{step}', [DripController::class, 'updateStep'])->name('drip.steps.update');
                Route::delete('/drip/{sequence}/steps/{step}', [DripController::class, 'removeStep'])->name('drip.steps.remove');
                Route::post('/drip/{sequence}/enroll', [DripController::class, 'enroll'])->name('drip.enroll');
                Route::post('/drip/{sequence}/enrollments/{enrollment}/cancel', [DripController::class, 'cancelEnrollment'])->name('drip.enrollments.cancel');
            });

            // Automations
            Route::middleware(['feature:automations_enabled'])->group(function () {
                Route::get('/automations', [AutomationController::class, 'index'])->name('automations.index');
                Route::get('/automations/create', [AutomationController::class, 'create'])->name('automations.create');
                Route::post('/automations', [AutomationController::class, 'store'])->name('automations.store');
                Route::get('/automations/{rule}/edit', [AutomationController::class, 'edit'])->name('automations.edit');
                Route::put('/automations/{rule}', [AutomationController::class, 'update'])->name('automations.update');
                Route::post('/automations/{rule}/toggle', [AutomationController::class, 'toggleActive'])->name('automations.toggle');
                Route::delete('/automations/{rule}', [AutomationController::class, 'destroy'])->name('automations.destroy');
            });

            // Analytics
            Route::middleware(['feature:reports_enabled'])->group(function () {
                Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
                Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
            });

            // Tenant aliases
            Route::get('/tenant/inbox', [InboxController::class, 'index'])->name('tenant.inbox');
            Route::get('/tenant/kanban', [DealBoardController::class, 'index'])->name('tenant.kanban');
            Route::get('/tenant/settings', [SettingsController::class, 'show'])->name('tenant.settings');
            Route::get('/tenant/dashboard', function () {
                return view('tenant.dashboard');
            })->name('tenant.dashboard');
        });
    });
});
