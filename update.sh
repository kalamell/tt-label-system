#!/bin/bash
# =============================================================
# update.sh — ดึงโค้ดใหม่จาก GitHub (Linux / macOS / Shared Hosting)
# วิธีใช้: bash update.sh
# =============================================================

TOKEN="ghp_xxxxxxxxxxxxxxxxxxx"
REPO="https://${TOKEN}@github.com/kalamell/tt-label-system.git"

echo "=============================="
echo " TikTok Label — Update System"
echo "=============================="

# ตรวจสอบ git
if ! command -v git &>/dev/null; then
    echo ""
    echo "❌ ไม่พบ Git — กรุณาติดตั้งก่อน"
    echo ""
    echo "  macOS  : brew install git"
    echo "         หรือ https://git-scm.com/download/mac"
    echo "  Linux  : sudo apt install git   (Ubuntu/Debian)"
    echo "         : sudo yum install git   (CentOS/RHEL)"
    echo ""
    exit 1
fi

echo "✓ Git $(git --version | awk '{print $3}')"

# ตั้ง remote พร้อม token
git remote set-url origin "$REPO"

# ดึงโค้ดใหม่
echo ""
echo "[1/2] Pulling latest code..."
git pull origin main
echo "✓ Done"

# ลบ token ออกจาก remote (ความปลอดภัย)
git remote set-url origin "https://github.com/kalamell/tt-label-system.git"

echo ""
echo "[2/2] Clearing Laravel cache..."
php artisan config:clear 2>/dev/null
php artisan view:clear  2>/dev/null
php artisan cache:clear 2>/dev/null
echo "✓ Done"

echo ""
echo "=============================="
echo " Update complete!"
echo "=============================="
