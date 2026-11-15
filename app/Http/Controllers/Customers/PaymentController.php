<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Jobs\SendTicketEmailJob;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Services\PayOSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    private function restoreStock(Order $order): void
    {
        $orderItems = OrderItem::where('order_id', $order->id)->get();

        foreach ($orderItems as $item) {
            TicketType::where('id', $item->ticket_type_id)
                ->increment('available_quantity', $item->quantity);
        }
    }

    private function clearCart($userId, Order $order): void
    {
        $cart = Cart::where('user_id', $userId)->first();
        if (!$cart) {
            return;
        }

        $purchasedTicketTypeIds = OrderItem::where('order_id', $order->id)
            ->pluck('ticket_type_id')
            ->toArray();

        if (empty($purchasedTicketTypeIds)) {
            return;
        }

        CartItem::where('cart_id', $cart->id)
            ->whereIn('ticket_type_id', $purchasedTicketTypeIds)
            ->delete();
    }

    private function finalizePaidOrder(Order $order): void
    {
        $shouldDispatch = false;

        DB::transaction(function () use ($order, &$shouldDispatch) {
            $locked = Order::where('id', $order->id)->lockForUpdate()->first();
            if (!$locked || $locked->payment_status !== 'pending') {
                return;
            }

            $locked->update(['payment_status' => 'paid']);
            $this->clearCart($locked->user_id, $locked);
            $shouldDispatch = true;
        });

        if ($shouldDispatch) {
            $order->refresh();
            $order->loadMissing('user');
            SendTicketEmailJob::dispatch($order);
        }
    }

    private function markOrderUnpaid(Order $order, string $newStatus): void
    {
        DB::transaction(function () use ($order, $newStatus) {
            $locked = Order::where('id', $order->id)->lockForUpdate()->first();
            if (!$locked || $locked->payment_status !== 'pending') {
                return;
            }

            $locked->update(['payment_status' => $newStatus]);
            $this->restoreStock($locked);
        });
    }

    public function createOrder(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['code' => 401, 'message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.ticket_type_id' => 'required|integer|exists:ticket_types,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'return_path' => 'nullable|string|max:500',
            'payment_method' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $params = $validator->validated();

        DB::beginTransaction();
        try {
            $order = Order::create([
                'user_id' => $userId,
                'order_code' => 'TMP-' . uniqid('', true),
                'total_amount' => 0,
                'discount_amount' => 0,
                'final_amount' => 0,
                'payment_status' => 'pending',
                'return_path' => $params['return_path'] ?? '',
                'payment_method' => $params['payment_method'] ?? 'payos',
            ]);

            $orderCode = (string) ((int) ($order->id * 1000) + random_int(100, 999));
            $order->update(['order_code' => $orderCode]);

            $orderItemsData = [];
            $total = 0;

            foreach ($params['items'] as $item) {
                $qty = (int) $item['quantity'];

                $ticketType = TicketType::where('id', $item['ticket_type_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$ticketType) {
                    DB::rollBack();
                    return response()->json(['code' => 404, 'message' => 'Không tìm thấy vé'], 404);
                }

                if ($ticketType->available_quantity < $qty) {
                    DB::rollBack();
                    return response()->json([
                        'code' => 400,
                        'message' => "Vé '{$ticketType->name}' chỉ còn {$ticketType->available_quantity}, không đủ.",
                    ], 400);
                }

                $ticketType->decrement('available_quantity', $qty);

                $subtotal = $ticketType->base_price * $qty;
                $total += $subtotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $qty,
                    'unit_price' => $ticketType->base_price,
                    'subtotal' => $subtotal,
                ]);

                $orderItemsData[] = [
                    'name' => $ticketType->name,
                    'quantity' => $qty,
                    'price' => (float) $ticketType->base_price,
                ];
            }

            $order->update(['total_amount' => $total, 'final_amount' => $total]);

            if ($total <= 0) {
                DB::commit();
                $this->finalizePaidOrder($order);

                return response()->json([
                    'code' => 200,
                    'message' => 'Đơn hàng miễn phí đã được xác nhận',
                    'data' => [
                        'order_id' => $order->id,
                        'order_code' => $order->order_code,
                        'checkout_url' => null,
                    ],
                ]);
            }

            Log::info('payload_payos', ['order_id' => $order->id, 'order_code' => $orderCode]);

            $paymentLink = PayOSService::createPaymentLink($order, $orderItemsData);

            if (!$paymentLink || !isset($paymentLink['checkoutUrl'])) {
                DB::rollBack();
                return response()->json([
                    'code' => 500,
                    'message' => 'Không thể khởi tạo link thanh toán PayOS.',
                ], 500);
            }

            DB::commit();

            return response()->json([
                'code' => 200,
                'message' => 'Order tạo thành công',
                'data' => [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'checkout_url' => $paymentLink['checkoutUrl'],
                ],
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            return response()->json(['code' => 500, 'message' => 'Lỗi server'], 500);
        }
    }

    public function checkPaymentStatus(Request $request)
    {
        $orderCode = $request->input('order_code');
        if (!$orderCode) {
            return response()->json(['code' => 400, 'message' => 'Thiếu order_code'], 400);
        }

        $order = Order::where('order_code', (string) $orderCode)
            ->where('user_id', Auth::id())
            ->first();

        if (!$order) {
            return response()->json(['code' => 404, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        try {
            $data = PayOSService::getPaymentStatus($orderCode);
            if (!$data) {
                return response()->json(['code' => 500, 'message' => 'Lỗi lấy trạng thái'], 500);
            }

            $status = $data['status'] ?? 'FAILED';
            if ($status === 'PAID') {
                $this->finalizePaidOrder($order);
            } elseif ($status !== 'PENDING') {
                $this->markOrderUnpaid($order, $status === 'CANCELLED' ? 'cancelled' : 'failed');
            }

            return response()->json(['code' => 200, 'message' => 'Success', 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    public function payOSReturn(Request $request)
    {
        $payosOrderCode = $request->input('orderCode');
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        if (!$payosOrderCode) {
            return redirect("$frontendUrl/cart?status=error&message=missing_code");
        }

        $order = Order::where('order_code', (string) $payosOrderCode)->with('user')->first();
        if (!$order) {
            return redirect("$frontendUrl/cart?status=error&message=order_not_found");
        }

        if ($order->payment_status === 'paid') {
            return redirect("$frontendUrl/cart?status=success&id={$order->id}");
        }

        try {
            $paymentInfo = PayOSService::getPaymentStatus($payosOrderCode);

            if (!$paymentInfo || ($paymentInfo['status'] ?? '') !== 'PAID') {
                $this->markOrderUnpaid($order, 'failed');
                return redirect("$frontendUrl/cart?status=failed&id={$order->id}");
            }

            $this->finalizePaidOrder($order);

            return redirect("$frontendUrl/cart?status=success&id={$order->id}");
        } catch (\Throwable $e) {
            Log::error("PayOS Return Error: " . $e->getMessage());
            return redirect("$frontendUrl/cart?status=error&message=system_error&id={$order->id}");
        }
    }

    public function payOSWebhook(Request $request)
    {
        $verifiedData = PayOSService::verifyWebhookData($request->all());
        if (!$verifiedData || empty($verifiedData['orderCode'])) {
            return response()->json(['code' => 400, 'message' => 'Invalid webhook signature'], 400);
        }

        $order = Order::where('order_code', (string) $verifiedData['orderCode'])
            ->with('user')
            ->first();

        if (!$order) {
            return response()->json(['code' => 404, 'message' => 'Order not found'], 404);
        }

        if (($verifiedData['code'] ?? '') === '00') {
            $this->finalizePaidOrder($order);
        }

        return response()->json(['code' => 200, 'message' => 'OK']);
    }

    public function payOSCancel(Request $request)
    {
        $payosOrderCode = $request->input('orderCode');
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        if (!$payosOrderCode) {
            return redirect("$frontendUrl/cart");
        }

        $order = Order::where('order_code', (string) $payosOrderCode)->first();
        if (!$order) {
            return redirect("$frontendUrl/cart");
        }

        if ($order->payment_status === 'paid') {
            return redirect("$frontendUrl/cart?status=success&id={$order->id}");
        }

        $this->markOrderUnpaid($order, 'cancelled');

        return redirect("$frontendUrl/cart?status=cancelled&id={$order->id}");
    }
}
