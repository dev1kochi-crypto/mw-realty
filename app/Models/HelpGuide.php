<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A screen's "!" quick guide (Help Desk): Welcome → What you can do (features) → How it works
 * (steps) → Good to know (tips). `topic` is the key the screen asks for; managed in Admin › Help Desk.
 */
class HelpGuide extends Model
{
    protected $fillable = ['topic', 'title', 'icon', 'intro', 'features', 'steps', 'tips', 'status'];

    protected $casts = [
        'features' => 'array',
        'steps' => 'array',
        'tips' => 'array',
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /** The shape the popups read (CRM HelpButton.vue, portal layouts/_help). */
    public function toGuide(): array
    {
        return [
            'title' => $this->title,
            'icon' => $this->icon,
            'intro' => $this->intro,
            'features' => $this->features ?? [],
            'steps' => $this->steps ?? [],
            'tips' => $this->tips ?? [],
        ];
    }
}
