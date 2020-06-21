<?php
/**
 * TwoFactorAuth web routes
 * php version 7.3.1
 *
 * @category API
 * @package  Moka_APIs
 * @author   BySwadi <muath.ye@gmail.com>
 * @license  IC https://www.infinitecloud.co
 * @link     Moka_Sweets https://www.mokasweets.com/
 */
namespace Stwilio;

use App\User;
use Carbon\Carbon;
use Twilio\Rest\Client;

/**
 * TwoFactorAuth web routes
 * php version 7.3.1
 *
 * @category API
 * @package  Moka_APIs
 * @author   BySwadi <muath.ye@gmail.com>
 * @license  IC https://www.infinitecloud.co
 * @link     Moka_Sweets https://www.mokasweets.com/
 */
class TwoFactorAuth
{
    // /**
    //  * Receiver Number.
    //  */
    // public $receiver_number = 0;

    // /**
    //  * Handle an incoming parameters.
    //  *
    //  * @param string $receiver_number .
    //  *
    //  * @return void
    //  */
    // public function __construct($receiver_number)
    // {
    //     $this->receiver_number = $receiver_number;
    // }

    /**
     * Send sms massage.
     *
     * @param string $receiver_number .
     * @param int    $code            .
     *
     * @return MessageInstance Created MessageInstance
     */
    public function sendMessage($receiver_number = '774133488', $code = '000000')
    {
        // * Look for the user
        if (!$this->userPhoneExists($receiver_number)) {
            return response()->json(
                ['success'=>false,'error' => 'User not found!'],
                401
            );
        }

        // * Set code current user and generate a code for him
        $code = $this->addTokenCode($receiver_number);

        // Your Account SID and Auth Token from twilio.com/console
        $account_sid = env(
            'TWILIO_ACCOUNT_ID',
            'AC01f4b1ea30eb6a44327646282226060c'
        );

        $auth_token = env(
            'TWILIO_AUTH_TOKEN',
            '69bdae91f8f00be5493a03eb46ab1e23'
        );

        // !In production, these should be environment variables. E.g.:
        // $auth_token = $_ENV["TWILIO_AUTH_TOKEN"]

        // A Twilio number you own with SMS capabilities
        $twilio_number = env("TWILIO_NUMBER", "+13252463808");

        $client = new Client($account_sid, $auth_token);
        $client->messages->create(
            // Where to send a text message (your cell phone?)
            "+967".$receiver_number,
            array(
                'from' => $twilio_number,
                'body' => 'رمز التحقق الخاص بك هو في موكا هو' . ' : ' . $code
            )
        );
    }

    /**
     * Check if user phone exists
     *
     * @param string $receiver_number .
     *
     * @return boolean
     */
    public function userPhoneExists($receiver_number)
    {
        return User::where('phone', '=', $receiver_number)->exists();
    }

    /**
     * Check if user token exists
     *
     * @param string $receiver_number .
     *
     * @return boolean
     */
    public function userTokenExists($receiver_number)
    {
        return User::where('phone', '=', $receiver_number)->first()->token_2fa;
    }

    /**
     * Insert code for selected user
     *
     * @param string $receiver_number .
     *
     * @return int
     */
    public function addTokenCode($receiver_number)
    {
        $code = mt_rand(100000, 999999);
        User::where('phone', '=', $receiver_number)
            ->update(
                [
                    // 'token_2fa_at' => Carbon::now(),
                    'token_2fa' => $code,
                    'token_2fa_expire_at' => Carbon::now()->addMinutes(
                        env(
                            'TWILIO_AUTH_TOKEN_EXPIRE_MINUTES', 60*24*30*12
                        )
                    )
                ]
            );
        return $code;
    }

    /**
     * Check code for selected user
     *
     * @param string $receiver_number .
     * @param int    $code            .
     *
     * @return \Illuminate\Http\Response
     */
    public function checkTokenCode($receiver_number, $code)
    {
        // * Look for the user
        if (!$this->userPhoneExists($receiver_number)) {

            return response()->json(
                ['success'=>false,'error' => 'User not found!'],
                401
            );

        }

        // * check if token exists for selected user
        if (!isset(User::where('phone', '=', $receiver_number)->first()->token_2fa)) {

            return response()->json(
                ['success'=>false,'error' => 'User has not token'],
                401
            );

        }

        // * check if token valid for selected user
        if (User::where('phone', '=', $receiver_number)->first()->token_2fa !== $code) {
            return response()->json(
                ['success'=>false,'error' => 'Token is mismatch'],
                401
            );
        }

        // * check if token not expired
        if (Carbon::parse(User::where('phone', '=', $receiver_number)->first()->token_2fa_expire_at)->lessThan(Carbon::now())) {
            return response()->json(
                ['success'=>false,'error' => 'Token is expired'],
                401
            );
        }

        $user = User::where('phone', '=', $receiver_number)
        ->update(
            [
                'token_2fa_at' => Carbon::now(),
            ]
        );
        return response()->json(
            [
                'success'=>true,
                'data' =>
                'Token will expire at: ' . Carbon::parse(
                    User::where('phone', '=', $receiver_number)
                        ->first()
                        ->token_2fa_expire_at
                )
            ],
            200
        );

        // equalTo()
        // notEqualTo()
        // greaterThan()
        // greaterThanOrEqualTo()
        // lessThan()
        // lessThanOrEqualTo()
    }
}
