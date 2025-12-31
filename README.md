# BookStore_IS207

Chạy project gồm:
- **Frontend**: Next.js 15 (port **3001**)
- **Backend**: Laravel (PHP 8.3) (port **8080**)
- **Database**: PostgreSQL (port **5432**)

> Cấu trúc repo:
> - `backend/` : Laravel API
> - `web-app/` : Next.js frontend

---

## 1) Yêu cầu môi trường

### Bắt buộc
- Node.js **18+** (khuyến nghị 20 LTS)
- PHP **8.3**
- Composer **2.x**
- PostgreSQL **13+**

### Khuyến nghị (tuỳ chọn)
- Git

---

## 2) Clone project

```bash
git clone https://github.com/AkaDNT/BookStore_IS207.git
cd BookStore_IS207
```

---

## 3) Setup Database (PostgreSQL)

### Tạo database
Ví dụ tạo DB tên `Test`:

```bash
psql -U postgres
```

Trong psql:

```sql
CREATE DATABASE "Test";
```

---

## 4) Setup Backend (Laravel / PHP 8.3)

### 4.1 Cài dependency
```bash
cd backend
composer install
```

### 4.2 Tạo file `.env`
Nếu có sẵn `.env.example`:

```bash
cp .env.example .env
```

> **Lưu ý:** Các giá trị “secret cá nhân” đã được thay bằng **tên viết tắt IN HOA dính liền** (ví dụ: `DBPASSWORD`, `JWTSECRET`, ...)

#### `.env` (Backend)
```env
APP_NAME=Laravel
APP_ENV=local
APP_KEY=APPKEY
APP_DEBUG=true
APP_URL=http://localhost:8080

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
# APP_MAINTENANCE_STORE=database

PHP_CLI_SERVER_WORKERS=4

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=Test
DB_USERNAME=postgres
DB_PASSWORD=DBPASSWORD

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database
# CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"

JWT_SECRET=JWTSECRET

VNP_TMN_CODE=VNPTMNCODE
VNP_HASH_SECRET=VNPHASHSECRET
VNP_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNP_RETURN_URL=http://localhost:8080/api/payments/vnpay/return
VNP_IPN_URL=https://api.akadnt.id.vn/api/payments/vnpay/ipn
APP_FRONTEND=http://localhost:3001

FX_API_URL=https://api.exchangerate.host/convert
FX_API_FROM=USD
FX_API_TO=VND
FX_API_KEY=FXAPIKEY
```

### 4.3 Generate APP_KEY (nếu cần)
Nếu bạn muốn tạo key thật (khuyến nghị):

```bash
php artisan key:generate
```

### 4.4 Migrate database
Chạy migrate:

```bash
php artisan migrate
```

### 4.5 (Tuỳ chọn) Seed dữ liệu mẫu
```bash
php artisan db:seed
```

### 4.6 Chạy backend (port 8080)
```bash
php artisan serve --host=0.0.0.0 --port=8080
```

Backend chạy tại:
- `http://localhost:8080`
- API base: `http://localhost:8080/api`

---

## 5) Setup Frontend (Next.js 15)

### 5.1 Cài dependency
Mở terminal mới, tại root repo:

```bash
cd web-app
npm install
```

### 5.2 Tạo file `.env` (Frontend)
Tạo `.env` (hoặc `.env.local`) trong `web-app/`:

```env
NEXT_PUBLIC_BASE_URL="http://localhost:3001"
NEXT_PUBLIC_API_URL="http://localhost:8080/api"
API_URL="http://localhost:8080/api"
```

### 5.3 Chạy frontend (port 3001)
```bash
npm run dev -- -p 3001
```

Frontend chạy tại:
- `http://localhost:3001`

---

## 6) Quick checklist (chạy đủ hệ thống)

- PostgreSQL có DB `Test`
- Backend chạy: `http://localhost:8080`
- Frontend chạy: `http://localhost:3001`
- Frontend gọi API qua: `NEXT_PUBLIC_API_URL=http://localhost:8080/api`

---

## 7) Troubleshooting

### Lỗi connect DB (Laravel)
- Kiểm tra Postgres đang chạy
- Kiểm tra đúng `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

### VNPay/IPN (khi test local)
- `VNP_RETURN_URL` dùng được vì redirect về localhost
- `VNP_IPN_URL` thường cần domain public (dùng Cloudflare tunnel để trỏ đến local)

---

## 8) Ports

- Frontend (Next.js): **3001**
- Backend (Laravel): **8080**
- PostgreSQL: **5432**
