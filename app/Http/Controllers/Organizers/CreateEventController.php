<?php

namespace App\Http\Controllers\Organizers;

use App\Helpers\DescriptionImageHelper;
use App\Helpers\ResponseApi;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventSchedule;
use App\Models\Organizer;
use App\Models\TicketType;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateEventController extends Controller
{
    private $responseApi;

    public function __construct()
    {
        $this->responseApi = new ResponseApi();
    }

    public function createEvent(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['code' => 401, 'message' => 'Unauthenticated'], 401);
        }

        $baseUrl = rtrim(url('/'), '/') . '/';

        $info = $request->input('info', []);
        if (!is_array($info)) {
            $info = json_decode((string) $info, true) ?? [];
        }

        $address = $info['address'] ?? [];
        if (is_string($address)) {
            $address = json_decode($address, true) ?? [];
        }

        $timeRaw = $request->input('time', []);
        $time = is_array($timeRaw) ? $timeRaw : (json_decode((string) $timeRaw, true) ?? []);

        $paymentRaw = $request->input('payment', []);
        $payment = is_array($paymentRaw) ? $paymentRaw : (json_decode((string) $paymentRaw, true) ?? []);

        $validator = Validator::make([
            'info' => $info,
            'address' => $address,
            'time' => $time,
        ], [
            'info.eventName' => 'required|string|max:255',
            'info.orgName' => 'required|string|max:255',
            'info.orgDescription' => 'required|string',
            'info.category' => 'required|exists:event_categories,id',
            'address.cityId' => 'required',
            'address.districtId' => 'required',
            'address.wardId' => 'required',
            'address.street' => 'required|string',
            'time.shows' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        foreach ($time['shows'] ?? [] as $show) {
            validator($show, [
                'start_time' => 'required|date',
                'end_time' => 'required|date|after_or_equal:start_time',
                'tickets' => 'required|array|min:1',
            ])->validate();

            foreach ($show['tickets'] as $ticket) {
                validator($ticket, [
                    'ticket_name' => 'required|string',
                    'price' => 'required|numeric|min:0',
                    'quantity' => 'required|integer|min:1',
                ])->validate();
            }
        }

        DB::beginTransaction();
        try {
            $venue = Venue::create([
                'name' => $info['venueName'] ?? '',
                'city' => $address['cityId'],
                'district' => $address['districtId'],
                'ward' => $address['wardId'],
                'street' => $address['street'],
            ]);

            $bgUrls = $this->moveUploadedFiles($request, 'bgFile', 'bg');
            $bgUrl = $bgUrls[0] ?? null;

            $posterUrls = $this->moveUploadedFiles($request, 'eventFile', 'poster');
            $posterUrl = $posterUrls[0] ?? null;

            $previewUrl = null;
            if ($request->hasFile('eventPreview')) {
                $previewDir = public_path('preview');
                if (!is_dir($previewDir)) {
                    mkdir($previewDir, 0755, true);
                }
                $file = $request->file('eventPreview');
                $filename = uniqid() . '_' . $file->getClientOriginalName();
                $file->move($previewDir, $filename);
                $previewUrl = 'preview/' . $filename;
            }

            $logoUrls = $this->moveUploadedFiles($request, 'orgLogoFile', 'logo');
            $logoUrl = $logoUrls[0] ?? null;

            $organizerData = [
                'organization_name' => $info['orgName'],
                'description' => $info['orgDescription'],
                'bank_account_name' => $payment['accountName'] ?? null,
                'bank_account_number' => $payment['accountNumber'] ?? null,
                'bank_name' => $payment['bankName'] ?? null,
                'bank_branch' => $payment['branch'] ?? null,
                'business_type' => $payment['businessType'] ?? null,
                'personal_full_name' => $payment['personalName'] ?? ($payment['companyName'] ?? null),
                'personal_address' => $payment['personalAddress'] ?? ($payment['companyAddress'] ?? null),
                'tax_number' => $payment['taxCode'] ?? null,
            ];
            if ($logoUrl) {
                $organizerData['logo'] = $baseUrl . $logoUrl;
            }

            $organizer = Organizer::updateOrCreate(
                ['user_id' => $userId],
                $organizerData
            );

            $descriptionHtml = DescriptionImageHelper::saveImages($info['eventDescription'] ?? '');

            $event = Event::create([
                'event_name' => $info['eventName'],
                'organizer_id' => $organizer->id,
                'venue_id' => $venue->id,
                'category_id' => $info['category'],
                'description' => $descriptionHtml,
                'background_image_url' => $bgUrl ? $baseUrl . $bgUrl : null,
                'poster_image_url' => $posterUrl ? $baseUrl . $posterUrl : null,
                'preview_image_url' => $previewUrl ? $baseUrl . $previewUrl : null,
                'status' => Event::STATUS_PENDING_REVIEW,
            ]);

            $event->categories()->syncWithoutDetaching([$info['category']]);

            foreach ($time['shows'] as $show) {
                $schedule = EventSchedule::create([
                    'event_id' => $event->id,
                    'start_datetime' => $show['start_time'],
                    'end_datetime' => $show['end_time'],
                    'status' => EventSchedule::STATUS_UPCOMING,
                ]);

                foreach ($show['tickets'] as $ticket) {
                    $qty = (int) $ticket['quantity'];
                    TicketType::create([
                        'schedule_id' => $schedule->id,
                        'name' => $ticket['ticket_name'],
                        'base_price' => $ticket['price'],
                        'total_quantity' => $qty,
                        'available_quantity' => $qty,
                        'status' => TicketType::STATUS_ACTIVE,
                    ]);
                }
            }

            DB::commit();

            return $this->responseApi->success([
                'event' => $event,
                'venue' => $venue,
                'time' => $time,
                'payment' => $payment,
                'uploadedBgFiles' => $bgUrls,
                'uploadedPosterFiles' => $posterUrls,
                'uploadedPreview' => $previewUrl,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'code' => 500,
                'message' => 'Lỗi tạo sự kiện: ' . $th->getMessage(),
            ], 500);
        }
    }

    private function moveUploadedFiles(Request $request, string $field, string $folder): array
    {
        $urls = [];
        if (!$request->hasFile($field)) {
            return $urls;
        }

        $targetDir = public_path($folder);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $files = $request->file($field);
        if (!is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            $filename = uniqid() . '_' . $file->getClientOriginalName();
            $file->move($targetDir, $filename);
            $urls[] = $folder . '/' . $filename;
        }

        return $urls;
    }

    public function getInfo(Request $request, $id = null)
    {
        $organizer = Organizer::where('user_id', Auth::id())->first();
        return $this->responseApi->success($organizer);
    }

    public function getEventCategories()
    {
        $categories = EventCategory::orderBy('name', 'asc')
            ->get(['id', 'name']);

        return $this->responseApi->success($categories);
    }
}
