<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $fillable = ['name','email','password','role','is_active'];
    protected $hidden = ['password','remember_token'];
    protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed','is_active'=>'boolean']; }
    public function canManageContent(): bool { return $this->is_active && in_array($this->role, ['administrator','editor'], true); }
    public function isAdministrator(): bool { return $this->is_active && $this->role === 'administrator'; }
}
