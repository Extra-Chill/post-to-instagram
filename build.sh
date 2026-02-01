#!/bin/bash
# Production build script for Post to Instagram WordPress Plugin
# Creates clean production package in /dist with only essential files

set -e

PLUGIN_SLUG="post-to-instagram"
BUILD_DIR="build"
ROOT_DIR="$(pwd)"

echo "🚀 Building $PLUGIN_SLUG for production..."

# Clean previous builds
echo "🧹 Cleaning previous builds..."
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

# Install production dependencies (if composer.json exists)
if [ -f "composer.json" ]; then
    echo "📦 Installing production dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

# Build Gutenberg editor assets
if [ -f "package.json" ]; then
    echo "🔨 Building Gutenberg editor assets..."
    npm install > /dev/null
    npx wp-scripts build inc/Assets/src/js/post-editor.js --output-path=inc/Assets/dist/js/
    
    # Copy CSS files to dist
    echo "📄 Copying CSS assets..."
    mkdir -p inc/Assets/dist/css
    cp inc/Assets/src/css/*.css inc/Assets/dist/css/ 2>/dev/null || echo "No CSS files to copy"
fi

# Copy files with exclusions from .buildignore
echo "📁 Copying production files..."
if [ -f ".buildignore" ]; then
    # Use rsync with exclude-from for .buildignore patterns
    rsync -av --exclude-from=.buildignore ./ "$BUILD_DIR/$PLUGIN_SLUG/"
else
    # Fallback exclusions if .buildignore doesn't exist
    rsync -av --exclude='node_modules' \
              --exclude='vendor' \
              --exclude='admin/assets/src' \
              --exclude='.git' \
              --exclude='.DS_Store' \
              --exclude='.gitignore' \
              --exclude='.cursor' \
              --exclude='*.log' \
              --exclude='webpack.config.js' \
              --exclude='package.json' \
              --exclude='package-lock.json' \
              --exclude='composer.lock' \
              --exclude='build*.sh' \
              --exclude='build' \
              --exclude='*.zip' \
              --exclude='CLAUDE.md' \
              --exclude='AGENTS.md' \
              --exclude='README.md' \
              ./ "$BUILD_DIR/$PLUGIN_SLUG/"
fi

# Build validation - ensure essential plugin files exist
echo "✅ Validating build..."
ESSENTIAL_FILES=(
    "$BUILD_DIR/$PLUGIN_SLUG/post-to-instagram.php"
    "$BUILD_DIR/$PLUGIN_SLUG/inc/Core/Admin.php"
    "$BUILD_DIR/$PLUGIN_SLUG/inc/Core/Auth.php"
    "$BUILD_DIR/$PLUGIN_SLUG/inc/Core/RestApi.php"
    "$BUILD_DIR/$PLUGIN_SLUG/inc/Core/Actions/Post.php"
    "$BUILD_DIR/$PLUGIN_SLUG/inc/Core/Actions/Schedule.php"
    "$BUILD_DIR/$PLUGIN_SLUG/inc/Core/Actions/Cleanup.php"
    "$BUILD_DIR/$PLUGIN_SLUG/auth/oauth-handler.html"
    "$BUILD_DIR/$PLUGIN_SLUG/inc/Assets/dist/js/post-editor.js"
    "$BUILD_DIR/$PLUGIN_SLUG/inc/Assets/dist/js/post-editor.asset.php"
    "$BUILD_DIR/$PLUGIN_SLUG/inc/Assets/dist/css/admin-styles.css"
)

for file in "${ESSENTIAL_FILES[@]}"; do
    if [ ! -f "$file" ]; then
        echo "❌ ERROR: Essential file missing: $file"
        exit 1
    fi
done

# Create ZIP in /build
echo "📦 Creating production ZIP..."
cd "$BUILD_DIR"
zip -r "${PLUGIN_SLUG}.zip" "$PLUGIN_SLUG/" > /dev/null
cd "$ROOT_DIR"

# Restore dev dependencies (if composer.json exists)
if [ -f "composer.json" ]; then
    echo "🔄 Restoring development dependencies..."
    composer install --no-interaction > /dev/null
fi

echo "✅ Production build complete: $BUILD_DIR/${PLUGIN_SLUG}.zip"
echo "📊 Build size: $(du -h "$BUILD_DIR/${PLUGIN_SLUG}.zip" | cut -f1)"