<!doctype html>
@php
    use Illuminate\Support\Facades\Http;

    // Optional theme overrides (hex without #). Default skin matches Amelia booking widget.
    // /web-session-bookings?primary=2d555b&accent=2d555b&…
    // /web-session-bookings?brand_from_logo=1 — sample logo for palette (off by default).
    $hex = static function (?string $value, string $fallback): string {
        $v = strtolower(trim((string) $value));
        $v = ltrim($v, '#');
        if ($v === '' || ! preg_match('/^[0-9a-f]{6}$/', $v)) {
            return $fallback;
        }

        return '#'.$v;
    };

    $hexToRgb = static function (string $hex): ?array {
        $h = ltrim(strtolower(trim($hex)), '#');
        if (strlen($h) !== 6 || ! preg_match('/^[0-9a-f]{6}$/', $h)) {
            return null;
        }

        return [
            hexdec(substr($h, 0, 2)),
            hexdec(substr($h, 2, 2)),
            hexdec(substr($h, 4, 2)),
        ];
    };

    $rgbaFromHex = static function (string $hex, float $alpha) use ($hexToRgb): string {
        $rgb = $hexToRgb($hex);
        if (! $rgb) {
            return 'rgba(0,0,0,'.$alpha.')';
        }
        $a = max(0.0, min(1.0, $alpha));

        return 'rgba('.$rgb[0].','.$rgb[1].','.$rgb[2].','.$a.')';
    };

    // Defaults mirror Amelia’s public booking widget (#amelia-booking-wrap) — see ameliabooking uploads CSS.
    $defaults = [
        'primary' => '#2d555b',
        'primary2' => '#edf2f3',
        'accent' => '#2d555b',
        'bg' => '#ffffff',
        'card' => '#ffffff',
        'text' => '#354052',
        'muted' => '#616e7c',
        'border' => '#e2e6ec',
    ];

    $clamp255 = static function (int $v): int {
        if ($v < 0) {
            return 0;
        }
        if ($v > 255) {
            return 255;
        }

        return $v;
    };

    $rgbToHex = static function (int $r, int $g, int $b): string {
        return '#'.sprintf('%02x%02x%02x', $r, $g, $b);
    };

    $relativeLuminance = static function (int $r, int $g, int $b): float {
        $sr = $r / 255;
        $sg = $g / 255;
        $sb = $b / 255;

        $lin = static function (float $c): float {
            return $c <= 0.03928 ? ($c / 12.92) : (($c + 0.055) / 1.055) ** 2.4;
        };

        $R = $lin($sr);
        $G = $lin($sg);
        $B = $lin($sb);

        return 0.2126 * $R + 0.7152 * $G + 0.0722 * $B;
    };

    $extractPaletteFromLogo = static function (string $absolutePath) use ($clamp255, $rgbToHex, $relativeLuminance): ?array {
        if (! is_readable($absolutePath)) {
            return null;
        }

        if (! extension_loaded('gd')) {
            return null;
        }

        $bytes = @file_get_contents($absolutePath);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        $im = @imagecreatefromstring($bytes);
        if (! $im) {
            return null;
        }

        $w = imagesx($im);
        $h = imagesy($im);
        if ($w < 2 || $h < 2) {
            imagedestroy($im);

            return null;
        }

        $samples = [];

        $step = 6; // sampling step (performance)
        $trueColor = function_exists('imageistruecolor') ? imageistruecolor($im) : true;

        $rgbToHsl = static function (int $r, int $g, int $b): array {
            $r /= 255;
            $g /= 255;
            $b /= 255;

            $max = max($r, $g, $b);
            $min = min($r, $g, $b);
            $delta = $max - $min;

            $l = ($max + $min) / 2;
            if ($delta < 1e-6) {
                return [0.0, 0.0, $l]; // hue undefined for neutrals
            }

            $s = $l > 0.5 ? $delta / (2 - $max - $min) : $delta / ($max + $min);

            if ($max === $r) {
                $h = fmod((($g - $b) / $delta), 6) / 6;
            } elseif ($max === $g) {
                $h = ((($b - $r) / $delta) + 2) / 6;
            } else {
                $h = ((($r - $g) / $delta) + 4) / 6;
            }
            if ($h < 0) {
                $h += 1.0;
            }

            return [$h, $s, $l];
        };

        $hslToRgb = static function (float $h, float $s, float $l) use ($clamp255): array {
            if ($s < 1e-6) {
                $v = (int) round($l * 255);

                return [$clamp255($v), $clamp255($v), $clamp255($v)];
            }

            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;

            $hue2rgb = static function (float $p, float $q, float $t): float {
                if ($t < 0) {
                    $t += 1;
                }
                if ($t > 1) {
                    $t -= 1;
                }
                if ($t < 1 / 6) {
                    return $p + ($q - $p) * 6 * $t;
                }
                if ($t < 1 / 2) {
                    return $q;
                }
                if ($t < 2 / 3) {
                    return $p + ($q - $p) * (2 / 3 - $t) * 6;
                }

                return $p;
            };

            $r = $hue2rgb($p, $q, $h + 1 / 3);
            $g = $hue2rgb($p, $q, $h);
            $b = $hue2rgb($p, $q, $h - 1 / 3);

            return [
                $clamp255((int) round($r * 255)),
                $clamp255((int) round($g * 255)),
                $clamp255((int) round($b * 255)),
            ];
        };

        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $idx = imagecolorat($im, $x, $y);

                if ($trueColor) {
                    // 32-bit packed RGBA: A is 0 (opaque) .. 127 (transparent)
                    $a = ($idx & 0x7F000000) >> 24;
                    if ($a >= 120) {
                        continue;
                    }
                    $r = ($idx >> 16) & 0xFF;
                    $g = ($idx >> 8) & 0xFF;
                    $b = $idx & 0xFF;
                } else {
                    $cols = imagecolorsforindex($im, $idx);
                    $a = (int) ($cols['alpha'] ?? 0); // 0 opaque .. 127 transparent
                    if ($a >= 120) {
                        continue;
                    }
                    $r = (int) ($cols['red'] ?? 0);
                    $g = (int) ($cols['green'] ?? 0);
                    $b = (int) ($cols['blue'] ?? 0);
                }

                // Ignore near-white / near-black background pixels
                $lum = $relativeLuminance($r, $g, $b);
                if ($lum > 0.94 || $lum < 0.06) {
                    continue;
                }

                [$hh, $ss, $ll] = $rgbToHsl($r, $g, $b);

                // Ignore very low saturation (near grey) unless it's a deliberate dark mark
                if ($ss < 0.08 && $ll > 0.25) {
                    continue;
                }

                // Weight opaque pixels higher than semi-transparent edges
                $wgt = max(0.05, (127 - $a) / 127);
                $chroma = $ss * (1 - abs(2 * $ll - 1)); // simple "colorfulness" proxy

                $samples[] = [
                    'r' => $r,
                    'g' => $g,
                    'b' => $b,
                    'w' => $wgt,
                    'h' => $hh,
                    's' => $ss,
                    'l' => $ll,
                    'c' => $chroma,
                ];
            }
        }

        imagedestroy($im);

        if (count($samples) < 10) {
            return null;
        }

        // Pick the most "brand-like" sample: colorful + not too light/dark.
        usort($samples, static function (array $a, array $b): int {
            $scoreA = ($a['c'] * 1.15 + $a['s'] * 0.55) * $a['w'];
            $scoreB = ($b['c'] * 1.15 + $b['s'] * 0.55) * $b['w'];

            return $scoreB <=> $scoreA;
        });

        // Logos often contain a saturated blush/pink mark that reads as "the whole UI is pink".
        // Prefer a strong non-pink accent if one exists in the top candidates.
        $isPinkFamily = static function (float $hh, float $ss, float $ll): bool {
            if ($ss < 0.10 || $ll < 0.12 || $ll > 0.96) {
                return false;
            }
            // Magenta → red-rose band in normalized hue [0,1)
            if ($hh >= 0.88 || $hh <= 0.045) {
                return true;
            }

            return false;
        };

        $pick = null;
        foreach ($samples as $s) {
            if ($s['l'] <= 0.18 || $s['l'] >= 0.92) {
                continue;
            }
            if ($isPinkFamily((float) $s['h'], (float) $s['s'], (float) $s['l'])) {
                continue;
            }
            $pick = $s;
            break;
        }
        if (! $pick) {
            foreach ($samples as $s) {
                if ($s['l'] > 0.18 && $s['l'] < 0.92) {
                    $pick = $s;
                    break;
                }
            }
        }
        if (! $pick) {
            $pick = $samples[0];
        }

        $br = (int) $pick['r'];
        $bg = (int) $pick['g'];
        $bb = (int) $pick['b'];

        // Primary: slightly more saturated + a touch lighter than the picked brand pixel
        [$h0, $s0, $l0] = $rgbToHsl($br, $bg, $bb);
        if ($isPinkFamily($h0, $s0, $l0)) {
            // Still pink after best-effort pick: nudge hue toward warm coral and cap saturation
            // so large UI surfaces (pills, gradients) do not read as "all pink".
            $h0 = fmod($h0 + 0.055, 1.0);
            $s0 = min($s0, 0.42) * 0.72;
            $l0 = min(0.58, max(0.42, $l0));
        }
        $s1 = min(1.0, $s0 + 0.08);
        $l1 = min(0.92, $l0 + 0.06);
        [$pr, $pg, $pb] = $hslToRgb($h0, $s1, $l1);
        $primary = $rgbToHex($pr, $pg, $pb);

        // Accent: deeper version of same hue (not "pink average")
        $l2 = max(0.12, min(0.35, $l0 * 0.55));
        $s2 = min(1.0, $s0 + 0.05);
        if ($isPinkFamily((float) $pick['h'], (float) $pick['s'], (float) $pick['l'])) {
            $s2 = min($s2, 0.38);
        }
        [$ar, $ag, $ab] = $hslToRgb($h0, $s2, $l2);
        $accent = $rgbToHex($ar, $ag, $ab);

        // Surfaces: keep large areas mostly neutral (only a hint of the brand hue),
        // while buttons/pills can use the more saturated `primary` + `accent`.
        $surfaceS = min(0.10, max(0.03, $s0 * 0.18));

        $primary2 = $rgbToHex(...$hslToRgb($h0, $surfaceS, 0.965));
        $border = $rgbToHex(...$hslToRgb($h0, min(0.12, $surfaceS + 0.03), 0.90));
        $bgHex = $rgbToHex(...$hslToRgb($h0, min(0.08, $surfaceS), 0.99));

        $text = '#111111';
        // (muted derived from accent)
        $muted = $rgbToHex(
            $clamp255((int) round($ar + 70)),
            $clamp255((int) round($ag + 70)),
            $clamp255((int) round($ab + 70))
        );

        return [
            'primary' => $primary,
            'primary2' => $primary2,
            'accent' => $accent,
            'bg' => $bgHex,
            'card' => '#ffffff',
            'text' => $text,
            'muted' => $muted,
            'border' => $border,
        ];
    };

    $extractHexes = static function (string $css): array {
        preg_match_all('/#([0-9a-fA-F]{6})\b/', $css, $m);
        $out = [];
        foreach (($m[1] ?? []) as $h) {
            $out[] = '#'.strtolower($h);
        }

        return $out;
    };

    $scorePalette = static function (array $hexes, array $baseDefaults): array {
        $freq = [];
        foreach ($hexes as $h) {
            $freq[$h] = ($freq[$h] ?? 0) + 1;
        }

        arsort($freq);
        $candidates = array_keys($freq);

        $isVeryLight = static function (string $hex): bool {
            $h = ltrim($hex, '#');
            if (strlen($h) !== 6) {
                return false;
            }
            $r = hexdec(substr($h, 0, 2));
            $g = hexdec(substr($h, 2, 2));
            $b = hexdec(substr($h, 4, 2));
            // perceived luminance-ish
            $y = (0.2126 * $r) + (0.7152 * $g) + (0.0722 * $b);

            return $y > 235;
        };

        $isBlush = static function (string $hex): bool {
            $h = ltrim($hex, '#');
            if (strlen($h) !== 6) {
                return false;
            }
            $r = hexdec(substr($h, 0, 2));
            $g = hexdec(substr($h, 2, 2));
            $b = hexdec(substr($h, 4, 2));

            // Pink-ish / blush-ish heuristic
            return $r >= 200 && $g >= 160 && $b >= 160 && $r >= $g && $r >= $b;
        };

        $primary = $baseDefaults['primary'];
        foreach ($candidates as $h) {
            if ($isBlush($h) && ! $isVeryLight($h)) {
                $primary = $h;
                break;
            }
        }

        $accent = $baseDefaults['accent'];
        foreach ($candidates as $h) {
            if ($h === $primary) {
                continue;
            }
            if (! $isVeryLight($h)) {
                $accent = $h;
                break;
            }
        }

        $text = $baseDefaults['text'];
        foreach ($candidates as $h) {
            if ($h === $primary || $h === $accent) {
                continue;
            }
            if (! $isVeryLight($h)) {
                $text = $h;
                break;
            }
        }

        $border = $baseDefaults['border'];
        foreach ($candidates as $h) {
            if ($h === $primary) {
                $border = $h;
                break;
            }
        }

        $muted = $baseDefaults['muted'];
        // Derive muted from text if possible
        $t = ltrim($text, '#');
        if (strlen($t) === 6) {
            $r = max(0, hexdec(substr($t, 0, 2)) - 40);
            $g = max(0, hexdec(substr($t, 2, 2)) - 40);
            $b = max(0, hexdec(substr($t, 4, 2)) - 40);
            $muted = '#'.sprintf('%02x%02x%02x', $r, $g, $b);
        }

        $p = ltrim($primary, '#');
        $primary2 = $baseDefaults['primary2'];
        if (strlen($p) === 6) {
            $r = min(255, hexdec(substr($p, 0, 2)) + 10);
            $g = min(255, hexdec(substr($p, 2, 2)) + 10);
            $b = min(255, hexdec(substr($p, 4, 2)) + 10);
            $primary2 = '#'.sprintf('%02x%02x%02x', $r, $g, $b);
        }

        return [
            'primary' => $primary,
            'primary2' => $primary2,
            'accent' => $accent,
            'bg' => $baseDefaults['bg'],
            'card' => $baseDefaults['card'],
            'text' => $text,
            'muted' => $muted,
            'border' => $border,
        ];
    };

    $allowedHosts = array_values(array_filter(array_map('trim', explode(',', (string) config('booking_embed.palette_source_hosts', '')))));

    $isAllowedHost = static function (string $host, array $allowedHosts): bool {
        $host = strtolower(trim($host));
        if ($host === '') {
            return false;
        }
        foreach ($allowedHosts as $rule) {
            $rule = strtolower(trim((string) $rule));
            if ($rule === '') {
                continue;
            }
            if ($host === $rule) {
                return true;
            }
        }

        return false;
    };

    $resolveUrl = static function (string $base, string $href): ?string {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, 'data:')) {
            return null;
        }
        if (str_starts_with($href, '//')) {
            $parts = parse_url($base);
            $scheme = $parts['scheme'] ?? 'https';

            return $scheme.':'.$href;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        return (string) \Illuminate\Support\Uri::of($base)->join($href);
    };

    $paletteSource = (string) (request()->query('palette_source') ?: (string) config('booking_embed.palette_source_url', ''));

    $remotePalette = $defaults;

    // Logo sampling is opt-in so the embed matches Amelia’s neutral widget by default.
    if (request()->boolean('brand_from_logo')) {
        $logoPath = public_path('images/logo.png');
        $logoPalette = $extractPaletteFromLogo($logoPath);
        if (is_array($logoPalette)) {
            $remotePalette = $logoPalette;
        }
    }

    if ($paletteSource !== '') {
        try {
            $parts = parse_url($paletteSource);
            $host = strtolower((string) ($parts['host'] ?? ''));
            if ($host !== '' && $isAllowedHost($host, $allowedHosts)) {
                $htmlResp = Http::timeout(2)->connectTimeout(2)->accept('text/html')->get($paletteSource);
                if ($htmlResp->successful()) {
                    $html = (string) $htmlResp->body();

                    $hrefs = [];
                    if (preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]*>/i', $html, $linkTags)) {
                        foreach ($linkTags[0] as $tag) {
                            if (preg_match('/href=["\']([^"\']+)["\']/i', $tag, $mm)) {
                                $hrefs[] = $mm[1];
                            }
                        }
                    }

                    $max = max(1, (int) config('booking_embed.palette_max_stylesheets', 6));
                    $cssBlob = '';
                    $count = 0;
                    foreach ($hrefs as $href) {
                        if ($count >= $max) {
                            break;
                        }
                        $url = $resolveUrl($paletteSource, $href);
                        if (! $url) {
                            continue;
                        }
                        $u = parse_url($url);
                        $h = strtolower((string) ($u['host'] ?? ''));
                        if ($h === '' || ! $isAllowedHost($h, $allowedHosts)) {
                            continue;
                        }

                        $cssResp = Http::timeout(2)->connectTimeout(2)->accept('text/css')->get($url);
                        if (! $cssResp->successful()) {
                            continue;
                        }
                        $cssBlob .= "\n".(string) $cssResp->body();
                        $count++;
                    }

                    if ($cssBlob !== '') {
                        $hexes = $extractHexes($cssBlob);
                        if (count($hexes) > 0) {
                            // Remote palette (optional) wins when explicitly configured.
                            $remotePalette = $scorePalette($hexes, $remotePalette);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Keep defaults on any failure (timeouts, DNS, etc.)
        }
    }

    $primary = $hex(request()->query('primary'), $remotePalette['primary']);
    $primary2 = $hex(request()->query('primary2'), $remotePalette['primary2']);
    $accent = $hex(request()->query('accent'), $remotePalette['accent']);
    $bg = $hex(request()->query('bg'), $remotePalette['bg']);
    $card = $hex(request()->query('card'), $remotePalette['card']);
    $text = $hex(request()->query('text'), $remotePalette['text']);
    $muted = $hex(request()->query('muted'), $remotePalette['muted']);
    $border = $hex(request()->query('border'), $remotePalette['border']);

    $accentRgb = $hexToRgb($accent) ?: [45, 85, 91];
    $accentDark = $rgbToHex(
        $clamp255((int) round($accentRgb[0] * 0.85)),
        $clamp255((int) round($accentRgb[1] * 0.85)),
        $clamp255((int) round($accentRgb[2] * 0.85))
    );
@endphp
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book a session</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --flex-primary: {{ $primary }};
            --flex-primary-2: {{ $primary2 }};
            --flex-accent: {{ $accent }};
            --flex-bg: {{ $bg }};
            --flex-card: {{ $card }};
            --flex-text: {{ $text }};
            --flex-muted: {{ $muted }};
            --flex-border: {{ $border }};
            --flex-accent-dark: {{ $accentDark }};
            --amelia-link: #1a84ee;
            --amelia-primary-hover: rgba(45, 85, 91, 0.75);
            --flex-shadow: 0 1px 3px rgba(53, 64, 82, 0.08);
        }

        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        html {
            scroll-behavior: auto;
        }

        body {
            margin: 0;
            font-family: 'Roboto', 'Amelia Roboto', system-ui, -apple-system, Segoe UI, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            background: var(--flex-bg);
            color: var(--flex-text);
        }

        #flexana-booking-wrap,
        #flexana-booking-wrap h1,
        #flexana-booking-wrap h2,
        #flexana-booking-wrap h3,
        #flexana-booking-wrap p,
        #flexana-booking-wrap span,
        #flexana-booking-wrap div,
        #flexana-booking-wrap label,
        #flexana-booking-wrap button,
        #flexana-booking-wrap input,
        #flexana-booking-wrap select {
            font-family: 'Roboto', 'Amelia Roboto', system-ui, -apple-system, Segoe UI, Arial, sans-serif;
        }

        #flexana-booking-wrap h1 {
            font-size: 24px;
            line-height: 1.5;
            font-weight: 400;
            color: var(--flex-text);
            margin: 0;
        }

        header {
            padding: 20px 32px;
            border-bottom: 1px solid var(--flex-border);
            background: var(--flex-card);
            position: sticky;
            top: 0;
            z-index: 2;
        }

        header .sub {
            margin: 4px 0 0;
            font-size: 14px;
            font-weight: 400;
            color: var(--flex-muted);
        }

        .wrap { padding: 24px 32px 48px; max-width: 980px; margin: 0 auto; box-sizing: border-box; }
        .row { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; margin: 0 0 24px; }
        .row .field-inline { flex: 0 1 auto; }
        .row-align-actions {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            min-height: 72px;
        }
        label {
            font-size: 14px;
            font-weight: 500;
            color: var(--flex-text);
            display: block;
            margin: 0 0 8px;
        }
        input, select, button { font: inherit; }
        input, select {
            height: 40px;
            padding: 0 16px;
            border: 1px solid var(--flex-border);
            border-radius: 4px;
            background: var(--flex-card);
            color: var(--flex-text);
            min-width: 200px;
            outline: 0;
            box-sizing: border-box;
            box-shadow: none;
            transition: border-color 0.2s cubic-bezier(0.645, 0.045, 0.355, 1);
        }
        input:focus, select:focus {
            border-color: var(--flex-primary);
            box-shadow: none;
        }
        .btn {
            display: inline-block;
            height: auto;
            min-height: 40px;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 400;
            line-height: 1.5;
            cursor: pointer;
            box-sizing: border-box;
            transition: color 0.2s, background-color 0.2s, border-color 0.2s;
        }
        .btn--primary {
            background-color: var(--flex-primary);
            border: 1px solid var(--flex-primary);
            color: #fff;
        }
        .btn--primary:hover:not(:disabled),
        .btn--primary:focus-visible:not(:disabled) {
            background-color: var(--amelia-primary-hover);
            border-color: var(--amelia-primary-hover);
            color: #fff;
        }
        .btn--secondary {
            background: var(--flex-card);
            border: 1px solid var(--flex-border);
            color: var(--flex-text);
        }
        .btn--secondary:hover:not(:disabled),
        .btn--secondary:focus-visible:not(:disabled) {
            color: var(--flex-primary);
            border-color: {{ $rgbaFromHex($primary, 0.1) }};
            background-color: {{ $rgbaFromHex($primary, 0.1) }};
        }
        button:disabled { opacity: 0.55; cursor: not-allowed; }
        .muted { color: var(--flex-muted); font-size: 14px; line-height: 1.35; }
        .card {
            border: 1px solid var(--flex-border);
            border-radius: 6px;
            background: var(--flex-card);
            padding: 20px 24px;
            margin: 0 0 16px;
            box-shadow: var(--flex-shadow);
        }
        .title {
            font-size: 16px;
            font-weight: 500;
            line-height: 1.5;
            color: var(--flex-text);
            margin: 0 0 8px;
        }
        .meta { font-size: 14px; color: var(--flex-text); line-height: 1.6; }
        .meta strong { font-weight: 500; color: var(--flex-muted); }
        .pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 500;
            line-height: 1.3;
            border: 1px solid var(--flex-border);
            background: {{ $rgbaFromHex($primary, 0.1) }};
            color: var(--flex-primary);
        }
        .right { float: right; }
        .card::after { content: ''; display: table; clear: both; }
        .banner {
            border: 1px solid var(--flex-border);
            background: var(--flex-primary-2);
            color: var(--flex-text);
            padding: 12px 16px;
            border-radius: 6px;
            margin: 0 0 16px;
            white-space: pre-wrap;
            font-size: 14px;
        }
        .banner--error {
            border-color: #f3b4b4;
            background: #fff4f4;
            color: #7a1212;
        }
        .banner--success {
            border-color: #b7e7d6;
            background: #f0fdf9;
            color: #0f5132;
        }
        dialog {
            border: 1px solid var(--flex-border);
            border-radius: 6px;
            padding: 0;
            width: min(640px, calc(100vw - 24px));
            box-shadow: 0 8px 24px rgba(53, 64, 82, 0.12);
            font-family: 'Roboto', system-ui, sans-serif;
        }
        dialog::backdrop { background: rgba(0, 0, 0, 0.35); }
        .dlg-head {
            padding: 20px 24px;
            border-bottom: 1px solid var(--flex-border);
            font-size: 16px;
            font-weight: 500;
            color: var(--flex-text);
            background: var(--flex-card);
        }
        .dlg-body { padding: 20px 24px 8px; }
        .dlg-foot {
            padding: 16px 24px 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            border-top: 1px solid var(--flex-border);
            background: var(--flex-card);
        }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 720px) { .grid { grid-template-columns: 1fr; } }
        .field { margin-bottom: 12px; }
        .field input { width: 100%; box-sizing: border-box; min-width: 0; }
        .card-actions { margin-top: 16px; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
    </style>
</head>
<body>
<div id="flexana-booking-wrap" class="amelia-booking amelia-frontend amelia-app-booking">
<header>
    <h1>Book a session</h1>
    <p class="sub">Powered by Flexana scheduling</p>
</header>

<div class="wrap">
    <div id="banner" class="banner" style="display:none;"></div>

    <div class="row">
        <div>
            <label for="date">Date</label>
            <input id="date" type="date">
        </div>
        <div>
            <label for="category">Category</label>
            <select id="category">
                <option value="Yoga">Yoga</option>
                <option value="Reformer Pilates">Reformer Pilates</option>
            </select>
        </div>
        <div class="field-inline row-align-actions">
            <button id="reload" type="button" class="btn btn--secondary">Refresh</button>
        </div>
    </div>

    <div id="sessions"></div>
</div>
</div>

<dialog id="bookDlg">
    <form method="dialog" id="bookForm">
        <div class="dlg-head">Confirm booking</div>
        <div class="dlg-body">
            <div class="muted" id="dlgSession"></div>
            <div class="grid" style="margin-top: 10px;">
                <div class="field">
                    <label for="firstName">First name</label>
                    <input id="firstName" autocomplete="given-name" required>
                </div>
                <div class="field">
                    <label for="lastName">Last name</label>
                    <input id="lastName" autocomplete="family-name" required>
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <label for="email">Email</label>
                    <input id="email" type="email" autocomplete="email" required>
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <label for="phone">Phone (optional)</label>
                    <input id="phone" autocomplete="tel">
                </div>
                <div class="field">
                    <label for="spots">Spots</label>
                    <input id="spots" type="number" min="1" max="20" value="1" required>
                </div>
                <div class="field">
                    <label for="promo">Promo code (optional)</label>
                    <input id="promo" maxlength="64">
                </div>
            </div>
        </div>
        <div class="dlg-foot">
            <button type="submit" class="btn btn--secondary" value="cancel" formnovalidate>Cancel</button>
            <button type="submit" id="confirmBook" class="btn btn--primary" value="default">Book</button>
        </div>
    </form>
</dialog>

<script>
(() => {
    const sessionsUrl = @json(url('/api/v1/web-sessions'));
    const bookingUrl = @json(url('/api/v1/web-session-bookings'));
    const paymobInitUrl = @json(url('/api/v1/web-session-paymob/init'));

    const banner = document.getElementById('banner');
    const sessionsEl = document.getElementById('sessions');
    const dateInput = document.getElementById('date');
    const categorySelect = document.getElementById('category');
    const reloadBtn = document.getElementById('reload');

    const dlg = document.getElementById('bookDlg');
    const dlgSession = document.getElementById('dlgSession');

    let selected = null;

    function showBanner(msg, kind = 'info') {
        banner.style.display = 'block';
        banner.textContent = msg;
        banner.classList.remove('banner--error', 'banner--success');
        if (kind === 'error') banner.classList.add('banner--error');
        if (kind === 'success') banner.classList.add('banner--success');
    }
    function hideBanner() {
        banner.style.display = 'none';
        banner.textContent = '';
        banner.classList.remove('banner--error', 'banner--success');
    }

    function formatApiError(status, json) {
        const parts = [];
        parts.push(`Request failed (HTTP ${status}).`);

        if (!json) {
            parts.push('No JSON body was returned.');
            return parts.join('\n');
        }

        if (json.message) {
            parts.push(`Message: ${json.message}`);
        }

        if (json.errors) {
            parts.push('Details:');
            try {
                parts.push(JSON.stringify(json.errors, null, 2));
            } catch {
                parts.push(String(json.errors));
            }
        } else {
            // Include compact payload for unknown shapes
            try {
                const clone = { ...json };
                parts.push(JSON.stringify(clone, null, 2));
            } catch {
                parts.push(String(json));
            }
        }

        return parts.join('\n');
    }

    function ymd(d) {
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function fmtWhen(iso) {
        try {
            const d = new Date(iso);
            return d.toLocaleString(undefined, { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
        } catch {
            return iso;
        }
    }

    async function loadSessions() {
        hideBanner();
        sessionsEl.innerHTML = '';
        reloadBtn.disabled = true;

        const params = new URLSearchParams();
        const date = dateInput.value;
        const category = categorySelect.value;
        if (date) params.set('date', date);
        if (category) params.set('category', category);

        const url = `${sessionsUrl}?${params.toString()}`;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const json = await res.json().catch(() => null);

        if (!res.ok) {
            showBanner(formatApiError(res.status, json), 'error');
            reloadBtn.disabled = false;
            return;
        }

        const items = Array.isArray(json?.data) ? json.data : [];
        if (items.length === 0) {
            sessionsEl.innerHTML = `<div class="muted">No sessions found for this filter.</div>`;
            reloadBtn.disabled = false;
            return;
        }

        for (const s of items) {
            const card = document.createElement('div');
            card.className = 'card';

            const canBook = !!s.canBook && !s.isFull;
            const spots = Number(s.remainingSpots ?? 0);
            const price = Number(s.price ?? 0);

            card.innerHTML = `
                <div class="pill right">${s.serviceType || ''}</div>
                <div class="title">${s.service || 'Session'}</div>
                <div class="meta">
                    <div><strong>When:</strong> ${fmtWhen(s.date)}</div>
                    <div><strong>Instructor:</strong> ${s.instructor || ''}</div>
                    <div><strong>Location:</strong> ${s.location_name || ''}</div>
                    <div><strong>Spots left:</strong> ${spots}</div>
                    <div><strong>Price (drop-in):</strong> ${price.toFixed(2)}</div>
                </div>
                <div class="card-actions">
                    <button type="button" class="bookBtn btn btn--primary" ${canBook ? '' : 'disabled'}>Book</button>
                </div>
            `;

            const btn = card.querySelector('.bookBtn');
            if (btn) {
                btn.addEventListener('click', () => openDialog(s));
            }

            sessionsEl.appendChild(card);
        }

        reloadBtn.disabled = false;
    }

    function openDialog(session) {
        selected = session;
        dlgSession.textContent = `${session.service} — ${fmtWhen(session.date)} — ${session.instructor || ''}`;
        document.getElementById('spots').value = '1';
        document.getElementById('promo').value = '';
        dlg.showModal();
    }

    document.getElementById('bookForm').addEventListener('submit', async (e) => {
        // dialog submit closes unless prevented; we handle async ourselves.
        e.preventDefault();

        if (!selected) {
            dlg.close();
            return;
        }

        const submitter = e.submitter;
        if (submitter && submitter.getAttribute('formnovalidate') !== null) {
            dlg.close();
            return;
        }

        hideBanner();
        const btn = document.getElementById('confirmBook');
        btn.disabled = true;

        const promoRaw = document.getElementById('promo').value.trim();
        const phoneRaw = document.getElementById('phone').value.trim();

        const payload = {
            sessionID: Number(selected.id),
            spots: Number(document.getElementById('spots').value || '1'),
            ...(promoRaw ? { promoCode: promoRaw } : {}),
            // Website embed is drop-in only (package bookings are handled in-app / admin flows).
            isDropIn: true,
            customer: {
                firstName: document.getElementById('firstName').value.trim(),
                lastName: document.getElementById('lastName').value.trim(),
                email: document.getElementById('email').value.trim(),
                ...(phoneRaw ? { phone: phoneRaw } : {}),
            },
        };

        // Drop-in bookings require payment first (Paymob). After success, user is redirected back here.
        const initRes = await fetch(paymobInitUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const initJson = await initRes.json().catch(() => null);
        btn.disabled = false;

        if (!initRes.ok) {
            showBanner(formatApiError(initRes.status, initJson), 'error');
            return;
        }

        const redirectUrl = initJson?.data?.redirectUrl;
        if (!redirectUrl) {
            showBanner('Payment initialization failed: missing redirect URL.', 'error');
            return;
        }

        dlg.close();
        // If embedded in WordPress via iframe, break out to top window for payment.
        // 3DS / card auth often fails inside nested/3rd-party iframes.
        try {
            if (window.top && window.top !== window) {
                window.top.location.href = redirectUrl;
                return;
            }
        } catch (e) {
            // ignore and fallback to same-frame navigation
        }
        window.location.href = redirectUrl;
    });

    reloadBtn.addEventListener('click', loadSessions);
    dateInput.addEventListener('change', loadSessions);
    categorySelect.addEventListener('change', loadSessions);

    // Default: today (browser local calendar). Users can change date.
    const today = new Date();
    dateInput.value = ymd(today);

    loadSessions();

    // Show payment status banner after returning from Paymob
    const qs = new URLSearchParams(window.location.search);
    const payment = qs.get('payment');
    const bookingId = qs.get('booking_id');
    if (payment === 'success') {
        showBanner(`Payment successful.${bookingId ? ` Booking ID: ${bookingId}` : ''}`, 'success');
    } else if (payment === 'failed') {
        showBanner('Payment was declined or cancelled, so no booking was created.\nIf your bank shows a deducted amount, it is usually a temporary hold/pre-authorization and will be reversed automatically (timing depends on the bank). If it is not reversed, please contact support with the transaction details.', 'error');
    }
})();
</script>
</body>
</html>
