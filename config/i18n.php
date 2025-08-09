<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Supported languages
$GLOBALS['SUPPORTED_LANGS'] = ['de', 'en', 'fr'];

function i18n_set_language_from_request(): void {
    $supported = $GLOBALS['SUPPORTED_LANGS'];

    // Priority: GET param -> Cookie -> Session -> Browser -> default
    $requested = isset($_GET['lang']) ? strtolower(trim($_GET['lang'])) : null;

    if ($requested && in_array($requested, $supported, true)) {
        $_SESSION['lang'] = $requested;
        setcookie('lang', $requested, time() + (365 * 24 * 60 * 60), '/');
    } elseif (empty($_SESSION['lang'])) {
        $cookieLang = isset($_COOKIE['lang']) ? strtolower(trim($_COOKIE['lang'])) : null;
        if ($cookieLang && in_array($cookieLang, $supported, true)) {
            $_SESSION['lang'] = $cookieLang;
        } else {
            // Try browser language
            $browser = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
            $pref = substr($browser, 0, 2);
            $_SESSION['lang'] = in_array($pref, $supported, true) ? $pref : 'de';
        }
    }
}

i18n_set_language_from_request();

function i18n_current_lang(): string {
    return $_SESSION['lang'] ?? 'de';
}

// Load dictionary
$GLOBALS['I18N_DICTIONARY'] = (function () {
    $lang = i18n_current_lang();
    $basePath = __DIR__ . '/../lang/';
    $file = $basePath . $lang . '.php';
    $fallback = $basePath . 'de.php';

    $dict = [];
    if (file_exists($fallback)) {
        $dict = include $fallback;
    }
    if ($lang !== 'de' && file_exists($file)) {
        // Overlay language-specific entries on top of German defaults
        $specific = include $file;
        if (is_array($specific)) {
            $dict = array_replace_recursive($dict, $specific);
        }
    }
    return is_array($dict) ? $dict : [];
})();

function __(string $key, array $replacements = []): string {
    $dict = $GLOBALS['I18N_DICTIONARY'] ?? [];
    $value = $dict[$key] ?? $key;
    if (!empty($replacements)) {
        foreach ($replacements as $placeholder => $replacement) {
            $value = str_replace('{' . $placeholder . '}', (string)$replacement, $value);
        }
    }
    return $value;
}

function i18n_url_with_lang(string $lang): string {
    $supported = $GLOBALS['SUPPORTED_LANGS'];
    if (!in_array($lang, $supported, true)) {
        $lang = 'de';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    $parts = parse_url($uri);
    $path = $parts['path'] ?? '/';
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query['lang'] = $lang;
    $qs = http_build_query($query);

    return $scheme . '://' . $host . $path . ($qs ? ('?' . $qs) : '');
}