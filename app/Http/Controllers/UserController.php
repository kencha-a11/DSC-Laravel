<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function user(Request $request)
    {
        return $request->user();
    }

    public function index()
    {
        $users = User::with([
            'sales.saleItems' => fn($query) => $query->orderBy('created_at', 'desc'),
            'timeLogs' => fn($query) => $query->orderBy('start_time', 'desc')
        ])->get();

        $formattedUsers = $users->map(function ($user) {
            // Map all time logs
            $timeLogs = $user->timeLogs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'start_time' => $log->start_time,
                    'end_time' => $log->end_time,
                    'status' => $log->status,
                    'current_status' => $log->status === 'logged_in' ? 'Active' : 'Inactive',
                ];
            });

            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim($user->first_name . ' ' . $user->last_name),
                'email' => $user->email,
                'phone_number' => $user->phone_number ?? null,

                // Full time logs
                'time_logs' => $timeLogs,

                // Latest time log for quick access
                'latest_time_log' => $timeLogs->first() ?? [
                    'current_status' => 'Inactive'
                ],

                // 💰 Sales logs
                'sales_logs' => $user->sales->sortByDesc('created_at')->values()->map(function ($sale) {
                    return [
                        'date' => $sale->created_at->format('F d, Y'),
                        'time' => $sale->created_at->format('h:i A'),
                        'items' => $sale->saleItems->sum('quantity'),
                        'total' => $sale->total_amount,
                    ];
                }),
            ];
        });

        return response()->json($formattedUsers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:user,admin',
            'account_status' => 'required|in:activated,deactivated',
            'phone_number' => 'nullable|string|max:20',
        ]);

        $validated['password'] = bcrypt($validated['password']);
        $validated['active_status'] = 'active';

        $user = User::create($validated);
        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role' => 'required|in:user,admin',
            'account_status' => 'required|in:activated,deactivated',
            'phone_number' => 'nullable|string|max:20',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        return response()->json($user);
    }

    // public function destroy(string $id)
    // {
    //     $user = \App\Models\User::find($id);
    //     $user->delete();
    //     return response()->json(['message' => 'User deleted']);
    // }
}
