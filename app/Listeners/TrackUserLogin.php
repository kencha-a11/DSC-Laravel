<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Auth\Events\Login;
use App\Models\TimeLog;

class TrackUserLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        TimeLog::create([
            'user_id' => $user->id,
            'start_time' => now(),  // ✅ set start time properly
            'status' => 'logged_in',
        ]);
    }
}
