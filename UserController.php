<?php

/**
 * Http api user
 * php version 7.3.1
 *
 * @category API
 * @package  Moka_APIs
 * @author   mohammed <mohammed.ammar1110@gmail.com>
 * @license  IC https://www.infinitecloud.co
 * @link     Moka_Sweets https://www.mokasweets.com/
 */

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Validator;

use App\User;
use App\UserFavorite;
use App\PointHistory;
use App\Image;
use App\GainMethod;
use App\userWallet;
use App\UserFireBaseToken;

/**
 * Http api user
 * php version 7.3.1
 *
 * @category API
 * @package  Moka_APIs
 * @author   mohammed <mohammed.ammar1110@gmail.com>
 * @license  IC https://www.infinitecloud.co
 * @link     Moka_Sweets https://www.mokasweets.com/
 */
class UserController extends Controller
{
    public $successStatus = 200;

    /**
     * Login api:
     *
     * @return \Illuminate\Http\Response
     */
    public function login()
    {
        if (Auth::attempt(
            ['phone' => request('phone'), 'password' => request('password')]
        )) {
            $user = Auth::user();

            // if user is deleted
            if ($user->status == 2) {
                $data = $user;
                $data['status_message'] = 'deleted';
                $error['status_message'] = 'deleted';
                $error['massage'] = 'Unauthorized';
                return response()->json(
                    ['success' => false, 'data' => $data, 'error' => $error],
                    401
                );
            }

            //TODO: Prevent this user from applying requests and paying for
            // products by API side
            // if user is in blacklist
            if ($user->status == 3) {
                $data = $user;
                $data['token'] = $user->createToken('MyApp')->accessToken;

                $this->saveFirebaseToken(request('fcm'), $user->id);

                $data['status_message'] = 'blacklist';
                $error['status_message'] = 'blacklist';
                $error['massage'] = 'Unauthorized';
                return response()->json(
                    [
                        'success' => false,
                        'data' => $data, 'error' => $error
                    ],
                    $this->successStatus
                );
            }

            // if user is attitude
            if ($user->status == 0) {
                $data = $user;
                $data['status_message'] = 'attitude';
                $error['status_message'] = 'attitude';
                $error['massage'] = 'Unauthorized';
                return response()->json(
                    ['success' => false, 'data' => $data, 'error' => $error],
                    401
                );
            }

            // if user is active
            if ($user->status == 1) {
                $data = $user;
                $data['token'] = $user->createToken('MyApp')->accessToken;

                $this->saveFirebaseToken(request('fcm'), $user->id);

                $data['status_message'] = 'active';
                $error['status_message'] = 'active';
                return response()->json(
                    [
                        'success' => true,
                        'data' => $data,
                        // 'error'=>$error
                    ],
                    $this->successStatus
                );
            } else {
                $error['massage'] = 'Unauthorized';
                $data['status_message'] = 'unknown';
                $error['status_message'] = 'unknown';
                return response()->json(
                    ['success' => false, 'data' => $data, 'error' => $error],
                    401
                );
            }
        } else {
            $error['massage'] = 'Unauthorized';
            return response()->json(['success' => false, 'error' => $error], 401);
        }
    }

    /**
     * Register api
     *
     * @param $request Illuminate\Http\Request
     *
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|unique:users,phone',
                'gender' => 'required',
                'birth_date' => 'required',
                'password' => 'required|min:6',
                'c_password' => 'required|same:password',
                'fcm' => 'required',
                // 'points_account_num' => 'unique:users,points_account_num',
            ]
        );
        // die((bool)empty($request->parent_points_account_num).'ll');

        $validator->sometimes(
            'parent_points_account_num',
            ' exists:users,points_account_num',
            function ($request) {
                if (strlen($request->parent_points_account_num) == 0) {
                    return false;
                    // die(true.'lk');
                    return true;
                }
            }
        );

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 401);
        }

        $input = $request->all();
        $input['password'] = Hash::make($input['password']);
        //$input['points_account_num'] = Hash::make($input['password']);
        $user = User::create($input);

        $this->saveFirebaseToken($input['fcm'], $user->id);

        $user->points_account_num = rand(10, 99) . $user->id . rand(10, 99);
        $user->save();
        $data =  $user;
        $data['token'] = $user->createToken('MyApp')->accessToken;


        /**
         * Stop adding points
         */
        /*
        $gain_type = GainMethod::where('gain_type_id', 1)
            ->orderBy("required_min_price", "DESC")->first();
        $gain_type[] = null; //added bySwadi
        BySwadi: Check if there is a gain type
        if ($gain_type['price'] == null) {
            return response()->json(
                [
                    'success' => false, 'error' => [
                        'The user was created, but there is no price for [gain type id = 1]'
                    ]
                ],
                401
            );
        }

        $input = ['user_id' => $user->id, "gain_method_id" => 1, 'price' => $gain_type['price']];
        $point = PointHistory::create($input);
        $data['point'] =  $point->GainMethod->price;
        */

        $data['current_balance'] = "{$this->add_to_balance($user->id)}";
        return response()->json(['success' => true, 'data' => $data], $this->successStatus);
    }

    /**
     * Add to balance
     *
     * @param $request Illuminate\Http\Request
     *
     * @return int
     */
    public function add_to_balance($id)
    {
        $user = Auth::user();
        // TODO: add this option
        $price_amount = env('openning_balance', 500);
        $wallet = userWallet::create([
            'user_id' => $id,
            'current_balance' =>  $price_amount,
            'openning_balance' =>  $price_amount
            ]
        );
        return $price_amount;
    }

    /**
     * Register api
     *
     * @param $request Illuminate\Http\Request
     *
     * @return \Illuminate\Http\Response
     */
    public function change_image(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image',
        ]);

        if ($validator->fails()) {
            return response()->json(
                ['success' => false, 'error' => $validator->errors()],
                401
            );
        }

        $user = Auth::user();
        $input = $request->all();

        $imageName = Image::savePublicImage($request, 'image', 'users');
        // $custom_folder_name = 'special_product/'.date('y-m-d');
        // $custom_file_name = time().'-'.$request->file('image')->getClientOriginalName();
        // $request->file('image')->storeAs($custom_folder_name, $custom_file_name,'public');

        $user->image = $imageName;
        $user->save();
        return response()->json(['success' => true], $this->successStatus);
    }

    /**
     * Register api
     *
     * @return \Illuminate\Http\Response
     */
    public function delete_image()
    {

        $user = Auth::user();

        $user->image = "users/avatar.jpg";
        $user->save();

        return response()->json(['success' => true], $this->successStatus);
    }
    /**
     * Details api
     *
     * @return \Illuminate\Http\Response
     */
    public function details()
    {
        $user = Auth::user();

        $data = $user;
        if ($user->status == 3) {
            $data['status_message'] = 'blacklist';
        } elseif ($user->status == 1) {
            $data['status_message'] = 'active';
        }

        return response()->json(
            ['success' => true, 'data' => $data],
            $this->successStatus
        );
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */





    /**
     * Update the specified resource in storage.
     *
     * @param $request \Illuminate\Http\Request
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $userId = Auth::id();
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $userId . ',id',
            'phone' => 'required',
            'gender' => 'required',
            'birth_date' => 'required',
            'address' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 401);
        }
        $userId = Auth::id();
        $user = User::findOrFail($userId);
        $input = $request->all();

        $user->name = $input['name'];
        $user->email = $input['email'];
        $user->phone = $input['phone'];
        $user->gender = $input['gender'];
        $user->birth_date = $input['birth_date'];
        $user->address = $input['address'];


        $user->save();
        return response()->json(
            ['success' => true, 'data' => $user],
            $this->successStatus
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\User $user the user of application
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        //
    }

    /**
     * BySwadi[test logout]
     * Delete auth token
     *
     * @return \Illuminate\Http\Response
     */
    public function logout()
    {
        //        dd(Auth::check());
        if (Auth::check()) {
            Auth::user()->token()->revoke();
            return response()->json(['success' => 'logout_success'], 200);
        } else {
            return response()->json(['error' => 'api.something_went_wrong'], 500);
        }
    }

    /**
     * Check the phone if exits
     *
     * @param int $phone user phone
     *
     * @return \Illuminate\Http\Response
     */
    public function phoneCheck($phone)
    {
        $user = User::where('phone', $phone)->first();
        // return response()->json(is_null($user) ? 0 : 1);
        return response()->json(
            ['success' => true, 'data' => is_null($user) ? 0 : 1],
            is_null($user) ? 404 : 200
        );
        if ($user) {
            return response()->json(
                ['success' => true, 'data' => $user],
                200
            );
        } else {
            return response()->json(['error' => 'api.something_went_wrong'], 500);
        }
    }

    /**
     * Save Firebase Token
     *
     * @param mixed $token   from firebase
     * @param mixed $user_id authenticated user
     *
     * @return void
     */
    public function saveFirebaseToken($token, $user_id)
    {
        $fcm['token'] = $token;
        $fcm['user_id'] = $user_id;
        return UserFireBaseToken::create($fcm);
    }

    /**
     * Send SMS to registered phone number
     *
     * @return \Illuminate\Http\Response
     */
    public function sendSmsCode()
    {
        return sendSMS(request('phone'));
    }

    /**
     * Verify SMS to registered phone number
     *
     * @return \Illuminate\Http\Response
     */
    public function verifySmsCode()
    {
        return verifyCode(request('phone'), request('code'));
    }
}
