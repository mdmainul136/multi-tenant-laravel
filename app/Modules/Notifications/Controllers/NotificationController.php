<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Notifications\{StoreNotification, NotificationTemplate};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = StoreNotification::notExpired()->recent();

        if ($request->boolean('unread_only')) $query->unread();
        if ($request->filled('type'))         $query->where('type', $request->type);

        if ($request->filled('notifiable_type') && $request->filled('notifiable_id')) {
            $query->where(fn($q) => $q
                ->where('is_broadcast', true)
                ->orWhere(fn($q2) => $q2
                    ->where('notifiable_type', $request->notifiable_type)
                    ->where('notifiable_id', $request->notifiable_id)));
        } else {
            $query->where('is_broadcast', true);
        }

        $unreadCount = (clone $query)->unread()->count();
        return response()->json([
            'success'      => true,
            'unread_count' => $unreadCount,
            'data'         => $query->paginate(min((int)$request->get('per_page', 20), 100)),
        ]);
    }

    public function stats()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total'   => StoreNotification::count(),
                'unread'  => StoreNotification::unread()->count(),
                'by_type' => StoreNotification::select('type', DB::raw('COUNT(*) as count'))->groupBy('type')->pluck('count', 'type'),
                'today'   => StoreNotification::whereDate('created_at', today())->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'            => 'required|string|max:100',
            'title'           => 'required|string|max:255',
            'message'         => 'required|string',
            'data'            => 'nullable|array',
            'icon'            => 'nullable|string|max:50',
            'color'           => 'nullable|string|max:20',
            'action_url'      => 'nullable|string|max:255',
            'notifiable_type' => 'nullable|string|max:100',
            'notifiable_id'   => 'nullable|integer',
            'is_broadcast'    => 'nullable|boolean',
            'expires_at'      => 'nullable|date',
        ]);

        $dto = \App\Modules\Notifications\DTOs\NotificationDTO::fromRequest($validated);
        $notification = app(\App\Modules\Notifications\Actions\SendNotificationAction::class)->execute($dto);

        return response()->json(['success' => true, 'data' => $notification], 201);
    }

    public function broadcast(Request $request)
    {
        $validated = $request->validate([
            'type'       => 'required|string|max:100',
            'title'      => 'required|string|max:255',
            'message'    => 'required|string',
            'action_url' => 'nullable|string',
        ]);

        $dto = new \App\Modules\Notifications\DTOs\NotificationDTO(
            type: $validated['type'],
            title: $validated['title'],
            message: $validated['message'],
            action_url: $validated['action_url'] ?? null,
            is_broadcast: true
        );

        $notification = app(\App\Modules\Notifications\Actions\SendNotificationAction::class)->execute($dto);

        return response()->json(['success' => true, 'data' => $notification], 201);
    }

    public function sendFromTemplate(Request $request)
    {
        $request->validate([
            'template_key'    => 'required|string',
            'variables'       => 'nullable|array',
            'notifiable_type' => 'nullable|string',
            'notifiable_id'   => 'nullable|integer',
            'action_url'      => 'nullable|string',
        ]);

        $notification = app(\App\Modules\Notifications\Actions\SendTemplateNotificationAction::class)->execute(
            $request->template_key,
            $request->variables ?? [],
            $request->only(['notifiable_type', 'notifiable_id', 'action_url'])
        );

        return response()->json(['success' => true, 'data' => $notification], 201);
    }

    public function markRead($id)
    {
        StoreNotification::findOrFail($id)->markRead();
        return response()->json(['success' => true, 'message' => 'Marked as read']);
    }

    public function markAllRead()
    {
        StoreNotification::unread()->where('is_broadcast', true)->update(['read_at' => now()]);
        return response()->json(['success' => true, 'message' => 'All marked as read']);
    }

    public function destroy($id)
    {
        StoreNotification::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Deleted']);
    }

    public function cleanup(Request $request)
    {
        $days  = (int) $request->get('days', 30);
        $count = StoreNotification::whereNotNull('read_at')->where('created_at', '<', now()->subDays($days))->delete();
        return response()->json(['success' => true, 'message' => "{$count} notifications cleaned up"]);
    }

    // ── Templates ──────────────────────────────────────────────────────────
    public function getTemplates()
    {
        return response()->json(['success' => true, 'data' => NotificationTemplate::orderBy('key')->get()]);
    }

    public function storeTemplate(Request $request)
    {
        return response()->json(['success' => true, 'data' => NotificationTemplate::create($request->validate([
            'key'            => 'required|string|max:100|unique:tenant_dynamic.ec_notification_templates,key',
            'name'           => 'required|string|max:255',
            'title_template' => 'required|string|max:255',
            'body_template'  => 'required|string',
            'channel'        => 'nullable|in:in_app,email,sms,push',
            'icon'           => 'nullable|string|max:50',
            'color'          => 'nullable|string|max:20',
            'is_active'      => 'nullable|boolean',
        ]))], 201);
    }

    public function updateTemplate(Request $request, $id)
    {
        $template = NotificationTemplate::findOrFail($id);
        $template->update($request->validate([
            'name'           => 'sometimes|required|string|max:255',
            'title_template' => 'sometimes|required|string|max:255',
            'body_template'  => 'sometimes|required|string',
            'channel'        => 'nullable|in:in_app,email,sms,push',
            'is_active'      => 'nullable|boolean',
        ]));
        return response()->json(['success' => true, 'data' => $template->fresh()]);
    }
}
