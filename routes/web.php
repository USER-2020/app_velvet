<?php

use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\OnboardingDraftController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ContractingController;
use App\Http\Controllers\UserAccessController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\PortalRoomController;
use App\Http\Controllers\DocumentsController;
use App\Http\Controllers\TrainingDataController;
use App\Http\Controllers\TourProgressController;
use App\Http\Controllers\GuidedTourSettingsController;
use App\Http\Controllers\OperationalProfileController;
use App\Http\Controllers\StudioOperationsController;
use App\Http\Controllers\MasterOperationsController;
use App\Http\Controllers\PlatformDocumentationController;
use App\Http\Controllers\AgentWorkSessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('home');
Route::get('/apply', [RecruitmentController::class, 'apply'])->name('apply');
Route::get('/politica-de-privacidad', fn () => Inertia::render('Public/PrivacyPolicy'))->name('privacy');
Route::get('/terminos-y-condiciones', fn () => Inertia::render('Public/TermsAndConditions'))->name('terms');
Route::post('/apply', [RecruitmentController::class, 'store'])->middleware('throttle:10,1')->name('apply.store');
Route::get('/apply/draft', [OnboardingDraftController::class, 'show'])->name('apply.draft.show');
Route::post('/apply/draft', [OnboardingDraftController::class, 'store'])->middleware('throttle:60,1')->name('apply.draft.store');
Route::delete('/apply/draft', [OnboardingDraftController::class, 'destroy'])->name('apply.draft.destroy');
Route::get('/prequalification/completada', [RecruitmentController::class, 'prequalificationSuccess'])->name('prequalification.success');
Route::get('/prequalification/{candidate}/{token}', [RecruitmentController::class, 'prequalification'])->middleware('signed')->name('prequalification.show');
Route::get('/prequalification/{candidate}/{token}/identity-callback', [RecruitmentController::class, 'diditCallback'])->name('prequalification.identity.callback');
Route::post('/prequalification/{candidate}/{token}', [RecruitmentController::class, 'storePrequalification'])->middleware(['signed', 'throttle:10,1'])->name('prequalification.store');
Route::post('/prequalification/{candidate}/{token}/identity-session', [RecruitmentController::class, 'createIdentityVerificationSession'])->middleware(['signed', 'throttle:5,1'])->name('prequalification.identity.session');
Route::post('/webhooks/didit', [RecruitmentController::class, 'diditWebhook'])->name('didit.webhook');
Route::get('/entrevista/completada', [InterviewController::class, 'bookingSuccess'])->name('interview.booking.success');
Route::get('/entrevista/{interview}/{token}', [InterviewController::class, 'booking'])->middleware('signed')->name('interview.booking.show');
Route::post('/entrevista/{interview}/{token}', [InterviewController::class, 'book'])->middleware(['signed', 'throttle:10,1'])->name('interview.booking.book');
Route::get('/contratacion/agenda/completada', [ContractingController::class, 'bookingSuccess'])->name('contracting.booking.success');
Route::get('/contratacion/agenda/{invitation}/{token}', [ContractingController::class, 'booking'])->middleware('signed')->name('contracting.booking.show');
Route::post('/contratacion/agenda/{invitation}/{token}', [ContractingController::class, 'bookPublic'])->middleware(['signed', 'throttle:10,1'])->name('contracting.booking.book');
Route::get('/contratacion/{candidate}/{token}/documentos', [ContractingController::class, 'publicDocuments'])->middleware('signed')->name('contracting.documents.show');
Route::post('/contratacion/{candidate}/{token}/documentos', [ContractingController::class, 'storePublicDocuments'])->middleware(['signed', 'throttle:10,1'])->name('contracting.documents.store');
Route::get('/contratacion/{candidate}/{token}/documentos/exito', [ContractingController::class, 'publicDocumentsSuccess'])->middleware('signed')->name('contracting.documents.success');
Route::get('/contratacion/{candidate}/{token}/documentos/realtime-token', [ContractingController::class, 'publicRealtimeToken'])->middleware('signed')->name('contracting.documents.realtime-token');
Route::get('/contratacion/{candidate}/{token}/documentos/{document}/vista', [ContractingController::class, 'previewPublicDocument'])->middleware('signed')->name('contracting.documents.preview');
Route::get('/contratacion/{candidate}/{token}/documentos/{document}/descarga', [ContractingController::class, 'downloadPublicDocument'])->middleware('signed')->name('contracting.documents.download');
Route::post('/cookie-consent', [CookieConsentController::class, 'store'])->middleware('throttle:20,1')->name('cookie-consent.store');

Route::get('/dashboard', function () {
    if (request()->user()->hasRole('model')) {
        return to_route('model.dashboard');
    }
    if (request()->user()->hasRole('monitor')) {
        return to_route('monitor.dashboard');
    }

    return to_route('admin.recruitment');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::put('/tour-progress/{tourKey}', [TourProgressController::class, 'update'])->name('tour-progress.update');
    Route::middleware(['permission:rooms.view_own', 'active.profile'])->prefix('model')->name('model.')->group(function () {
        Route::get('/dashboard', [PortalRoomController::class, 'modelDashboard'])->name('dashboard');
        Route::get('/rooms', [PortalRoomController::class, 'modelRooms'])->name('rooms');
        Route::get('/profile', [PortalRoomController::class, 'modelProfile'])->middleware('permission:profile.view_own')->name('profile');
        Route::get('/work-sessions', [AgentWorkSessionController::class, 'index'])->middleware('permission:metrics.view_own')->name('work-sessions');
        Route::post('/rooms/reservations', [PortalRoomController::class, 'storeModelReservation'])->middleware(['permission:rooms.reserve_own', 'active.profile'])->name('rooms.reservations.store');
        Route::patch('/rooms/reservations/{reservation}/status', [PortalRoomController::class, 'updateModelReservationStatus'])->middleware(['permission:rooms.use_own', 'active.profile'])->name('rooms.reservations.status.update');
    });
    Route::middleware(['permission:rooms.view_team', 'active.profile'])->prefix('monitor')->name('monitor.')->group(function () {
        Route::get('/dashboard', [PortalRoomController::class, 'monitorDashboard'])->name('dashboard');
        Route::get('/rooms', [PortalRoomController::class, 'monitorRooms'])->name('rooms');
        Route::get('/work-sessions', [AgentWorkSessionController::class, 'index'])->middleware('permission:models.view_metrics')->name('work-sessions');
        Route::post('/rooms/reservations', [PortalRoomController::class, 'storeMonitorReservation'])->middleware(['permission:rooms.manage_reservations', 'active.profile'])->name('rooms.reservations.store');
        Route::patch('/rooms/reservations/{reservation}/status', [PortalRoomController::class, 'updateMonitorReservationStatus'])->middleware(['permission:rooms.manage_usage', 'active.profile'])->name('rooms.reservations.status.update');
    });
    Route::middleware(['permission:models.view_assigned', 'active.profile'])->prefix('monitor/models')->name('monitor.models.')->group(function () {
        Route::get('/', [RecruitmentController::class, 'monitorModels'])->name('index');
        Route::get('/{candidate}', [RecruitmentController::class, 'monitorModel'])->name('show');
        Route::post('/{candidate}/platform-accounts', [OperationalProfileController::class, 'linkAssignedPlatformAccount'])->middleware('permission:studio_accounts.assign_assigned')->name('platform-accounts.store');
        Route::delete('/{candidate}/platform-accounts/{modelPlatformAccount}', [OperationalProfileController::class, 'unlinkAssignedPlatformAccount'])->middleware('permission:studio_accounts.assign_assigned')->name('platform-accounts.destroy');
    });
    Route::get('/realtime/token', [PortalRoomController::class, 'realtimeToken'])->name('realtime.token');
    Route::post('/portal/requests', [StudioOperationsController::class, 'storeRequest'])->middleware('permission:requests.create')->name('portal.requests.store');
    Route::middleware('permission:documents.view_own')->prefix('portal/documents')->name('portal.documents.')->group(function () {
        Route::get('/{document}/preview', [PortalRoomController::class, 'previewContractDocument'])->name('preview');
        Route::get('/{document}/download', [PortalRoomController::class, 'downloadContractDocument'])->name('download');
    });
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/work-sessions', [AgentWorkSessionController::class, 'index'])->middleware('permission:dashboard.view')->name('work-sessions');
        Route::get('/guided-tours', [GuidedTourSettingsController::class, 'index'])->name('guided-tours.index');
        Route::put('/guided-tours', [GuidedTourSettingsController::class, 'update'])->name('guided-tours.update');
        Route::prefix('documentation')->name('documentation.')->group(function () {
            Route::get('/', [PlatformDocumentationController::class, 'index'])->name('index');
            Route::get('/{documentation}/download', [PlatformDocumentationController::class, 'download'])->name('download');
            Route::get('/references/{key}', [PlatformDocumentationController::class, 'reference'])->name('reference');
            Route::middleware('permission:super_admin')->group(function () {
                Route::post('/', [PlatformDocumentationController::class, 'store'])->name('store');
                Route::delete('/{documentation}', [PlatformDocumentationController::class, 'destroy'])->name('destroy');
            });
        });
        Route::middleware('permission:dashboard.view')->group(function () {
            Route::get('/', [RecruitmentController::class, 'dashboard'])->name('dashboard');
            Route::get('/recruitment', [RecruitmentController::class, 'dashboard'])->name('recruitment');
        });
        Route::get('/workflows', [RecruitmentController::class, 'workflows'])->middleware('permission:settings.manage')->name('workflows');
        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('/training-data', [TrainingDataController::class, 'index'])->name('training-data.index');
            Route::post('/training-data/reset', [TrainingDataController::class, 'reset'])->name('training-data.reset');
        });
        Route::get('/processes', [RecruitmentController::class, 'processes'])->middleware('permission:onboarding.view')->name('processes');
        Route::get('/operations', [StudioOperationsController::class, 'index'])->middleware('permission:shifts.view_team')->name('operations.index');
        Route::post('/operations/shifts', [StudioOperationsController::class, 'storeShift'])->middleware('permission:shifts.manage_team')->name('operations.shifts.store');
        Route::patch('/operations/shifts/{shift}/attendance', [StudioOperationsController::class, 'updateAttendance'])->middleware('permission:attendance.manage')->name('operations.shifts.attendance');
        Route::patch('/operations/checklists/{checklist}/complete', [StudioOperationsController::class, 'completeChecklist'])->middleware('permission:room_checklists.manage')->name('operations.checklists.complete');
        Route::post('/operations/incidents', [StudioOperationsController::class, 'storeIncident'])->middleware('permission:incidents.manage')->name('operations.incidents.store');
        Route::patch('/operations/incidents/{incident}', [StudioOperationsController::class, 'resolveIncident'])->middleware('permission:incidents.manage')->name('operations.incidents.update');
        Route::post('/operations/notes', [StudioOperationsController::class, 'storeNote'])->middleware('permission:incidents.manage')->name('operations.notes.store');
        Route::patch('/operations/requests/{operationalRequest}', [StudioOperationsController::class, 'resolveRequest'])->middleware('permission:incidents.manage')->name('operations.requests.update');
        Route::get('/platforms', [MasterOperationsController::class, 'platforms'])->middleware('permission:platforms.view')->name('platforms.index');
        Route::post('/platforms', [MasterOperationsController::class, 'storePlatform'])->middleware('permission:platforms.manage')->name('platforms.store');
        Route::post('/platforms/accounts', [MasterOperationsController::class, 'storeStudioAccount'])->middleware('permission:studio_accounts.manage')->name('platforms.accounts.store');
        Route::post('/platforms/accounts/{studioAccount}/models', [MasterOperationsController::class, 'linkModelAccount'])->middleware('permission:studio_accounts.manage')->name('platforms.accounts.models.store');
        Route::post('/platforms/accounts/{studioAccount}/simulate-sync', [MasterOperationsController::class, 'simulateSync'])->middleware('permission:platform_simulations.run')->name('platforms.accounts.sync.simulate');
        Route::post('/platforms/accounts/{studioAccount}/import', [MasterOperationsController::class, 'importManual'])->middleware('permission:platform_imports.manage')->name('platforms.accounts.import');
        Route::get('/productivity', [MasterOperationsController::class, 'productivity'])->middleware('permission:reports.operational.view')->name('productivity.index');
        Route::post('/productivity/sessions', [MasterOperationsController::class, 'storeSession'])->middleware('permission:sessions.manage')->name('productivity.sessions.store');
        Route::patch('/productivity/sessions/{session}/close', [MasterOperationsController::class, 'closeSession'])->middleware('permission:sessions.manage')->name('productivity.sessions.close');
        Route::post('/productivity/sessions/{session}/streams', [MasterOperationsController::class, 'storeStream'])->middleware('permission:streams.manage')->name('productivity.streams.store');
        Route::patch('/productivity/streams/{stream}/close', [MasterOperationsController::class, 'closeStream'])->middleware('permission:streams.manage')->name('productivity.streams.close');
        Route::post('/productivity/metrics', [MasterOperationsController::class, 'storeMetric'])->middleware('permission:productivity.manage')->name('productivity.metrics.store');
        Route::patch('/productivity/metrics/{metric}/validate', [MasterOperationsController::class, 'validateMetric'])->name('productivity.metrics.validate');
        Route::get('/administration', [MasterOperationsController::class, 'administration'])->middleware('permission:branches.manage')->name('administration.index');
        Route::post('/administration/branches', [MasterOperationsController::class, 'storeBranch'])->middleware('permission:branches.manage')->name('administration.branches.store');
        Route::post('/administration/assets', [MasterOperationsController::class, 'storeAsset'])->middleware('permission:inventory.manage')->name('administration.assets.store');
        Route::post('/administration/maintenance', [MasterOperationsController::class, 'storeMaintenance'])->middleware('permission:maintenance.manage')->name('administration.maintenance.store');
        Route::post('/administration/reviews', [MasterOperationsController::class, 'storeReview'])->middleware('permission:performance_reviews.manage')->name('administration.reviews.store');
        Route::post('/finance/exports', [MasterOperationsController::class, 'exportFinance'])->middleware('permission:integrations.finance_export')->name('finance.exports.store');
        Route::middleware('permission:leads.view')->group(function () {
            Route::get('/leads', [RecruitmentController::class, 'leads'])->name('leads');
            Route::get('/leads/{lead}', [RecruitmentController::class, 'lead'])->name('leads.show');
        });
        Route::put('/leads/{lead}', [RecruitmentController::class, 'updateLead'])->middleware('permission:leads.manage')->name('leads.update');
        Route::patch('/leads/{lead}/status', [RecruitmentController::class, 'updateLeadStatus'])->middleware('permission:leads.manage')->name('leads.status.update');
        Route::post('/leads/{lead}/discard', [RecruitmentController::class, 'discardLead'])->middleware('permission:leads.manage')->name('leads.discard');
        Route::post('/leads/{lead}/convert', [RecruitmentController::class, 'convert'])->middleware('permission:candidates.convert')->name('leads.convert');
        Route::post('/ably/token', [RecruitmentController::class, 'ablyToken'])->name('ably.token');
        Route::get('/ably/token', [RecruitmentController::class, 'ablyToken'])->name('ably.token.get');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/history', [NotificationController::class, 'history'])->name('notifications.history');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::middleware('permission:candidates.view')->group(function () {
            Route::get('/candidates', [RecruitmentController::class, 'candidates'])->name('candidates');
            Route::get('/candidates/{candidate}', [RecruitmentController::class, 'candidate'])->name('candidates.show');
        });
        Route::put('/candidates/{candidate}', [RecruitmentController::class, 'updateCandidate'])->middleware('permission:candidates.update')->name('candidates.update');
        Route::post('/candidates/{candidate}/identity-verification/retry', [RecruitmentController::class, 'requestIdentityVerificationRetry'])->middleware('permission:documents.verify')->name('candidates.identity-verification.retry');
        Route::post('/candidates/{candidate}/activate-access', [RecruitmentController::class, 'activateCandidateAccess'])->middleware('permission:candidates.activate_access')->name('candidates.activate-access');
        Route::patch('/candidates/{candidate}/access-status', [RecruitmentController::class, 'updateCandidateAccessStatus'])->middleware('permission:candidates.activate_access')->name('candidates.access-status');
        Route::post('/candidates/{candidate}/training-records', [OperationalProfileController::class, 'storeTrainingRecord'])->middleware('permission:onboarding.manage')->name('candidates.training-records.store');
        Route::patch('/candidates/{candidate}/training-records/{trainingRecord}', [OperationalProfileController::class, 'updateTrainingRecord'])->middleware('permission:onboarding.manage')->name('candidates.training-records.update');
        Route::post('/models/{model}/monitor-assignments', [OperationalProfileController::class, 'assignMonitor'])->middleware('permission:models.assign_monitor')->name('models.monitor-assignments.store');
        Route::patch('/models/{model}/monitor-assignments/{assignment}/end', [OperationalProfileController::class, 'endMonitorAssignment'])->middleware('permission:models.change_monitor')->name('models.monitor-assignments.end');
        Route::post('/candidates/{candidate}/start-contracting', [RecruitmentController::class, 'startContracting'])->middleware('permission:contracts.create')->name('candidates.start-contracting');
        Route::post('/candidates/{candidate}/contracting-invitation', [RecruitmentController::class, 'sendContractingInvitation'])->middleware('permission:contracts.create')->name('candidates.contracting-invitation.send');
        Route::post('/candidates/{candidate}/interview-notes', [RecruitmentController::class, 'uploadInterviewNotes'])->middleware('permission:candidates.update')->name('candidates.interview-notes.upload');
        Route::get('/candidates/{candidate}/interview-notes', [RecruitmentController::class, 'downloadInterviewNotes'])->middleware('permission:candidates.view')->name('candidates.interview-notes.download');
        Route::get('/candidates/{candidate}/interview-notes/preview', [RecruitmentController::class, 'previewInterviewNotes'])->middleware('permission:candidates.view')->name('candidates.interview-notes.preview');
        Route::get('/candidates/{candidate}/interview-notes/download', [RecruitmentController::class, 'downloadInterviewNotes'])->middleware('permission:candidates.view')->name('candidates.interview-notes.download.file');
        Route::post('/candidates/{candidate}/discard', [RecruitmentController::class, 'discardCandidate'])->middleware('permission:candidates.reject')->name('candidates.discard');
        Route::patch('/candidates/{candidate}/status', [RecruitmentController::class, 'updateCandidateStatus'])->middleware('permission:candidates.change_status')->name('candidates.status.update');
        Route::post('/candidates/{candidate}/prequalification', [RecruitmentController::class, 'sendPrequalification'])->middleware('permission:candidates.request_documents')->name('candidates.prequalification.send');
        Route::middleware('permission:contracts.create')->prefix('contracting')->name('contracting.')->group(function () {
            Route::get('/', [ContractingController::class, 'index'])->name('index');
            Route::post('/slots', [ContractingController::class, 'generateSlots'])->name('slots.generate');
            Route::delete('/slots', [ContractingController::class, 'destroyAvailableSlots'])->name('slots.destroy-available');
            Route::post('/appointments', [ContractingController::class, 'book'])->name('appointments.store');
            Route::patch('/appointments/{appointment}/reschedule', [ContractingController::class, 'reschedule'])->name('appointments.reschedule');
            Route::patch('/appointments/{appointment}/complete', [ContractingController::class, 'complete'])->name('appointments.complete');
            Route::post('/candidates/{candidate}/documents', [ContractingController::class, 'uploadDocuments'])->name('candidates.documents.store');
        });
        Route::middleware('permission:interviews.view')->group(function () {
            Route::get('/interviews', [InterviewController::class, 'index'])->name('interviews.index');
        });
        Route::middleware('permission:documents.verify')->group(function () {
            Route::get('/validations', [RecruitmentController::class, 'validations'])->name('validations');
            Route::get('/validations/{candidate}/evidence/{image}', [RecruitmentController::class, 'diditEvidence'])->name('validations.evidence');
            Route::post('/validations/{candidate}/refresh', [RecruitmentController::class, 'refreshDiditValidation'])->name('validations.refresh');
        });
        Route::middleware('permission:documents.view')->prefix('documents')->name('documents.')->group(function () {
            Route::get('/', [DocumentsController::class, 'index'])->name('index');
            Route::patch('/people/{lead}/status', [DocumentsController::class, 'updatePersonStatus'])->middleware('permission:documents.manage')->name('people.status.update');
            Route::get('/{document}/preview', [DocumentsController::class, 'preview'])->name('preview');
            Route::get('/{document}/download', [DocumentsController::class, 'download'])->name('download');
            Route::patch('/{document}/status', [DocumentsController::class, 'updateStatus'])->middleware('permission:documents.manage')->name('status.update');
        });
        Route::middleware('permission:calendar.view')->group(function () {
            Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
        });
        Route::middleware('permission:rooms.view')->group(function () {
            Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
            Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
            Route::patch('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
            Route::patch('/rooms/{room}/status', [RoomController::class, 'updateStatus'])->name('rooms.status.update');
            Route::post('/rooms/reservations', [RoomController::class, 'storeReservation'])->name('rooms.reservations.store');
            Route::patch('/rooms/reservations/{reservation}/status', [RoomController::class, 'updateReservationStatus'])->name('rooms.reservations.status.update');
        });
        Route::middleware('permission:calendar.manage')->group(function () {
            Route::post('/calendar/events', [CalendarController::class, 'store'])->name('calendar.events.store');
            Route::patch('/calendar/events/{calendarEvent}/complete', [CalendarController::class, 'complete'])->name('calendar.events.complete');
            Route::patch('/calendar/events/{calendarEvent}', [CalendarController::class, 'update'])->name('calendar.events.update');
            Route::delete('/calendar/events/{calendarEvent}', [CalendarController::class, 'destroy'])->name('calendar.events.destroy');
        });
        Route::post('/interviews/slots', [InterviewController::class, 'generateSlots'])->middleware('permission:candidates.schedule_interview')->name('interviews.slots.generate');
        Route::delete('/interviews/slots', [InterviewController::class, 'destroyAvailableSlots'])->middleware('permission:candidates.schedule_interview')->name('interviews.slots.destroy-available');
        Route::patch('/interviews/slots/{slot}', [InterviewController::class, 'updateSlot'])->middleware('permission:candidates.schedule_interview')->name('interviews.slots.update');
        Route::delete('/interviews/slots/{slot}', [InterviewController::class, 'destroySlot'])->middleware('permission:candidates.schedule_interview')->name('interviews.slots.destroy');
        Route::post('/candidates/{candidate}/interview-invitation', [InterviewController::class, 'invite'])->middleware('permission:candidates.schedule_interview')->name('candidates.interview.invite');
        Route::patch('/interviews/{interview}/status', [InterviewController::class, 'updateStatus'])->middleware('permission:candidates.schedule_interview')->name('interviews.status.update');
        Route::post('/interviews/{interview}/reschedule', [InterviewController::class, 'reschedule'])->middleware('permission:candidates.schedule_interview')->name('interviews.reschedule');
        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('/access', [AccessControlController::class, 'index'])->name('access.index');
            Route::get('/access/create', [AccessControlController::class, 'create'])->name('access.create');
            Route::post('/access', [AccessControlController::class, 'store'])->middleware('password.confirm')->name('access.store');
            Route::get('/access/{role}/edit', [AccessControlController::class, 'edit'])->name('access.edit');
            Route::put('/access/{role}', [AccessControlController::class, 'update'])->middleware('password.confirm')->name('access.update');
            Route::delete('/access/{role}', [AccessControlController::class, 'destroy'])->middleware('password.confirm')->name('access.destroy');
        });
        Route::get('/users', [UserAccessController::class, 'index'])->middleware('permission:users.view')->name('users.index');
        Route::get('/users/create', [UserAccessController::class, 'create'])->middleware('permission:users.create')->name('users.create');
        Route::post('/users', [UserAccessController::class, 'store'])->middleware(['permission:users.create', 'password.confirm'])->name('users.store');
        Route::get('/users/{user}/edit', [UserAccessController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
        Route::put('/users/{user}', [UserAccessController::class, 'update'])->middleware(['permission:users.update', 'password.confirm'])->name('users.update');
        Route::delete('/users/{user}', [UserAccessController::class, 'destroy'])->middleware(['permission:users.disable', 'password.confirm'])->name('users.destroy');
    });
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// OAuth authorization for the desktop Agent. The browser session is the user login;
// the resulting code is bound to the station, redirect URI and PKCE verifier.
Route::get('/oauth/agent/authorize', [\App\Http\Controllers\Agent\AgentOAuthController::class, 'authorize'])
    ->middleware(['auth', 'verified', \App\Http\Middleware\EnsureAgentOAuthPermission::class])
    ->name('oauth.agent.authorize');

// Administrative integration API: existing web session, CSRF, active account and explicit permission.
Route::prefix('admin/agent')->middleware(['auth', 'verified', \App\Http\Middleware\Agent\AgentTransport::class, 'throttle:30,1'])->group(function () {
    $controller = \App\Http\Controllers\Agent\AgentAdministrationController::class;
    Route::post('enrollment-tokens', [$controller, 'issue'])->middleware(\App\Http\Middleware\EnsurePermission::class.':stations.enroll');
    Route::post('stations/{station}/revoke', [$controller, 'revoke'])->middleware(\App\Http\Middleware\EnsurePermission::class.':stations.revoke');
    Route::put('stations/{station}/configuration', [$controller, 'configure'])->middleware(\App\Http\Middleware\EnsurePermission::class.':stations.configure');
    Route::post('stations/{station}/commands', [$controller, 'command'])->middleware(\App\Http\Middleware\EnsurePermission::class.':stations.commands');
});

Route::middleware(['auth', 'verified', \App\Http\Middleware\EnsurePermission::class.':stations.enroll'])->prefix('admin/agent')->name('admin.agent.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Agent\AgentAdministrationController::class, 'index'])->name('index');
    Route::post('/enrollment-tokens/panel', [\App\Http\Controllers\Agent\AgentAdministrationController::class, 'issuePanel'])->name('enrollment-tokens.panel');
});

Route::get('/admin/station-manager', [\App\Http\Controllers\StationManagerController::class, 'index'])
    ->middleware(['auth', 'verified', \App\Http\Middleware\EnsurePermission::class.':stations.assign'])
    ->name('admin.station-manager.index');

Route::middleware(['auth', 'verified', \App\Http\Middleware\EnsurePermission::class.':stations.assign'])->prefix('admin/station-manager')->name('admin.station-manager.')->group(function () {
    Route::post('/enrollment-codes', [\App\Http\Controllers\StationManagerController::class, 'issueEnrollmentCode'])->name('enrollment-codes.store');
    Route::post('/assignments', [\App\Http\Controllers\StationManagerController::class, 'assign'])->name('assignments.store');
    Route::patch('/assignments/{assignment}/end', [\App\Http\Controllers\StationManagerController::class, 'end'])->name('assignments.end');
});
