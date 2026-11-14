<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Auth & Public endpoints
Route::post('/login', [App\Http\Controllers\Auth\AuthController::class, 'login']);
Route::post('/register', [App\Http\Controllers\Auth\AuthController::class, 'register']);
Route::post('/forgot-password', [App\Http\Controllers\Auth\AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [App\Http\Controllers\Auth\AuthController::class, 'resetPassword']);
Route::post('/loginwithgoogle', [App\Http\Controllers\Auth\AuthController::class, 'loginWithGoogle']);
Route::post('/verifyAccount', [App\Http\Controllers\Auth\AuthController::class, 'verifyAccount']);
Route::post('/resend-code', [App\Http\Controllers\Auth\AuthController::class, 'resendCode']);

Route::get('/music-events-index', [App\Http\Controllers\Customers\IndexController::class, 'musicEventsIndex']);
Route::get('/art-events-index', [App\Http\Controllers\Customers\IndexController::class, 'artEventsIndex']);
Route::get('/other-events-index', [App\Http\Controllers\Customers\IndexController::class, 'otherEventsIndex']);
Route::get('/end-of-month-events-index', [App\Http\Controllers\Customers\IndexController::class, 'endOfMonthEventsIndex']);
Route::get('/weekend-events-index', [App\Http\Controllers\Customers\IndexController::class, 'weekendEventsIndex']);
Route::get('/trending-events-index', [App\Http\Controllers\Customers\IndexController::class, 'trendingEventsIndex']);
Route::get('/search', [App\Http\Controllers\Customers\SearchController::class, 'searchEvents']);
Route::get('/categories', [App\Http\Controllers\Customers\IndexController::class, 'getCategories']);
Route::get('/event/{id}', [App\Http\Controllers\Customers\EventDetailController::class, 'index']);
Route::get('/getinfoorganizer/{id}', [App\Http\Controllers\Customers\EventDetailController::class, 'getInfoOrganizer']);
Route::get('/event-review', [App\Http\Controllers\Customers\EventDetailController::class, 'getEventReviews']);
Route::get('/organizer/event-categories', [App\Http\Controllers\Organizers\CreateEventController::class, 'getEventCategories']);

// PayOS callbacks & webhook (xác thực qua PayOS checksum / API server-to-server)
Route::get('/payment/payos/return', [App\Http\Controllers\Customers\PaymentController::class, 'payOSReturn']);
Route::get('/payment/payos/cancel', [App\Http\Controllers\Customers\PaymentController::class, 'payOSCancel']);
Route::post('/payment/payos/webhook', [App\Http\Controllers\Customers\PaymentController::class, 'payOSWebhook']);

// Authenticated User & Organizer endpoints
Route::group(['middleware' => 'auth:api'], function () {
    // Customer Cart, Order, Payment, Review, Report & Profile
    Route::post('add-to-cart', [App\Http\Controllers\Customers\EventDetailController::class, 'addToCart']);
    Route::post('update-cart-quantity', [App\Http\Controllers\Customers\EventDetailController::class, 'updateCartQuantity']);
    Route::get('get-cart', [App\Http\Controllers\Customers\EventDetailController::class, 'getCart']);
    Route::post('remove-from-cart', [App\Http\Controllers\Customers\EventDetailController::class, 'removeFromCart']);

    Route::post('create-order', [App\Http\Controllers\Customers\PaymentController::class, 'createOrder']);
    Route::get('payment/status', [App\Http\Controllers\Customers\PaymentController::class, 'checkPaymentStatus']);
    Route::get('/my-orders', [App\Http\Controllers\Customers\OrderController::class, 'index']);
    Route::get('orders/{orderId}', [App\Http\Controllers\Customers\OrderController::class, 'getOrderDetails']);
    Route::post('reviews', [App\Http\Controllers\Customers\ReviewController::class, 'submitReview']);
    Route::post('/reports', [App\Http\Controllers\Customers\ReportController::class, 'submitReport']);

    Route::post('update-profile', [App\Http\Controllers\Customers\ProfileController::class, 'updateProfile']);
    Route::post('change-password', [App\Http\Controllers\Customers\ProfileController::class, 'changePassword']);

    // Organizer
    Route::post('create-event', [App\Http\Controllers\Organizers\CreateEventController::class, 'createEvent']);
    Route::get('organizer/getInfo/{id?}', [App\Http\Controllers\Organizers\CreateEventController::class, 'getInfo']);
    Route::get('organizer/my-events', [App\Http\Controllers\Organizers\MyEventsController::class, 'getMyEvents']);
});

// Admin endpoints (yêu cầu đăng nhập & role Admin = 3)
Route::group(['prefix' => 'admin', 'middleware' => ['auth:api', 'role:3']], function () {
    Route::get('/dashboard-stats', [App\Http\Controllers\Admin\DashboardController::class, 'stats']);
    Route::get('/dashboard-revenue', [App\Http\Controllers\Admin\DashboardController::class, 'revenue']);
    Route::get('/organizers', [App\Http\Controllers\Admin\OrganizerController::class, 'index']);

    Route::get('/events/pending', [App\Http\Controllers\Admin\EventReviewController::class, 'pending']);
    Route::get('/events/{id}', [App\Http\Controllers\Admin\EventReviewController::class, 'show']);
    Route::post('/events/{id}/approve', [App\Http\Controllers\Admin\EventReviewController::class, 'approve']);
    Route::post('/events/{id}/reject', [App\Http\Controllers\Admin\EventReviewController::class, 'reject']);

    Route::get('/orders/paid', [App\Http\Controllers\Admin\OrderController::class, 'paidOrders']);
    Route::get('/reviews', [App\Http\Controllers\Admin\ReviewController::class, 'getAllReviews']);
    Route::post('/reviews/{id}/status', [App\Http\Controllers\Admin\ReviewController::class, 'updateReviewStatus']);
    Route::get('/reports', [App\Http\Controllers\Admin\ReportController::class, 'index']);
    Route::post('/reports/{id}/status', [App\Http\Controllers\Admin\ReportController::class, 'updateReportStatus']);
});
