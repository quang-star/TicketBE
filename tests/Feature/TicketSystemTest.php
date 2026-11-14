<?php

namespace Tests\Feature;

use App\Jobs\SendMailForgotPassword;
use App\Jobs\SendMailRegisterAccount;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventSchedule;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organizer;
use App\Models\Report;
use App\Models\Review;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\UserVerification;
use App\Models\Venue;
use App\Services\ContentModeratorService;
use App\Services\PayOSService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Mockery;
use Tests\TestCase;

class TicketSystemTest extends TestCase
{
    use RefreshDatabase;

    private function seedPublishedEvent(int $stock = 10, int $price = 150000): array
    {
        $orgUser = User::create([
            'name' => 'Org User',
            'email' => 'org_' . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ORGANIZER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $organizer = Organizer::create([
            'user_id' => $orgUser->id,
            'organization_name' => 'Test Organizer',
            'description' => 'Org desc',
            'verified' => true,
        ]);

        $venue = Venue::create([
            'name' => 'Nhà hát Hòa Bình',
            'city' => 79,
            'district' => 760,
            'ward' => 26734,
            'street' => '240 Đường 3/2',
        ]);

        $category = EventCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'Nhạc sống']
        );

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'venue_id' => $venue->id,
            'category_id' => $category->id,
            'event_name' => 'Live Concert Night',
            'description' => 'Great live music',
            'status' => Event::STATUS_PUBLISHED,
        ]);
        $event->categories()->syncWithoutDetaching([$category->id]);

        $schedule = EventSchedule::create([
            'event_id' => $event->id,
            'start_datetime' => Carbon::now()->addDays(2),
            'end_datetime' => Carbon::now()->addDays(2)->addHours(3),
            'status' => EventSchedule::STATUS_UPCOMING,
        ]);

        $ticketType = TicketType::create([
            'schedule_id' => $schedule->id,
            'name' => 'VIP',
            'base_price' => $price,
            'total_quantity' => $stock,
            'available_quantity' => $stock,
            'status' => TicketType::STATUS_ACTIVE,
        ]);

        return compact('orgUser', 'organizer', 'venue', 'category', 'event', 'schedule', 'ticketType');
    }

    public function test_unauthenticated_and_non_admin_are_blocked_from_protected_routes(): void
    {
        $this->getJson('/api/get-cart')->assertStatus(401);
        $this->postJson('/api/create-order', [])->assertStatus(401);
        $this->postJson('/api/create-event', [])->assertStatus(401);
        $this->postJson('/api/update-profile', [])->assertStatus(401);
        $this->getJson('/api/admin/dashboard-stats')->assertStatus(401);
        $this->getJson('/api/admin/reviews')->assertStatus(401);
        $this->getJson('/api/admin/reports')->assertStatus(401);

        $customer = User::create([
            'name' => 'Normal User',
            'email' => 'customer@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_CUSTOMER,
            'status' => User::STATUS_ACTIVE,
        ]);

        Passport::actingAs($customer);
        $this->getJson('/api/admin/dashboard-stats')->assertStatus(403);
        $this->getJson('/api/admin/reviews')->assertStatus(403);
        $this->getJson('/api/admin/reports')->assertStatus(403);
        $this->getJson('/api/admin/events/pending')->assertStatus(403);
        $this->postJson('/api/admin/events/1/approve')->assertStatus(403);
    }

    public function test_register_verify_resend_and_forgot_reset_password_flow(): void
    {
        Queue::fake();

        $this->postJson('/api/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password123',
        ])->assertStatus(200)->assertJsonPath('code', 200);

        Queue::assertPushed(SendMailRegisterAccount::class);

        $user = User::where('email', 'alice@example.com')->firstOrFail();

        UserVerification::create([
            'user_id' => $user->id,
            'code' => 'ABC123',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $this->postJson('/api/verifyAccount', [
            'email' => 'alice@example.com',
            'code' => 'ABC123',
        ])->assertStatus(200);

        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->postJson('/api/forgot-password', [
            'email' => 'alice@example.com',
        ])->assertStatus(200);

        Queue::assertPushed(SendMailForgotPassword::class);

        UserVerification::create([
            'user_id' => $user->id,
            'code' => 'RST999',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $this->postJson('/api/reset-password', [
            'email' => 'alice@example.com',
            'code' => 'RST999',
            'password' => 'newpass123',
        ])->assertStatus(200);

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    public function test_cart_order_payos_return_webhook_and_cancel_flow(): void
    {
        Mail::fake();
        $seeded = $this->seedPublishedEvent(stock: 5, price: 200000);
        /** @var TicketType $ticketType */
        $ticketType = $seeded['ticketType'];

        $customer = User::create([
            'name' => 'Buyer',
            'email' => 'buyer@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_CUSTOMER,
            'status' => User::STATUS_ACTIVE,
        ]);

        Passport::actingAs($customer);

        // Empty cart returns [] cleanly without null error
        $this->getJson('/api/get-cart')->assertStatus(200)->assertJsonPath('data', []);

        // Cannot exceed available_quantity
        $this->postJson('/api/add-to-cart', [
            'ticket_id' => $ticketType->id,
            'quantity' => 10,
        ])->assertStatus(200)->assertJsonPath('code', 400);

        $this->postJson('/api/add-to-cart', [
            'ticket_id' => $ticketType->id,
            'quantity' => 2,
        ])->assertStatus(200);

        $mockPayos = Mockery::mock(PayOSService::class);
        $mockPayos->shouldReceive('createLink')
            ->once()
            ->andReturn(['checkoutUrl' => 'https://pay.payos.vn/mock-checkout']);
        $mockPayos->shouldReceive('fetchStatus')
            ->once()
            ->andReturn(['status' => 'PAID']);
        $this->app->instance(PayOSService::class, $mockPayos);

        $orderRes = $this->postJson('/api/create-order', [
            'items' => [
                ['ticket_type_id' => $ticketType->id, 'quantity' => 2],
            ],
            'payment_method' => 'payos',
        ])->assertStatus(200);

        $orderId = $orderRes->json('data.order_id');
        $orderCode = $orderRes->json('data.order_code');
        $this->assertEquals(3, $ticketType->fresh()->available_quantity);

        $this->get('/api/payment/payos/return?orderCode=' . $orderCode)
            ->assertRedirectContains('/cart?status=success&id=' . $orderId);

        $this->assertEquals('paid', Order::find($orderId)->payment_status);
        $this->assertEquals(2, Ticket::count());
        $this->assertEmpty($this->getJson('/api/get-cart')->json('data'));

        // Order list & order detail QR codes
        $this->getJson('/api/my-orders')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.0.events.0.schedules.0.tickets.0.issuedTickets');

        $this->getJson('/api/orders/' . $orderId)
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.tickets.0.ticketCodes');

        // Separate order cancel restores stock idempotently
        $mockPayos2 = Mockery::mock(PayOSService::class);
        $mockPayos2->shouldReceive('createLink')
            ->once()
            ->andReturn(['checkoutUrl' => 'https://pay.payos.vn/mock-cancel']);
        $this->app->instance(PayOSService::class, $mockPayos2);

        $cancelOrderRes = $this->postJson('/api/create-order', [
            'items' => [
                ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
            ],
        ])->assertStatus(200);

        $cancelCode = $cancelOrderRes->json('data.order_code');
        $this->assertEquals(2, $ticketType->fresh()->available_quantity);

        $this->get('/api/payment/payos/cancel?orderCode=' . $cancelCode)->assertRedirect();
        $this->assertEquals(3, $ticketType->fresh()->available_quantity);

        // Repeat cancel does not double-restore stock
        $this->get('/api/payment/payos/cancel?orderCode=' . $cancelCode)->assertRedirect();
        $this->assertEquals(3, $ticketType->fresh()->available_quantity);
    }

    public function test_reviews_moderation_reports_and_profile_password(): void
    {
        $seeded = $this->seedPublishedEvent(stock: 5, price: 100000);
        $event = $seeded['event'];
        $ticketType = $seeded['ticketType'];

        $buyer = User::create([
            'name' => 'Reviewer',
            'email' => 'reviewer@example.com',
            'password' => Hash::make('oldpass123'),
            'role' => User::ROLE_CUSTOMER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $other = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_CUSTOMER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_code' => 'ORD9001',
            'total_amount' => 100000,
            'discount_amount' => 0,
            'final_amount' => 100000,
            'payment_status' => 'paid',
            'payment_method' => 'payos',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'subtotal' => 100000,
        ]);

        // Other user cannot review buyer's order
        Passport::actingAs($other);
        $this->postJson('/api/reviews', [
            'order_id' => $order->id,
            'event_id' => $event->id,
            'rating' => 5,
            'comment' => 'Fake review',
        ])->assertStatus(403);

        // Mock AI moderation flagging unsafe comment as pending
        $mockModerator = Mockery::mock(ContentModeratorService::class);
        $mockModerator->shouldReceive('checkContent')
            ->once()
            ->andReturn(['is_safe' => false, 'reason' => 'Ngôn từ không phù hợp']);
        $this->app->instance(ContentModeratorService::class, $mockModerator);

        Passport::actingAs($buyer);
        $this->postJson('/api/reviews', [
            'order_id' => $order->id,
            'event_id' => $event->id,
            'rating' => 4,
            'comment' => 'Flagged comment content',
        ])->assertStatus(200);

        $review = Review::where('order_id', $order->id)->firstOrFail();
        $this->assertEquals('pending', $review->status);
        $this->assertEquals('Ngôn từ không phù hợp', $review->admin_note);

        // Duplicate review rejected
        $this->postJson('/api/reviews', [
            'order_id' => $order->id,
            'event_id' => $event->id,
            'rating' => 5,
            'comment' => 'Another one',
        ])->assertStatus(409);

        // Customer submits report on event
        $this->postJson('/api/reports', [
            'type' => 'event',
            'id' => $event->id,
            'reason' => 'Spam',
            'description' => 'Test report description',
        ])->assertStatus(200);

        $report = Report::firstOrFail();
        $this->assertEquals('pending', $report->status);

        // Profile update & change password work under auth:api
        $this->postJson('/api/update-profile', [
            'name' => 'Reviewer Updated',
            'phone' => '0901234567',
        ])->assertStatus(200)->assertJsonPath('data.user.name', 'Reviewer Updated');

        $this->postJson('/api/change-password', [
            'current_password' => 'oldpass123',
            'new_password' => 'newpass456',
            'new_password_confirmation' => 'newpass456',
        ])->assertStatus(200);
        $this->assertTrue(Hash::check('newpass456', $buyer->fresh()->password));

        // Admin approves review & resolves report
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        Passport::actingAs($admin);
        $this->getJson('/api/admin/reviews?status=pending')->assertStatus(200);
        $this->postJson("/api/admin/reviews/{$review->id}/status", ['status' => 'active'])->assertStatus(200);
        $this->assertEquals('active', $review->fresh()->status);

        $this->getJson('/api/admin/reports?status=pending')->assertStatus(200);
        $this->postJson("/api/admin/reports/{$report->id}/status", ['status' => 'resolved'])->assertStatus(200);
        $this->assertEquals('resolved', $report->fresh()->status);
    }

    public function test_organizer_create_admin_approve_dashboard_and_search(): void
    {
        $category = EventCategory::firstOrCreate(['id' => 1], ['name' => 'Nhạc sống']);

        $orgUser = User::create([
            'name' => 'Org Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ORGANIZER,
            'status' => User::STATUS_ACTIVE,
        ]);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin2@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        // Before organizer profile exists, my-events returns empty safely
        Passport::actingAs($orgUser);
        $this->getJson('/api/organizer/my-events')
            ->assertStatus(200)
            ->assertJsonPath('data.events', []);

        $this->postJson('/api/create-event', [
            'info' => [
                'eventName' => 'Hanoi Indie Fest',
                'orgName' => 'Indie Co',
                'orgDescription' => 'Music collective',
                'category' => $category->id,
                'venueName' => 'Trung tâm Hội nghị',
                'eventDescription' => '<p>Show details</p>',
                'address' => json_encode([
                    'cityId' => 1,
                    'districtId' => 1,
                    'wardId' => 1,
                    'street' => 'Số 1 Đại lộ Thăng Long',
                ]),
            ],
            'time' => json_encode([
                'shows' => [
                    [
                        'start_time' => Carbon::now()->addDays(3)->format('Y-m-d H:i:s'),
                        'end_time' => Carbon::now()->addDays(3)->addHours(3)->format('Y-m-d H:i:s'),
                        'tickets' => [
                            ['ticket_name' => 'Standard', 'price' => 0, 'quantity' => 50],
                        ],
                    ],
                ],
            ]),
            'payment' => json_encode([
                'accountName' => 'INDIE CO',
                'accountNumber' => '123456789',
                'bankName' => 'Vietcombank',
            ]),
        ])->assertStatus(200);

        $event = Event::where('event_name', 'Hanoi Indie Fest')->firstOrFail();
        $this->assertEquals(Event::STATUS_PENDING_REVIEW, $event->status);
        $this->assertEquals('123456789', Organizer::firstOrFail()->bank_account_number);

        $this->getJson('/api/organizer/my-events?status=pending')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events');

        Passport::actingAs($admin);
        $this->postJson("/api/admin/events/{$event->id}/approve")->assertStatus(200);
        $this->assertEquals(Event::STATUS_PUBLISHED, $event->fresh()->status);

        // Search, index, and dashboard endpoints run cleanly on SQLite
        $this->getJson('/api/search?q=Indie&price=free')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/music-events-index')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        Order::create([
            'user_id' => $orgUser->id,
            'order_code' => 'ORD-CANCEL-1',
            'total_amount' => 100000,
            'discount_amount' => 0,
            'final_amount' => 100000,
            'payment_status' => 'cancelled',
            'payment_method' => 'payos',
        ]);

        $this->getJson('/api/admin/dashboard-revenue')
            ->assertStatus(200)
            ->assertJsonPath('data.order_chart.2', 1);
    }
}
