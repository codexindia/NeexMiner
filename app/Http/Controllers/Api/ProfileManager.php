<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Berkayk\OneSignal\OneSignalFacade as OneSignal;

class ProfileManager extends Controller
{
    public function GetUser(Request $request)
    {
        $data = $request->user();
        $refer_claimed = false;
        if ($data->referred_by != null || $data->referred_by == "skiped")
            $refer_claimed = true;
        if ($data->name == 'Name' || $data->username == null) {
            $required_profile = true;
        } else {
            $required_profile = false;
        }
        return response()->json([
            'status' => true,
            'data' => $data,
            'refer_claimed' => $refer_claimed,
            'required_profile' => $required_profile,
            'message' => 'done'
        ]);
    }
    public function UpdateUser(Request $request)
    {
        $update = array();
        $user = User::find($request->user()->id);
        if ($request->has('name')) {
            $update['name'] = $request->name;
        }
        if ($request->has('username')) {
            if (User::where('username', $request->username)->first() != null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Username Already Exists'
                ]);
            }
            $update['username'] = $request->username;
        }
        if ($request->has('email')) {
            $update['email'] = $request->email;
        }
        if ($request->has('dob')) {
            $update['date_of_birth'] = $request->dob;
        }
        if ($request->has('gender')) {
            $update['gender'] = $request->gender;
        }
        if ($request->hasFile('profile_pic')) {
            $update['profile_pic'] = Storage::put('public/users/profile', $request->file('profile_pic'));
        }

        $params = [];
        //$params['android_channel_id'] = '7fbda4a1-81c5-4eb6-9936-a80543c5c06f';
        try {
            OneSignal::addParams($params)->sendNotificationToExternalUser(
                "Your Profile Has Been Updated",
                $user->country_code . $user->phone_number,
                $url = null,
                $data = null,
                $buttons = null,
                $schedule = null
            );
        } catch (\Exception $e) {
        }
        $user->update($update);
        return response()->json([
            'status' => true,
            'message' => 'Details Updated SuccessfUlly'
        ]);
    }
}
