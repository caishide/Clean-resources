<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use App\Rules\FileTypeValidate;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function profile(): \Illuminate\View\View
    {
        $pageTitle = "Profile Setting";
        $user = auth()->user();
        return view('Template::user.profile_setting', compact('pageTitle','user'));
    }

    public function submitProfile(UpdateProfileRequest $request): \Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();

        // Sanitize input to prevent XSS attacks
        $user->firstname = strip_tags($request->firstname);
        $user->lastname = strip_tags($request->lastname);

        $user->address = strip_tags($request->address);
        $user->city = strip_tags($request->city);
        $user->state = strip_tags($request->state);
        $user->zip = strip_tags($request->zip);

        if ($request->hasFile('image')) {
            try {
                $old = $user->image;
                $user->image = fileUploader($request->image, getFilePath('userProfile'), getFileSize('userProfile'), $old);
            } catch (\Exception $exp) {
                $notify[] = ['error', 'Couldn\'t upload your image'];
                return back()->withNotify($notify);
            }
        }

        $user->save();
        $notify[] = ['success', 'Profile updated successfully'];
        return back()->withNotify($notify);
    }

    public function changePassword(): \Illuminate\View\View
    {
        $pageTitle = 'Change Password';
        return view('Template::user.password', compact('pageTitle'));
    }

    public function submitPassword(Request $request): \Illuminate\Http\RedirectResponse
    {

        // Strong password requirement - minimum 8 characters with complexity
        $passwordValidation = Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised();

        $request->validate([
            'current_password' => 'required',
            'password' => ['required','confirmed',$passwordValidation]
        ], [
            'password.min' => 'Password must be at least 8 characters',
            'password.letters' => 'Password must contain at least one letter',
            'password.mixed_case' => 'Password must contain both uppercase and lowercase letters',
            'password.numbers' => 'Password must contain at least one number',
            'password.symbols' => 'Password must contain at least one special character (!@#$%^&*)',
            'password.uncompromised' => 'This password has been compromised in a data breach. Please choose a different password'
        ]);

        $user = auth()->user();
        if (Hash::check($request->current_password, $user->password)) {
            $password = Hash::make($request->password);
            $user->password = $password;
            $user->save();
            $notify[] = ['success', 'Password changed successfully'];
            return back()->withNotify($notify);
        } else {
            $notify[] = ['error', 'The password doesn\'t match!'];
            return back()->withNotify($notify);
        }
    }
}
