# Docker Deployment Guide

## 🚀 Quick Start

### 1. Build Docker Image

```bash
docker build -t simlpu-backend .
```

### 2. Run Container

```bash
docker run -d \
  --name simlpu_backend \
  -p 8000:8000 \
  -v $(pwd)/storage:/app/storage \
  -v $(pwd)/.env:/app/.env \
  --restart unless-stopped \
  simlpu-backend
```

### 3. Atau Pakai Docker Compose (Recommended)

```bash
# Build dan jalankan
docker-compose up -d --build

# Lihat logs
docker-compose logs -f

# Stop
docker-compose down
```

## 📋 Di Server Production

### 1. Clone/Pull Latest Code

```bash
cd /var/www/backend
git pull origin main
```

### 2. Copy .env Production

```bash
cp .env.example .env
nano .env  # Edit sesuai production settings
```

### 3. Build dan Run

```bash
docker-compose up -d --build
```

### 4. Setup Nginx Proxy Pass

Edit `/etc/nginx/sites-available/default` atau buat config baru:

```nginx
# Laravel Backend
location /backend/ {
    proxy_pass http://localhost:8000/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection 'upgrade';
    proxy_set_header Host $host;
    proxy_cache_bypass $http_upgrade;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    
    # Upload settings
    client_max_body_size 500M;
    proxy_connect_timeout 300s;
    proxy_send_timeout 300s;
    proxy_read_timeout 300s;
}

# Next.js Frontend
location / {
    proxy_pass http://localhost:3000;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection 'upgrade';
    proxy_set_header Host $host;
    proxy_cache_bypass $http_upgrade;
}
```

### 5. Reload Nginx

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 🔧 Useful Commands

### Check Container Status

```bash
docker ps
docker logs simlpu_backend
docker logs -f simlpu_backend  # follow logs
```

### Execute Commands in Container

```bash
# Artisan commands
docker exec simlpu_backend php artisan migrate
docker exec simlpu_backend php artisan cache:clear
docker exec simlpu_backend php artisan config:cache

# Composer
docker exec simlpu_backend composer install
```

### Restart Container

```bash
docker restart simlpu_backend

# atau via compose
docker-compose restart
```

### Stop and Remove

```bash
docker stop simlpu_backend
docker rm simlpu_backend

# atau via compose
docker-compose down
```

### Rebuild Image

```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

## 📊 Monitoring

### Check PHP Settings

```bash
docker exec simlpu_backend php -i | grep -E "max_file_uploads|upload_max_filesize|post_max_size"
```

Output:
```
max_file_uploads => 100 => 100
upload_max_filesize => 200M => 200M
post_max_size => 500M => 500M
```

### Check Logs

```bash
# Container logs
docker logs simlpu_backend --tail 100

# Laravel logs
docker exec simlpu_backend tail -f storage/logs/laravel.log

# Nginx logs
docker exec simlpu_backend tail -f /var/log/nginx/access.log
docker exec simlpu_backend tail -f /var/log/nginx/error.log
```

## 🔄 Update Deployment

```bash
# Pull latest code
git pull origin main

# Rebuild and restart
docker-compose up -d --build

# Check if running
docker ps
curl http://localhost:8000/api
```

## 🛑 Troubleshooting

### Port 8000 Already in Use

```bash
# Check what's using port 8000
sudo lsof -i :8000
sudo netstat -tulpn | grep :8000

# Stop the process
sudo kill -9 <PID>

# Or change port in docker-compose.yml
ports:
  - "8001:8000"  # host:container
```

### Permission Issues

```bash
docker exec simlpu_backend chown -R www-data:www-data /app/storage
docker exec simlpu_backend chmod -R 775 /app/storage
```

### Clear Cache

```bash
docker exec simlpu_backend php artisan cache:clear
docker exec simlpu_backend php artisan config:clear
docker exec simlpu_backend php artisan route:clear
docker exec simlpu_backend php artisan view:clear
```

## ✅ Verify Upload Limits

Test endpoint:
```bash
curl http://localhost:8000/api/phpinfo
```

Atau buat route temporary di `routes/api.php`:
```php
Route::get('/phpinfo', function() {
    return response()->json([
        'max_file_uploads' => ini_get('max_file_uploads'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'memory_limit' => ini_get('memory_limit'),
    ]);
});
```

## 📝 Notes

- Container akan auto-restart jika crash
- Storage folder di-mount sebagai volume untuk persist uploaded files
- .env file di-mount untuk easy configuration
- PHP settings sudah dikonfigurasi untuk handle 100 file uploads dengan size 200M per file
- Total POST size maksimal 500M
