<?php

namespace App\Http\Controllers\Customers;

use App\Helpers\ResponseApi;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IndexController extends Controller
{
    private $responseApi;

    public function __construct()
    {
        $this->responseApi = new ResponseApi();
    }

    private function baseEventIndex(Request $request, $categoryId)
    {
        return Event::with(['schedules', 'venue'])
            ->where('status', Event::STATUS_PUBLISHED)
            ->where(function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId)
                    ->orWhereHas('categories', function ($sub) use ($categoryId) {
                        $sub->where('event_categories.id', $categoryId);
                    });
            })
            ->withMin('ticketTypes', 'base_price')
            ->withMin('schedules', 'start_datetime')
            ->whereHas('schedules', function ($q) {
                $q->where('end_datetime', '>=', now());
            })
            ->orderBy('schedules_min_start_datetime', 'asc')
            ->get();
    }

    public function musicEventsIndex(Request $request)
    {
        return $this->responseApi->success($this->baseEventIndex($request, 1));
    }

    public function artEventsIndex(Request $request)
    {
        return $this->responseApi->success($this->baseEventIndex($request, 3));
    }

    public function otherEventsIndex(Request $request)
    {
        return $this->responseApi->success($this->baseEventIndex($request, 4));
    }

    private function eventsByRange($start, $end, $categoryId = null, $limit = 4)
    {
        return Event::with(['schedules', 'venue'])
            ->where('status', Event::STATUS_PUBLISHED)
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->where(function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId)
                        ->orWhereHas('categories', function ($sub) use ($categoryId) {
                            $sub->where('event_categories.id', $categoryId);
                        });
                });
            })
            ->withMin('ticketTypes', 'base_price')
            ->withMin('schedules', 'start_datetime')
            ->whereHas('schedules', function ($q) use ($start, $end) {
                $q->whereBetween('start_datetime', [$start, $end]);
            })
            ->orderBy('schedules_min_start_datetime', 'asc')
            ->limit($limit)
            ->get();
    }

    public function weekendEventsIndex(Request $request)
    {
        $startOfWeek = now()->startOfWeek();
        $startDate = $startOfWeek->copy()->addDays(5)->format('Y-m-d 00:00:00');
        $endDate = $startOfWeek->copy()->addDays(6)->format('Y-m-d 23:59:59');

        return $this->responseApi->success($this->eventsByRange($startDate, $endDate, null, 10));
    }

    public function endOfMonthEventsIndex(Request $request)
    {
        $startDate = now()->format('Y-m-d H:i:s');
        $endDate = now()->endOfMonth()->format('Y-m-d 23:59:59');

        return $this->responseApi->success($this->eventsByRange($startDate, $endDate, null, 7));
    }

    public function trendingEventsIndex()
    {
        $from = now()->subDays(30);
        $to = now();

        // Tính tổng vé bán qua subquery để tránh fan-out khi join reviews & lỗi ONLY_FULL_GROUP_BY
        $salesSub = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('ticket_types', 'ticket_types.id', '=', 'order_items.ticket_type_id')
            ->join('event_schedules', 'event_schedules.id', '=', 'ticket_types.schedule_id')
            ->where('orders.payment_status', 'paid')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('event_schedules.end_datetime', '>=', now())
            ->groupBy('event_schedules.event_id')
            ->select(
                'event_schedules.event_id',
                DB::raw('SUM(order_items.quantity) as tickets_sold')
            );

        $events = Event::query()
            ->with(['schedules', 'venue'])
            ->joinSub($salesSub, 'sales', function ($join) {
                $join->on('sales.event_id', '=', 'events.id');
            })
            ->where('events.status', Event::STATUS_PUBLISHED)
            ->withMin('ticketTypes', 'base_price')
            ->withMin('schedules', 'start_datetime')
            ->select('events.*', 'sales.tickets_sold')
            ->orderByDesc('sales.tickets_sold')
            ->limit(10)
            ->get();

        return $this->responseApi->success($events);
    }

    public function search(Request $request)
    {
        $keyword = $request->query('q');

        if (!$keyword) {
            return $this->responseApi->BadRequest('Missing search keyword');
        }

        $events = Event::with(['schedules', 'venue'])
            ->where('events.status', Event::STATUS_PUBLISHED)
            ->where(function ($query) use ($keyword) {
                $query->where('events.event_name', 'like', "%{$keyword}%")
                    ->orWhere('events.description', 'like', "%{$keyword}%")
                    ->orWhereHas('venue', function ($q) use ($keyword) {
                        $q->where('venues.name', 'like', "%{$keyword}%");
                    });
            })
            ->withMin('ticketTypes', 'base_price')
            ->withMin('schedules', 'start_datetime')
            ->whereHas('schedules', function ($q) {
                $q->where('start_datetime', '>', now());
            })
            ->orderBy('schedules_min_start_datetime', 'asc')
            ->limit(20)
            ->get();

        if ($events->isEmpty()) {
            return $this->responseApi->dataNotfound();
        }

        return $this->responseApi->success($events);
    }

    public function getCategories()
    {
        $categories = DB::table('event_categories')
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        return $this->responseApi->success($categories);
    }
}
