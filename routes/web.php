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

Route::get('/', function () {
    return view('index',['v'=>'2.1']);
});
Route::get('/swim', function () {
	return view('index',['v'=>'2.1']);
});
Route::get('/upload', function () {
		return view('upload');
});
Route::post('upload', 'UploadController@upload');
Route::get('ajax', 'ReactController@ajax');
Route::post('ajax', 'ReactController@ajax');
