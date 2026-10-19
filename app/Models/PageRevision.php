<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageRevision extends Model
{
    protected $fillable = ['page_id', 'user_id', 'revision_label', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
