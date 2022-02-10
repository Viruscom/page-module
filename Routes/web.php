<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Modules\Page\Http\Controllers\PageController;
use Modules\Page\Http\Controllers\PageFrontController;

/* Front */
Route::prefix('page')->group(function() {
    Route::get('/{slug}', [PageFrontController::class,'index']);
});

/* Admin */
Route::group(['prefix' => 'admin/pages'], static function () {
    Route::get('/', [PageController::class, 'index'])->name('page');
    Route::get('/list', [PageController::class, 'index'])->name('page.list');
    Route::get('/create', [PageController::class, 'create'])->name('page.create');
    Route::post('/store', [PageController::class, 'store'])->name('page.store');
    Route::get('/{id}/edit', [PageController::class, 'edit'])->name('page.edit');
    Route::post('/{id}/update', [PageController::class, 'update'])->name('page.update');
    Route::delete('/{id}/delete', [PageController::class, 'delete'])->name('page.delete');
    Route::get('/{id}/show', [PageController::class, 'show'])->name('page.show');
    Route::post('/active/{id}/{active}', [PageController::class, 'changeActiveStatus'])->name('page.changeStatus');
});
