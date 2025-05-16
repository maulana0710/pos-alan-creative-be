<?php

use App\Http\Controllers\api\OrderController;
use App\Http\Controllers\api\ProductController;
use App\Http\Controllers\OrderLabelController;

Route::prefix('v1')->group(function () {
  Route::prefix('app')->group(function () {
    Route::prefix('master-data')->group(function () {
      Route::apiResource('product', ProductController::class);
      Route::get('all-products', [ProductController::class, 'allProducts']);
      Route::get('all-product-with-deleted', [ProductController::class, 'allProductWithDeleted']);
      Route::get('product-deleted', [ProductController::class, 'productDeleted']);
      Route::post('product/{uuid}/restore', [ProductController::class, 'productRestore']);
      Route::delete('product/{uuid}/force', [ProductController::class, 'productForceDelete']);
    });
    Route::apiResource('order', OrderController::class);
    Route::post('order/{orderno}/print-label', [OrderLabelController::class, 'printLabel']);
  });
});
