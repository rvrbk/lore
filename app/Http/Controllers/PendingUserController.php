<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Telegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where an admin approves or rejects the accounts that registered.
 */
class PendingUserController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('admin.users', ['pendingUsers' => User::pending()->oldest()->get()]);
    }

    public function approve(Request $request, User $user, Telegram $telegram): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $user->approve();
        $telegram->sendTo($user, '✅ <b>Lore</b>: your account was approved. You can log in now.');

        return back()->with('status', "{$user->username} is approved.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_if($user->isApproved(), 422, 'Only accounts waiting for approval can be rejected.');

        $user->delete();

        return back()->with('status', "{$user->username} was rejected and removed.");
    }
}
