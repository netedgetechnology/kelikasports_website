<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name=trim((string) env('KELIKA_ADMIN_NAME',''));
        $email=strtolower(trim((string) env('KELIKA_ADMIN_EMAIL','')));
        $password=(string) env('KELIKA_ADMIN_PASSWORD','');
        if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<14) return;
        User::query()->updateOrCreate(['email'=>$email],['name'=>$name,'password'=>Hash::make($password),'role'=>'administrator','is_active'=>true]);
    }
}
