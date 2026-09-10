<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Admin\MembershipApplicationDocumentController;
use App\Http\Controllers\AlManarUrgentImportWebhookController;
use App\Http\Controllers\BreakingApiController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HostedVideoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LiveController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\PublicMoneyController;
use App\Http\Controllers\PublicMoneyImportWebhookController;
use App\Http\Controllers\RssImportWebhookController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitePageController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/home/latest-news', [HomeController::class, 'latest'])->name('home.latest');
Route::get('/live', LiveController::class)->name('live');
Route::get('/search', SearchController::class)->name('search');
Route::get('/membership', [MembershipController::class, 'show'])->name('membership');
Route::post('/membership', [MembershipController::class, 'store'])->name('membership.store')->middleware('throttle:membership');
Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store')->middleware('throttle:contact');

Route::prefix('public-money')->name('public-money.')->group(function (): void {
    Route::get('/', [PublicMoneyController::class, 'index'])->name('index');
    Route::get('/procurements', [PublicMoneyController::class, 'procurements'])->name('procurements');
    Route::get('/procurements/{record}', [PublicMoneyController::class, 'procurement'])->name('procurement');
    Route::get('/budget', [PublicMoneyController::class, 'budget'])->name('budget');
    Route::get('/sources', [PublicMoneyController::class, 'sources'])->name('sources');
    Route::get('/reports/{report:slug}', [PublicMoneyController::class, 'report'])->name('report');
});
Route::post('/admin/blob-upload', \App\Http\Controllers\AdminBlobUploadController::class)
    ->middleware(['auth', 'throttle:30,1'])
    ->name('admin.blob-upload');

Route::get('/pages/{slug}', [SitePageController::class, 'show'])->name('pages.show');

Route::get('/news/{slug}', [ArticleController::class, 'show'])->name('news.show');

Route::get('/videos/{videoItem}', [VideoController::class, 'show'])->name('videos.show');
Route::get('/videos/hosted/{hostedVideo:slug}', [HostedVideoController::class, 'show'])->name('hosted-videos.show');
Route::get('/weather-feed', WeatherController::class)->name('api.weather');
Route::get('/breaking-feed', BreakingApiController::class)->name('api.breaking')->middleware('throttle:api-breaking');
Route::get('/api/weather', WeatherController::class);
Route::get('/api/breaking', BreakingApiController::class)->middleware('throttle:api-breaking');

Route::get('/admin/membership-applications/{membershipApplication}/id-document', MembershipApplicationDocumentController::class)
    ->name('admin.membership-applications.id-document')
    ->middleware('auth');

Route::match(['GET', 'POST'], '/tasks/import-rss/{secret}', RssImportWebhookController::class)
    ->name('tasks.import-rss')
    ->middleware('throttle:rss-import');

Route::match(['GET', 'POST'], '/tasks/import-almanar-urgent/{secret}', AlManarUrgentImportWebhookController::class)
    ->name('tasks.import-almanar-urgent')
    ->middleware('throttle:almanar-urgent-import');

Route::match(['GET', 'POST'], '/tasks/public-money-import', PublicMoneyImportWebhookController::class)
    ->name('tasks.public-money-import')
    ->middleware('throttle:6,1');
