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
docker build -t dsc-laravel .

if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Docker build failed!" -ForegroundColor Red
    exit 1
}

Write-Host "`n✅ Build successful!" -ForegroundColor Green

Write-Host "`n🚀 Starting container..." -ForegroundColor Cyan
$containerId = docker run -d -p 8000:80 `
    -e APP_ENV=production `
    -e APP_KEY=base64:cBzuiJ8cHn5Oc5TC4ZHuyejjo6R1FN97MNiX/mGtVSI= `
    dsc-laravel

Write-Host "Container ID: $containerId" -ForegroundColor Yellow

Write-Host "`n⏳ Waiting for container to start..." -ForegroundColor Cyan
Start-Sleep -Seconds 5

Write-Host "`n🔍 Testing health endpoint..." -ForegroundColor Cyan
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8000/health" -UseBasicParsing
    Write-Host "✅ Health check: $($response.StatusCode) - $($response.Content)" -ForegroundColor Green
} catch {
    Write-Host "❌ Health check failed: $_" -ForegroundColor Red
}

Write-Host "`n🔍 Testing API endpoint..." -ForegroundColor Cyan
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8000/api/user" -UseBasicParsing
    Write-Host "Response: $($response.StatusCode)" -ForegroundColor Yellow
} catch {
    Write-Host "Expected 401 (unauthorized): $($_.Exception.Response.StatusCode)" -ForegroundColor Yellow
}

Write-Host "`n📋 Container logs:" -ForegroundColor Cyan
docker logs $containerId

Write-Host "`n🛑 To stop container, run:" -ForegroundColor Magenta
Write-Host "docker stop $containerId" -ForegroundColor White
Write-Host "docker rm $containerId" -ForegroundColor White


# 