<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\TicketType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpcomingEventsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Đảm bảo đủ 4 danh mục chuẩn (khớp ID với DB & IndexController)
        $categories = [
            1 => 'Nhạc sống',
            2 => 'Thể thao',
            3 => 'Sân khấu và Nghệ thuật',
            4 => 'Khác',
        ];

        foreach ($categories as $id => $name) {
            DB::table('event_categories')->updateOrInsert(
                ['id' => $id],
                ['name' => $name, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        // 2. Địa điểm thực tế theo từng sự kiện
        // Quy ước city code: Hà Nội=1, Quảng Ninh=22, Sơn La=14, Nghệ An=40, Đà Nẵng=48, Lâm Đồng/Đà Lạt=42, Kon Tum=62, TP.HCM=79, Kiên Giang=91, Cần Thơ=92
        $venuesData = [
            'vec_hanoi' => ['name' => 'Trung tâm Triển lãm Việt Nam (VEC)', 'street' => 'Đông Anh', 'city' => 1, 'district' => 18, 'ward' => 544],
            'my_dinh_hn' => ['name' => 'Sân vận động Quốc gia Mỹ Đình', 'street' => 'Đường Lê Đức Thọ, Nam Từ Liêm', 'city' => 1, 'district' => 19, 'ward' => 622],
            'ncc_hanoi' => ['name' => 'Trung tâm Hội nghị Quốc gia (NCC)', 'street' => 'Đại lộ Thăng Long, Nam Từ Liêm', 'city' => 1, 'district' => 19, 'ward' => 622],
            'cung_dien_kinh_hn' => ['name' => 'Cung Điền kinh Mỹ Đình', 'street' => 'Trần Hữu Dực, Nam Từ Liêm', 'city' => 1, 'district' => 19, 'ward' => 622],
            'nha_hat_kich_vn' => ['name' => 'Nhà hát Kịch Việt Nam', 'street' => 'Số 1 Tràng Tiền, Hoàn Kiếm', 'city' => 1, 'district' => 2, 'ward' => 70],
            'nha_hat_lon_hn' => ['name' => 'Nhà hát Lớn Hà Nội', 'street' => '01 Tràng Tiền, Hoàn Kiếm', 'city' => 1, 'district' => 2, 'ward' => 70],
            'nha_hat_tuoi_tre' => ['name' => 'Nhà hát Tuổi Trẻ Việt Nam', 'street' => '11 Ngô Thì Nhậm, Hai Bà Trưng', 'city' => 1, 'district' => 7, 'ward' => 256],
            'hoan_kiem_hn' => ['name' => 'Phố đi bộ Hồ Hoàn Kiếm', 'street' => 'Đinh Tiên Hoàng, Hoàn Kiếm', 'city' => 1, 'district' => 2, 'ward' => 64],
            'hcmc_riverside' => ['name' => 'Công viên Bờ sông Sài Gòn & Phố đi bộ Nguyễn Huệ', 'street' => 'Bến Bạch Đằng - Nguyễn Huệ, Quận 1', 'city' => 79, 'district' => 760, 'ward' => 26740],
            'hcmc_stadium' => ['name' => 'Khu đô thị Vạn Phúc / Sân khấu Ngoài trời TP.HCM', 'street' => 'Quốc lộ 13, Thủ Đức', 'city' => 79, 'district' => 769, 'ward' => 26815],
            'vpbank_hcm' => ['name' => 'VPBank Flagship TP.HCM', 'street' => 'Quận 1', 'city' => 79, 'district' => 760, 'ward' => 26734],
            'the_gioi_tre' => ['name' => 'Sân khấu Kịch Thế Giới Trẻ', 'street' => '125 Cống Quỳnh, Quận 1', 'city' => 79, 'district' => 760, 'ward' => 26752],
            'kich_hong_van' => ['name' => 'Sân khấu Kịch Hồng Vân (NVH Sinh Viên)', 'street' => '643 Điện Biên Phủ, Quận 3', 'city' => 79, 'district' => 770, 'ward' => 27145],
            'hoang_thai_thanh' => ['name' => 'Sân khấu Kịch Hoàng Thái Thanh', 'street' => '139 Bắc Hải, Quận 10', 'city' => 79, 'district' => 771, 'ward' => 27190],
            'le_duan_hcm' => ['name' => 'Đường Lê Duẩn - Thảo Cầm Viên', 'street' => 'Quận 1', 'city' => 79, 'district' => 760, 'ward' => 26734],
            'sihub_hcm' => ['name' => 'Trung tâm Khởi nghiệp Sáng tạo TP.HCM (SIHUB)', 'street' => '123 Trương Định, Quận 3', 'city' => 79, 'district' => 770, 'ward' => 27145],
            'thiskyhall_hcm' => ['name' => 'Thiskyhall Sala Convention Center', 'street' => '10 Mai Chí Thọ, Thủ Đức', 'city' => 79, 'district' => 769, 'ward' => 27106],
            'secc_hcm' => ['name' => 'Trung tâm Hội chợ & Triển lãm Sài Gòn (SECC)', 'street' => '799 Nguyễn Văn Linh, Quận 7', 'city' => 79, 'district' => 778, 'ward' => 27466],
            'bach_dang_dn' => ['name' => 'Phố đi bộ Bạch Đằng - Bờ sông Hàn', 'street' => 'Bạch Đằng, Hải Châu, Đà Nẵng', 'city' => 48, 'district' => 492, 'ward' => 20200],
            'bao_tang_dn' => ['name' => 'Khu vực Bảo tàng Đà Nẵng', 'street' => '42 Bạch Đằng, Hải Châu, Đà Nẵng', 'city' => 48, 'district' => 492, 'ward' => 20200],
            'bien_my_khe_dn' => ['name' => 'Công viên Biển Đông - Mỹ Khê', 'street' => 'Võ Nguyên Giáp, Sơn Trà, Đà Nẵng', 'city' => 48, 'district' => 493, 'ward' => 20245],
            'halong_bay' => ['name' => 'Quảng trường 30/10 - Vịnh Hạ Long', 'street' => 'Đường Trần Quốc Nghiễn, Hạ Long', 'city' => 22, 'district' => 193, 'ward' => 6670],
            'dalat_lam_vien' => ['name' => 'Quảng trường Lâm Viên - Đà Lạt', 'street' => 'Trần Quốc Toản, Phường 1, Đà Lạt', 'city' => 42, 'district' => 672, 'ward' => 24775],
            'phu_quoc' => ['name' => 'Happy Buddha Club - Bãi biển Phú Quốc', 'street' => 'Trần Hưng Đạo, Dương Đông, Phú Quốc', 'city' => 91, 'district' => 911, 'ward' => 31066],
            'cua_lo_na' => ['name' => 'Quảng trường Bình Minh - Biển Cửa Lò', 'street' => 'Bình Minh, Cửa Lò, Nghệ An', 'city' => 40, 'district' => 413, 'ward' => 16720],
            'can_tho' => ['name' => 'Công viên Sông Hậu - Ninh Kiều', 'street' => 'Cái Khế, Ninh Kiều, Cần Thơ', 'city' => 92, 'district' => 916, 'ward' => 31117],
            'moc_chau' => ['name' => 'Rừng thông Bản Áng - Mộc Châu', 'street' => 'Đông Sang, Mộc Châu, Sơn La', 'city' => 14, 'district' => 123, 'ward' => 4096],
            'mang_den' => ['name' => 'Quảng trường Trung tâm Măng Đen', 'street' => 'Kon Plông, Kon Tum', 'city' => 62, 'district' => 613, 'ward' => 23455],
            'phu_tho_hcm' => ['name' => 'Nhà thi đấu Phú Thọ', 'street' => '1 Lữ Gia, Quận 11', 'city' => 79, 'district' => 772, 'ward' => 27220],
            'nha_hat_hcm' => ['name' => 'Nhà hát Thành phố Hồ Chí Minh', 'street' => '7 Công trường Lam Sơn, Quận 1', 'city' => 79, 'district' => 760, 'ward' => 26734],
            'duong_sach_hcm' => ['name' => 'Đường sách TP.HCM - Nguyễn Văn Bình', 'street' => 'Nguyễn Văn Bình, Quận 1', 'city' => 79, 'district' => 760, 'ward' => 26734],
            'thu_duc_hcm' => ['name' => 'Công viên Sáng tạo Thủ Đức', 'street' => 'Phường Thủ Thiêm, Thủ Đức', 'city' => 79, 'district' => 769, 'ward' => 27106],
            'cho_lon_hcm' => ['name' => 'Phố Văn hóa & Ẩm thực Chợ Lớn', 'street' => 'Quận 5', 'city' => 79, 'district' => 774, 'ward' => 27280],
            'bac_ninh' => ['name' => 'Quảng trường Trung tâm Văn hóa Kinh Bắc', 'street' => 'TP. Bắc Ninh, Bắc Ninh', 'city' => 27, 'district' => 256, 'ward' => 9190],
            'sapa' => ['name' => 'Quảng trường Sân Quần - Thị xã Sa Pa', 'street' => 'Sa Pa, Lào Cai', 'city' => 10, 'district' => 88, 'ward' => 2944],
            'hue' => ['name' => 'Quảng trường Ngọ Môn - Kinh thành Huế', 'street' => '23 Tháng 8, TP. Huế', 'city' => 46, 'district' => 474, 'ward' => 19744],
        ];

        $venueIds = [];
        foreach ($venuesData as $key => $v) {
            $existing = DB::table('venues')->where('name', $v['name'])->first();
            if ($existing) {
                $venueIds[$key] = $existing->id;
            } else {
                $venueIds[$key] = DB::table('venues')->insertGetId(array_merge($v, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        // 3. Organizer mẫu
        $organizersData = [
            'music' => ['name' => 'Vietnam Live Experience', 'email' => 'seeder.live@ticket.local'],
            'stage' => ['name' => 'Sân Khấu & Nghệ Thuật Việt', 'email' => 'seeder.stage@ticket.local'],
            'sport' => ['name' => 'Vietnam Marathon Alliance', 'email' => 'seeder.marathon@ticket.local'],
            'tech' => ['name' => 'Vietnam Tech & Festival Hub', 'email' => 'seeder.tech@ticket.local'],
        ];

        $organizerIds = [];
        foreach ($organizersData as $key => $org) {
            $user = DB::table('users')->where('email', $org['email'])->first();
            $userId = $user ? $user->id : DB::table('users')->insertGetId([
                'name' => $org['name'],
                'email' => $org['email'],
                'password' => Hash::make('123456'),
                'phone' => '0900000' . rand(100, 999),
                'role' => 2,
                'status' => 1,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $organizer = DB::table('organizers')->where('user_id', $userId)->first();
            $organizerIds[$key] = $organizer ? $organizer->id : DB::table('organizers')->insertGetId([
                'user_id' => $userId,
                'organization_name' => $org['name'],
                'description' => 'Đơn vị tổ chức sự kiện, concert, giải chạy và lễ hội tại Việt Nam.',
                'website' => 'https://ticket.quanglab.site',
                'verified' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Ngày cuối tuần gần nhất (để tab "Cuối tuần này" luôn có dữ liệu)
        $weekendDate = $now->copy()->startOfWeek()->addDays(6)->setTime(19, 30);
        if ($weekendDate->lessThanOrEqualTo($now)) {
            $weekendDate = $now->copy()->next(Carbon::SATURDAY)->setTime(19, 30);
        }

        // 4. 38 sự kiện mới giai đoạn 11/2026 - 04/2027 với 38 ảnh riêng biệt
        $eventsCatalog = [
            // ================= CATEGORY 1: NHẠC SỐNG (12 sự kiện) =================
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'hcmc_stadium',
                'event_name' => 'Mỹ Tâm – See The Light Live Concert (Day 2)',
                'description' => '<p>Đêm diễn thứ hai trong chuỗi concert đặc biệt của Mỹ Tâm tại TP.HCM với sân khấu ánh sáng hiện đại và những bản hit gắn liền với nhiều thế hệ khán giả.</p>',
                'image' => '/images/events/my-tam-see-the-light-2026.jpg',
                'schedules' => [['2026-11-21 19:30:00', '2026-11-21 23:00:00']],
                'prices' => [900000, 2800000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'vec_hanoi',
                'event_name' => 'Concert Anh Trai Vượt Ngàn Chông Gai - Đêm Miền Bắc',
                'description' => '<p>Hai đêm đại nhạc hội bùng nổ tại miền Bắc với hàng loạt sân khấu trình diễn mãn nhãn, hệ thống âm thanh ánh sáng chuẩn quốc tế.</p>',
                'image' => '/images/events/anh-trai-vuot-ngan-chong-gai-2026.jpg',
                'schedules' => [
                    ['2026-12-05 19:00:00', '2026-12-05 23:00:00'],
                    ['2026-12-06 19:00:00', '2026-12-06 23:00:00'],
                ],
                'prices' => [800000, 2500000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'cung_dien_kinh_hn',
                'event_name' => 'Kosmik Live Concert 2026 in Hanoi: Homecoming',
                'description' => '<p>Đêm nhạc đánh dấu sự trở lại của SpaceSpeakers Label tại Thủ đô Hà Nội cùng những bản phối Hip-hop, R&B và Electronic đỉnh cao.</p>',
                'image' => '/images/events/kosmik-live-concert-hanoi-2026.jpg',
                'schedules' => [['2026-12-12 19:30:00', '2026-12-12 23:00:00']],
                'prices' => [750000, 2200000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'hcmc_riverside',
                'event_name' => 'HOZO 2026 - Liên Hoan Âm Nhạc Quốc Tế TP.HCM',
                'description' => '<p>Lễ hội âm nhạc cộng đồng lớn nhất TP.HCM tại Công viên Bờ sông Sài Gòn quy tụ các nghệ sĩ trong nước và quốc tế.</p>',
                'image' => '/images/events/hozo-music-festival-2026.jpg',
                'schedules' => [
                    ['2026-12-13 18:00:00', '2026-12-13 22:30:00'],
                    ['2026-12-14 18:00:00', '2026-12-14 22:30:00'],
                ],
                'prices' => [300000, 950000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'my_dinh_hn',
                'event_name' => 'Tùng Dương Live Concert: Bay Về Phía Mặt Trời',
                'description' => '<p>Live concert quy mô sân vận động của diva Tùng Dương tại SVĐ Mỹ Đình với sự góp mặt của dàn nhạc giao hưởng và khách mời đặc biệt.</p>',
                'image' => '/images/events/tung-duong-concert-2026.jpg',
                'schedules' => [['2026-12-19 19:30:00', '2026-12-19 23:00:00']],
                'prices' => [850000, 3200000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'hcmc_stadium',
                'event_name' => 'Sao Nhập Ngũ Concert - Day 3 (TP.HCM 2026)',
                'description' => '<p>Đại nhạc hội quy mô lớn kết hợp EDM, Rock và Pop cùng trải nghiệm văn hóa quân ngũ hiện đại tại TP.HCM.</p>',
                'image' => '/images/events/sao-nhap-ngu-concert-2026.jpg',
                'schedules' => [['2026-12-20 19:00:00', '2026-12-20 23:00:00']],
                'prices' => [750000, 1950000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'hcmc_riverside',
                'event_name' => 'Say Hi 2027 New Year Celebration Concert',
                'description' => '<p>Đêm nhạc ngoài trời quy tụ 27 nghệ sĩ Việt biểu diễn xuyên suốt khu vực Công viên Bờ sông Sài Gòn và Phố đi bộ Nguyễn Huệ cùng pháo hoa tầm cao.</p>',
                'image' => '/images/events/say-hi-2027-concert.jpg',
                'schedules' => [['2026-12-26 19:00:00', '2026-12-26 23:30:00']],
                'prices' => [500000, 1500000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'bach_dang_dn',
                'event_name' => 'Da Nang New Year Festival 2027 - Countdown Sông Hàn',
                'description' => '<p>Đại nhạc hội Countdown chào năm mới 2027 bên bờ sông Hàn và phố đi bộ Bạch Đằng kết hợp trình diễn pháo hoa nghệ thuật.</p>',
                'image' => '/images/events/da-nang-new-year-countdown-2027.jpg',
                'schedules' => [['2026-12-31 19:30:00', '2027-01-01 00:30:00']],
                'prices' => [350000, 1100000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'phu_quoc',
                'event_name' => 'TIDES Festival NYE 2027 - Phú Quốc',
                'description' => '<p>Lễ hội âm nhạc điện tử đón giao thừa trên bãi biển Phú Quốc với hai đêm trình diễn audio-visual đẳng cấp quốc tế.</p>',
                'image' => '/images/events/tides-festival-phu-quoc-2027.jpg',
                'schedules' => [
                    ['2026-12-31 20:00:00', '2027-01-01 03:00:00'],
                    ['2027-01-01 20:00:00', '2027-01-02 02:00:00'],
                ],
                'prices' => [900000, 2200000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'vec_hanoi',
                'event_name' => 'Golden Disc Awards lần thứ 41 - Vietnam Edition 2027',
                'description' => '<p>Lễ trao giải âm nhạc danh giá hàng đầu Hàn Quốc lần đầu tiên tổ chức tại Trung tâm Triển lãm Việt Nam (VEC), Hà Nội, quy tụ dàn nghệ sĩ K-pop hàng đầu.</p>',
                'image' => '/images/events/golden-disc-awards-2027.jpg',
                'schedules' => [
                    ['2027-01-09 18:30:00', '2027-01-09 22:30:00'],
                    ['2027-01-10 18:30:00', '2027-01-10 22:30:00'],
                ],
                'prices' => [1800000, 4500000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'vec_hanoi',
                'event_name' => 'Sao Nhập Ngũ Festival & Concert - Day 4 (Hà Nội 2027)',
                'description' => '<p>Sự kiện kết hợp lễ hội trải nghiệm ban ngày và đại nhạc hội bùng nổ buổi tối tại Thủ đô Hà Nội đầu năm 2027.</p>',
                'image' => '/images/events/sao-nhap-ngu-day-4-hanoi-2027.jpg',
                'schedules' => [['2027-01-16 17:00:00', '2027-01-16 23:00:00']],
                'prices' => [650000, 1850000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'bien_my_khe_dn',
                'event_name' => 'Summer Beach Music Festival 2027 - Biển Mỹ Khê',
                'description' => '<p>Đại nhạc hội mùa hè bên bãi biển Mỹ Khê Đà Nẵng dịp nghỉ lễ 30/4 - 1/5 với sân khấu ngoài trời và DJ quốc tế.</p>',
                'image' => '/images/events/summer-beach-music-fest-2027.jpg',
                'schedules' => [['2027-04-30 18:00:00', '2027-04-30 23:30:00']],
                'prices' => [450000, 1350000],
            ],

            // ================= CATEGORY 2: SÂN KHẤU & NGHỆ THUẬT (9 sự kiện) =================
            [
                'category_id' => 2,
                'org_key' => 'stage',
                'venue_key' => 'nha_hat_kich_vn',
                'event_name' => 'Vở Kịch Kinh Điển: Vũ Như Tô - Nhà hát Kịch Việt Nam',
                'description' => '<p>Tác phẩm chính kịch kinh điển được dàn dựng công phu tại Nhà hát Kịch Việt Nam (Số 1 Tràng Tiền, Hoàn Kiếm, Hà Nội).</p>',
                'image' => '/images/events/vo-kich-vu-nhu-to-2026.jpg',
                'schedules' => [
                    [$weekendDate->format('Y-m-d H:i:s'), $weekendDate->copy()->addHours(3)->format('Y-m-d H:i:s')],
                    [$weekendDate->copy()->addDays(7)->format('Y-m-d H:i:s'), $weekendDate->copy()->addDays(7)->addHours(3)->format('Y-m-d H:i:s')],
                ],
                'prices' => [250000, 600000],
            ],
            [
                'category_id' => 2,
                'org_key' => 'stage',
                'venue_key' => 'the_gioi_tre',
                'event_name' => 'Vở Kịch: Hẹn Anh Ở Bến Sông - Sân khấu Thế Giới Trẻ',
                'description' => '<p>Vở diễn mới giàu cảm xúc kết hợp hài hước duyên dáng tại Sân khấu Thế Giới Trẻ (125 Cống Quỳnh, Quận 1, TP.HCM).</p>',
                'image' => '/images/events/vo-kich-hen-anh-o-ben-song.jpg',
                'schedules' => [
                    [$weekendDate->copy()->setTime(20, 0)->format('Y-m-d H:i:s'), $weekendDate->copy()->setTime(22, 30)->format('Y-m-d H:i:s')],
                    [$weekendDate->copy()->addDays(7)->setTime(20, 0)->format('Y-m-d H:i:s'), $weekendDate->copy()->addDays(7)->setTime(22, 30)->format('Y-m-d H:i:s')],
                ],
                'prices' => [220000, 420000],
            ],
            [
                'category_id' => 2,
                'org_key' => 'stage',
                'venue_key' => 'kich_hong_van',
                'event_name' => 'Kịch Kinh Dị Hài: Người Vợ Ma - Sân khấu Kịch Hồng Vân',
                'description' => '<p>Tác phẩm ăn khách nhiều năm liền của Sân khấu Kịch Hồng Vân với những màn tung hứng duyên dáng và tình tiết bất ngờ.</p>',
                'image' => '/images/events/kich-hong-van-nguoi-vo-ma.jpg',
                'schedules' => [
                    [$weekendDate->copy()->addDays(6)->setTime(19, 30)->format('Y-m-d H:i:s'), $weekendDate->copy()->addDays(6)->setTime(22, 0)->format('Y-m-d H:i:s')],
                    ['2026-12-05 19:30:00', '2026-12-05 22:00:00'],
                ],
                'prices' => [200000, 380000],
            ],
            [
                'category_id' => 2,
                'org_key' => 'stage',
                'venue_key' => 'hoang_thai_thanh',
                'event_name' => 'Kịch Tâm Lý: Bông Hồng Cài Áo - Sân khấu Hoàng Thái Thanh',
                'description' => '<p>Vở kịch tâm lý xã hội giàu tính nhân văn mang đậm dấu ấn nghệ thuật của sân khấu Hoàng Thái Thanh.</p>',
                'image' => '/images/events/hoang-thai-thanh-bong-hong-cai-ao.jpg',
                'schedules' => [
                    ['2026-11-28 19:30:00', '2026-11-28 22:15:00'],
                    ['2026-12-13 19:30:00', '2026-12-13 22:15:00'],
                ],
                'prices' => [230000, 450000],
            ],
            [
                'category_id' => 2,
                'org_key' => 'stage',
                'venue_key' => 'vpbank_hcm',
                'event_name' => 'Cosmos BigBang 20th Anniversary Media Exhibition',
                'description' => '<p>Triển lãm nghệ thuật tương tác số kỷ niệm 20 năm BigBang đầu tiên tại Đông Nam Á, ứng dụng VR, ánh sáng laser và hologram.</p>',
                'image' => '/images/events/cosmos-bigbang-exhibition-2026.jpg',
                'schedules' => [
                    ['2026-11-21 10:00:00', '2026-11-21 21:00:00'],
                    ['2026-12-12 10:00:00', '2026-12-12 21:00:00'],
                ],
                'prices' => [450000, 890000],
            ],
            [
                'category_id' => 2,
                'org_key' => 'stage',
                'venue_key' => 'bao_tang_dn',
                'event_name' => 'Da Nang Concert 2027 - Giao Hưởng & Opera Đương Đại',
                'description' => '<p>Chương trình nghệ thuật ngày đầu năm 2027 kết hợp dàn nhạc giao hưởng, opera và dancesport tại Bảo tàng Đà Nẵng.</p>',
                'image' => '/images/events/da-nang-concert-2027.jpg',
                'schedules' => [['2027-01-01 19:30:00', '2027-01-01 22:00:00']],
                'prices' => [300000, 750000],
            ],
            [
                'category_id' => 2,
                'org_key' => 'stage',
                'venue_key' => 'nha_hat_lon_hn',
                'event_name' => 'Hòa Nhạc Giao Hưởng Chào Xuân 2027 - Nhà hát Lớn Hà Nội',
                'description' => '<p>Đêm hòa nhạc thính phòng và giao hưởng đặc biệt chào đón năm mới 2027 trong không gian kiến trúc cổ điển của Nhà hát Lớn Hà Nội.</p>',
                'image' => '/images/events/hoa-nhac-giao-huong-ha-noi.jpg',
                'schedules' => [['2027-01-23 20:00:00', '2027-01-23 22:30:00']],
                'prices' => [500000, 1200000],
            ],
            [
                'category_id' => 2,
                'org_key' => 'stage',
                'venue_key' => 'nha_hat_tuoi_tre',
                'event_name' => 'Nhà hát Tuổi Trẻ: Đêm Hài Kịch Chào Xuân Đinh Mùi 2027',
                'description' => '<p>Chương trình hài kịch châm biếm đặc sắc nằm trong chuỗi thương hiệu Đời Cười phục vụ khán giả Thủ đô dịp đầu xuân 2027.</p>',
                'image' => '/images/events/nha-hat-tuoi-tre-doi-cuoi-2027.jpg',
                'schedules' => [['2027-02-14 20:00:00', '2027-02-14 22:30:00']],
                'prices' => [200000, 480000],
            ],
            [
                'category_id' => 2,
                'org_key' => 'stage',
                'venue_key' => 'bao_tang_dn',
                'event_name' => 'Van Gogh Art Lighting Experience - Đà Nẵng 2027',
                'description' => '<p>Triển lãm nghệ thuật tương tác đa giác quan với công nghệ trình chiếu 3D Mapping tái hiện thế giới hội họa của danh họa Van Gogh.</p>',
                'image' => '/images/events/van-gogh-art-lighting-danang-2027.jpg',
                'schedules' => [
                    ['2027-02-20 09:00:00', '2027-02-20 21:00:00'],
                    ['2027-03-06 09:00:00', '2027-03-06 21:00:00'],
                ],
                'prices' => [320000, 680000],
            ],

            // ================= CATEGORY 3: THỂ THAO (8 sự kiện) =================
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'halong_bay',
                'event_name' => 'Halong Bay Heritage Marathon 2026',
                'description' => '<p>Cung đường chạy di sản dọc theo bờ biển và những dãy núi đá vôi hùng vĩ của Vịnh Hạ Long với các cự ly 5km, 10km, 21km và 42km.</p>',
                'image' => '/images/events/halong-bay-heritage-marathon-2026.jpg',
                'schedules' => [['2026-11-22 04:30:00', '2026-11-22 11:30:00']],
                'prices' => [650000, 1450000],
            ],
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'hoan_kiem_hn',
                'event_name' => 'VnExpress Marathon Hanoi Midnight 2026',
                'description' => '<p>Giải chạy đêm đặc trưng của Thủ đô đưa các runner khám phá Hồ Hoàn Kiếm, Phố Cổ và những cây cầu rực sáng ánh đèn về đêm.</p>',
                'image' => '/images/events/vnexpress-marathon-hanoi-midnight-2026.jpg',
                'schedules' => [['2026-11-29 00:30:00', '2026-11-29 06:30:00']],
                'prices' => [700000, 1500000],
            ],
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'le_duan_hcm',
                'event_name' => 'Techcombank Ho Chi Minh City International Marathon 2026',
                'description' => '<p>Một trong những giải marathon quốc tế lớn nhất Việt Nam đi qua các công trình biểu tượng của TP. Hồ Chí Minh.</p>',
                'image' => '/images/events/techcombank-hcmc-marathon-2026.jpg',
                'schedules' => [['2026-12-06 04:00:00', '2026-12-06 11:00:00']],
                'prices' => [750000, 1600000],
            ],
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'cua_lo_na',
                'event_name' => 'Nghệ An Legacy Marathon - Dấu Chân Quê Bác 2026',
                'description' => '<p>Giải chạy ven biển Cửa Lò kết nối hành trình văn hóa lịch sử xứ Nghệ với các cự ly từ 5km đến Full Marathon 42km.</p>',
                'image' => '/images/events/nghe-an-legacy-marathon-2026.jpg',
                'schedules' => [['2026-12-06 04:30:00', '2026-12-06 11:30:00']],
                'prices' => [550000, 1250000],
            ],
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'can_tho',
                'event_name' => 'Can Tho Marathon Heritage Race 2026',
                'description' => '<p>Giải chạy khám phá vẻ đẹp sông nước, những cây cầu biểu tượng và nét văn hóa miệt vườn Tây Đô Cần Thơ.</p>',
                'image' => '/images/events/can-tho-marathon-heritage-2026.jpg',
                'schedules' => [['2026-12-20 04:30:00', '2026-12-20 11:00:00']],
                'prices' => [600000, 1300000],
            ],
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'moc_chau',
                'event_name' => 'Vietnam Trail Marathon 2027 - Mộc Châu',
                'description' => '<p>Cung đường chạy địa hình đầu xuân qua những đồi chè xanh mướt, vườn mận trắng và bản làng vùng cao Mộc Châu.</p>',
                'image' => '/images/events/vietnam-trail-marathon-moc-chau-2027.jpg',
                'schedules' => [['2027-01-15 05:00:00', '2027-01-15 19:00:00']],
                'prices' => [890000, 2300000],
            ],
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'mang_den',
                'event_name' => 'Măng Đen Ultra Trail 2027 - Đại Ngàn Tây Nguyên',
                'description' => '<p>Thử thách chạy trail giữa rừng thông nguyên sinh và khí hậu se lạnh đặc trưng của cao nguyên Măng Đen.</p>',
                'image' => '/images/events/mang-den-ultra-trail-2027.jpg',
                'schedules' => [['2027-01-23 05:00:00', '2027-01-24 17:00:00']],
                'prices' => [850000, 2100000],
            ],
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'dalat_lam_vien',
                'event_name' => 'Dalat Ultra Trail 2027 - Chinh Phục Cao Nguyên',
                'description' => '<p>Giải chạy địa hình xuyên rừng thông và thung lũng Đà Lạt dành cho các vận động viên yêu thích thử thách thiên nhiên.</p>',
                'image' => '/images/events/dalat-ultra-trail-2027.jpg',
                'schedules' => [['2027-03-19 05:00:00', '2027-03-21 18:00:00']],
                'prices' => [950000, 2400000],
            ],

            // ================= CATEGORY 4: KHÁC (9 sự kiện) =================
            [
                'category_id' => 4,
                'org_key' => 'tech',
                'venue_key' => 'thiskyhall_hcm',
                'event_name' => 'DNSE Future Tech Summit 2026 - Market IQ & AI',
                'description' => '<p>Hội nghị công nghệ tài chính thường niên với chủ đề "Market IQ: Level Up Your Investment", tập trung vào AI và dữ liệu đầu tư.</p>',
                'image' => '/images/events/dnse-future-tech-summit-2026.jpg',
                'schedules' => [['2026-11-20 08:30:00', '2026-11-20 17:30:00']],
                'prices' => [200000, 650000],
            ],
            [
                'category_id' => 4,
                'org_key' => 'tech',
                'venue_key' => 'sihub_hcm',
                'event_name' => 'Vietnam Women Tech Summit & Award 2026',
                'description' => '<p>Diễn đàn kết nối, chia sẻ kinh nghiệm và vinh danh phụ nữ trong lĩnh vực công nghệ tại Trung tâm Khởi nghiệp Sáng tạo TP.HCM (SIHUB).</p>',
                'image' => '/images/events/vietnam-women-tech-summit-2026.jpg',
                'schedules' => [['2026-11-27 09:00:00', '2026-11-27 17:00:00']],
                'prices' => [150000, 450000],
            ],
            [
                'category_id' => 4,
                'org_key' => 'tech',
                'venue_key' => 'ncc_hanoi',
                'event_name' => 'AV Show Hà Nội 2026 - Triển Lãm Thiết Bị Nghe Nhìn',
                'description' => '<p>Triển lãm thiết bị âm thanh hi-end, home theater và công nghệ nghe nhìn cao cấp tại Trung tâm Hội nghị Quốc gia.</p>',
                'image' => '/images/events/av-show-hanoi-2026.jpg',
                'schedules' => [
                    ['2026-11-27 09:00:00', '2026-11-27 18:00:00'],
                    ['2026-11-28 09:00:00', '2026-11-29 18:00:00'],
                ],
                'prices' => [100000, 300000],
            ],
            [
                'category_id' => 4,
                'org_key' => 'tech',
                'venue_key' => 'thiskyhall_hcm',
                'event_name' => 'Vietnam Web Summit 2026 (VWS) - AI & Digital Era',
                'description' => '<p>Sự kiện công nghệ lớn nhất năm dành cho lập trình viên, Product Manager, Marketer và doanh nghiệp số với hơn 50 phiên thảo luận.</p>',
                'image' => '/images/events/vietnam-web-summit-2026.jpg',
                'schedules' => [['2026-12-11 08:30:00', '2026-12-11 17:30:00']],
                'prices' => [390000, 1290000],
            ],
            [
                'category_id' => 4,
                'org_key' => 'tech',
                'venue_key' => 'hcmc_riverside',
                'event_name' => 'Lễ Hội Ẩm Thực & Chợ Đêm Sáng Tạo Sài Gòn 2026',
                'description' => '<p>Không gian giao lưu ẩm thực đường phố, thủ công sáng tạo và acoustic bên bờ sông Sài Gòn dịp cuối năm.</p>',
                'image' => '/images/events/le-hoi-am-thuc-van-hoa-2026.jpg',
                'schedules' => [
                    ['2026-12-18 16:00:00', '2026-12-18 22:30:00'],
                    ['2026-12-19 16:00:00', '2026-12-19 22:30:00'],
                ],
                'prices' => [99000, 299000],
            ],
            [
                'category_id' => 4,
                'org_key' => 'tech',
                'venue_key' => 'bach_dang_dn',
                'event_name' => 'Lễ Hội Ẩm Thực Xứ Quảng - Món Ngon Miền Trung 2026',
                'description' => '<p>Không gian trải nghiệm hàng trăm món đặc sản miền Trung bên bờ sông Hàn trong chuỗi lễ hội chào năm mới tại Đà Nẵng.</p>',
                'image' => '/images/events/le-hoi-am-thuc-xu-quang-2026.jpg',
                'schedules' => [
                    ['2026-12-28 15:00:00', '2026-12-28 22:00:00'],
                    ['2026-12-31 15:00:00', '2026-12-31 23:00:00'],
                ],
                'prices' => [80000, 220000],
            ],
            [
                'category_id' => 4,
                'org_key' => 'tech',
                'venue_key' => 'hcmc_riverside',
                'event_name' => 'Đêm Hội Ẩm Thực & Văn Hóa Phố Đêm Sài Gòn 2027',
                'description' => '<p>Trải nghiệm ẩm thực đường phố, đèn lồng nghệ thuật và biểu diễn đường phố sôi động giữa lòng Quận 1.</p>',
                'image' => '/images/events/dem-cho-am-thuc-sai-gon-2027.jpg',
                'schedules' => [['2027-02-14 17:00:00', '2027-02-14 23:00:00']],
                'prices' => [120000, 320000],
            ],
            [
                'category_id' => 4,
                'org_key' => 'tech',
                'venue_key' => 'ncc_hanoi',
                'event_name' => 'Vietnam AI & Startup Day 2027 - Hà Nội',
                'description' => '<p>Ngày hội kết nối startup, quỹ đầu tư và cộng đồng kỹ sư AI với các workshop thực chiến và khu triển lãm sản phẩm công nghệ.</p>',
                'image' => '/images/events/ai-startup-day-hanoi-2027.jpg',
                'schedules' => [['2027-02-25 08:30:00', '2027-02-25 17:30:00']],
                'prices' => [250000, 790000],
            ],
            [
                'category_id' => 4,
                'org_key' => 'tech',
                'venue_key' => 'secc_hcm',
                'event_name' => 'Hội Chợ Triển Lãm Công Nghệ & Thương Mại SECC 2027',
                'description' => '<p>Triển lãm thương mại và công nghệ quy mô quốc tế quy tụ hàng trăm gian hàng doanh nghiệp tại SECC Quận 7, TP.HCM.</p>',
                'image' => '/images/events/secc-vietnam-expo-2027.jpg',
                'schedules' => [
                    ['2027-03-10 09:00:00', '2027-03-10 17:30:00'],
                    ['2027-03-11 09:00:00', '2027-03-12 17:30:00'],
                ],
                'prices' => [100000, 400000],
            ],

            // ================= BỔ SUNG THÊM 20 SỰ KIỆN MỚI (TỔNG 58 SỰ KIỆN) =================
            // Nhạc sống
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'hcmc_stadium',
                'event_name' => 'Rock Storm Vietnam Tour 2027 - Đêm Bùng Nổ TP.HCM',
                'description' => '<p>Đêm nhạc Rock cuồng nhiệt quy tụ các ban nhạc rock hàng đầu Việt Nam với dàn âm thanh công suất lớn và hiệu ứng sân khấu rực lửa.</p>',
                'image' => '/images/events/rock-storm-vietnam-2027.jpg',
                'schedules' => [['2027-03-14 19:00:00', '2027-03-14 23:00:00']],
                'prices' => [450000, 1200000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'hue',
                'event_name' => 'Festival Huế 2027 - Đêm Khai Mạc Hương Sắc Cố Đô',
                'description' => '<p>Chương trình nghệ thuật và âm nhạc cung đình kết hợp đương đại tại Quảng trường Ngọ Môn, Kinh thành Huế.</p>',
                'image' => '/images/events/festival-hue-2027.jpg',
                'schedules' => [['2027-04-24 19:30:00', '2027-04-24 22:30:00']],
                'prices' => [350000, 950000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'hcmc_riverside',
                'event_name' => 'Đêm Nhạc Hương Sắc Việt 2027 - Sài Gòn Đêm Hội',
                'description' => '<p>Không gian âm nhạc giao thoa giữa dân gian đương đại và Pop Ballad bên dòng sông Sài Gòn rực rỡ ánh đèn.</p>',
                'image' => '/images/events/dem-nhac-huong-sac-viet-2027.jpg',
                'schedules' => [['2027-02-21 19:30:00', '2027-02-21 22:30:00']],
                'prices' => [380000, 980000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'bach_dang_dn',
                'event_name' => 'DIFF 2027 - Lễ Hội Pháo Hoa Quốc Tế Đà Nẵng (Đêm Khai Mạc)',
                'description' => '<p>Đêm khai mạc Lễ hội Pháo hoa Quốc tế Đà Nẵng 2027 bên sông Hàn kết hợp sân khấu âm nhạc hoành tráng.</p>',
                'image' => '/images/events/diff-da-nang-fireworks-2027.jpg',
                'schedules' => [['2027-05-29 19:30:00', '2027-05-29 22:30:00']],
                'prices' => [800000, 2500000],
            ],
            [
                'category_id' => 1,
                'org_key' => 'music',
                'venue_key' => 'bach_dang_dn',
                'event_name' => 'DIFF 2027 - Đêm Chung Kết Pháo Hoa Quốc Tế Đà Nẵng',
                'description' => '<p>Màn tranh tài chung kết rực rỡ nhất mùa hè 2027 tại khán đài sông Hàn, Đà Nẵng cùng dàn sao V-pop.</p>',
                'image' => '/images/events/diff-da-nang-finale-2027.jpg',
                'schedules' => [['2027-07-10 19:30:00', '2027-07-10 23:00:00']],
                'prices' => [1000000, 3500000],
            ],

            // Sân khấu & Nghệ thuật
            [
                'category_id' => 2,
                'org_key' => 'stage',
                'venue_key' => 'nha_hat_lon_hn',
                'event_name' => 'Múa Rối Nước & Nghệ Thuật Truyền Thống Thăng Long 2026',
                'description' => '<p>Chương trình biểu diễn nghệ thuật dân gian đặc sắc tôn vinh di sản văn hóa Bắc Bộ dành cho du khách trong và ngoài nước.</p>',
                'image' => '/images/events/mua-roi-nuoc-thang-long-2026.jpg',
                'schedules' => [
                    ['2026-12-05 18:30:00', '2026-12-05 20:00:00'],
                    ['2026-12-19 18:30:00', '2026-12-19 20:00:00'],
                ],
                'prices' => [180000, 350000],
            ],
            [
                'category_id' => 2,
                'org_key' => 'stage',
                'venue_key' => 'nha_hat_hcm',
                'event_name' => 'Triển Lãm Nghệ Thuật Ấn Tượng Mùa Xuân 2027',
                'description' => '<p>Không gian trưng bày tranh sơn dầu, nghệ thuật thị giác và tương tác ánh sáng mừng Xuân 2027 tại trung tâm TP.HCM.</p>',
                'image' => '/images/events/trien-lam-nghe-thuat-mua-xuan-2027.jpg',
                'schedules' => [
                    ['2027-02-10 09:00:00', '2027-02-10 20:00:00'],
                    ['2027-02-17 09:00:00', '2027-02-17 20:00:00'],
                ],
                'prices' => [150000, 350000],
            ],
            [
                'category_id' => 2,
                'org_key' => 'stage',
                'venue_key' => 'nha_hat_hcm',
                'event_name' => 'Gala Múa Đương Đại Quốc Tế - Saigon Dance Festival 2027',
                'description' => '<p>Đêm diễn hội tụ các biên đạo và vũ công xuất sắc trong nước và quốc tế tại Nhà hát Thành phố Hồ Chí Minh.</p>',
                'image' => '/images/events/royal-dance-gala-saigon-2027.jpg',
                'schedules' => [['2027-04-11 19:30:00', '2027-04-11 22:00:00']],
                'prices' => [400000, 1100000],
            ],

            // Thể thao
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'le_duan_hcm',
                'event_name' => 'Ho Chi Minh City Marathon 2027 - Đường Chạy Khai Xuân',
                'description' => '<p>Giải chạy đường trường truyền thống lâu đời bậc nhất TP.HCM mở màn mùa giải marathon năm 2027.</p>',
                'image' => '/images/events/hcmc-marathon-2027.jpg',
                'schedules' => [['2027-01-17 04:00:00', '2027-01-17 11:00:00']],
                'prices' => [750000, 1650000],
            ],
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'phu_tho_hcm',
                'event_name' => 'VBA 2027: Saigon Heat vs Hanoi Buffaloes',
                'description' => '<p>Trận cầu tâm điểm của Giải Bóng Rổ Chuyên Nghiệp Việt Nam (VBA) tại Nhà thi đấu Phú Thọ với bầu không khí cuồng nhiệt.</p>',
                'image' => '/images/events/vba-saigon-heat-vs-hanoi-2027.jpg',
                'schedules' => [['2027-03-27 19:30:00', '2027-03-27 22:00:00']],
                'prices' => [250000, 850000],
            ],
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'cung_dien_kinh_hn',
                'event_name' => 'VBA All-Star Game 2027 - Đêm Hội Bóng Rổ Việt Nam',
                'description' => '<p>Sự kiện quy tụ dàn ngôi sao sáng nhất giải bóng rổ chuyên nghiệp VBA cùng các màn úp rổ và ném 3 điểm mãn nhãn.</p>',
                'image' => '/images/events/vba-all-star-game-2027.jpg',
                'schedules' => [['2027-05-08 19:00:00', '2027-05-08 22:00:00']],
                'prices' => [300000, 950000],
            ],
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'bac_ninh',
                'event_name' => 'Tiền Phong Marathon 2027 - Giải Vô Địch Quốc Gia (Bắc Ninh)',
                'description' => '<p>Giải việt dã lâu đời nhất Việt Nam năm 2027 tại vùng đất quan họ Bắc Ninh quy tụ hàng nghìn VĐV chuyên nghiệp và phong trào.</p>',
                'image' => '/images/events/tien-phong-marathon-2027.jpg',
                'schedules' => [['2027-03-28 04:30:00', '2027-03-28 11:30:00']],
                'prices' => [650000, 1400000],
            ],
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'phu_tho_hcm',
                'event_name' => 'Chung Kết VCS Mùa Xuân 2027 - Đấu Trường Thể Thao Điện Tử',
                'description' => '<p>Trận chung kết giải đấu Esports chuyên nghiệp hàng đầu Việt Nam với hàng nghìn khán giả cổ vũ trực tiếp tại nhà thi đấu.</p>',
                'image' => '/images/events/esports-vcs-spring-finals-2027.jpg',
                'schedules' => [['2027-04-04 14:00:00', '2027-04-04 21:30:00']],
                'prices' => [200000, 750000],
            ],
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'halong_bay',
                'event_name' => 'Aqua Warriors Ha Long Bay 2027 - Bơi & Chạy Vịnh Di Sản',
                'description' => '<p>Giải đấu phối hợp bơi biển - chạy bộ hấp dẫn bậc nhất miền Bắc tổ chức bên kỳ quan thiên nhiên Vịnh Hạ Long.</p>',
                'image' => '/images/events/aqua-warriors-halong-2027.jpg',
                'schedules' => [['2027-04-11 05:30:00', '2027-04-11 12:30:00']],
                'prices' => [850000, 1950000],
            ],
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'sapa',
                'event_name' => 'Vietnam Mountain Marathon 2027 - Chinh Phục Sa Pa',
                'description' => '<p>Hành trình chạy trail băng qua những thửa ruộng bậc thang và dãy Hoàng Liên Sơn hùng vĩ tại Sa Pa.</p>',
                'image' => '/images/events/sapa-mountain-marathon-2027.jpg',
                'schedules' => [['2027-04-18 05:00:00', '2027-04-18 19:00:00']],
                'prices' => [1100000, 2800000],
            ],
            [
                'category_id' => 3,
                'org_key' => 'sport',
                'venue_key' => 'hcmc_stadium',
                'event_name' => 'Lễ Hội Bóng Đá Trẻ & Giao Lưu Ngôi Sao TP.HCM 2027',
                'description' => '<p>Ngày hội bóng đá cộng đồng quy tụ các tuyển thủ quốc gia, trận đấu giao hữu và khu trải nghiệm dành cho người hâm mộ.</p>',
                'image' => '/images/events/festival-bong-da-tre-hcmc-2027.jpg',
                'schedules' => [['2027-05-16 16:00:00', '2027-05-16 21:00:00']],
                'prices' => [150000, 500000],
            ],

            // Khác (Hội sách, Lễ hội ẩm thực, Văn hóa)
            [
                'category_id' => 4,
                'org_key' => 'tech',
                'venue_key' => 'hcmc_riverside',
                'event_name' => 'Lễ Hội Phố Ẩm Thực Đêm Việt - Nhật 2026',
                'description' => '<p>Hành trình khám phá tinh hoa ẩm thực đường phố Việt Nam - Nhật Bản cùng không gian check-in đèn lồng lung linh.</p>',
                'image' => '/images/events/pho-am-thuc-dem-nhat-viet-2026.jpg',
                'schedules' => [
                    ['2026-12-26 16:00:00', '2026-12-26 22:30:00'],
                    ['2026-12-27 16:00:00', '2026-12-27 22:30:00'],
                ],
                'prices' => [90000, 250000],
            ],
            [
                'category_id' => 4,
                'org_key' => 'tech',
                'venue_key' => 'hoan_kiem_hn',
                'event_name' => 'Hội Sách Mùa Xuân Hà Nội 2027 - Văn Hóa Đọc Thủ Đô',
                'description' => '<p>Hàng trăm gian hàng từ các nhà xuất bản lớn, tọa đàm tác giả và hoạt động ký tặng sách đầu xuân tại Hà Nội.</p>',
                'image' => '/images/events/hoi-sach-mua-xuan-hanoi-2027.jpg',
                'schedules' => [
                    ['2027-02-20 08:30:00', '2027-02-20 20:00:00'],
                    ['2027-02-21 08:30:00', '2027-02-21 20:00:00'],
                ],
                'prices' => [50000, 150000],
            ],
            [
                'category_id' => 4,
                'org_key' => 'tech',
                'venue_key' => 'cho_lon_hcm',
                'event_name' => 'Lễ Hội Ẩm Thực & Đèn Lồng Chợ Lớn 2027',
                'description' => '<p>Sự kiện văn hóa ẩm thực đặc sắc mùa lễ hội tại khu vực Chợ Lớn với hàng chục gian hàng món ngon truyền thống và múa lân sư rồng.</p>',
                'image' => '/images/events/le-hoi-am-thuc-cho-lon-2027.jpg',
                'schedules' => [['2027-02-12 16:00:00', '2027-02-12 22:30:00']],
                'prices' => [80000, 220000],
            ],
            [
                'category_id' => 4,
                'org_key' => 'tech',
                'venue_key' => 'duong_sach_hcm',
                'event_name' => 'Ngày Sách & Văn Hóa Đọc Việt Nam 2027 - TP.HCM',
                'description' => '<p>Chuỗi hoạt động hưởng ứng Ngày Sách và Văn hóa đọc Việt Nam 21/4 với hội sách, triển lãm sách thông minh và giao lưu tác giả.</p>',
                'image' => '/images/events/hoi-cho-sach-thu-duc-2027.jpg',
                'schedules' => [
                    ['2027-04-21 08:00:00', '2027-04-21 21:00:00'],
                    ['2027-04-22 08:00:00', '2027-04-22 21:00:00'],
                ],
                'prices' => [50000, 180000],
            ],
        ];

        $baseUrl = rtrim(config('app.url'), '/');
        if ($this->command && (empty($baseUrl) || str_contains($baseUrl, 'localhost'))) {
            $this->command->warn("APP_URL hiện là '{$baseUrl}'. Trên production nhớ đặt APP_URL là URL HTTPS của backend (vd: https://api.quanglab.site) trước khi chạy seeder để ảnh load đúng.");
        }

        $categoryMap = [
            'music' => 1,
            'sport' => 2,
            'stage' => 3,
            'tech' => 4,
        ];

        foreach ($eventsCatalog as $item) {
            $categoryId = $categoryMap[$item['org_key']] ?? $item['category_id'];
            $imageUrl = $baseUrl . '/' . ltrim($item['image'], '/');

            $existingEvent = Event::where('event_name', $item['event_name'])->first();
            if ($existingEvent) {
                $existingEvent->update([
                    'category_id' => $categoryId,
                    'background_image_url' => $imageUrl,
                    'poster_image_url' => $imageUrl,
                ]);
                $existingEvent->categories()->syncWithoutDetaching([$categoryId]);
                continue;
            }

            $event = Event::create([
                'organizer_id' => $organizerIds[$item['org_key']],
                'venue_id' => $venueIds[$item['venue_key']],
                'category_id' => $categoryId,
                'event_name' => $item['event_name'],
                'description' => $item['description'],
                'background_image_url' => $imageUrl,
                'poster_image_url' => $imageUrl,
                'status' => Event::STATUS_PUBLISHED,
            ]);

            $event->categories()->syncWithoutDetaching([$categoryId]);

            foreach ($item['schedules'] as [$startStr, $endStr]) {
                $start = Carbon::parse($startStr);
                $end = Carbon::parse($endStr);

                // Đảm bảo nếu chạy seeder sau thời điểm cố định thì tự dời sang tương lai
                if ($end->lessThan($now)) {
                    $daysDiff = $end->diffInDays($now) + 14;
                    $start = $start->addDays($daysDiff);
                    $end = $end->addDays($daysDiff);
                }

                $schedule = EventSchedule::create([
                    'event_id' => $event->id,
                    'start_datetime' => $start,
                    'end_datetime' => $end,
                    'status' => EventSchedule::STATUS_UPCOMING,
                    'available_tickets' => 600,
                ]);

                TicketType::create([
                    'schedule_id' => $schedule->id,
                    'name' => 'Standard',
                    'base_price' => $item['prices'][0],
                    'total_quantity' => 500,
                    'available_quantity' => 500,
                    'status' => TicketType::STATUS_ACTIVE,
                ]);

                TicketType::create([
                    'schedule_id' => $schedule->id,
                    'name' => 'VIP',
                    'base_price' => $item['prices'][1],
                    'total_quantity' => 100,
                    'available_quantity' => 100,
                    'status' => TicketType::STATUS_ACTIVE,
                ]);
            }
        }

        $this->seedTrendingOrders($now);
    }

    private function seedTrendingOrders(Carbon $now): void
    {
        $buyerId = DB::table('users')->where('role', 1)->value('id')
            ?? DB::table('users')->value('id');
        if (!$buyerId) {
            return;
        }

        $minEnd = $now->copy()->addDays(28);
        $topEvents = Event::where('status', Event::STATUS_PUBLISHED)
            ->whereHas('schedules', fn($q) => $q->where('end_datetime', '>=', $minEnd))
            ->with(['schedules' => fn($q) => $q->where('end_datetime', '>=', $minEnd)->orderBy('start_datetime')])
            ->orderBy('id')
            ->limit(10)
            ->get();

        foreach ($topEvents as $idx => $event) {
            $schedule = $event->schedules->first();
            if (!$schedule) {
                continue;
            }
            $ticketType = TicketType::where('schedule_id', $schedule->id)->first();
            if (!$ticketType) {
                continue;
            }

            $orderCode = 'TKB-TREND-UP-' . $event->id;
            $qty = 95 - ($idx * 5);
            $amount = $qty * (int) $ticketType->base_price;
            $createdAt = $now->copy()->subHours($idx);

            $existingOrder = DB::table('orders')->where('order_code', $orderCode)->first();
            if ($existingOrder) {
                DB::table('orders')->where('id', $existingOrder->id)->update([
                    'created_at' => $createdAt,
                    'updated_at' => $now,
                ]);
                continue;
            }

            $orderId = DB::table('orders')->insertGetId([
                'user_id' => $buyerId,
                'order_code' => $orderCode,
                'total_amount' => $amount,
                'discount_amount' => 0,
                'final_amount' => $amount,
                'payment_status' => 'paid',
                'payment_method' => 'payos',
                'created_at' => $createdAt,
                'updated_at' => $now,
            ]);

            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'ticket_type_id' => $ticketType->id,
                'quantity' => $qty,
                'unit_price' => (int) $ticketType->base_price,
                'subtotal' => $amount,
                'created_at' => $createdAt,
                'updated_at' => $now,
            ]);
        }
    }
}
