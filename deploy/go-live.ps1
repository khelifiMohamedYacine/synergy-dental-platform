# Put the full platform online from this PC through a free Cloudflare quick tunnel
# (no account, no card). The site stays online while this PC is on and Docker is running.
#   powershell -ExecutionPolicy Bypass -File C:\dev\synergy-dental-platform\deploy\go-live.ps1
# Each run gives a NEW public address. Stop everything:  docker rm -f synergy-live synergy-tunnel
$ErrorActionPreference = "Stop"
$repo = (Resolve-Path "$PSScriptRoot\..").Path

# Logins for the live site (created once, git-ignored).
$secretsFile = Join-Path $repo ".env.huggingface"
if (-not (Test-Path $secretsFile)) {
    function New-Password { -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 24 | ForEach-Object { [char]$_ }) }
    @("# Live site logins (git-ignored)", "WP_ADMIN_PASSWORD=$(New-Password)", "SEED_ADMIN_PASSWORD=$(New-Password)") |
        Set-Content -Encoding ascii $secretsFile
}
$s = @{}; Get-Content $secretsFile | Where-Object { $_ -match '^([A-Z_]+)=(.*)$' } | ForEach-Object { $s[$Matches[1]] = $Matches[2] }

# 1. Build the all-in-one image (fast when nothing changed).
docker build -q -f "$repo\deploy\huggingface\Dockerfile" -t synergy-hf $repo | Out-Null

# 2. Public HTTPS tunnel to port 7860.
docker rm -f synergy-tunnel synergy-live 2>$null | Out-Null
docker run -d --name synergy-tunnel cloudflare/cloudflared:latest tunnel --no-autoupdate --url http://host.docker.internal:7860 | Out-Null
$url = ""; $t = 0
while (-not $url -and $t -lt 90) { Start-Sleep 3; $t += 3; $url = [regex]::Match((docker logs synergy-tunnel 2>&1 | Out-String), 'https://[a-z0-9-]+\.trycloudflare\.com').Value }
if (-not $url) { throw "The tunnel did not start. See: docker logs synergy-tunnel" }
$hostName = $url -replace '^https://', ''

# 3. Start the site, telling it its public address.
docker run -d --name synergy-live -p 7860:7860 -e SPACE_HOST=$hostName `
    -e WP_ADMIN_PASSWORD=$($s.WP_ADMIN_PASSWORD) -e SEED_ADMIN_PASSWORD=$($s.SEED_ADMIN_PASSWORD) synergy-hf | Out-Null
Write-Host "Starting the site (about 1 minute)..."
$t = 0
while ($t -lt 300) {
    Start-Sleep 5; $t += 5
    $log = docker logs synergy-live 2>&1 | Out-String
    if ($log -match '\[start\] Ready:') { break }
    if ($log -match 'A service stopped' -or -not (docker ps -q -f name=synergy-live)) { throw "The site failed to start. See: docker logs synergy-live" }
}

Write-Host ""
Write-Host "LIVE LINKS"
Write-Host "  Website:         $url/"
Write-Host "  Shop:            $url/shop/"
Write-Host "  Staff dashboard: $url/dashboard/   (admin@example.test / SEED_ADMIN_PASSWORD)"
Write-Host "  API docs:        $url/api/v1/docs"
Write-Host "  WordPress admin: $url/wp-admin/    (sd_site_owner / WP_ADMIN_PASSWORD)"
Write-Host "  Passwords are in: $secretsFile"
