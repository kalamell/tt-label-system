#!/bin/bash
# =============================================================
# update.sh — ดึงโค้ดใหม่จาก GitHub (Linux / macOS / Shared Hosting)
# วิธีใช้: bash update.sh
# =============================================================

TOKEN="ใส่_TOKEN_ตรงนี้"

# ไปที่โฟลเดอร์ของไฟล์นี้เสมอ (สำคัญมาก)
cd "$(dirname "$0")"

REPO="https://${TOKEN}@github.com/kalamell/tt-label-system.git"

echo "=============================="
echo " TikTok Label — Update System"
echo "=============================="
echo " Folder: $(pwd)"
echo "=============================="

# ตรวจสอบ token
if [ "$TOKEN" = "ใส่_TOKEN_ตรงนี้" ]; then
    echo ""
    echo "❌ ยังไม่ได้ใส่ TOKEN"
    echo "   เปิดไฟล์นี้ด้วย text editor แล้วแก้บรรทัด TOKEN="
    echo ""
    exit 1
fi

# ตรวจสอบ git
if ! command -v git &>/dev/null; then
    echo ""
    echo "❌ ไม่พบ Git — กรุณาติดตั้งก่อน"
    echo ""
    echo "  macOS  : brew install git"
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
if ! git pull origin main; then
    echo ""
    echo "❌ Pull ไม่สำเร็จ — ตรวจสอบ TOKEN หรือการเชื่อมต่อ"
    git remote set-url origin "https://github.com/kalamell/tt-label-system.git"
    exit 1
fi
echo "✓ Done"

# ลบ token ออกจาก remote (ความปลอดภัย)
git remote set-url origin "https://github.com/kalamell/tt-label-system.git"

# Clear Laravel cache
echo ""
echo "[2/2] Clearing Laravel cache..."
php artisan config:clear 2>/dev/null
php artisan view:clear   2>/dev/null
php artisan cache:clear  2>/dev/null
echo "✓ Done"

echo ""
echo "=============================="
echo " Update complete!"
echo "=============================="
