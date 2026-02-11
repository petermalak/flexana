#!/bin/bash

# Flexana Production Deployment Script for cPanel/Shared Hosting
# Usage: ./deploy-production.sh

set -e  # Exit on error

# Configuration
APP_DIR="/domains/admin-panel-flexana-egypt/flexana"
PUBLIC_HTML_DIR="/domains/admin-panel-flexana-egypt/public_html"

echo "=========================================="
echo "Flexana Production Deployment"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_info() {
    echo -e "${BLUE}→${NC} $1"
}

# Check if running in correct directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

print_info "Script directory: $SCRIPT_DIR"
echo ""

# Ask deployment method
echo "Choose deployment method:"
echo "1) Symlink (Recommended - keeps app secure outside web root)"
echo "2) Move public contents (use if symlinks not supported)"
echo ""
read -p "Enter choice (1 or 2): " DEPLOY_METHOD

if [[ ! "$DEPLOY_METHOD" =~ ^[12]$ ]]; then
    print_error "Invalid choice. Please run again and select 1 or 2."
    exit 1
fi

echo ""
print_info "Starting deployment process..."
echo ""

# Step 1: Check prerequisites
print_info "Checking prerequisites..."
if ! command -v php &> /dev/null; then
    print_error "PHP is not installed"
    exit 1
fi
print_success "PHP found: $(php -v | head -n 1)"

if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed"
    exit 1
fi
print_success "Composer found: $(composer --version | head -n 1)"

if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed"
    exit 1
fi
print_success "Node.js found: $(node -v)"

if ! command -v npm &> /dev/null; then
    print_error "NPM is not installed"
    exit 1
fi
print_success "NPM found: $(npm -v)"
echo ""

# Step 2: Check/Create .env file
print_info "Checking environment configuration..."
if [ ! -f .env ]; then
    print_warning ".env file not found"
    if [ -f .env.example ]; then
        print_info "Copying .env.example to .env"
        cp .env.example .env
        print_warning "IMPORTANT: Configure .env file with production values!"
        print_info "Edit with: nano .env"
        read -p "Press Enter after configuring .env file..."
    else
        print_error ".env.example not found. Cannot proceed."
        exit 1
    fi
else
    print_success ".env file exists"
fi
echo ""

# Step 3: Install PHP dependencies
print_info "Installing PHP dependencies (production mode)..."
composer install --optimize-autoloader --no-dev --no-interaction
print_success "PHP dependencies installed"
echo ""

# Step 4: Install Node dependencies
print_info "Installing Node dependencies..."
npm ci --silent
print_success "Node dependencies installed"
echo ""

# Step 5: Build assets
print_info "Building frontend assets..."
npm run build
print_success "Assets built successfully"
echo ""

# Step 6: Generate application key if not set
print_info "Checking application key..."
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null || grep -q "APP_KEY=$" .env 2>/dev/null; then
    print_warning "Application key not set, generating..."
    php artisan key:generate --force
    print_success "Application key generated"
else
    print_success "Application key already set"
fi
echo ""

# Step 7: Run database migrations
print_info "Database migrations..."
read -p "Run database migrations? (y/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php artisan migrate --force
    print_success "Database migrations completed"
else
    print_warning "Skipping database migrations"
fi
echo ""

# Step 8: Set permissions
print_info "Setting file permissions..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
print_success "Permissions set for storage and bootstrap/cache"
echo ""

# Step 9: Link storage
print_info "Linking storage..."
if [ ! -L public/storage ]; then
    php artisan storage:link
    print_success "Storage linked"
else
    print_success "Storage already linked"
fi
echo ""

# Step 10: Optimize application
print_info "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
print_success "Application optimized"
echo ""

# Step 11: Configure public_html based on method
if [ "$DEPLOY_METHOD" = "1" ]; then
    # Symlink method
    print_info "Configuring symlink method..."
    
    if [ -L "$PUBLIC_HTML_DIR" ] || [ -d "$PUBLIC_HTML_DIR" ]; then
        print_warning "public_html already exists"
        read -p "Remove and recreate symlink? (y/n) " -n 1 -r
        echo ""
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            print_info "Backing up existing public_html..."
            mv "$PUBLIC_HTML_DIR" "${PUBLIC_HTML_DIR}_backup_$(date +%Y%m%d_%H%M%S)"
            print_success "Backup created"
            
            print_info "Creating symlink..."
            ln -s "$APP_DIR/public" "$PUBLIC_HTML_DIR"
            print_success "Symlink created: $PUBLIC_HTML_DIR -> $APP_DIR/public"
        else
            print_warning "Keeping existing public_html"
        fi
    else
        print_info "Creating symlink..."
        ln -s "$APP_DIR/public" "$PUBLIC_HTML_DIR"
        print_success "Symlink created: $PUBLIC_HTML_DIR -> $APP_DIR/public"
    fi
    
elif [ "$DEPLOY_METHOD" = "2" ]; then
    # Move public contents method
    print_info "Configuring move public contents method..."
    print_warning "This will copy public folder contents to public_html"
    print_warning "You will need to manually update index.php paths"
    
    read -p "Continue? (y/n) " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        if [ -d "$PUBLIC_HTML_DIR" ]; then
            print_info "Backing up existing public_html..."
            mv "$PUBLIC_HTML_DIR" "${PUBLIC_HTML_DIR}_backup_$(date +%Y%m%d_%H%M%S)"
        fi
        
        print_info "Copying public folder contents..."
        cp -r public "$PUBLIC_HTML_DIR"
        print_success "Public contents copied to $PUBLIC_HTML_DIR"
        
        print_warning "IMPORTANT: You must manually edit $PUBLIC_HTML_DIR/index.php"
        print_info "Update these paths:"
        echo "  - __DIR__.'/../flexana/storage/framework/maintenance.php'"
        echo "  - __DIR__.'/../flexana/vendor/autoload.php'"
        echo "  - __DIR__.'/../flexana/bootstrap/app.php'"
        echo ""
        read -p "Press Enter after updating index.php..."
    else
        print_warning "Skipping public_html configuration"
    fi
fi

echo ""

# Step 12: Final checks
print_info "Running final checks..."

if grep -q "APP_DEBUG=true" .env 2>/dev/null; then
    print_error "APP_DEBUG is set to true!"
    print_warning "Set APP_DEBUG=false in .env for production"
else
    print_success "APP_DEBUG is correctly set to false"
fi

if ! grep -q "APP_ENV=production" .env 2>/dev/null; then
    print_warning "APP_ENV is not set to production"
else
    print_success "APP_ENV is correctly set to production"
fi

echo ""
echo "=========================================="
print_success "Deployment completed successfully!"
echo "=========================================="
echo ""

print_info "Next steps:"
echo ""
echo "1. Create admin user:"
echo "   php artisan make:filament-user"
echo ""
echo "2. Generate API keys (if needed):"
echo "   php artisan api:generate-key \"Production App\""
echo ""
echo "3. Set up cron job for scheduled tasks:"
echo "   * * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1"
echo ""
echo "4. Test your application:"
echo "   - Homepage: https://admin-panel-flexana-egypt.com"
echo "   - Admin Panel: https://admin-panel-flexana-egypt.com/admin"
echo "   - Health Check: https://admin-panel-flexana-egypt.com/up"
echo ""
echo "5. Monitor logs:"
echo "   tail -f storage/logs/laravel.log"
echo ""

print_info "For detailed instructions, see PRODUCTION_DEPLOYMENT_CPANEL.md"
echo ""
