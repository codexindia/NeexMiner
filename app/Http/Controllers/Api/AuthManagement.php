<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerficationCodes;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as Twilio;
class AuthManagement extends Controller
{
    public function check_username(Request $request)
    {
        $request->validate([
            'username' => 'required',
        ]);
        $check = User::where('username', $request->username);
        if ($check->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'username not available'
            ]);
        } else {
            return response()->json([
                'status' => true,
                'message' => 'username is available'
            ]);
        }
    }
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|numeric|min:5',
            'country_code' => 'required|numeric',
        ]);
        $temp = [
            'country_code' => $request->country_code
        ];
        $this->genarateotp($request->phone,$temp);
        return response()->json([
            'status' => true,
            'message' => 'otp send successfully',
        ]);
    }

    public function loginOrSignup(Request $request)
    {
        $request->validate([
            'otp' => 'required|numeric|digits:6',
            'country_code' => 'required|numeric',
            'phone' => 'required|numeric'
        ]);
        $check_otp = $this->VerifyOTP($request->phone, $request->otp);
        if ($check_otp) {
            $checkphone = User::where([
                'phone_number'=>$request->phone,
                'country_code' => $request->country_code
            ])->first();
            if ($checkphone) {

                $token = $checkphone->createToken('auth_token')->plainTextToken;
                return response()->json([
                    'status' => true,
                    'message' => 'OTP Verified  Successfully (Login)',
                    'token' => $token,
                ]);
            } else {
                $temp = json_decode($check_otp->temp);
                $newuser = User::create([
                    'phone_number' => $request->phone,
                    'country_code' =>  $temp->country_code,
                    'name' => 'name',
                    'refer_code' => 'NEX' . rand('100000', '999999'),
                    'coin' => 0,
                ]);
             

                $token = $newuser->createToken('auth_token')->plainTextToken;
                return response()->json([
                    'status' => true,
                    'message' => 'OTP Verified  Successfully (new user)',
                    'token' => $token,
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Your OTP Is Invalid'
            ]);
        }
    }

    private function VerifyOTP($phone, $otp)
    {
        //this for test otp
        if ($otp == "913432") {
            $checkotp = VerficationCodes::where('phone', $phone)
                ->latest()->first();
            VerficationCodes::where('phone', $phone)->delete();
            return $checkotp;
        }
        //end for test otp
        $checkotp = VerficationCodes::where('phone', $phone)
            ->where('otp', $otp)->latest()->first();
        $now = Carbon::now();
        if (!$checkotp) {
            return 0;
        } elseif ($checkotp && $now->isAfter($checkotp->expire_at)) {

            return 0;
        } else {
            $device = 'Auth_Token';
            VerficationCodes::where('phone', $phone)->delete();
            return $checkotp;
        }
    }
  
    public function resend(Request $request)
    {
        $request->validate([
            'phone' => 'required|numeric|min_digits:7|max_digits:15',
        ], [
            
            'phone.min_digits' => 'You Have Entered An Invalid Mobile Number',
            'phone.max_digits' => 'You Have Entered An Invalid Mobile Number'
        ]);
        $phone = $request->phone;

        if ($this->genarateotp($phone)) {
            return response()->json([
                'status' => true,
                'message' => 'Sms Sent Successfully',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Sms Could Not Be Sent',
            ]);
        }
    }
    private function genarateotp($number, $temp = [])
    {
        $otpmodel = VerficationCodes::where('phone', $number);

        if ($otpmodel->count() > 10) {
            return false;
        }
        $checkotp = $otpmodel->latest()->first();
        $now = Carbon::now();

        if ($checkotp && $now->isBefore($checkotp->expire_at)) {

            $otp = $checkotp->otp;
            $checkotp->update([
                'temp' => json_encode($temp),
            ]);
        } else {
            $otp = rand('100000', '999999');
            //$otp = 123456;
            VerficationCodes::create([
                'temp' => json_encode($temp),
                'phone' => $number,
                'otp' => $otp,
                'expire_at' => Carbon::now()->addMinute(10)
            ]);
        };
        $receiverNumber =  '+' . $temp['country_code'] . $number;
        $message = "Hello\nNeexMiner Verification OTP is " . $otp;


        try {
            $account_sid = env("TWILIO_SID");
            $auth_token = env("TWILIO_TOKEN");
            $twilio_number = env("TWILIO_FROM");
            $client = new Twilio($account_sid, $auth_token);
            $client->messages->create($receiverNumber, [
                'from' => $twilio_number,
                'body' => $message
            ]);

            return true;
        } catch (Exception $e) {
            return 0;
        }
    }
}
