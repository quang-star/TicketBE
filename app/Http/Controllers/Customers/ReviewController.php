<?php

namespace App\Http\Controllers\Customers;

use App\Helpers\ResponseApi;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Services\ContentModeratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    protected $responseApi;

    public function __construct()
    {
        $this->responseApi = new ResponseApi();
    }

    public function submitReview(Request $request)
    {
        $userId = Auth::id();

        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:orders,id',
            'event_id' => 'required|integer|exists:events,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:2|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $data = $validator->validated();

        $order = Order::where('id', $data['order_id'])
            ->where('user_id', $userId)
            ->first();

        if (!$order) {
            return response()->json([
                'code' => 403,
                'message' => 'Bạn không có quyền đánh giá đơn hàng này.',
            ], 403);
        }

        if ($order->payment_status !== 'paid') {
            return response()->json([
                'code' => 400,
                'message' => 'Chỉ có thể đánh giá đơn hàng đã thanh toán.',
            ], 400);
        }

        $hasEventInOrder = OrderItem::where('order_items.order_id', $order->id)
            ->join('ticket_types', 'order_items.ticket_type_id', '=', 'ticket_types.id')
            ->join('event_schedules', 'ticket_types.schedule_id', '=', 'event_schedules.id')
            ->where('event_schedules.event_id', $data['event_id'])
            ->exists();

        if (!$hasEventInOrder) {
            return response()->json([
                'code' => 400,
                'message' => 'Sự kiện này không thuộc đơn hàng của bạn.',
            ], 400);
        }

        if (Review::where('order_id', $order->id)->exists()) {
            return response()->json([
                'code' => 409,
                'message' => 'Đơn hàng này đã được đánh giá trước đó.',
            ], 409);
        }

        // Kiểm duyệt nội dung bình luận
        $aiCheck = app(ContentModeratorService::class)->checkContent($data['comment']);
        $status = ($aiCheck['is_safe'] ?? true) ? 'active' : 'pending';
        $reason = $aiCheck['reason'] ?? null;

        $review = Review::create([
            'order_id' => $order->id,
            'event_id' => $data['event_id'],
            'user_id' => $userId,
            'rating' => $data['rating'],
            'comment' => trim($data['comment']),
            'status' => $status,
            'admin_note' => $status === 'pending' ? $reason : null,
        ]);

        if ($status === 'active') {
            return $this->responseApi->success([
                'message' => 'Gửi đánh giá thành công!',
                'data' => $review,
            ]);
        }

        return $this->responseApi->success([
            'message' => 'Đánh giá đang chờ duyệt do chứa nội dung cần xem xét' . ($reason ? ': ' . $reason : '.'),
            'data' => $review,
        ]);
    }
}
