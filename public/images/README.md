# System Images Directory

Place your system images here for the Flexana admin panel.

## Supported Images:

### Logo Files (choose one):
1. **logo.svg** (recommended) - Main logo for the admin panel
   - Format: SVG (scalable vector graphics)
   - Recommended size: 200x50px or similar aspect ratio
   - Should have transparent background
   - Best for: Crisp display at any size

2. **logo.png** (alternative) - Main logo in PNG format
   - Format: PNG with transparency
   - Recommended size: 200x50px or similar aspect ratio
   - Use if SVG is not available

### Favicon Files (choose one):
1. **favicon.ico** - Browser favicon
   - Format: ICO
   - Standard sizes: 16x16px, 32x32px, or multi-size ICO

2. **favicon.png** (alternative) - Browser favicon in PNG
   - Format: PNG
   - Recommended size: 32x32px or 64x64px

## How It Works:

The system automatically detects and loads images in this order:

**Logo Priority:**
1. `logo.svg` (if exists)
2. `logo.png` (if exists)
3. No logo (shows text "Flexana" only)

**Favicon Priority:**
1. `images/favicon.ico` (if exists)
2. `images/favicon.png` (if exists)
3. `public/favicon.ico` (fallback)

## Where Images Appear:

- **Logo**: Appears in the sidebar navigation and login page
- **Favicon**: Appears in browser tabs and bookmarks

## Best Practices:

✅ **Do:**
- Use SVG format for logos when possible (smaller file size, scalable)
- Optimize images for web (compress PNGs, minify SVGs)
- Use transparent backgrounds
- Keep logo height around 40-60px for best appearance
- Test in both light and dark modes (if enabled)

❌ **Don't:**
- Use very large image files (> 100KB)
- Use JPEG for logos (no transparency support)
- Use complex images (keep it simple and clean)

## Quick Start:

1. Place your logo file as `logo.svg` or `logo.png` in this directory
2. Place your favicon as `favicon.ico` or `favicon.png` in this directory
3. Clear your browser cache if images don't appear immediately
4. The images will automatically appear in the admin panel!

## File Structure:

```
public/
  images/
    logo.svg          ← Your logo here
    logo.png          ← Or use PNG
    favicon.ico       ← Your favicon here
    favicon.png       ← Or use PNG
```

