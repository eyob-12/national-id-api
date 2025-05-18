<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\IDCardController;

Route::post('/extract-text', [IDCardController::class, 'extractTextFromPDF']);


Route::post('/extract-id-images', [IDCardController::class, 'extractImagesFromPDF']);


Route::post('/upload-id', [IDCardController::class, 'upload']);



Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
