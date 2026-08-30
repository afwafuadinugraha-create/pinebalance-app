# Deployment Guide: Laravel + Supabase + Railway

## 📋 Prerequisites

- Railway Account (railway.app)
- Supabase Account (supabase.com)
- Git installed locally
- GitHub repository

## 🚀 Step 1: Setup Supabase PostgreSQL

### 1.1 Create Supabase Project
1. Go to https://supabase.com
2. Sign up / Login
3. Click "New Project"
4. Fill in:
   - Project name: `pinebalance`
   - Database password: (save securely!)
   - Region: Choose closest to you
5. Wait for database to initialize (~2 min)

### 1.2 Get Supabase Credentials
1. Go to Settings → Database
2. Note down:
   - **Host**: `db.xxxxx.supabase.co`
   - **Port**: `5432`
   - **Database**: `postgres`
   - **Username**: `postgres`
   - **Password**: (your DB password)
3. Also get from Settings → API:
   - **Project URL**: (for later)
   - **API Key**: (for later)

---

## 🚀 Step 2: Setup Railway Project

### 2.1 Connect Repository
1. Go to https://railway.app
2. Sign up with GitHub
3. Click "New Project" → "Deploy from GitHub repo"
4. Authorize GitHub & select your `pinebalance-app` repository
5. Click "Deploy"

### 2.2 Add PostgreSQL Database (Optional - if not using Supabase)
If using Supabase, skip this. Otherwise:
1. In Railway dashboard → Add service
2. Choose "PostgreSQL"
3. Connect to your app

### 2.3 Configure Environment Variables
In Railway Dashboard:
1. Go to your app → Variables
2. Add these environment variables:

```
APP_NAME=PineBalance
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-railway-domain.up.railway.app

DB_CONNECTION=pgsql
DB_HOST=db.xxxxx.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-supabase-password
DB_SSLMODE=require

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
LOG_CHANNEL=stack
LOG_LEVEL=info
```

### 2.4 Generate APP_KEY
1. In Railway → Variables
2. Temporarily add: `APP_KEY=` (empty)
3. Wait for first deploy to fail
4. Run locally:
   ```bash
   php artisan key:generate --show
   ```
5. Copy the key (base64:xxxx...)
6. Update `APP_KEY` in Railway variables

### 2.5 Configure Domain
1. Railway Dashboard → Settings
2. Add custom domain or use Railway's default domain
3. Update `APP_URL` in Variables

---

## 🗄️ Step 3: Database Setup

### 3.1 Run Migrations on Supabase
Railway automatically runs migrations on deploy (see `release:` in Procfile).

To manually run:
```bash
php artisan migrate --force
```

### 3.2 Import Data (Optional)
If you have existing Excel files:
```bash
php artisan tinker
# Inside tinker:
$import = new \App\Imports\WaterBalanceImport();
\Maatwebsite\Excel\Facades\Excel::import($import, 'path/to/file.xlsx');
```

Or via web interface if you add an upload feature.

---

## 🔐 Step 4: SSL & Security

### 4.1 SSL Certificate
- Railway provides free SSL automatically
- Verified once domain is added

### 4.2 Update APP_URL
Make sure `APP_URL` uses `https://`:
```
APP_URL=https://your-domain.up.railway.app
```

### 4.3 Force HTTPS in Laravel
In `app/Providers/AppServiceProvider.php`:
```php
public function boot(): void
{
    if ($this->app->environment('production')) {
        \Illuminate\Support\Facades\URL::forceScheme('https');
    }
}
```

---

## 📊 Step 5: Verify Deployment

1. Go to Railway app → Logs
2. Check for deployment errors
3. Visit your app URL in browser
4. Test the dashboard loads

### Expected Output in Logs:
```
Compressing...
Uploading...
Launching app...
Running release tasks...
Migrating database...
[OK] Migration completed
Server running on port 8080
```

---

## 🆘 Troubleshooting

### Issue: "SQLSTATE[08006]"
**Solution**: Check `DB_SSLMODE=require` in variables

### Issue: "Port 5432: Connection refused"
**Solution**: 
- Verify Supabase host is correct
- Check firewall in Supabase: Settings → Network → Allow all IPs

### Issue: "Migrations table not found"
**Solution**: 
- SSH into Railway container
- Run: `php artisan migrate --force`

### Issue: "500 error on deployment"
**Solution**:
- Check Rails logs: Railway Dashboard → Logs
- Run: `php artisan config:clear`
- Check APP_KEY is valid

---

## 📱 Local Development with Supabase

### .env (Local)
```
DB_CONNECTION=pgsql
DB_HOST=db.xxxxx.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-password
DB_SSLMODE=require
APP_ENV=local
APP_DEBUG=true
```

### Test Connection
```bash
php artisan tinker
>>> DB::connection()->getPdo();
# Should return PDO object without error
```

---

## 🔄 Deployment Workflow

### Every time you push to main:
1. Git push to GitHub
2. Railway auto-detects changes
3. Redeploys automatically
4. Migrations run automatically

### Manual deploy:
```bash
git push origin main
```

Railway will trigger deployment automatically.

---

## 📈 Next Steps

1. ✅ Setup Supabase project
2. ✅ Add Railway environment variables
3. ✅ Deploy to Railway
4. ✅ Run migrations
5. 📊 Monitor logs & performance
6. 🔐 Setup monitoring/alerts
7. 💾 Setup backups in Supabase

---

## 📚 Useful Links

- Railway Docs: https://docs.railway.app
- Supabase Docs: https://supabase.com/docs
- Laravel Deployment: https://laravel.com/docs/deployment
- Supabase PostgreSQL: https://supabase.com/docs/guides/database

---

**Questions?** Check Railway/Supabase docs or run:
```bash
php artisan about
```
