# Bookstore (Laravel API + Next.js) + Local Chatbot (LLM)

## 1) Tổng quan
- `backend/`: Laravel REST API
- `web-app/`: Next.js (frontend)
- `models/`: model GGUF để chạy chatbot local  `Llama-3.2-1B-Instruct-Q4_K_M.gguf`)


## 1) Setup & Run (Docker Compose)
## Yêu cầu
- Docker + Docker Compose
- (Khuyến nghị) Linux/WSL2 nếu chạy Windows



### Bước 1 — Clone repo
```bash
git clone <https://github.com/Gay22222/BookStore_IS207.git>
cd <REPO_FOLDER>
```


### Bước 2 — Tải model (LLM)
```bash
mkdir -p models
cd models
wget https://huggingface.co/bartowski/Llama-3.2-1B-Instruct-GGUF/resolve/main/Llama-3.2-1B-Instruct-Q4_K_M.gguf
ls -lah

```
Kết quả mong muốn: thấy file Llama-3.2-1B-Instruct-Q4_K_M.gguf (khoảng ~800MB).


### Bước 3 — Tạo file ENV cho backend
Trong thư mục backend
```bash
cp .env.example .env
```
Trong thư mục web-app
tạo .env với nội dung
```bash
NEXT_PUBLIC_BASE_URL="http://localhost:3001"
NEXT_PUBLIC_API_URL ="http://localhost:8080/api"
API_URL="http://backend:8080/api"
```


### Bước 4 — Build & chạy docker
```bash
docker compose up -d --build
```

Kiểm tra container đang chạy:
```bash
docker compose ps
```

Xem log
```bash
docker compose logs -f backend
docker compose logs -f frontend
docker compose logs -f llama

```

### Bước 5 — Cài dependencies (vendor + node_modules)
Cài vendor cho Laravel (backend)
```bash
docker compose exec backend composer install
```
Tạo APP_KEY:

```bash
docker compose exec backend php artisan key:generate
```

Cài node_modules cho Next.js (frontend)
```bash
docker compose exec frontend npm install
# hoặc nếu repo có package-lock.json thì khuyến nghị:
# docker compose exec frontend npm ci
```

### Bước 6 — Migrate & Seed database

```bash
docker compose exec backend php artisan migrate --seed
```

### Bước 7 — Truy cập
Frontend: http://localhost:3001
Backend: http://localhost:8080


### Một số lệnh hữu ích khi dev
Dừng container
```bash
docker compose down
```

Dừng và xoá luôn dữ liệu DB (reset sạch):

```bash
docker compose down -v
```

Rebuild lại

```bash
docker compose up -d --build
```