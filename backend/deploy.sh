#!/bin/bash

# Flexana Production Deployment Script
# Usage: ./deploy.sh [environment]
# Example: ./deploy.sh production

set -e  # Exit on error

ENVIRONMENT=${1:-production}
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

echo "=========================================="
echo "Flexana Deployment Script"
echo "Environment: $ENVIRONMENT"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
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
    echo -e "${NC}→${NC} $1"
}

# Check if running as root
if [ "$EUID" -eq 0 ]; then 
    print_error "Please do not run this script as root"
    exit 1
fi

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

# Step 2: Check if .env exists
print_info "Checking environment configuration..."
if [ ! -f .env ]; then
    print_warning ".env file not found"
    if [ -f .env.example ]; then
        print_info "Copying .env.example to .env"
        cp .env.example .env
        print_warning "Please configure .env file before continuing"
        print_info "You can edit it with: nano .env"
        read -p "Press Enter after configuring .env file..."
    else
        print_error ".env.example not found. Cannot proceed."
        exit 1
    fi
else
    print_success ".env file exists"
fi
echo ""

# Step 3: Install/Update dependencies
print_info "Installing PHP dependencies..."
if [ "$ENVIRONMENT" = "production" ]; then
    composer install --optimize-autoloader --no-dev --no-interaction
else
    composer install --optimize-autoloader --no-interaction
fi
print_success "PHP dependencies installed"
echo ""

print_info "Installing Node dependencies..."
npm ci --silent
print_success "Node dependencies installed"
echo ""

# Step 4: Generate application key if not set
print_info "Checking application key..."
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null || grep -q "APP_KEY=$" .env 2>/dev/null; then
    print_warning "Application key not set, generating..."
    php artisan key:generate --force
    print_success "Application key generated"
else
    print_success "Application key already set"
fi
echo ""

# Step 5: Build assets
print_info "Building assets..."
npm run build
print_success "Assets built successfully"
echo ""

# Step 6: Run database migrations
print_info "Running database migrations..."
read -p "Run database migrations? (y/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php artisan migrate --force
    print_success "Database migrations completed"
else
    print_warning "Skipping database migrations"
fi
echo ""

# Step 7: Set permissions
print_info "Setting file permissions..."
if [ -w storage ] && [ -w bootstrap/cache ]; then
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true
    print_success "Permissions set"
else
    print_warning "Cannot set permissions. You may need to run:"
    print_info "sudo chmod -R 775 storage bootstrap/cache"
    print_info "sudo chown -R www-data:www-data storage bootstrap/cache"
fi
echo ""

# Step 8: Link storage
print_info "Linking storage..."
if [ ! -L public/storage ]; then
    php artisan storage:link
    print_success "Storage linked"
else
    print_success "Storage already linked"
fi
echo ""

# Step 9: Clear and cache configuration
print_info "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
print_success "Application optimized"
echo ""

# Step 10: Generate API keys reminder
print_info "API Key Generation"
print_warning "Don't forget to generate API keys for frontend:"
print_info "php artisan api:generate-key \"Production Frontend App\""
echo ""

# Step 11: Final checks
print_info "Running final checks..."

# Check if APP_DEBUG is false in production
if [ "$ENVIRONMENT" = "production" ]; then
    if grep -q "APP_DEBUG=true" .env 2>/dev/null; then
        print_error "APP_DEBUG is set to true in production!"
        print_warning "Please set APP_DEBUG=false in .env file"
    else
        print_success "APP_DEBUG is correctly set to false"
    fi

    if ! grep -q "APP_ENV=production" .env 2>/dev/null; then
        print_warning "APP_ENV is not set to production"
    else
        print_success "APP_ENV is correctly set to production"
    fi
fi

echo ""
echo "=========================================="
print_success "Deployment completed successfully!"
echo "=========================================="
echo ""
print_info "Next steps:"
echo "  1. Verify .env configuration"
echo "  2. Generate API keys: php artisan api:generate-key"
echo "  3. Create admin user: php artisan make:filament-user"
echo "  4. Test the application: https://yourdomain.com/up"
echo "  5. Set up queue workers and scheduled tasks"
echo ""
print_info "For detailed instructions, see DEPLOYMENT.md"
echo ""





