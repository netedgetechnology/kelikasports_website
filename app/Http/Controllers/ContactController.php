<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        // Honeypot field: quietly accept bot submissions without storing them.
        if ($request->filled('website')) return back()->with('success','Thank you. Your message has been received.');
        $key = 'contact:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) return back()->withErrors(['form'=>'Too many messages were submitted. Please try again later.'])->withInput();
        RateLimiter::hit($key, 600);
        $data = $request->validate([
            'name'=>['required','string','max:120'], 'email'=>['required','email:rfc','max:190'], 'phone'=>['nullable','string','max:40'],
            'category'=>['nullable','string','max:100'], 'message'=>['required','string','max:5000'], 'consent'=>['accepted'],
        ]);
        unset($data['consent']);
        $data['status'] = 'new'; $data['source_page'] = Str::limit((string) $request->input('source_page','contact'),120);
        $data['ip_hash'] = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
        $data['consented_at'] = now();
        $enquiry = Enquiry::create($data);
        $recipient = Setting::read('contact_notification_email');
        if (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            try { Mail::raw("A new KELIKA enquiry was received.\n\nName: {$enquiry->name}\nEmail: {$enquiry->email}\nCategory: {$enquiry->category}\n\nMessage:\n{$enquiry->message}", fn($mail) => $mail->to($recipient)->subject('New KELIKA website enquiry')); }
            catch (\Throwable $exception) { report($exception); }
        }
        return back()->with('success','Thank you. Your message has been received. We will respond as soon as possible.');
    }
}
