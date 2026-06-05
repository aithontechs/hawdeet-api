<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadingCouncilMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'reading_council_id', 'user_id', 'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function council()
    {
        return $this->belongsTo(ReadingCouncil::class, 'reading_council_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
