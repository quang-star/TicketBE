# Ticket Backend (`ticket_backend`)

## Giới thiệu dự án

**Ticket Backend** là hệ thống RESTful API được xây dựng trên nền tảng **Laravel 10**, đóng vai trò xử lý nghiệp vụ, xác thực người dùng, quản lý dữ liệu và tích hợp cổng thanh toán cho ứng dụng đặt vé sự kiện trực tuyến **Ticket** (`ticketvue`).

## Các phân hệ và chức năng chính

### 1. Xác thực & Quản lý tài khoản (`/api/login`, `/api/register`, ...)
- Đăng ký tài khoản mới và xác thực tài khoản bằng mã xác nhận gửi qua email (`/verifyAccount`, `/resend-code`).
- Đăng nhập truyền thống (JWT/OAuth2 qua **Laravel Passport**), đăng nhập bằng Google (`/loginwithgoogle`), quên mật khẩu & đặt lại mật khẩu.
- Cập nhật thông tin cá nhân và đổi mật khẩu.

### 2. Nghiệp vụ Khách hàng (Customer)
- **Khám phá & Tìm kiếm sự kiện:** Cung cấp API lấy danh sách sự kiện xu hướng, cuối tuần, cuối tháng, theo danh mục, chi tiết sự kiện, thông tin ban tổ chức và tìm kiếm sự kiện theo bộ lọc.
- **Giỏ hàng & Đơn hàng:** Thêm/sửa/xóa vé trong giỏ hàng, tạo đơn hàng, xem lịch sử đơn hàng và chi tiết vé kèm mã QR (`simple-qrcode`).
- **Thanh toán trực tuyến qua PayOS:** Tạo liên kết thanh toán qua `payos/payos`, xử lý callback `return`, `cancel`, `webhook` và kiểm tra trạng thái thanh toán (`/payment/status`).
- **Đánh giá & Báo cáo:** Gửi đánh giá sự kiện và báo cáo vi phạm cho sự kiện.

### 3. Nghiệp vụ Ban tổ chức (Organizer)
- Lấy danh mục sự kiện, thông tin ban tổ chức (`/organizer/getInfo`).
- Tạo sự kiện mới (`/create-event`) và xem danh sách sự kiện đã tạo (`/organizer/my-events`).

### 4. Nghiệp vụ Quản trị viên (Admin — middleware `auth:api`, `role:3`)
- **Thống kê Dashboard:** Số liệu tổng quan (`/admin/dashboard-stats`) và biểu đồ doanh thu (`/admin/dashboard-revenue`).
- **Kiểm duyệt sự kiện:** Xem danh sách sự kiện chờ duyệt, xem chi tiết, phê duyệt hoặc từ chối sự kiện.
- **Quản lý hệ thống:** Theo dõi đơn hàng đã thanh toán, danh sách ban tổ chức, kiểm duyệt trạng thái đánh giá và xử lý báo cáo từ người dùng.

## Công nghệ sử dụng

- **Framework:** Laravel 10 (PHP `^8.1`)
- **Database:** MySQL
- **Xác thực:** Laravel Passport (`^12.4`)
- **Thanh toán & Tiện ích:** PayOS SDK (`payos/payos ^2.0`), Simple QRCode (`^4.2`), Firebase Admin SDK (`kreait/laravel-firebase ^5.10`)

## Cấu trúc thư mục chính

```text
ticket_backend/
├── app/Http/Controllers/
│   ├── Auth/            # Đăng nhập, đăng ký, Google login, quên mật khẩu
│   ├── Customers/       # Trang chủ, tìm kiếm, chi tiết, giỏ hàng, thanh toán, đơn hàng, review, report
│   ├── Organizers/      # Tạo & quản lý sự kiện của ban tổ chức
│   └── Admin/           # Dashboard, duyệt sự kiện, quản lý đơn hàng/đánh giá/báo cáo
├── config/services.php  # Cấu hình PayOS & Firebase credentials
├── database/
│   ├── migrations/      # Cấu trúc bảng cơ sở dữ liệu
│   └── seeders/         # Dữ liệu mẫu (SampleEventsSeeder, UpcomingEventsSeeder, WeeklyEventsSeeder, ...)
├── routes/api.php       # Toàn bộ định tuyến API
└── .env.example         # Cấu hình biến môi trường mẫu
```

---

## Hướng dẫn cài đặt và chạy dự án

### Yêu cầu môi trường
- **PHP:** `>= 8.1` (đầy đủ các extension chuẩn của Laravel)
- **Composer**
- **MySQL** (hoặc MariaDB tương thích)

### Các bước thực hiện

1. **Cài đặt dependencies:**
   ```sh
   cd ticket_backend
   composer install
   ```

2. **Tạo và cấu hình file `.env`:**
   ```sh
   cp .env.example .env
   php artisan key:generate
   ```
   Cập nhật các thông số trong `.env`:
   - **Database:** `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
   - **Mail (gửi mã xác thực/quên mật khẩu):** `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`
   - **PayOS (nếu test thanh toán online):**
     ```env
     PAYOS_CLIENT_ID=
     PAYOS_API_KEY=
     PAYOS_CHECKSUM_KEY=
     ```
   - *(Nếu sử dụng Firebase Admin SDK)* Đặt file credentials tại `storage/firebase_credentials.json` theo cấu hình trong `config/services.php`.

3. **Khởi tạo cơ sở dữ liệu & Passport:**
   ```sh
   php artisan migrate
   php artisan passport:install
   ```
   *(Tùy chọn)* Nạp dữ liệu mẫu nếu cần:
   ```sh
   php artisan db:seed
   ```

4. **Khởi chạy server API:**
   ```sh
   php artisan serve
   ```
   Mặc định backend sẽ chạy tại `http://127.0.0.1:8000` (API prefix: `/api`).
