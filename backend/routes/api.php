<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\ApplicationChallanController;
use App\Http\Controllers\Api\V1\ApplicationChecklistController;
use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\ApplicationIssueController;
use App\Http\Controllers\Api\V1\ApplicationPenaltyController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\V1\ChecklistTemplateController;
use App\Http\Controllers\Api\V1\CompanyAssetController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CompanyPersonController;
use App\Http\Controllers\Api\V1\CompanyPremiseController;
use App\Http\Controllers\Api\V1\CompanyProductController;
use App\Http\Controllers\Api\V1\CompanyProfileController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DealerController;
use App\Http\Controllers\Api\V1\DealerOwnerController;
use App\Http\Controllers\Api\V1\DeficiencyLetterController;
use App\Http\Controllers\Api\V1\DistrictController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DocumentTypeController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\LicenseController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\PersonController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProvinceController;
use App\Http\Controllers\Api\V1\PublicVerificationController;
use App\Http\Controllers\Api\V1\QualificationController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\TehsilController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VerificationController;
use App\Http\Controllers\Api\V1\WorkflowStageController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::get('/public/verify/{token}', [PublicVerificationController::class, 'show'])
    ->middleware('throttle:public-verify');

Route::middleware(['auth:sanctum', 'password.changed', 'throttle:api'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('permission:activity_logs.view');
    Route::get('/exports/activity-logs', [ActivityLogController::class, 'export'])
        ->middleware('permission:exports.run');

    Route::get('/imports', [ImportController::class, 'index'])->middleware('permission:imports.run|imports.resolve');
    Route::post('/imports', [ImportController::class, 'store'])->middleware('permission:imports.run');
    Route::get('/imports/{importBatch}', [ImportController::class, 'show'])->middleware('permission:imports.run|imports.resolve');
    Route::post('/imports/{importBatch}/run', [ImportController::class, 'run'])->middleware('permission:imports.run');
    Route::get('/import-exceptions/{importException}', [ImportController::class, 'showException'])->middleware('permission:imports.run|imports.resolve');
    Route::put('/import-exceptions/{importException}', [ImportController::class, 'updateException'])->middleware('permission:imports.resolve');

    Route::middleware('permission:dashboard.view')->group(function () {
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('/dashboard/expiry-alerts', [DashboardController::class, 'expiryAlerts']);
        Route::get('/applications/documents-incomplete', [DashboardController::class, 'documentsIncomplete']);
        Route::get('/search', [DashboardController::class, 'search']);
    });

    Route::middleware('permission:portal.access')->group(function () {
        Route::get('/portal/dashboard', [PortalController::class, 'dashboard']);
        Route::get('/portal/company', [PortalController::class, 'company']);
        Route::get('/portal/staff', [PortalController::class, 'staff']);
        Route::post('/portal/staff/check', [PortalController::class, 'checkStaff'])->middleware('permission:staff.manage');
        Route::post('/portal/staff', [PortalController::class, 'storeStaff'])->middleware('permission:staff.manage');
        Route::post('/portal/staff/{id}/end', [PortalController::class, 'endStaff'])->middleware('permission:staff.manage');
        Route::get('/portal/documents', [PortalController::class, 'documents']);
        Route::post('/portal/documents', [PortalController::class, 'storeDocument'])->middleware('permission:documents.upload');
        Route::post('/portal/documents/{document}/replace', [PortalController::class, 'replaceDocument'])->middleware('permission:documents.upload');
        Route::get('/portal/products', [PortalController::class, 'products']);
        Route::post('/portal/products', [PortalController::class, 'storeProduct'])->middleware('permission:products.manage');
        Route::get('/portal/applications', [PortalController::class, 'applications']);
        Route::get('/portal/applications/{id}', [PortalController::class, 'showApplication']);
        Route::get('/portal/renewal', [PortalController::class, 'renewal']);
        Route::post('/portal/renewal', [PortalController::class, 'startRenewal'])->middleware('permission:portal.renewal.submit');
        Route::post('/portal/renewal/items/{item}/upload', [PortalController::class, 'uploadRenewalItem'])->middleware('permission:documents.upload');
        Route::post('/portal/renewal/submit', [PortalController::class, 'submitRenewal'])->middleware('permission:portal.renewal.submit');
        Route::middleware('permission:portal.users.manage')->group(function () {
            Route::get('/portal/users', [PortalController::class, 'users']);
            Route::post('/portal/users', [PortalController::class, 'storeUser']);
            Route::post('/portal/users/{id}/deactivate', [PortalController::class, 'deactivateUser']);
            Route::post('/portal/users/{id}/reset-password', [PortalController::class, 'resetUser']);
        });
    });

    Route::middleware('permission:staff.verify|documents.verify|products.verify')->group(function () {
        Route::get('/verifications', [VerificationController::class, 'index']);
        Route::get('/verifications/{type}/{id}', [VerificationController::class, 'show'])
            ->where('type', 'staff|document|product');
        Route::post('/verifications/{type}/{id}/approve', [VerificationController::class, 'approve'])
            ->where('type', 'staff|document|product');
        Route::post('/verifications/{type}/{id}/reject', [VerificationController::class, 'reject'])
            ->where('type', 'staff|document|product');
    });

    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/password/change', [PasswordController::class, 'change'])->name('auth.password.change');
    Route::post('/auth/two-factor/setup', [TwoFactorController::class, 'setup'])->name('auth.two-factor.setup');
    Route::post('/auth/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('auth.two-factor.confirm');
    Route::post('/auth/two-factor/disable', [TwoFactorController::class, 'disable'])->name('auth.two-factor.disable');

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/users/options', [UserController::class, 'options']);
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
    });

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:users.manage|roles.manage');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage');
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:users.manage|roles.manage');

    Route::middleware('permission:companies.view')->group(function () {
        Route::get('/companies', [CompanyController::class, 'index']);
        Route::get('/companies/{company}', [CompanyController::class, 'show']);
    });

    Route::post('/companies/check-duplicate', [CompanyController::class, 'checkDuplicate'])
        ->middleware('permission:companies.create|companies.update');
    Route::post('/companies', [CompanyController::class, 'store'])->middleware('permission:companies.create');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->middleware('permission:companies.update');
    Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->middleware('permission:companies.delete');

    Route::get('/exports/companies', [CompanyController::class, 'export'])->middleware('permission:exports.run');

    Route::middleware('permission:companies.view')->group(function () {
        Route::get('/companies/{company}/profile', [CompanyProfileController::class, 'show']);
        Route::get('/companies/{company}/activity', [CompanyProfileController::class, 'activity']);
        Route::get('/companies/{company}/people', [CompanyPersonController::class, 'index']);
        Route::get('/companies/{company}/premises', [CompanyPremiseController::class, 'index']);
        Route::get('/companies/{company}/assets', [CompanyAssetController::class, 'index']);
        Route::get('/companies/{company}/products', [CompanyProductController::class, 'index']);
    });

    Route::post('/companies/{company}/people/check', [CompanyPersonController::class, 'check'])
        ->middleware('permission:staff.manage');
    Route::post('/companies/{company}/people', [CompanyPersonController::class, 'store'])
        ->middleware('permission:staff.manage');
    Route::put('/company-people/{companyPerson}', [CompanyPersonController::class, 'update'])
        ->middleware('permission:staff.manage');
    Route::post('/company-people/{companyPerson}/end', [CompanyPersonController::class, 'end'])
        ->middleware('permission:staff.manage');
    Route::post('/company-people/{companyPerson}/verify', [CompanyPersonController::class, 'verify'])
        ->middleware('permission:staff.verify');

    Route::post('/companies/{company}/premises', [CompanyPremiseController::class, 'store'])
        ->middleware('permission:companies.update');
    Route::put('/company-premises/{companyPremise}', [CompanyPremiseController::class, 'update'])
        ->middleware('permission:companies.update');

    Route::post('/companies/{company}/assets', [CompanyAssetController::class, 'store'])
        ->middleware('permission:companies.update');
    Route::put('/company-assets/{companyAsset}', [CompanyAssetController::class, 'update'])
        ->middleware('permission:companies.update');

    Route::post('/companies/{company}/products', [CompanyProductController::class, 'store'])
        ->middleware('permission:products.manage');
    Route::put('/company-products/{companyProduct}', [CompanyProductController::class, 'update'])
        ->middleware('permission:products.manage');
    Route::delete('/company-products/{companyProduct}', [CompanyProductController::class, 'destroy'])
        ->middleware('permission:products.manage');

    Route::get('/companies/{company}/documents', [DocumentController::class, 'index'])
        ->middleware('permission:companies.view');
    Route::post('/companies/{company}/documents', [DocumentController::class, 'store'])
        ->middleware('permission:documents.upload');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])
        ->middleware('permission:documents.upload');
    Route::post('/documents/{document}/replace', [DocumentController::class, 'replace'])
        ->middleware('permission:documents.upload');
    Route::post('/documents/{document}/verify', [DocumentController::class, 'verify'])
        ->middleware('permission:documents.verify');
    Route::get('/documents/{document}/download-url', [DocumentController::class, 'downloadUrl'])
        ->middleware('permission:documents.download');
    Route::get('/documents/{document}/history', [DocumentController::class, 'history'])
        ->middleware('permission:companies.view|dealers.view');

    Route::middleware('permission:dealers.view')->group(function () {
        Route::get('/dealers', [DealerController::class, 'index']);
        Route::get('/dealers/{dealer}', [DealerController::class, 'show']);
        Route::get('/dealers/{dealer}/owners', [DealerOwnerController::class, 'index']);
        Route::get('/dealers/{dealer}/activity', [DealerController::class, 'activity']);
        Route::get('/dealers/{dealer}/documents', [DocumentController::class, 'indexDealer']);
    });

    Route::post('/dealers/check-duplicate', [DealerController::class, 'checkDuplicate'])
        ->middleware('permission:dealers.create|dealers.update');
    Route::post('/dealers', [DealerController::class, 'store'])->middleware('permission:dealers.create');
    Route::put('/dealers/{dealer}', [DealerController::class, 'update'])->middleware('permission:dealers.update');
    Route::delete('/dealers/{dealer}', [DealerController::class, 'destroy'])->middleware('permission:dealers.delete');
    Route::post('/dealers/{dealer}/owners/check', [DealerOwnerController::class, 'check'])
        ->middleware('permission:dealers.update');
    Route::post('/dealers/{dealer}/owners', [DealerOwnerController::class, 'store'])
        ->middleware('permission:dealers.update');
    Route::post('/dealer-owners/{dealerOwner}/end', [DealerOwnerController::class, 'end'])
        ->middleware('permission:dealers.update');
    Route::post('/dealers/{dealer}/documents', [DocumentController::class, 'storeDealer'])
        ->middleware('permission:documents.upload');

    Route::get('/exports/dealers', [DealerController::class, 'export'])->middleware('permission:exports.run');

    Route::middleware('permission:applications.view')->group(function () {
        Route::get('/applications', [ApplicationController::class, 'index']);
        Route::get('/applications/{application}', [ApplicationController::class, 'show']);
        Route::get('/applications/{application}/activity', [ApplicationController::class, 'activity']);
        Route::get('/deficiency-letters/{deficiencyLetter}/download', [DeficiencyLetterController::class, 'download']);
    });

    Route::post('/applications', [ApplicationController::class, 'store'])->middleware('permission:applications.create');
    Route::put('/applications/{application}', [ApplicationController::class, 'update'])
        ->middleware('permission:applications.create|applications.process');
    Route::post('/applications/{application}/stages/{workflowStage}/complete', [ApplicationController::class, 'complete'])
        ->middleware('permission:applications.create|applications.process|challans.verify|licenses.issue');
    Route::post('/applications/{application}/stages/{workflowStage}/skip', [ApplicationController::class, 'skip'])
        ->middleware('permission:applications.create|applications.process|challans.verify|licenses.issue');
    Route::post('/applications/{application}/reject', [ApplicationController::class, 'reject'])
        ->middleware('permission:applications.reject');
    Route::post('/applications/{application}/withdraw', [ApplicationController::class, 'withdraw'])
        ->middleware('permission:applications.process');
    Route::put('/applications/{application}/checklist-items/{applicationChecklistItem}', [ApplicationChecklistController::class, 'update'])
        ->middleware('permission:applications.process');
    Route::post('/applications/{application}/checklist-items/{applicationChecklistItem}/documents', [ApplicationChecklistController::class, 'upload'])
        ->middleware('permission:documents.upload');
    Route::post('/applications/{application}/deficiency-letters', [DeficiencyLetterController::class, 'store'])
        ->middleware('permission:applications.process');
    Route::post('/deficiency-letters/{deficiencyLetter}/resolve', [DeficiencyLetterController::class, 'resolve'])
        ->middleware('permission:applications.process');
    Route::post('/applications/{application}/penalties', [ApplicationPenaltyController::class, 'store'])
        ->middleware('permission:penalties.enter');
    Route::post('/applications/{application}/penalties/{applicationPenalty}/approve-waiver', [ApplicationPenaltyController::class, 'approve'])
        ->middleware('permission:penalties.waive');
    Route::post('/applications/{application}/challans', [ApplicationChallanController::class, 'store'])
        ->middleware('permission:applications.process|challans.verify');
    Route::post('/challans/{challan}/verify', [ApplicationChallanController::class, 'verify'])
        ->middleware('permission:challans.verify');
    Route::get('/applications/{application}/issue-preview', [ApplicationIssueController::class, 'preview'])
        ->middleware('permission:licenses.issue');
    Route::post('/applications/{application}/issue', [ApplicationIssueController::class, 'issue'])
        ->middleware('permission:licenses.issue');

    Route::get('/licenses/previous', [LicenseController::class, 'previous'])->middleware('permission:licenses.issue');
    Route::post('/licenses/previous', [LicenseController::class, 'storePrevious'])->middleware('permission:licenses.issue');
    Route::middleware('permission:companies.view|dealers.view|licenses.issue|licenses.suspend|licenses.cancel|licenses.restore')->group(function () {
        Route::get('/licenses', [LicenseController::class, 'index']);
        Route::get('/licenses/{license}', [LicenseController::class, 'show']);
        Route::get('/licenses/{license}/certificate', [LicenseController::class, 'certificate']);
    });
    Route::post('/licenses/{license}/suspend', [LicenseController::class, 'suspend'])->middleware('permission:licenses.suspend');
    Route::post('/licenses/{license}/cancel', [LicenseController::class, 'cancel'])->middleware('permission:licenses.cancel');
    Route::post('/licenses/{license}/restore', [LicenseController::class, 'restore'])->middleware('permission:licenses.restore');
    Route::get('/exports/licenses', [LicenseController::class, 'export'])->middleware('permission:exports.run');

    Route::middleware('permission:persons.view')->group(function () {
        Route::get('/persons/lookup', [PersonController::class, 'lookup']);
        Route::get('/persons/{person}', [PersonController::class, 'show']);
    });

    Route::put('/persons/{person}', [PersonController::class, 'update'])->middleware('permission:persons.update');

    Route::middleware('permission:checklists.manage')->group(function () {
        Route::get('/checklist-templates', [ChecklistTemplateController::class, 'index']);
        Route::get('/checklist-templates/{checklistTemplate}', [ChecklistTemplateController::class, 'show']);
        Route::post('/checklist-templates/{checklistTemplate}/new-version', [ChecklistTemplateController::class, 'newVersion']);
        Route::post('/checklist-templates/{checklistTemplate}/publish', [ChecklistTemplateController::class, 'publish']);
        Route::post('/checklist-templates/{checklistTemplate}/copy-from', [ChecklistTemplateController::class, 'copyFrom']);
        Route::post('/checklist-templates/{checklistTemplate}/items', [ChecklistTemplateController::class, 'storeItem']);
        Route::put('/checklist-templates/{checklistTemplate}/items/reorder', [ChecklistTemplateController::class, 'reorder']);
        Route::put('/checklist-templates/{checklistTemplate}/items/{checklistItem}', [ChecklistTemplateController::class, 'updateItem']);
        Route::delete('/checklist-templates/{checklistTemplate}/items/{checklistItem}', [ChecklistTemplateController::class, 'destroyItem']);
    });

    Route::middleware('permission:workflow.manage')->group(function () {
        Route::get('/workflow-stages', [WorkflowStageController::class, 'index']);
        Route::put('/workflow-stages', [WorkflowStageController::class, 'update']);
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings', [SettingController::class, 'update']);
    });

    Route::middleware('permission:lookups.manage')->group(function () {
        Route::get('/districts', [DistrictController::class, 'index']);
        Route::post('/districts', [DistrictController::class, 'store']);
        Route::get('/districts/{district}', [DistrictController::class, 'show']);
        Route::put('/districts/{district}', [DistrictController::class, 'update']);

        Route::get('/tehsils', [TehsilController::class, 'index']);
        Route::post('/tehsils', [TehsilController::class, 'store']);
        Route::get('/tehsils/{tehsil}', [TehsilController::class, 'show']);
        Route::put('/tehsils/{tehsil}', [TehsilController::class, 'update']);

        Route::get('/provinces', [ProvinceController::class, 'index']);
        Route::post('/provinces', [ProvinceController::class, 'store']);
        Route::get('/provinces/{province}', [ProvinceController::class, 'show']);
        Route::put('/provinces/{province}', [ProvinceController::class, 'update']);

        Route::get('/qualifications', [QualificationController::class, 'index']);
        Route::post('/qualifications', [QualificationController::class, 'store']);
        Route::get('/qualifications/{qualification}', [QualificationController::class, 'show']);
        Route::put('/qualifications/{qualification}', [QualificationController::class, 'update']);

        Route::get('/document-types', [DocumentTypeController::class, 'index']);
        Route::post('/document-types', [DocumentTypeController::class, 'store']);
        Route::get('/document-types/{documentType}', [DocumentTypeController::class, 'show']);
        Route::put('/document-types/{documentType}', [DocumentTypeController::class, 'update']);
    });

    Route::middleware('permission:products_master.manage')->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
    });
});
