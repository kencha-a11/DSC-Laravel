# ============================================
# Docker Build & Test Script for PowerShell
# ============================================

Write-Host "🔍 Checking Docker..." -ForegroundColor Cyan
docker version
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Docker is not running. Please start Docker Desktop!" -ForegroundColor Red
    exit 1
}

Write-Host "`n🏗️ Building Docker image..." -ForegroundColor Cyan
docker build -t dsc-laravel . | ForEach-Object { Write-Host $_ }

if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Docker build failed!" -ForegroundColor Red
    exit 1
}

Write-Host "`n✅ Docker build successful!" -ForegroundColor Green

# ============================================
# Set production environment variables securely
# ============================================
$envVars = @(
    "-e APP_ENV=production",
    "-e APP_KEY=base64:cBzuiJ8cHn5Oc5TC4ZHuyejjo6R1FN97MNiX/mGtVSI=",
    "-e APP_URL=https://dsc-laravel.onrender.com",
    "-e DB_CONNECTION=pgsql",
    "-e DB_HOST=dpg-d4g1f463jp1c73dbip10-a",
    "-e DB_PORT=5432",
    "-e DB_DATABASE=dsc_app_postgresql",
    "-e DB_USERNAME=dsc_app_postgresql_user",
    "-e DB_PASSWORD=$env:DB_PASSWORD",  # Securely pass password from host environment
    "-e DB_SSLMODE=require"
)

Write-Host "`n🚀 Starting container..." -ForegroundColor Cyan
$containerId = docker run -d -p 8000:80 $envVars dsc-laravel

if (-not $containerId) {
    Write-Host "❌ Failed to start container!" -ForegroundColor Red
    exit 1
}

Write-Host "Container ID: $containerId" -ForegroundColor Yellow

Write-Host "`n⏳ Waiting for container to initialize..." -ForegroundColor Cyan

# Wait for container to be healthy
$healthPassed = $false
for ($i=0; $i -lt 30; $i++) {
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:8000/health" -UseBasicParsing -ErrorAction Stop
        if ($response.StatusCode -eq 200) {
            Write-Host "✅ Health check passed: $($response.StatusCode) - $($response.Content)" -ForegroundColor Green
            $healthPassed = $true
            break
        }
    } catch {
        Write-Host "⏳ Health check not ready, retrying..." -ForegroundColor Yellow
    }
    Start-Sleep -Seconds 2
}

if (-not $healthPassed) {
    Write-Host "❌ Health check failed after multiple attempts!" -ForegroundColor Red
    docker logs $containerId
    exit 1
}

# ============================================
# Optional: API test endpoint
# ============================================
Write-Host "`n🔍 Testing API endpoint..." -ForegroundColor Cyan
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8000/api/user" -UseBasicParsing -ErrorAction Stop
    Write-Host "Response: $($response.StatusCode)" -ForegroundColor Yellow
} catch {
    if ($_.Exception.Response -ne $null) {
        Write-Host "Expected 401 (unauthorized): $($_.Exception.Response.StatusCode)" -ForegroundColor Yellow
    } else {
        Write-Host "❌ API request failed: $_" -ForegroundColor Red
    }
}

# ============================================
# Show container logs
# ============================================
Write-Host "`n📋 Container logs:" -ForegroundColor Cyan
docker logs -f $containerId

# ============================================
# Stop instructions
# ============================================
Write-Host "`n🛑 To stop container, run:" -ForegroundColor Magenta
Write-Host "docker stop $containerId" -ForegroundColor White
Write-Host "docker rm $containerId" -ForegroundColor White
