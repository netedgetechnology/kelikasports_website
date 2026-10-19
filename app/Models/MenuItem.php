<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = ['menu_id', 'parent_id', 'label', 'url', 'target', 'is_visible', 'sort_order'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean'];
    }

    public function children()
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function parent()
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
