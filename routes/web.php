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
    return view('welcome');
});

Route::get('/home', 'HomeController@index')->name('home');

Route::get('/authenticate', 'Auth\LoginController@authenticate');

Route::get('/chess_table', 'HomeController@chessTable');

Route::get('/table_status/{tableId}', 'HomeController@tableStatus');

Route::get('/join_table/{tableId}/{blackOrWhite}', 'HomeController@joinTable');
Route::get('/leave_table/{tableId}', 'HomeController@leaveTable');
Route::get('/walk/{tableId}/{x}/{y}', 'HomeController@walk');

