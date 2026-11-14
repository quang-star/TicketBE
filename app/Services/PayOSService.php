<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use PayOS\PayOS;

class PayOSService
{
    private ?PayOS $payOS = null;

    private function client(): PayOS
    {
        if ($this->payOS === null) {
            $this->payOS = new PayOS(
                env('PAYOS_CLIENT_ID', ''),
                env('PAYOS_API_KEY', ''),
                env('PAYOS_CHECKSUM_KEY', '')
            );
        }

        return $this->payOS;
    }

    /**
     * Instance method: Tạo link thanh toán online (có thể mock trong test)
     */
    public function createLink(Order $order, array $items): ?array
    {
        $returnUrlWithId = url('api/payment/payos/return') . '?ref_id=' . $order->id;
        $cancelUrlWithId = url('api/payment/payos/cancel') . '?ref_id=' . $order->id;

        try {
            $data = [
                'orderCode' => (int) $order->order_code,
                'amount' => (int) $order->final_amount,
                'description' => "Thanh toán đơn hàng #{$order->id}",
                'items' => $items,
                'returnUrl' => $returnUrlWithId,
                'cancelUrl' => $cancelUrlWithId,
                'expiredAt' => Carbon::now()->addMinutes(10)->timestamp,
            ];

            $result = $this->client()->createPaymentLink($data);
            Log::info('payos_response', $result);

            return $result;
        } catch (\Throwable $th) {
            Log::error("payos_error", ['msg' => $th->getMessage()]);
            return null;
        }
    }

    /**
     * Instance method: Lấy trạng thái thanh toán (có thể mock trong test)
     */
    public function fetchStatus(string|int $orderCode): ?array
    {
        try {
            $response = $this->client()->getPaymentLinkInformation($orderCode);
            Log::info("payos_status", $response);
            return $response;
        } catch (\Throwable $th) {
            Log::error("payos_get_error", ['msg' => $th->getMessage()]);
            return null;
        }
    }

    /**
     * Instance method: Xác thực dữ liệu webhook từ PayOS
     */
    public function verifyWebhook(array $webhookBody): ?array
    {
        try {
            return $this->client()->verifyPaymentWebhookData($webhookBody);
        } catch (\Throwable $th) {
            Log::warning("payos_webhook_invalid", ['msg' => $th->getMessage()]);
            return null;
        }
    }

    public static function createPaymentLink(Order $order, array $items): ?array
    {
        return app(self::class)->createLink($order, $items);
    }

    public static function getPaymentStatus(string|int $orderId): ?array
    {
        return app(self::class)->fetchStatus($orderId);
    }

    public static function verifyWebhookData(array $webhookBody): ?array
    {
        return app(self::class)->verifyWebhook($webhookBody);
    }
}
