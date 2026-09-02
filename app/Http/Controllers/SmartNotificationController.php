<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\SmartNotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SmartNotificationController extends Controller
{
    public function __construct(private SmartNotificationService $service)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $this->service->generate($user->id);

        $notifications = Notification::query()
            ->forUser($user->id)
            ->orderBy('read_at', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $unreadCount = Notification::query()
            ->forUser($user->id)
            ->unread()
            ->count();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $this->service->markAsRead($request->user()->id, $id);

        return back()->with('success', __('Notification marked as read.'));
    }

    public function markAllRead(Request $request)
    {
        $this->service->markAllAsRead($request->user()->id);

        return back()->with('success', __('All notifications marked as read.'));
    }
}