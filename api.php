<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => 'api',  'prefix' => 'password'], function () {
    // With email : TODO: need test
    // Route::post('reset_code', 'API\PasswordResetController@create');
    // Route::get('find/{token}', 'API\PasswordResetController@find');
    // Route::post('reset', 'API\PasswordResetController@reset');
    // Route::post('resetPassword', 'API\PasswordResetController@resetPass');

    // with phone
    Route::post('sms_send_code', 'API\UserController@sendSmsCode');
    Route::post('sms_verify_code', 'API\UserController@verifySmsCode');
    Route::post('sms_reset_password', 'API\PasswordResetController@resetPassword');
});

Route::get('version', 'API\VersionController@check');
Route::post('login', 'API\UserController@login');
Route::post('register', 'API\UserController@register');


//alternative register route because 409 conflict response
Route::post('alt_register', 'API\UserController@register');

Route::post('phone_check/{phone}', 'API\UserController@phoneCheck');

Route::group( //BySwadi
    ['middleware' => ['auth:api']], function () {
        Route::post('logout', 'API\UserController@logout');
    }
);
/* Test Environment */
// Route::get('test_rating', 'API\RateController@store');
