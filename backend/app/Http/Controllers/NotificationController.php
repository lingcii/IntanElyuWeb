<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/{role}/notifications/recent
     * Returns the 10 most recent notifications for the authenticated user.
     */
    public function recent(Request $request): JsonResponse
    {
        $userId = $request->session()->get('user_id');

        $notifications = Notification::where('user_id', $userId)
            ->latest()
            ->take(10)
            ->get();

        $unreadCount = Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * GET /api/{role}/notifications
     * Paginated notifications with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->session()->get('user_id');
        $perPage = min(max((int) $request->get('per_page', 20), 5), 100);

        $query = Notification::where('user_id', $userId)->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        if ($request->filled('is_read')) {
            $query->where('is_read', $request->get('is_read') === 'true' || $request->get('is_read') === '1');
        }

        if ($request->filled('search')) {
            $search = '%' . $request->get('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                  ->orWhere('message', 'like', $search)
                  ->orWhere('spot_name', 'like', $search)
                  ->orWhere('actor_name', 'like', $search);
            });
        }

        $paginator = $query->paginate($perPage);

        $unreadCount = Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'notifications' => $paginator->items(),
            'unread_count'  => $unreadCount,
            'pagination'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * PATCH /api/{role}/notifications/{id}/read
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $userId = $request->session()->get('user_id');

        $notification = Notification::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return response()->json(['error' => 'Notification not found.'], 404);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    /**
     * PATCH /api/{role}/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $userId = $request->session()->get('user_id');

        Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/{role}/notifications/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = $request->session()->get('user_id');

        $notification = Notification::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return response()->json(['error' => 'Notification not found.'], 404);
        }

        $notification->delete();

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/{role}/notifications/clear-all
     */
    public function clearAll(Request $request): JsonResponse
    {
        $userId = $request->session()->get('user_id');

        Notification::where('user_id', $userId)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/{role}/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $userId = $request->session()->get('user_id');

        $count = Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    /**
     * GET /api/{role}/notifications/stream
     * SSE endpoint for real-time notifications.
     */
    public function stream(Request $request)
    {
        $userId = $request->session()->get('user_id');
        $lastId = (int) $request->query('last_id', 0);

        return response()->stream(function () use ($userId, $lastId) {
            $startTime = time();
            $currentLastId = $lastId;

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $newNotifications = Notification::where('user_id', $userId)
                    ->where('id', '>', $currentLastId)
                    ->orderBy('id', 'asc')
                    ->get();

                if ($newNotifications->isNotEmpty()) {
                    foreach ($newNotifications as $notification) {
                        echo "id: {$notification->id}\n";
                        echo "event: notification\n";
                        echo "data: " . json_encode($notification) . "\n\n";
                        $currentLastId = $notification->id;
                    }

                    $unreadCount = Notification::where('user_id', $userId)
                        ->where('is_read', false)
                        ->count();

                    echo "event: count\n";
                    echo "data: " . json_encode(['unread_count' => $unreadCount]) . "\n\n";

                    ob_flush();
                    flush();
                }

                sleep(2);

                if (time() - $startTime > 5) {
                    echo "retry: 3000\n\n";
                    ob_flush();
                    flush();
                    break;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
