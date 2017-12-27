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

use FakeIpastore\Jobs\ProcessSignRequest;
use FakeIpastore\Models\SignRequest;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});


Route::any('/api_v2/status.php', function () {
    $response = [
        'maintenance' => 'no',
        'message' => 'We are updating our servers, the service will be back online in 30 min',
    ];

    return $response;
});

Route::any('/api/versions.php', function (Request $request) {
    $response = [
        'update' => 'yes',
        'appid' => 'com.iphonecake.ipastore',
        'version' => '5.2.5',
        'vforce' => '1',
        'message' => "New Update Available for Download! \r\n- iPhone X support\r\n- Bug Fixes \r\n- minor improvements",
        'download' => 'https://devmyi.com/api_v2/installer.php?udid=' . $request->input('udid'),
    ];

    return $response;
});

Route::any('/api/actcheck.php', function (Request $request) {
    $response = [
        'active' => 'yes',
        'validity' => 'valid',
        'result' => [
            [
                'status' => '2',
                'valid_till' => '2030-08-02',
                'payment_status' => 'completed',
                'udid' => $request->input('udid'),
                'email' => $request->input('email'),
                'usertype' => 'iPASTORE',
                'cert_name' => 'iPhone Developer: ju hui (6CUY6BMXDQ)',
                'prof_name' => 'iPASTORE508.mobileprovision',
            ],
        ],
    ];

    return $response;
});

Route::any('/api_v2/deviceinfo.php', function (Request $request) {
    $response = [
        'status' => 'failed',
        'msg' => 'device info insert or update failed!',
    ];

    return $response;
});

Route::any('/api_v2/path_v2.php', function (Request $request) {
    $response = file_get_contents('https://devmyi.com/api_v2/path_v2.php?appid=' . $request->input('appid') . '&type=' . $request->input('type'));

    $response = str_replace('devmyi.net', 'devmyi.xyz', $response);

    return $response;
});

Route::any('/api/disable.php', function (Request $request) {
    $response = [];

    return $response;
});

Route::any('/api/verifyuser.php', function (Request $request) {
    $response = [
        'status' => '1',
        'message' => 'Success',
        'usertype' => 'iPASTORE',
        'result' => [
            [
                'valid_till' => '2030-08-02',
                'invoice' => 'IP-78218',
                'payment_status' => 'completed',
                'udid' => $request->input('udid'),
                'email' => 'fake@ipastore.me',
                'usertype' => 'iPASTORE',
                'cert_name' => 'iPhone Developer: ju hui (6CUY6BMXDQ)',
                'prof_name' => 'iPASTORE508.mobileprovision',
            ],
        ],
        'addons' =>
            [
            ],
    ];

    header('Location: ipastore://?data=' . json_encode($response));
    die();
});


Route::any('/api_v3/resign_task.php', function (Request $request) {
    $signRequest = new SignRequest([
        'status' => SignRequest::STATUS_NEW,
        'server' => 'http://' . $request->getHttpHost() . '/',
        'udid' => $request->input('udid'),
        'icon' => $request->input('icon'),
        'bid' => $request->input('bid'),
        'ver' => $request->input('ver'),
        'name' => $request->input('name'),
        'aid' => $request->input('aid'),
        'cert' => $request->input('cert'),
    ]);

    $signRequest->save();

    ProcessSignRequest::dispatch($signRequest);

    $response = [
        'status' => 'queue',
        'info' => $signRequest->id,
    ];

    return $response;
});


// Replacing https://devmyi.com/api/get_udid.mobileconfig with https://devmyi.zyx/api/get_udid.mobileconfig... Done!


// {"status":"done","info":"https://str3.devmyi.net/plstrg_r/8881512590776_6CUY6BMXDQ.plist","link":"https://str3.devmyi.net/strg/cache/8881512590776_6CUY6BMXDQ.ipa"}


/*
 * /api_v3/resign_task.php?icon=http://is4.mzstatic.com/image/thumb/Purple128/v4/ce/2d/65/ce2d65e4-cb07-46f5-3e73-f2738bccf216/source/350x350bb.jpg&bid=com.thegentlebros.catquest.dev&name=Cat%20Quest&ver=1.1.3&aid=1148385289&udid=ef92b2c15ea8176d26de21a1e2ec395cfc7c7508&cert=6CUY6BMXDQ
 *
 * {"status":"queue","info":"1512899913"}
 *
 * POST /api_v3/task_status.php?udid=ef92b2c15ea8176d26de21a1e2ec395cfc7c7508
 *
 * {"status":"preparing","info":"1512899913","link":""}
 * {"status":"error","info":"No request from this device"}
 * {"status":"done","info":"https://str5.devmyi.net/plstrg_r/1148385289_6CUY6BMXDQ.plist","link":"https://str5.devmyi.net/strg/cache/1148385289_6CUY6BMXDQ.ipa"}
 *
 * GET /plstrg_r/1148385289_6CUY6BMXDQ.plist
 *
 *
 *
 *
 *
 *
 *
 *
 */
