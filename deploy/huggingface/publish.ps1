# Publish the committed code (HEAD) to the Hugging Face Space. Run from anywhere:
#   powershell -File deploy\huggingface\publish.ps1
# Needs Docker and a Hugging Face *write* token in HF_TOKEN (e.g. setx HF_TOKEN "hf_...").
# The site's admin passwords are generated once and kept in .env.huggingface (git-ignored).
param([string]$SpaceName = "synergy-dental")
$ErrorActionPreference = "Stop"

$repo = (Resolve-Path "$PSScriptRoot\..\..").Path
$token = $env:HF_TOKEN
if (-not $token) { $token = [Environment]::GetEnvironmentVariable("HF_TOKEN", "User") }
if (-not $token) { throw "Set HF_TOKEN first:  setx HF_TOKEN `"hf_...`"  (then open a new terminal)" }

# Admin passwords for the deployed site (created on first publish, reused afterwards).
$secretsFile = Join-Path $repo ".env.huggingface"
if (-not (Test-Path $secretsFile)) {
    function New-Password { -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 24 | ForEach-Object { [char]$_ }) }
    @(
        "# Hugging Face Space logins (git-ignored). Change them here and run publish.ps1 again."
        "WP_ADMIN_PASSWORD=$(New-Password)"
        "SEED_ADMIN_PASSWORD=$(New-Password)"
    ) | Set-Content -Encoding ascii $secretsFile
}
$secrets = @{}
Get-Content $secretsFile | Where-Object { $_ -match '^\s*([A-Z_]+)=(.*)$' } | ForEach-Object { $secrets[$Matches[1]] = $Matches[2] }

# Stage the committed tree: only what the image needs, with the Space's Dockerfile and README at the root.
$stage = Join-Path ([IO.Path]::GetTempPath()) "synergy-hf-space"
if (Test-Path $stage) { Remove-Item -Recurse -Force $stage }
New-Item -ItemType Directory $stage | Out-Null
git -C $repo archive --format=tar -o "$stage\src.tar" HEAD backend dashboard wordpress deploy/huggingface
tar -xf "$stage\src.tar" -C $stage
Remove-Item "$stage\src.tar"
Copy-Item "$stage\deploy\huggingface\Dockerfile" "$stage\Dockerfile"
Copy-Item "$stage\deploy\huggingface\space-README.md" "$stage\README.md"
$commit = git -C $repo log -1 --format="%h %s"

docker run --rm `
    -e HF_TOKEN=$token -e SPACE_NAME=$SpaceName -e COMMIT_MESSAGE="Deploy $commit" `
    -e WP_ADMIN_PASSWORD=$($secrets.WP_ADMIN_PASSWORD) -e SEED_ADMIN_PASSWORD=$($secrets.SEED_ADMIN_PASSWORD) `
    -v "${stage}:/space" python:3.13-slim `
    sh -c "pip install -q --root-user-action=ignore huggingface_hub && python /space/deploy/huggingface/publish.py"
if ($LASTEXITCODE -ne 0) { throw "Upload failed" }
Remove-Item -Recurse -Force $stage
Write-Host "Published. The Space builds for a few minutes, then the site is live."
