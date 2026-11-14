<?php

namespace App\Http\Controllers\Customers;

use App\Helpers\ResponseApi;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EventDetailController extends Controller
{
    private $responseApi;

    public function __construct()
    {
        $this->responseApi = new ResponseApi();
    }

    public function index(Request $request, $id)
    {
        $event = Event::with(['schedules', 'venue', 'organizer', 'ticketTypes'])
            ->withMin('ticketTypes', 'base_price')
            ->withMin('schedules', 'start_datetime')
            ->where('id', $id)
            ->first();

        if (!$event) {
            return $this->responseApi->dataNotfound();
        }

        return $this->responseApi->success($event);
    }

    public function getInfoOrganizer(Request $request, $id)
    {
        $organizer = Organizer::find($id);
        return $this->responseApi->success($organizer);
    }

    public function addToCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ticket_id' => 'required|integer|exists:ticket_types,id',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->responseApi->BadRequest($validator->errors()->first());
        }

        $param = $validator->validated();
        $ticketType = TicketType::find($param['ticket_id']);

        $cart = Cart::updateOrCreate([
            'user_id' => Auth::id(),
        ]);

        $cartItem = CartItem::firstOrNew([
            'cart_id' => $cart->id,
            'ticket_type_id' => $param['ticket_id'],
        ]);

        $newQty = ($cartItem->quantity ?? 0) + (int) $param['quantity'];
        if ($newQty > $ticketType->available_quantity) {
            return $this->responseApi->BadRequest("Số lượng vượt quá vé còn lại ({$ticketType->available_quantity}).");
        }

        $cartItem->quantity = $newQty;
        $cartItem->save();

        $countCart = CartItem::where('cart_id', $cart->id)->count();
        return $this->responseApi->success($countCart);
    }

    public function updateCartQuantity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ticket_id' => 'required|integer|exists:ticket_types,id',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->responseApi->BadRequest($validator->errors()->first());
        }

        $param = $validator->validated();
        $cart = Cart::where('user_id', Auth::id())->first();
        if (!$cart) {
            return $this->responseApi->BadRequest('Không tìm thấy giỏ hàng');
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('ticket_type_id', $param['ticket_id'])
            ->first();

        if (!$cartItem) {
            return $this->responseApi->BadRequest('Sản phẩm không tồn tại trong giỏ');
        }

        $ticketType = TicketType::find($param['ticket_id']);
        if ($param['quantity'] > $ticketType->available_quantity) {
            return $this->responseApi->BadRequest("Vé chỉ còn {$ticketType->available_quantity}.");
        }

        $cartItem->quantity = (int) $param['quantity'];
        $cartItem->save();

        return $this->responseApi->success($cartItem);
    }

    public function getCart(Request $request)
    {
        $cart = Cart::where('user_id', Auth::id())->first();
        if (!$cart) {
            return $this->responseApi->success([]);
        }

        $cartItems = CartItem::where('cart_id', $cart->id)
            ->join('ticket_types', 'cart_items.ticket_type_id', '=', 'ticket_types.id')
            ->join('event_schedules as schedules', 'ticket_types.schedule_id', '=', 'schedules.id')
            ->join('events', 'schedules.event_id', '=', 'events.id')
            ->select(
                'cart_items.*',
                'ticket_types.name as ticket_type_name',
                'ticket_types.base_price',
                'ticket_types.available_quantity',
                'schedules.start_datetime',
                'schedules.end_datetime',
                'events.event_name as event_name',
                'events.background_image_url',
                'events.poster_image_url',
                'events.description'
            )
            ->orderBy('schedules.start_datetime', 'asc')
            ->get();

        return $this->responseApi->success($cartItems);
    }

    public function removeFromCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ticket_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->responseApi->BadRequest($validator->errors()->first());
        }

        $ticketId = $request->input('ticket_id');
        $cart = Cart::where('user_id', Auth::id())->first();

        if (!$cart) {
            return $this->responseApi->BadRequest('Không tìm thấy giỏ hàng');
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('ticket_type_id', $ticketId)
            ->first();

        if (!$cartItem) {
            return $this->responseApi->BadRequest('Sản phẩm không tồn tại trong giỏ');
        }

        $cartItem->delete();

        $countCart = CartItem::where('cart_id', $cart->id)->count();
        return $this->responseApi->success($countCart);
    }

    public function getEventReviews(Request $request)
    {
        $eventId = $request->input('event_id');
        if (!$eventId) {
            return $this->responseApi->success([]);
        }

        $reviews = DB::table('reviews')
            ->join('users', 'reviews.user_id', '=', 'users.id')
            ->select(
                'reviews.id',
                'reviews.rating',
                'reviews.comment',
                'reviews.created_at',
                'users.name as user_name',
                'users.avatar as user_avatar'
            )
            ->where('reviews.event_id', $eventId)
            ->where('reviews.status', 'active')
            ->orderBy('reviews.created_at', 'desc')
            ->get();

        return $this->responseApi->success($reviews);
    }
}
