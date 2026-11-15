<?php

namespace App\Http\Controllers\Customers;

use App\Helpers\ResponseApi;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private $responseApi;

    public function __construct()
    {
        $this->responseApi = new ResponseApi();
    }

    public function searchEvents(Request $request)
    {
        $q = $request->query('q');
        $from = $request->query('from');
        $to = $request->query('to');
        $local = $request->query('local');
        $price = $request->query('price');
        $cate = $request->query('cate');

        $limit = max(1, min(50, (int) $request->input('limit', 12)));
        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * $limit;

        $query = Event::query()
            ->with(['venue', 'category', 'categories'])
            ->withMin('schedules as schedules_min_start_datetime', 'start_datetime')
            ->withMin('ticketTypes as ticket_types_min_base_price', 'base_price')
            ->where('status', Event::STATUS_PUBLISHED);

        if (!empty($q)) {
            $query->where(function ($sub) use ($q) {
                $sub->where('event_name', 'LIKE', '%' . $q . '%')
                    ->orWhere('description', 'LIKE', '%' . $q . '%');
            });
        }

        if (!empty($local) && $local !== 'all') {
            $cityGroups = [
                '01' => ['01', '1', 'Hà Nội', 'Hanoi', 'Ha Noi'],
                '1'  => ['01', '1', 'Hà Nội', 'Hanoi', 'Ha Noi'],
                '79' => ['79', 'Ho Chi Minh', 'Hồ Chí Minh', 'TP. Hồ Chí Minh', 'TP.HCM', 'HCM'],
                '48' => ['48', 'Đà Nẵng', 'Danang', 'Da Nang'],
                '42' => ['42', 'Đà Lạt', 'Da Lat', 'Lâm Đồng', 'Lam Dong'],
            ];

            $query->whereHas('venue', function ($v) use ($local, $cityGroups) {
                if ($local === 'other') {
                    $majorCities = array_values(array_unique(array_merge(...array_values($cityGroups))));
                    $v->whereNotNull('city')
                      ->where('city', '!=', '')
                      ->whereNotIn('city', $majorCities);
                } elseif (isset($cityGroups[$local])) {
                    $v->whereIn('city', $cityGroups[$local]);
                } elseif (is_numeric($local)) {
                    $v->where('city', $local);
                } else {
                    $v->where('city', 'LIKE', '%' . $local . '%');
                }
            });
        }

        if (!empty($from) || !empty($to)) {
            $query->whereHas('schedules', function ($s) use ($from, $to) {
                if (!empty($from)) {
                    $s->whereDate('start_datetime', '>=', $from);
                }
                if (!empty($to)) {
                    $s->whereDate('start_datetime', '<=', $to);
                }
            });
        }

        if ($price === 'free') {
            $query->whereHas('ticketTypes', function ($t) {
                $t->where('base_price', 0);
            });
        }

        if (!empty($cate)) {
            $cateIds = array_values(array_filter(array_map('trim', explode(',', $cate))));
            if (!empty($cateIds)) {
                $query->where(function ($sub) use ($cateIds) {
                    $sub->whereIn('category_id', $cateIds)
                        ->orWhereHas('categories', function ($c) use ($cateIds) {
                            $c->whereIn('event_categories.id', $cateIds);
                        });
                });
            }
        }

        $events = $query
            ->orderBy('schedules_min_start_datetime', 'asc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $data = $events->map(function (Event $e) {
            $cat = $e->category ?? $e->categories->first();

            return [
                'id' => $e->id,
                'event_name' => $e->event_name,
                'background_image_url' => $e->background_image_url,
                'ticket_types_min_base_price' => (int) $e->ticket_types_min_base_price,
                'schedules_min_start_datetime' => $e->schedules_min_start_datetime,
                'location' => optional($e->venue)->city,
                'category_id' => optional($cat)->id,
                'category_name' => optional($cat)->name,
            ];
        });

        return $this->responseApi->success($data);
    }
}
