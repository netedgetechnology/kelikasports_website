<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
class PasswordResetLinkController extends Controller
{
 public function create(){return view('auth.passwords.email');}
 public function store(Request $request){$request->validate(['email'=>['required','email']]);Password::sendResetLink($request->only('email'));
        // The same answer whether or not the address has an account (or was just sent a link), so the form cannot be
        // used to find out which email addresses have CMS access.
        return back()->with('status','If this email address belongs to a CMS account, a password reset link has been sent to it.');}
}
