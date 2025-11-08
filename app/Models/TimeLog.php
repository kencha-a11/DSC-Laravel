<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TimeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_time',
        'end_time',
        'duration',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    // 🧭 Automatically cast timestamps to the user’s current timezone
    protected function serializeDate(\DateTimeInterface $date): string
    {
        $tz = config('app.user_timezone', config('app.timezone', 'Asia/Manila'));
        return Carbon::parse($date)->setTimezone($tz)->toIso8601String();
    }

    // 🕒 Accessor: always return timestamps in the user’s timezone
    public function getStartTimeAttribute($value)
    {
        if (!$value) return null;
        $tz = config('app.user_timezone', config('app.timezone', 'Asia/Manila'));
        return Carbon::parse($value)->setTimezone($tz);
    }

    public function getEndTimeAttribute($value)
    {
        if (!$value) return null;
        $tz = config('app.user_timezone', config('app.timezone', 'Asia/Manila'));
        return Carbon::parse($value)->setTimezone($tz);
    }

    // 👥 Relationship to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
