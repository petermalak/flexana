#!/bin/bash

# Deployment Diagnostic Script for flexanastudios@server35
# Customized for: /home/flexanastudios/domains/admin-panel-flexana-egypt

echo "=========================================="
echo "Flexana Deployment Diagnostic Tool"
echo "Server: flexanastudios@server35"
echo "=========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_check() {
    echo -e "${BLUE}[CHECK]${NC} $1"
}

print_ok() {
    echo -e "${GREEN}[OK]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Your server configuration
APP_DIR="/home/flexanastudios/domains/admin-panel-flexana-egypt/flexana"
PUBLIC_HTML="/home/flexanastudios/domains/admin-panel-flexana-egypt/public_html"
BASE_DIR="/home/flexanastudios/domains/admin-panel-flexana-egypt"

echo "Configuration:"
echo "  Base Directory: $BASE_DIR"
echo "  App Directory: $APP_DIR"
echo "  Web Root: $PUBLIC_HTML"
echo ""

# Check 1: Directory Structure
echo "=========================================="
print_check "1. Checking directory structure..."
echo "=========================================="

if [ -d "$APP_DIR" ]; then
    print_ok "Laravel app directory exists"
else
    print_error "Laravel app directory NOT found: $APP_DIR"
    echo "Solution: Upload your Laravel application to this directory"
    exit 1
fi

if [ -d "$APP_DIR/public" ]; then
    print_ok "Public directory exists"
else
    print_error "Public directory NOT found: $APP_DIR/public"
    exit 1
fi

if [ -f "$APP_DIR/public/index.php" ]; then
    print_ok "index.php exists"
else
    print_error "index.php NOT found"
    exit 1
fi

echo ""

# Check 2: public_html status
echo "=========================================="
print_check "2. Checking public_html status..."
echo "=========================================="

if [ -L "$PUBLIC_HTML" ]; then
    print_ok "public_html is a SYMLINK"

    SYMLINK_TARGET=$(readlink "$PUBLIC_HTML")
    echo "  Points to: $SYMLINK_TARGET"

    EXPECTED_TARGET="$APP_DIR/public"
    if [ "$SYMLINK_TARGET" == "$EXPECTED_TARGET" ]; then
        print_ok "Symlink target is CORRECT"
    else
        print_warning "Symlink target might be incorrect"
        echo "  Expected: $EXPECTED_TARGET"
        echo "  Actual: $SYMLINK_TARGET"
    fi

    if [ -f "$PUBLIC_HTML/index.php" ]; then
        print_ok "index.php accessible through symlink"
        print_ok "Symlinks are WORKING on your server!"
    else
        print_error "index.php NOT accessible through symlink"
        print_error "Your hosting does NOT support symlinks"
        echo ""
        echo "SOLUTION: Use 'Move Public Contents' method"
        echo ""
        echo "Run these commands:"
        echo "  cd $BASE_DIR"
        echo "  rm -rf public_html"
        echo "  cp -r flexana/public public_html"
        echo "  sed -i \"s|__DIR__.'/../storage|__DIR__.'/../flexana/storage|g\" public_html/index.php"
        echo "  sed -i \"s|__DIR__.'/../vendor|__DIR__.'/../flexana/vendor|g\" public_html/index.php"
        echo "  sed -i \"s|__DIR__.'/../bootstrap|__DIR__.'/../flexana/bootstrap|g\" public_html/index.php"
    fi

elif [ -d "$PUBLIC_HTML" ]; then
    print_warning "public_html is a DIRECTORY (not symlink)"
    echo "  Using 'Move Public Contents' method"

    if [ -f "$PUBLIC_HTML/index.php" ]; then
        print_ok "index.php exists"

        if grep -q "flexana/vendor" "$PUBLIC_HTML/index.php"; then
            print_ok "index.php paths are MODIFIED correctly"
        else
            print_error "index.php paths NOT modified"
            echo ""
            echo "SOLUTION: Fix index.php paths"
            echo ""
            echo "Run these commands:"
            echo "  sed -i \"s|__DIR__.'/../storage|__DIR__.'/../flexana/storage|g\" $PUBLIC_HTML/index.php"
            echo "  sed -i \"s|__DIR__.'/../vendor|__DIR__.'/../flexana/vendor|g\" $PUBLIC_HTML/index.php"
            echo "  sed -i \"s|__DIR__.'/../bootstrap|__DIR__.'/../flexana/bootstrap|g\" $PUBLIC_HTML/index.php"
        fi
    else
        print_error "index.php NOT found"
        echo ""
        echo "SOLUTION:"
        echo "  cp -r $APP_DIR/public/* $PUBLIC_HTML/"
    fi
else
    print_error "public_html does NOT exist!"
    echo ""
    echo "SOLUTION: Create it with one of these methods:"
    echo ""
    echo "Method 1 (Symlink - try this first):"
    echo "  cd $BASE_DIR"
    echo "  ln -s $APP_DIR/public public_html"
    echo ""
    echo "Method 2 (Copy - if symlinks don't work):"
    echo "  cd $BASE_DIR"
    echo "  cp -r flexana/public public_html"
    echo "  sed -i \"s|__DIR__.'/../storage|__DIR__.'/../flexana/storage|g\" public_html/index.php"
    echo "  sed -i \"s|__DIR__.'/../vendor|__DIR__.'/../flexana/vendor|g\" public_html/index.php"
    echo "  sed -i \"s|__DIR__.'/../bootstrap|__DIR__.'/../flexana/bootstrap|g\" public_html/index.php"
fi

echo ""

# Check 3: .env Configuration
echo "=========================================="
print_check "3. Checking Laravel configuration..."
echo "=========================================="

if [ -f "$APP_DIR/.env" ]; then
    print_ok ".env file exists"

    if grep -q "APP_KEY=base64:" "$APP_DIR/.env"; then
        print_ok "APP_KEY is set"
    else
        print_error "APP_KEY NOT set"
        echo "  Solution: cd $APP_DIR && php artisan key:generate --force"
    fi

    if grep -q "APP_DEBUG=false" "$APP_DIR/.env"; then
        print_ok "APP_DEBUG=false (production)"
    else
        print_warning "APP_DEBUG is not false"
        echo "  Set: APP_DEBUG=false"
    fi

    if grep -q "APP_ENV=production" "$APP_DIR/.env"; then
        print_ok "APP_ENV=production"
    else
        print_warning "APP_ENV not set to production"
    fi
else
    print_error ".env file NOT found"
    echo ""
    echo "SOLUTION:"
    echo "  cd $APP_DIR"
    echo "  cp .env.example .env"
    echo "  nano .env  # Configure database and other settings"
    echo "  php artisan key:generate --force"
fi

echo ""

# Check 4: Dependencies
echo "=========================================="
print_check "4. Checking dependencies..."
echo "=========================================="

if [ -d "$APP_DIR/vendor" ]; then
    print_ok "vendor/ directory exists"

    if [ -f "$APP_DIR/vendor/autoload.php" ]; then
        print_ok "Composer autoloader exists"
    else
        print_error "Composer autoloader missing"
        echo "  Solution: cd $APP_DIR && composer install --no-dev"
    fi
else
    print_error "vendor/ directory NOT found"
    echo ""
    echo "SOLUTION:"
    echo "  cd $APP_DIR"
    echo "  composer install --optimize-autoloader --no-dev"
fi

echo ""

# Check 5: Permissions
echo "=========================================="
print_check "5. Checking permissions..."
echo "=========================================="

if [ -d "$APP_DIR/storage" ]; then
    STORAGE_PERMS=$(stat -c '%a' "$APP_DIR/storage" 2>/dev/null || stat -f '%Lp' "$APP_DIR/storage" 2>/dev/null)
    if [ "$STORAGE_PERMS" -ge 775 ] 2>/dev/null; then
        print_ok "Storage permissions OK: $STORAGE_PERMS"
    else
        print_error "Storage permissions too restrictive: $STORAGE_PERMS"
        echo "  Solution: chmod -R 775 $APP_DIR/storage"
    fi
else
    print_error "storage/ directory not found"
fi

if [ -d "$APP_DIR/bootstrap/cache" ]; then
    CACHE_PERMS=$(stat -c '%a' "$APP_DIR/bootstrap/cache" 2>/dev/null || stat -f '%Lp' "$APP_DIR/bootstrap/cache" 2>/dev/null)
    if [ "$CACHE_PERMS" -ge 775 ] 2>/dev/null; then
        print_ok "Cache permissions OK: $CACHE_PERMS"
    else
        print_error "Cache permissions too restrictive: $CACHE_PERMS"
        echo "  Solution: chmod -R 775 $APP_DIR/bootstrap/cache"
    fi
fi

echo ""

# Check 6: Built Assets
echo "=========================================="
print_check "6. Checking built assets..."
echo "=========================================="

if [ -d "$APP_DIR/public/css" ]; then
    CSS_COUNT=$(find "$APP_DIR/public/css" -name "*.css" 2>/dev/null | wc -l)
    if [ $CSS_COUNT -gt 0 ]; then
        print_ok "CSS files found: $CSS_COUNT"
    else
        print_warning "No CSS files"
        echo "  Solution: cd $APP_DIR && npm run build"
    fi
else
    print_warning "CSS directory not found"
fi

if [ -d "$APP_DIR/public/js" ]; then
    JS_COUNT=$(find "$APP_DIR/public/js" -name "*.js" 2>/dev/null | wc -l)
    if [ $JS_COUNT -gt 0 ]; then
        print_ok "JavaScript files found: $JS_COUNT"
    else
        print_warning "No JavaScript files"
        echo "  Solution: cd $APP_DIR && npm run build"
    fi
else
    print_warning "JavaScript directory not found"
fi

echo ""

# Check 7: Test Laravel
echo "=========================================="
print_check "7. Testing Laravel..."
echo "=========================================="

if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1)
    print_ok "PHP available: $PHP_VERSION"

    cd "$APP_DIR"
    if php artisan --version &> /dev/null; then
        LARAVEL_VERSION=$(php artisan --version)
        print_ok "Laravel working: $LARAVEL_VERSION"
    else
        print_error "Laravel command failed"
        echo "  Check: cd $APP_DIR && php artisan about"
    fi
else
    print_error "PHP not found"
fi

echo ""

# Summary
echo "=========================================="
echo "SUMMARY & QUICK FIXES"
echo "=========================================="
echo ""

HAS_ERRORS=false

# Critical checks
if [ ! -e "$PUBLIC_HTML" ]; then
    HAS_ERRORS=true
    print_error "CRITICAL: public_html not configured"
    echo ""
    echo "Quick Fix:"
    echo "  cd $BASE_DIR"
    echo "  ln -s $APP_DIR/public public_html"
    echo ""
fi

if [ ! -f "$APP_DIR/.env" ]; then
    HAS_ERRORS=true
    print_error "CRITICAL: .env missing"
    echo ""
    echo "Quick Fix:"
    echo "  cd $APP_DIR"
    echo "  cp .env.example .env"
    echo "  php artisan key:generate --force"
    echo ""
fi

if [ ! -d "$APP_DIR/vendor" ]; then
    HAS_ERRORS=true
    print_error "CRITICAL: Dependencies not installed"
    echo ""
    echo "Quick Fix:"
    echo "  cd $APP_DIR"
    echo "  composer install --no-dev"
    echo ""
fi

if [ "$HAS_ERRORS" = false ]; then
    print_ok "No critical issues found!"
    echo ""
    echo "If site still not working:"
    echo "  1. Check logs: tail -f $APP_DIR/storage/logs/laravel.log"
    echo "  2. Test URL: curl -I https://admin-panel-flexana-egypt.com"
    echo "  3. See: YOUR_SERVER_QUICK_FIX.md"
fi

echo ""
echo "=========================================="
echo "Diagnostic Complete!"
echo "=========================================="
echo ""
echo "For detailed solutions, see:"
echo "  - YOUR_SERVER_QUICK_FIX.md"
echo "  - DEPLOYMENT_COMMANDS_FOR_YOUR_SERVER.md"
echo "  - TROUBLESHOOTING_SYMLINK.md"
