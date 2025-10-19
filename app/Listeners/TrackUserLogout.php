<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Auth\Events\Logout;
use App\Models\TimeLog;
use Illuminate\Support\Facades\Log;

class TrackUserLogout
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
    public function handle(Logout $event): void
    {
         $user = $event->user;

        if ($user) {
            // Update the latest log entry for this user
            $latestLog = TimeLog::where('user_id', $user->id)
                ->whereNull('end_time')
                ->latest('start_time')
                ->first();

            if ($latestLog) {
                $latestLog->update([
                    'end_time' => now(),
                    'status' => 'logged_out',
                ]);
            }
        }

        Log::info('Logout event fired for user ID: ' . ($event->user->id ?? 'no user'));
    }
}
