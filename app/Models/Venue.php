<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use HasFactory;
    protected $table = 'venues';
    protected $fillable = [
        'name',
        'city',
        'district',
        'ward',
        'street',
    ];

    protected $appends = ['address'];

    public function getAddressAttribute()
    {
        $raw = [$this->street, $this->ward, $this->district, $this->city];
        $parts = [];
        foreach ($raw as $part) {
            $trimmed = trim((string) ($part ?? ''));
            if ($trimmed !== '' && !in_array($trimmed, $parts, true)) {
                $parts[] = $trimmed;
            }
        }
        return count($parts) > 0 ? implode(', ', $parts) : ($this->attributes['address'] ?? '');
    }
    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
