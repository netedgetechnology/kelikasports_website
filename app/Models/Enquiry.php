<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'category', 'message', 'status', 'internal_notes',
        'source_page', 'ip_hash', 'consented_at',
    ];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime'];
    }
}
