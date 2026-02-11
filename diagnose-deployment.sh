#!/bin/bash

# Deployment Diagnostic Script
# Run this if you can't reach your site after deployment

echo "=========================================="
echo "Flexana Deployment Diagnostic Tool"
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

# Configuration
APP_DIR="/domains/admin-panel-flexana-egypt/flexana"
PUBLIC_HTML="/domains/admin-panel-flexana-egypt/public_html"

echo "Configuration:"
echo "  App Directory: $APP_DIR"
echo "  Web Root: $PUBLIC_HTML"
echo ""

# Check 1: Directory Structure
echo "=========================================="
print_check "1. Checking directory structure..."
echo "=========================================="

if [ -d "$APP_DIR" ]; then
    print_ok "Laravel app directory exists: $APP_DIR"
else
    print_error "Laravel app directory NOT found: $APP_DIR"
    echo "Solution: Upload your Laravel application to $APP_DIR"
    exit 1
fi

if [ -d "$APP_DIR/public" ]; then
    print_ok "Public directory exists: $APP_DIR/public"
else
    print_error "Public directory NOT found: $APP_DIR/public"
    exit 1
fi

if [ -f "$APP_DIR/public/index.php" ]; then
    print_ok "index.php exists"
else
    print_error "index.php NOT found in public directory"
    exit 1
fi

echo ""

# Check 2: public_html status
echo "=========================================="
print_check "2. Checking public_html status..."
echo "=========================================="

if [ -L "$PUBLIC_HTML" ]; then
    print_ok "public_html is a symlink"

    # Check symlink target
    SYMLINK_TARGET=$(readlink "$PUBLIC_HTML")
    echo "  Symlink points to: $SYMLINK_TARGET"

    if [ "$SYMLINK_TARGET" == "$APP_DIR/public" ] || [ "$SYMLINK_TARGET" == "/domains/admin-panel-flexana-egypt/flexana/public" ]; then
        print_ok "Symlink target is correct"
    else
        print_error "Symlink target is INCORRECT"
        echo "  Expected: $APP_DIR/public"
        echo "  Actual: $SYMLINK_TARGET"
        echo ""
        echo "Solution:"
        echo "  rm $PUBLIC_HTML"
        echo "  ln -s $APP_DIR/public $PUBLIC_HTML"
    fi

    # Check if target exists
    if [ -d "$SYMLINK_TARGET" ]; then
        print_ok "Symlink target exists"
    else
        print_error "Symlink target does NOT exist"
    fi

    # Check if index.php accessible through symlink
    if [ -f "$PUBLIC_HTML/index.php" ]; then
        print_ok "index.php accessible through symlink"
    else
        print_error "index.php NOT accessible through symlink"
        print_warning "Your hosting may not support symlinks"
        echo ""
        echo "Solution: Use 'Move Public Contents' method"
        echo "  See: TROUBLESHOOTING_SYMLINK.md"
    fi

elif [ -d "$PUBLIC_HTML" ]; then
    print_warning "public_html is a regular directory (not symlink)"
    echo "  This is OK if using 'Move Public Contents' method"

    # Check if index.php exists
    if [ -f "$PUBLIC_HTML/index.php" ]; then
        print_ok "index.php exists in public_html"

        # Check if paths are modified
        if grep -q "../flexana/vendor" "$PUBLIC_HTML/index.php"; then
            print_ok "index.php paths appear to be modified for Laravel"
        else
            print_error "index.php paths NOT modified"
            echo ""
            echo "Solution: Edit $PUBLIC_HTML/index.php"
            echo "  Change all paths from '../' to '../flexana/'"
            echo "  See: TROUBLESHOOTING_SYMLINK.md"
        fi
    else
        print_error "index.php NOT found in public_html"
        echo ""
        echo "Solution:"
        echo "  cp -r $APP_DIR/public/* $PUBLIC_HTML/"
    fi
else
    print_error "public_html does NOT exist"
    echo ""
    echo "Solution: Create symlink or copy public folder"
    echo "  Symlink: ln -s $APP_DIR/public $PUBLIC_HTML"
    echo "  Or copy: cp -r $APP_DIR/public $PUBLIC_HTML"
fi

echo ""

# Check 3: File Permissions
echo "=========================================="
print_check "3. Checking file permissions..."
echo "=========================================="

PUBLIC_PERMS=$(stat -c '%a' "$APP_DIR/public" 2>/dev/null || stat -f '%A' "$APP_DIR/public" 2>/dev/null)
if [ "$PUBLIC_PERMS" -ge 755 ]; then
    print_ok "Public directory permissions OK: $PUBLIC_PERMS"
else
    print_warning "Public directory permissions may be too restrictive: $PUBLIC_PERMS"
    echo "  Solution: chmod 755 $APP_DIR/public"
fi

if [ -d "$APP_DIR/storage" ]; then
    STORAGE_PERMS=$(stat -c '%a' "$APP_DIR/storage" 2>/dev/null || stat -f '%A' "$APP_DIR/storage" 2>/dev/null)
    if [ "$STORAGE_PERMS" -ge 775 ]; then
        print_ok "Storage directory permissions OK: $STORAGE_PERMS"
    else
        print_error "Storage directory permissions too restrictive: $STORAGE_PERMS"
        echo "  Solution: chmod -R 775 $APP_DIR/storage"
    fi
fi

echo ""

# Check 4: .htaccess
echo "=========================================="
print_check "4. Checking .htaccess..."
echo "=========================================="

if [ -f "$APP_DIR/public/.htaccess" ]; then
    print_ok ".htaccess exists"

    # Check if RewriteEngine is On
    if grep -q "RewriteEngine On" "$APP_DIR/public/.htaccess"; then
        print_ok "RewriteEngine is enabled"
    else
        print_warning "RewriteEngine not found in .htaccess"
    fi
else
    print_error ".htaccess NOT found"
    echo "  Solution: Create .htaccess in $APP_DIR/public/"
fi

echo ""

# Check 5: Laravel Configuration
echo "=========================================="
print_check "5. Checking Laravel configuration..."
echo "=========================================="

if [ -f "$APP_DIR/.env" ]; then
    print_ok ".env file exists"

    # Check APP_KEY
    if grep -q "APP_KEY=base64:" "$APP_DIR/.env"; then
        print_ok "APP_KEY is set"
    else
        print_error "APP_KEY is NOT set"
        echo "  Solution: cd $APP_DIR && php artisan key:generate --force"
    fi

    # Check APP_DEBUG
    if grep -q "APP_DEBUG=false" "$APP_DIR/.env"; then
        print_ok "APP_DEBUG is false (production)"
    else
        print_warning "APP_DEBUG is not set to false"
        echo "  For production, set: APP_DEBUG=false"
    fi

    # Check APP_ENV
    if grep -q "APP_ENV=production" "$APP_DIR/.env"; then
        print_ok "APP_ENV is production"
    else
        print_warning "APP_ENV is not set to production"
    fi
else
    print_error ".env file NOT found"
    echo "  Solution: cp $APP_DIR/.env.example $APP_DIR/.env"
    echo "           cd $APP_DIR && php artisan key:generate --force"
fi

echo ""

# Check 6: Composer Dependencies
echo "=========================================="
print_check "6. Checking dependencies..."
echo "=========================================="

if [ -d "$APP_DIR/vendor" ]; then
    print_ok "Vendor directory exists"

    if [ -f "$APP_DIR/vendor/autoload.php" ]; then
        print_ok "Composer autoloader exists"
    else
        print_error "Composer autoloader NOT found"
        echo "  Solution: cd $APP_DIR && composer install --no-dev"
    fi
else
    print_error "Vendor directory NOT found"
    echo "  Solution: cd $APP_DIR && composer install --no-dev"
fi

echo ""

# Check 7: Built Assets
echo "=========================================="
print_check "7. Checking built assets..."
echo "=========================================="

if [ -d "$APP_DIR/public/css" ]; then
    CSS_COUNT=$(find "$APP_DIR/public/css" -name "*.css" | wc -l)
    if [ $CSS_COUNT -gt 0 ]; then
        print_ok "CSS files found: $CSS_COUNT"
    else
        print_warning "No CSS files found"
        echo "  Solution: cd $APP_DIR && npm run build"
    fi
else
    print_warning "CSS directory not found"
fi

if [ -d "$APP_DIR/public/js" ]; then
    JS_COUNT=$(find "$APP_DIR/public/js" -name "*.js" | wc -l)
    if [ $JS_COUNT -gt 0 ]; then
        print_ok "JavaScript files found: $JS_COUNT"
    else
        print_warning "No JavaScript files found"
        echo "  Solution: cd $APP_DIR && npm run build"
    fi
else
    print_warning "JavaScript directory not found"
fi

echo ""

# Check 8: Storage Link
echo "=========================================="
print_check "8. Checking storage link..."
echo "=========================================="

if [ -L "$APP_DIR/public/storage" ]; then
    print_ok "Storage link exists"
else
    print_warning "Storage link NOT found"
    echo "  Solution: cd $APP_DIR && php artisan storage:link"
fi

echo ""

# Check 9: Test PHP
echo "=========================================="
print_check "9. Testing PHP..."
echo "=========================================="

if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1)
    print_ok "PHP is available: $PHP_VERSION"

    # Test Laravel
    cd "$APP_DIR"
    if php artisan --version &> /dev/null; then
        LARAVEL_VERSION=$(php artisan --version)
        print_ok "Laravel is working: $LARAVEL_VERSION"
    else
        print_error "Laravel command failed"
        echo "  Check: cd $APP_DIR && php artisan about"
    fi
else
    print_error "PHP not found in PATH"
fi

echo ""

# Summary
echo "=========================================="
echo "SUMMARY & RECOMMENDATIONS"
echo "=========================================="
echo ""

ERRORS_FOUND=false

# Check critical issues
if [ ! -L "$PUBLIC_HTML" ] && [ ! -d "$PUBLIC_HTML" ]; then
    ERRORS_FOUND=true
    print_error "CRITICAL: public_html not configured"
    echo "  Run: ln -s $APP_DIR/public $PUBLIC_HTML"
    echo ""
fi

if [ ! -f "$APP_DIR/.env" ]; then
    ERRORS_FOUND=true
    print_error "CRITICAL: .env file missing"
    echo "  Run: cp $APP_DIR/.env.example $APP_DIR/.env"
    echo "       cd $APP_DIR && php artisan key:generate --force"
    echo ""
fi

if [ ! -d "$APP_DIR/vendor" ]; then
    ERRORS_FOUND=true
    print_error "CRITICAL: Dependencies not installed"
    echo "  Run: cd $APP_DIR && composer install --no-dev"
    echo ""
fi

if [ "$ERRORS_FOUND" = false ]; then
    print_ok "No critical issues found!"
    echo ""
    echo "If site is still not working:"
    echo "  1. Check Laravel logs: tail -f $APP_DIR/storage/logs/laravel.log"
    echo "  2. Check web server logs"
    echo "  3. See: TROUBLESHOOTING_SYMLINK.md"
else
    echo "Fix the errors above and run this script again."
fi

echo ""
echo "=========================================="
echo "Diagnostic complete!"
echo "=========================================="
