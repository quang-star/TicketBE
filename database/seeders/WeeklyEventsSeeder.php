<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\TicketType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WeeklyEventsSeeder extends Seeder
{
    public function run(): void
    {
        $baseUrl = rtrim(config('app.url'), '/');
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Set a valid APP_URL before running WeeklyEventsSeeder.');
        }

        $credits = json_decode(file_get_contents(database_path('seeders/weekly-image-credits.json')), true, 512, JSON_THROW_ON_ERROR);
        if (count($credits) !== 100) {
            throw new RuntimeException('Expected 100 image credits.');
        }

        // Reuse existing sample organizers: never create login accounts or orders here.
        $organizerIds = [];
        foreach (['music', 'stage', 'sport', 'tech'] as $key) {
            $email = ['music' => 'seeder.live@ticket.local', 'stage' => 'seeder.stage@ticket.local',
                'sport' => 'seeder.marathon@ticket.local', 'tech' => 'seeder.tech@ticket.local'][$key];
            $id = DB::table('organizers')->join('users', 'users.id', '=', 'organizers.user_id')
                ->where('users.email', $email)->value('organizers.id');
            if (!$id) {
                throw new RuntimeException('Missing sample organizer '.$key.'; run the prerequisite seeder only in a suitable test environment.');
            }
            $organizerIds[$key] = $id;
        }

        $venueId = DB::table('venues')->orderBy('id')->value('id');
        if (!$venueId || DB::table('event_categories')->whereIn('id', [1, 2, 3, 4])->count() !== 4) {
            throw new RuntimeException('A venue and the four event categories are required.');
        }

        $topics = [
            ['music', 1, 'Đêm nhạc', ['Dải Ngân Hà', 'Bên Thềm Mùa Hạ', 'Thanh Âm Thành Phố', 'Những Ngày Xanh', 'Mây Qua Phố', 'Giữa Ngàn Ánh Sao', 'Bản Tình Ca Đêm', 'Chạm Vào Bình Minh', 'Giai Điệu Tháng Năm', 'Tiếng Hát Tự Do', 'Vệt Nắng Cuối Ngày', 'Hẹn Nhau Dưới Trăng', 'Dấu Yêu Còn Lại', 'Khoảng Trời Âm Nhạc', 'Chuyến Tàu Thanh Xuân', 'Ngày Ta Gặp Lại', 'Lời Ca Từ Biển', 'Một Đêm Rực Rỡ', 'Gió Và Những Bản Tình Ca', 'Hòa Nhịp Tương Lai']],
            ['stage', 3, 'Sân khấu', ['Ánh Đèn Đầu Tiên', 'Chuyện Kể Bên Sông', 'Người Giữ Mùa Xuân', 'Những Cánh Cửa Mở', 'Phía Sau Tấm Màn', 'Giấc Mơ Sân Khấu', 'Bức Thư Chưa Gửi', 'Ngày Trở Về', 'Bản Giao Hưởng Của Gió', 'Ngôi Nhà Nhiều Cửa Sổ', 'Dấu Chân Trên Phố', 'Vũ Điệu Của Mây', 'Chuyện Một Chiều Mưa', 'Khi Thành Phố Lên Đèn', 'Mùa Hoa Trên Mái Nhà', 'Những Cuộc Gặp Gỡ', 'Sắc Màu Ký Ức', 'Câu Chuyện Mùa Thu', 'Khoảnh Khắc Giao Mùa', 'Hành Trình Của Ánh Sáng']],
            ['sport', 2, 'Giải chạy', ['Bước Chân Xanh', 'Cung Đường Bình Minh', 'Nhịp Sống Thành Phố', 'Hành Trình Bền Bỉ', 'Sải Bước Ven Sông', 'Cùng Nhau Về Đích', 'Đường Chạy Mùa Hạ', 'Chinh Phục Sớm Mai', 'Bước Chân Kết Nối', 'Cung Đường Nắng Mới', 'Vượt Qua Giới Hạn', 'Nhịp Chạy Cuối Tuần', 'Hành Trình Gió Mát', 'Vòng Chạy Cộng Đồng', 'Sức Sống Mới', 'Đường Chạy Ánh Dương', 'Kết Nối Những Bước Chân', 'Băng Qua Mùa Thu', 'Hướng Về Phía Trước', 'Sải Bước Tương Lai']],
            ['tech', 4, 'Lễ hội', ['Sắc Màu Phố Hội', 'Hương Vị Bốn Mùa', 'Ngày Vui Cộng Đồng', 'Không Gian Sáng Tạo', 'Nhịp Điệu Mùa Hè', 'Khu Vườn Ánh Sáng', 'Chạm Vào Văn Hóa', 'Những Miền Hương Vị', 'Gặp Nhau Giữa Phố', 'Ngày Hội Cuối Tuần', 'Sắc Xuân Hội Ngộ', 'Vòng Quanh Thế Giới', 'Miền Ký Ức', 'Hành Trình Di Sản', 'Đêm Hội Lung Linh', 'Góc Phố Sáng Tạo', 'Hội Ngộ Mùa Vàng', 'Hương Sắc Phương Nam', 'Ngày Hội Những Sắc Màu', 'Khúc Giao Mùa']],
            ['stage', 3, 'Triển lãm', ['Sắc Màu Thời Gian', 'Những Góc Nhìn Mới', 'Dấu Ấn Ánh Sáng', 'Miền Ký Ức', 'Câu Chuyện Của Màu', 'Hành Trình Thị Giác', 'Không Gian Mở', 'Nét Đẹp Đời Thường', 'Giao Điểm Sáng Tạo', 'Thành Phố Trong Tranh', 'Bốn Mùa Cảm Xúc', 'Nét Chạm Thiên Nhiên', 'Những Mảnh Ghép', 'Sắc Độ Đương Đại', 'Bên Kia Khung Hình', 'Một Thoáng Việt Nam', 'Dòng Chảy Sáng Tạo', 'Chuyện Kể Bằng Màu', 'Khoảng Lặng Nghệ Thuật', 'Chân Trời Mới']],
        ];

        $firstSaturday = Carbon::now()->startOfWeek()->addDays(5)->setTime(10, 0);
        if ($firstSaturday->lessThanOrEqualTo(Carbon::now())) {
            $firstSaturday->addWeek();
        }

        DB::transaction(function () use ($topics, $credits, $baseUrl, $organizerIds, $venueId, $firstSaturday) {
            for ($week = 0; $week < 100; $week++) {
                $group = $week % 5;
                $item = intdiv($week, 5);
                [$organizer, $category, $prefix, $names] = $topics[$group];
                $name = $prefix.': '.$names[$item];
                $credit = $credits[$group * 20 + $item];
                $file = $credit['file'];
                if (!is_file(public_path('images/weekly-events/'.$file))) {
                    throw new RuntimeException('Missing image '.$file);
                }
                if (Event::where('organizer_id', $organizerIds[$organizer])->where('event_name', $name)->exists()) {
                    continue;
                }

                $imageUrl = $baseUrl.'/images/weekly-events/'.$file;
                $licenseUrl = $credit['license_url'] ?? '';
                $license = e($credit['license']);
                if (filter_var($licenseUrl, FILTER_VALIDATE_URL)) {
                    $license = '<a href="'.e($licenseUrl).'">'.$license.'</a>';
                }
                $description = '<p>Nội dung minh họa, thời gian và địa điểm được tạo cho dữ liệu thử nghiệm.</p>'
                    .'<p>Ảnh: '.e($credit['title']).' — '.e($credit['author']).' — '.$license.'. '
                    .'<a href="'.e($credit['source']).'">Nguồn ảnh</a>.</p>';
                $event = Event::create([
                    'organizer_id' => $organizerIds[$organizer], 'venue_id' => $venueId,
                    'category_id' => $category, 'event_name' => $name, 'description' => $description,
                    'background_image_url' => $imageUrl, 'poster_image_url' => $imageUrl,
                    'preview_image_url' => $imageUrl, 'status' => Event::STATUS_PUBLISHED,
                ]);
                $event->categories()->syncWithoutDetaching([$category]);
                $start = $firstSaturday->copy()->addWeeks(intdiv($week, 10))
                    ->addDays(intdiv($week % 10, 5))->setTime(10 + 2 * ($week % 5), 0);
                $schedule = EventSchedule::create([
                    'event_id' => $event->id, 'start_datetime' => $start,
                    'end_datetime' => $start->copy()->addHours(3),
                    'status' => EventSchedule::STATUS_UPCOMING, 'available_tickets' => 300,
                ]);
                TicketType::create([
                    'schedule_id' => $schedule->id, 'name' => 'Standard', 'base_price' => 200000,
                    'total_quantity' => 300, 'available_quantity' => 300, 'status' => TicketType::STATUS_ACTIVE,
                ]);
            }
        });
    }
}
