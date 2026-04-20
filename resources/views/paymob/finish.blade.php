<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirecting…</title>
</head>
<body>
<noscript>
    <p>Payment completed. Continue here: <a href="{{ $targetUrl }}">{{ $targetUrl }}</a></p>
</noscript>
<script>
(() => {
    const target = @json($targetUrl);
    // Break out of nested iframes (WordPress embed → booking iframe → Paymob redirect).
    // Using replace avoids polluting browser history.
    try {
        if (window.top && window.top !== window) {
            window.top.location.replace(target);
            return;
        }
    } catch (e) {
        // Cross-origin access to top can throw; fallback to normal navigation.
    }
    window.location.replace(target);
})();
</script>
</body>
</html>

