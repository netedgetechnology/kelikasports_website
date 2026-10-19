<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create() { return view('auth.login'); }

    public function store(Request $request)
    {
        $credentials = $request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        $credentials['is_active'] = true;
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid or the account is inactive.']);
        }
        $request->session()->regenerate();
        abort_unless($request->user()->canManageContent(), 403, 'This account does not have CMS access.');
        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login')->with('status','You have been signed out.');
    }
}
