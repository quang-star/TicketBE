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
            $clientId = (string) config('services.payos.client_id', '');
            $apiKey = (string) config('services.payos.api_key', '');
            $checksumKey = (string) config('services.payos.checksum_key', '');

            if ($clientId === '' || $apiKey === '' || $checksumKey === '') {
                throw new \RuntimeException('PayOS credentials are not configured (PAYOS_CLIENT_ID / PAYOS_API_KEY / PAYOS_CHECKSUM_KEY).');
            }

            $this->payOS = new PayOS($clientId, $apiKey, $checksumKey);
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
            $normalizedItems = array_map(static function (array $item): array {
                return [
                    'name' => mb_substr((string) ($item['name'] ?? 'Ve su kien'), 0, 100),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'price' => (int) ($item['price'] ?? 0),
                ];
            }, $items);

            $data = [
                'orderCode' => (int) $order->order_code,
                'amount' => (int) $order->final_amount,
                'description' => "Don hang #{$order->id}",
                'items' => $normalizedItems,
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
