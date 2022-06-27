<?php

use Illuminate\Http\Request;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', 'AuthController@login');
//Dashboard
Route::get('/profile', 'UserController@getProfileDetails');
Route::post('/profile/password', 'UserController@resetPassword');
Route::get('/dashboard', 'DashboardController@getDashboardDetails');

//Certificates
Route::get('/certificates', 'CertificateController@getCertificates');
Route::post('/certificates', 'CertificateController@uploadCertificate');
Route::put('/certificates/{id}/update-status', 'CertificateController@updateStatus');
Route::post('/certificates/{id}/validate', 'CertificateController@validateData');
Route::post('/certificates/{id}/write-to-pi', 'CertificateController@writeToPI');
Route::post('/certificates/{id}/update-data', 'CertificateController@updateData');

Route::get('/parse-data/{id}', 'CertificateController@parseData');
Route::get('/dga/{id}', 'CertificateController@parseDGAData'); //For Testing
Route::get('/raw-data/{id}', 'CertificateController@getRawData');

//Tag Configuration
Route::post('/tag-configurations/{type}', 'TagConfigurationController@uploadTagConfigurations');
Route::get('/tag-configurations/{type}', 'TagConfigurationController@getTagConfigurations');

//Users
Route::get('/users', 'UserController@getUsers');
Route::post('/users', 'UserController@saveUser');
Route::post('/users/{id}', 'UserController@updateUser');
Route::delete('/users/{id}', 'UserController@deleteUser');

//Manual Logger
Route::get('/manual-logger/batches', 'ManualLoggerController@getBatches');
Route::post('/manual-logger/batches', 'ManualLoggerController@saveBatch');
//Manual Logger ata
Route::get('/manual-logger/batches/{id}', 'ManualLoggerController@getBatchDetails');
Route::post('/manual-logger/batches/{id}', 'ManualLoggerController@saveBatchDetails');
Route::post('/manual-logger/batches/{id}/csv', 'ManualLoggerController@uploadBatchDetails');
Route::post('/manual-logger/batches/{id}/write', 'ManualLoggerController@saveAndWrite');

//Misc
Route::get('/get-tag-details/{tagname}', 'PIWebAPIController@getTagDetails');


//Background Tasks
Route::get('/bg/certificates', 'BackgroundServiceController@getCertificates');
Route::put('/bg/certificates/{id}/update-status', 'BackgroundServiceController@updateStatus');
Route::get('/configurations', 'ConfigurationController@getConfig');

//Data Keys 
Route::get('/data-keys', 'ConfigurationController@saveDataKey');

//Parsing
Route::get('/parse-coal-data/{id}', 'CertificateController@parseCoalData');
Route::get('/parse-dga-data/{id}', 'CertificateController@parseDGAData');
