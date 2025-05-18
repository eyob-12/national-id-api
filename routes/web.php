<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/image-proxy/{filename}', function ($filename) {
    $path = public_path('images/temp/' . $filename);

    if (!file_exists($path)) {
        return response()->json(['error' => 'File not found', 'path' => $path], 404);
    }

    return response()->file($path, [
        'Access-Control-Allow-Origin' => '*',
    ]);
});
