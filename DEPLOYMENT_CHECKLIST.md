# 🚀 RAILWAY + SUPABASE DEPLOYMENT CHECKLIST

## ✅ Phase 1: Konfigurasi Lokal (DONE)
- [x] Update `.env.production` dengan template PostgreSQL
- [x] Create `Procfile` untuk Railway deployment
- [x] Create `apache_app.conf` untuk URL rewriting
- [x] Create `railway.json` untuk Railway configuration
- [x] Update `AppServiceProvider` untuk force HTTPS
- [x] Create `DEPLOYMENT.md` documentation

## 🔧 Phase 2: Setup Supabase (TODO - Lakukan di Browser)
- [ ] 1. Buat account di https://supabase.com
- [ ] 2. Create project baru (nama: pinebalance)
- [ ] 3. Catat credentials (Host, Port, Database, Username, Password)
- [ ] 4. Go to Settings → Network → Allow all IPs (untuk akses lokal)

## 🚂 Phase 3: Setup Railway (TODO - Lakukan di Browser)
- [ ] 1. Buat account di https://railway.app
- [ ] 2. Connect GitHub repository
- [ ] 3. Deploy project
- [ ] 4. Add Environment Variables (dari Supabase)
- [ ] 5. Set APP_KEY (generate via: php artisan key:generate --show)
- [ ] 6. Verify domain

## 📊 Phase 4: Database & Testing (TODO - Lakukan Lokal/Terminal)
- [ ] 1. Update `.env` lokal dengan Supabase credentials
- [ ] 2. Test connection: `php artisan tinker` → `DB::connection()->getPdo();`
- [ ] 3. Run migrations lokal: `php artisan migrate`
- [ ] 4. Push ke GitHub
- [ ] 5. Railway auto-deploys & runs migrations

## 🎯 Next Actions (URGENT)

### Step 1: Create Supabase Project
```bash
# Open browser:
https://supabase.com → Sign Up → New Project
```

**Save these credentials:**
```
Host: db.xxxxx.supabase.co
Port: 5432
Database: postgres
Username: postgres
Password: _________ (your password)
```

### Step 2: Connect Railway
```bash
# 1. Push current code to GitHub:
git add .
git commit -m "Add Railway + Supabase deployment config"
git push origin main

# 2. Go to https://railway.app
# 3. Connect GitHub and select this repo
# 4. Railway auto-detects Procfile and deploys
```

### Step 3: Add Supabase Credentials to Railway
```bash
# In Railway Dashboard → Your App → Variables
# Add these environment variables:
DB_HOST=db.xxxxx.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your_password
DB_CONNECTION=pgsql
DB_SSLMODE=require
APP_KEY=base64:your_key_from_artisan
APP_ENV=production
```

### Step 4: Test Locally First
```bash
# Update .env locally:
DB_CONNECTION=pgsql
DB_HOST=db.xxxxx.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your_password
DB_SSLMODE=require

# Test connection:
php artisan migrate
php artisan tinker
```

---

## 📝 Files Created/Modified

| File | Purpose |
|------|---------|
| `.env.production` | Production environment config |
| `Procfile` | Railway deployment commands |
| `apache_app.conf` | Apache rewrite rules |
| `railway.json` | Railway service config |
| `AppServiceProvider.php` | Force HTTPS in production |
| `DEPLOYMENT.md` | Full deployment guide |
| `DEPLOYMENT_CHECKLIST.md` | This file |

---

## 🔗 Important Links

- [Supabase](https://supabase.com)
- [Railway](https://railway.app)
- [DEPLOYMENT.md Guide](./DEPLOYMENT.md)

---

## 💬 Common Issues & Solutions

### "Connection refused to db.xxxxx.supabase.co"
→ Go to Supabase Settings → Network → Allow all IPs

### "SQLSTATE[08006]"
→ Add `DB_SSLMODE=require` to variables

### "Class DailyWaterBalance not found"
→ Autoloader issue - run: `composer dump-autoload`

### "500 Error on Production"
→ Check Railway Logs for actual error message

---

**Status**: Ready for deployment! ✅
