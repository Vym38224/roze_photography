<?php

declare(strict_types=1);

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_set_cookie_params([
    'path' => '/',
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'");
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000');
}

$adminPasswordHash = getenv('PORTFOLIO_ADMIN_PASSWORD_HASH') ?: '';
if ($adminPasswordHash === '' && is_readable(__DIR__ . DIRECTORY_SEPARATOR . 'admin-config.php')) {
    $adminConfig = require __DIR__ . DIRECTORY_SEPARATOR . 'admin-config.php';
    $adminPasswordHash = is_array($adminConfig) ? (string) ($adminConfig['password_hash'] ?? '') : '';
}
if ($adminPasswordHash === '') {
    http_response_code(500);
    exit('Administrace nema nastaveny hash hesla.');
}
$maxLoginAttempts = 5;
$loginLockSeconds = 15 * 60;
$loginRateLimitFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'roze_portfolio_login_' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) . '.lock';
$categories = [
    'psi' => 'Psi',
    'kone' => 'Kone',
    'zavody' => 'Zavody',
];
$maxFileSize = 25 * 1024 * 1024;
$portfolioFile = __DIR__ . DIRECTORY_SEPARATOR . 'portfolio.html';
$portfolioMarker =                     '<!-- PORTFOLIO_ITEMS_END -->';
$message = '';
$messageType = '';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirectToSelf(): void
{
    header('Location: portfolio-admin.php');
    exit;
}

function canAttemptLogin(string $rateLimitFile, int $maxAttempts, int $lockSeconds): bool
{
    $handle = @fopen($rateLimitFile, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        return false;
    }

    rewind($handle);
    $state = trim(stream_get_contents($handle));
    [$attempts, $blockedUntil] = array_pad(array_map('intval', explode('|', $state)), 2, 0);
    if ($blockedUntil > time()) {
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }

    $attempts++;
    $blockedUntil = $attempts >= $maxAttempts ? time() + $lockSeconds : 0;
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, $attempts . '|' . $blockedUntil);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return true;
}

function clearLoginRateLimit(string $rateLimitFile): void
{
    @unlink($rateLimitFile);
}

if (isset($_POST['logout']) && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
    $_SESSION = [];
    session_destroy();
    redirectToSelf();
}

if (!isset($_SESSION['portfolio_admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $password = (string) ($_POST['password'] ?? '');

        if (!canAttemptLogin($loginRateLimitFile, $maxLoginAttempts, $loginLockSeconds)) {
            $message = 'Prilis mnoho neuspesnych pokusu. Zkus to znovu pozdeji.';
            $messageType = 'error';
        } elseif (
            (strpos($adminPasswordHash, 'sha256:') === 0 && hash_equals($adminPasswordHash, 'sha256:' . hash('sha256', $password)))
            || (strpos($adminPasswordHash, 'sha256:') !== 0 && password_verify($password, $adminPasswordHash))
        ) {
            session_regenerate_id(true);
            $_SESSION['portfolio_admin'] = true;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            clearLoginRateLimit($loginRateLimitFile);
            redirectToSelf();
        } else {
            $message = 'Neplatne heslo.';
            $messageType = 'error';
        }
    }
?>
    <!doctype html>
    <html lang="cs">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Portfolio administrace</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&display=swap">
        <style>
            :root {
                color-scheme: light;
                font-family: Arial, sans-serif;
                background: #f2f2f2;
                color: #131312;
            }

            body {
                min-height: 100vh;
                margin: 0;
                display: grid;
                place-items: center;
            }

            main {
                width: min(100% - 2rem, 28rem);
                padding: 2rem;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 12px;
                box-shadow: 0 12px 35px #00000012;
            }

            h1 {
                margin-top: 0;
                font-family: "Playfair Display", Georgia, serif;
                font-size: 2.25rem;
                font-weight: 600;
            }

            label {
                display: block;
                margin-bottom: .5rem;
                font-weight: 700;
            }

            input,
            button {
                width: 100%;
                padding: .8rem;
                box-sizing: border-box;
                font: inherit;
            }

            button {
                font-family: "Playfair Display", Georgia, serif;
                font-weight: 600;
            }

            input {
                margin-bottom: 1rem;
                border: 1px solid #aaa;
                border-radius: 6px;
            }

            button {
                border: 0;
                border-radius: 6px;
                background: #1d3a5a;
                color: #fff;
                cursor: pointer;
            }

            .message {
                padding: .75rem;
                margin-bottom: 1rem;
                border-radius: 6px;
                background: #f8d7da;
                color: #842029;
            }
        </style>
        <link rel="apple-touch-icon" sizes="180x180" href="assets/favicon_io/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="48x48" href="assets/favicon_io/favicon-48x48.png">
        <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon_io/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon_io/favicon-16x16.png">
    </head>

    <body>
        <main>
            <h1>Úprava portfolia</h1>
            <?php if ($message !== ''): ?><p class="message"><?= h($message) ?></p><?php endif; ?>
            <form method="post">
                <label for="password">Heslo</label>
                <input id="password" name="password" type="password" required autofocus>
                <button type="submit" name="login" value="1">Prihlasit</button>
            </form>
        </main>
    </body>

    </html>
<?php
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    $thumbUrl = (string) ($_POST['thumb_url'] ?? '');
    $fullUrl = (string) ($_POST['full_url'] ?? '');
    $caption = trim((string) ($_POST['caption'] ?? ''));
    $file = $_FILES['photo'] ?? null;
    if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $file = null;
    }
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!hash_equals((string) $_SESSION['csrf_token'], $csrfToken)) {
        $message = 'Platnost formulare vyprsela. Obnov stranku a zkus to znovu.';
        $messageType = 'error';
    } elseif ($caption === '' || strlen($caption) > 200) {
        $message = 'Vypln popisek do 200 znaku.';
        $messageType = 'error';
    } elseif (
        !preg_match('#^assets/thumbs/(psi|kone|zavody)/\1_(\d+(?:_[A-Za-z0-9-]+)*)\.webp$#', $thumbUrl, $thumbMatch)
        || !preg_match('#^assets/pictures/' . preg_quote($thumbMatch[1], '#') . '/' . preg_quote($thumbMatch[1], '#') . '_' . preg_quote($thumbMatch[2], '#') . '\.(jpg|jpeg|png|gif|webp)$#i', $fullUrl)
    ) {
        $message = 'Neplatna polozka pro upravu.';
        $messageType = 'error';
    } elseif ($file && $file['error'] !== UPLOAD_ERR_NO_FILE && $file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Novou fotografii se nepodarilo nahrat.';
        $messageType = 'error';
    } elseif ($file && $file['error'] === UPLOAD_ERR_OK && ((int) $file['size'] > $maxFileSize || !is_uploaded_file($file['tmp_name']))) {
        $message = 'Novy soubor je prilis velky nebo neplatny.';
        $messageType = 'error';
    } else {
        $portfolio = file_get_contents($portfolioFile);
        // Extrakt pouze číslo (bez suffixu) pro vyhledávání položky
        if (preg_match('#^(\d+)#', $thumbMatch[2], $numberMatch) !== 1) {
            $message = 'Neplatna polozka pro upravu.';
            $messageType = 'error';
        } else {
            $itemNumber = $numberMatch[1];
            $itemPattern = '~[ \t]*<div class="portfolio__item" data-category="' . preg_quote($thumbMatch[1], '~') . '">\s*'
                . '<img\s+src="assets/thumbs/' . preg_quote($thumbMatch[1], '~') . '/' . preg_quote($thumbMatch[1], '~') . '_' . preg_quote($itemNumber, '~') . '[^"]*\.webp"\s+data-full="assets/pictures/[^"]*".*?</div>\s*~s';

            if ($portfolio === false || preg_match($itemPattern, $portfolio, $itemMatch) !== 1) {
                $message = 'Polozku se nepodarilo najit v portfolio.html.';
                $messageType = 'error';
            } else {
                $newFullUrl = $fullUrl;
                $newThumbUrl = $thumbUrl;
                $source = null;
                $newOriginalPath = null;
                $newThumbPath = null;
                $temporaryOriginalPath = null;
                $temporaryThumbPath = null;

                if ($file && $file['error'] === UPLOAD_ERR_OK) {
                    if (!function_exists('imagewebp') || !function_exists('finfo_open') || !function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng') || !function_exists('imagecreatefromgif') || !function_exists('imagecreatefromwebp')) {
                        $message = 'Na serveru chybi PHP rozsireni GD nebo Fileinfo.';
                        $messageType = 'error';
                    } else {
                        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                        switch ($mime) {
                            case 'image/jpeg':
                                $source = imagecreatefromjpeg($file['tmp_name']);
                                break;
                            case 'image/png':
                                $source = imagecreatefrompng($file['tmp_name']);
                                break;
                            case 'image/gif':
                                $source = imagecreatefromgif($file['tmp_name']);
                                break;
                            case 'image/webp':
                                $source = imagecreatefromwebp($file['tmp_name']);
                                break;
                        }

                        if (!isset($allowedMimeTypes[$mime]) || !$source) {
                            $message = 'Povolene jsou pouze platne JPG, PNG, GIF a WebP soubory.';
                            $messageType = 'error';
                        } else {
                            $baseName = pathinfo($fullUrl, PATHINFO_FILENAME) . '_' . bin2hex(random_bytes(6));
                            $newFullUrl = 'assets/pictures/' . $thumbMatch[1] . '/' . $baseName . '.' . $allowedMimeTypes[$mime];
                            $newOriginalPath = __DIR__ . '/' . $newFullUrl;
                            $newThumbUrl = 'assets/thumbs/' . $thumbMatch[1] . '/' . $baseName . '.webp';
                            $newThumbPath = __DIR__ . '/' . $newThumbUrl;
                            $temporaryOriginalPath = $newOriginalPath . '.upload-' . bin2hex(random_bytes(8));
                            $temporaryThumbPath = $newThumbPath . '.upload-' . bin2hex(random_bytes(8));
                            $width = imagesx($source);
                            $height = imagesy($source);
                            $thumbWidth = min($width, 1200);
                            $thumbHeight = max(1, (int) round($height * ($thumbWidth / $width)));
                            $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
                            imagealphablending($thumb, false);
                            imagesavealpha($thumb, true);
                            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

                            if (!move_uploaded_file($file['tmp_name'], $temporaryOriginalPath) || !imagewebp($thumb, $temporaryThumbPath, 82)) {
                                @unlink($temporaryOriginalPath);
                                @unlink($temporaryThumbPath);
                                $message = 'Novou fotografii se nepodarilo ulozit.';
                                $messageType = 'error';
                            }
                            imagedestroy($thumb);
                        }
                        if ($source) {
                            imagedestroy($source);
                        }
                    }
                }

                if ($message === '') {
                    $updatedItem = preg_replace_callback('/data-caption="[^"]*"/', static function () use ($caption): string {
                        return 'data-caption="' . h($caption) . '"';
                    }, $itemMatch[0], 1);
                    if ($file && $file['error'] === UPLOAD_ERR_OK) {
                        $updatedItem = str_replace(
                            'src="' . $thumbUrl . '" data-full="' . $fullUrl . '"',
                            'src="' . h($newThumbUrl) . '" data-full="' . h($newFullUrl) . '"',
                            $updatedItem
                        );
                    }
                    $updatedPortfolio = preg_replace_callback($itemPattern, static function () use ($updatedItem): string {
                        return $updatedItem;
                    }, $portfolio, 1);
                    if ($updatedPortfolio === null || file_put_contents($portfolioFile, $updatedPortfolio, LOCK_EX) === false) {
                        $message = 'Polozku se nepodarilo upravit v portfolio.html.';
                        $messageType = 'error';
                    } elseif (
                        $file && $file['error'] === UPLOAD_ERR_OK
                        && (!copy($temporaryOriginalPath, $newOriginalPath) || !copy($temporaryThumbPath, $newThumbPath))
                    ) {
                        file_put_contents($portfolioFile, $portfolio, LOCK_EX);
                        @unlink($temporaryOriginalPath);
                        @unlink($temporaryThumbPath);
                        $message = 'Novou fotografii se nepodarilo prepsat do ciloveho souboru.';
                        $messageType = 'error';
                    } else {
                        if ($file && $file['error'] === UPLOAD_ERR_OK && $newFullUrl !== $fullUrl) {
                            @unlink(__DIR__ . '/' . $fullUrl);
                            @unlink(__DIR__ . '/' . $thumbUrl);
                        }
                        $message = 'Polozka byla upravena.';
                        $messageType = 'success';
                    }
                }
                if ($temporaryOriginalPath !== null) {
                    @unlink($temporaryOriginalPath);
                }
                if ($temporaryThumbPath !== null) {
                    @unlink($temporaryThumbPath);
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    $thumbUrl = (string) ($_POST['thumb_url'] ?? '');
    $fullUrl = (string) ($_POST['full_url'] ?? '');

    if (!hash_equals((string) $_SESSION['csrf_token'], $csrfToken)) {
        $message = 'Platnost formulare vyprsela. Obnov stranku a zkus to znovu.';
        $messageType = 'error';
    } elseif (
        !preg_match('#^assets/thumbs/(psi|kone|zavody)/\1_(\d+(?:_[A-Za-z0-9-]+)*)\.webp$#', $thumbUrl, $thumbMatch)
        || !preg_match('#^assets/pictures/' . preg_quote($thumbMatch[1], '#') . '/' . preg_quote($thumbMatch[1], '#') . '_' . preg_quote($thumbMatch[2], '#') . '\.(jpg|jpeg|png|gif|webp)$#i', $fullUrl)
    ) {
        $message = 'Neplatna polozka pro smazani.';
        $messageType = 'error';
    } else {
        $portfolio = file_get_contents($portfolioFile);
        $itemPattern = '~[ \t]*<div class="portfolio__item" data-category="' . preg_quote($thumbMatch[1], '~') . '">\s*'
            . '<img\s+src="' . preg_quote($thumbUrl, '~') . '"\s+data-full="' . preg_quote($fullUrl, '~') . '".*?</div>\s*~s';

        if ($portfolio === false || preg_match($itemPattern, $portfolio) !== 1) {
            $message = 'Polozku se nepodarilo najit v portfolio.html.';
            $messageType = 'error';
        } elseif (file_put_contents($portfolioFile, preg_replace($itemPattern, '', $portfolio, 1), LOCK_EX) === false) {
            $message = 'Polozku se nepodarilo odstranit z portfolio.html.';
            $messageType = 'error';
        } else {
            @unlink(__DIR__ . '/' . $thumbUrl);
            @unlink(__DIR__ . '/' . $fullUrl);
            $message = 'Fotografie byla smazana z portfolia.';
            $messageType = 'success';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $csrfToken)) {
        $message = 'Platnost formulare vyprsela. Obnov stranku a zkus to znovu.';
        $messageType = 'error';
    } else {
        $category = (string) ($_POST['category'] ?? '');
        $caption = trim((string) ($_POST['caption'] ?? ''));
        $file = $_FILES['photo'] ?? null;
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        if (!isset($categories[$category])) {
            $message = 'Vyber platnou kategorii.';
            $messageType = 'error';
        } elseif ($caption === '' || strlen($caption) > 200) {
            $message = 'Vypln popisek do 200 znaku.';
            $messageType = 'error';
        } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $message = 'Fotografii se nepodarilo nahrat.';
            $messageType = 'error';
        } elseif ((int) $file['size'] > $maxFileSize || !is_uploaded_file($file['tmp_name'])) {
            $message = 'Soubor je prilis velky nebo neplatny. Maximum je 25 MB.';
            $messageType = 'error';
        } elseif (!function_exists('imagewebp') || !function_exists('finfo_open') || !function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng') || !function_exists('imagecreatefromgif') || !function_exists('imagecreatefromwebp')) {
            $message = 'Na serveru chybi PHP rozsireni GD nebo Fileinfo.';
            $messageType = 'error';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!isset($allowedMimeTypes[$mime])) {
                $message = 'Povolene jsou pouze JPG, PNG, GIF a WebP.';
                $messageType = 'error';
            } else {
                $source = null;
                switch ($mime) {
                    case 'image/jpeg':
                        $source = imagecreatefromjpeg($file['tmp_name']);
                        break;
                    case 'image/png':
                        $source = imagecreatefrompng($file['tmp_name']);
                        break;
                    case 'image/gif':
                        $source = imagecreatefromgif($file['tmp_name']);
                        break;
                    case 'image/webp':
                        $source = imagecreatefromwebp($file['tmp_name']);
                        break;
                }

                if (!$source) {
                    $message = 'Soubor neni platny obrazek.';
                    $messageType = 'error';
                } else {
                    $pictureDirectory = __DIR__ . '/assets/pictures/' . $category;
                    $thumbDirectory = __DIR__ . '/assets/thumbs/' . $category;
                    $number = 1;
                    $existingFiles = glob($pictureDirectory . '/' . $category . '_*.*') ?: [];
                    foreach ($existingFiles as $existingFile) {
                        if (preg_match('/^' . preg_quote($category, '/') . '_(\d+)\.[a-z0-9]+$/i', basename($existingFile), $match)) {
                            $number = max($number, (int) $match[1] + 1);
                        }
                    }
                    while (file_exists($pictureDirectory . '/' . $category . '_' . $number . '.' . $allowedMimeTypes[$mime]) || file_exists($thumbDirectory . '/' . $category . '_' . $number . '.webp')) {
                        $number++;
                    }

                    $baseName = $category . '_' . $number . '_' . bin2hex(random_bytes(6));
                    $originalPath = $pictureDirectory . '/' . $baseName . '.' . $allowedMimeTypes[$mime];
                    $thumbPath = $thumbDirectory . '/' . $baseName . '.webp';
                    $originalUrl = 'assets/pictures/' . $category . '/' . basename($originalPath);
                    $thumbUrl = 'assets/thumbs/' . $category . '/' . basename($thumbPath);

                    $width = imagesx($source);
                    $height = imagesy($source);
                    $maxWidth = 1200;
                    $thumbWidth = min($width, $maxWidth);
                    $thumbHeight = max(1, (int) round($height * ($thumbWidth / $width)));
                    $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
                    imagealphablending($thumb, false);
                    imagesavealpha($thumb, true);
                    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

                    if (!move_uploaded_file($file['tmp_name'], $originalPath) || !imagewebp($thumb, $thumbPath, 82)) {
                        @unlink($originalPath);
                        @unlink($thumbPath);
                        $message = 'Fotografii se nepodarilo ulozit.';
                        $messageType = 'error';
                    } else {
                        $html = "                    <div class=\"portfolio__item\" data-category=\"" . h($category) . "\">\n";
                        $html .= "                        <img src=\"" . h($thumbUrl) . "\" data-full=\"" . h($originalUrl) . "\" alt=\"" . h($categories[$category] . ' ' . $number) . "\"\n";
                        $html .= "                            data-caption=\"" . h($caption) . "\" loading=\"lazy\">\n";
                        $html .= "                    </div>\n";

                        $portfolio = file_get_contents($portfolioFile);
                        if ($portfolio === false || substr_count($portfolio, $portfolioMarker) !== 1) {
                            @unlink($originalPath);
                            @unlink($thumbPath);
                            $message = 'V portfolio.html chybi jednoznacny vkladaci marker.';
                            $messageType = 'error';
                        } else {
                            $updatedPortfolio = str_replace($portfolioMarker, $html . $portfolioMarker, $portfolio, $replaceCount);
                            if ($replaceCount !== 1 || file_put_contents($portfolioFile, $updatedPortfolio, LOCK_EX) === false) {
                                @unlink($originalPath);
                                @unlink($thumbPath);
                                $message = 'Polozku se nepodarilo zapsat do portfolio.html.';
                                $messageType = 'error';
                            } else {
                                $message = 'Fotografie byla pridana do kategorie ' . $categories[$category] . '.';
                                $messageType = 'success';
                            }
                        }
                    }
                    imagedestroy($thumb);
                    imagedestroy($source);
                }
            }
        }
    }
}

$portfolioItems = [];
$portfolio = file_get_contents($portfolioFile);
if ($portfolio !== false) {
    $matches = [];
    $matchResult = preg_match_all(
        '~<div class="portfolio__item" data-category="(psi|kone|zavody)">\s*'
            . '<img src="(assets/thumbs/(?:psi|kone|zavody)/[^"]+\.webp)" data-full="(assets/pictures/(?:psi|kone|zavody)/[^"]+)"'
            . '[^>]*data-caption="([^"]*)"[^>]*>\s*</div>~',
        $portfolio,
        $matches,
        PREG_SET_ORDER
    );
    if ($matchResult !== false) {
        foreach ($matches as $match) {
            $portfolioItems[] = [
                'category' => $match[1],
                'thumb' => $match[2],
                'full' => $match[3],
                'caption' => html_entity_decode($match[4], ENT_QUOTES, 'UTF-8'),
            ];
        }
    }
}
?>
<!doctype html>
<html lang="cs">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portfolio administrace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@500;600&display=swap">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        :root {
            font-family: "Montserrat", sans-serif;
            background: #f2f2f2;
            color: #131312;
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 2rem 1rem;
            overflow-x: hidden;
        }

        main {
            width: min(100%, 44rem);
            max-width: 100%;
            margin: 0 auto;
            padding: 2rem;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 12px;
            box-shadow: 0 12px 35px #00000012;
        }

        h1 {
            margin-top: 0;
            font-family: "Playfair Display", Georgia, serif;
            font-size: 2.25rem;
            font-weight: 600;
        }

        label {
            display: block;
            margin: 1rem 0 .4rem;
            font-weight: 700;
        }

        input,
        select,
        button {
            width: 100%;
            padding: .8rem;
            box-sizing: border-box;
            font: inherit;
        }

        input,
        select {
            border: 1px solid #aaa;
            border-radius: 6px;
        }

        input[type=file] {
            padding: .55rem;
        }

        .file-picker {
            display: flex;
            align-items: center;
            gap: .6rem;
            min-width: 0;
        }

        .file-picker input[type=file] {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .file-picker__button {
            display: inline-block;
            width: auto;
            margin: 0;
            padding: .55rem .8rem;
            border-radius: 6px;
            background: #555;
            color: #fff;
            cursor: pointer;
            font-family: "Playfair Display", Georgia, serif;
            font-weight: 600;
        }

        .file-picker__status {
            color: #555;
            font-size: .85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        button {
            margin-top: 1.25rem;
            border: 0;
            border-radius: 6px;
            background: #1d3a5a;
            color: #fff;
            cursor: pointer;
            font-family: "Playfair Display", Georgia, serif;
            font-weight: 600;
        }

        .logout {
            width: auto;
            float: right;
            margin: 0;
            padding: .55rem .9rem;
            background: #555;
        }

        .message {
            padding: .75rem;
            border-radius: 6px;
        }

        .success {
            background: #d1e7dd;
            color: #0f5132;
        }

        .error {
            background: #f8d7da;
            color: #842029;
        }

        .hint {
            color: #555;
            font-size: .9rem;
        }

        h2 {
            margin: 2.5rem 0 1rem;
            font-family: "Playfair Display", Georgia, serif;
            font-weight: 600;
        }

        .portfolio-list {
            display: grid;
            gap: .75rem;
        }

        .portfolio-list__item {
            display: grid;
            grid-template-columns: 5rem minmax(4rem, auto) minmax(0, 1fr) auto;
            align-items: center;
            gap: .8rem;
            padding: .6rem;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .portfolio-list__item img {
            width: 5rem;
            height: 5rem;
            object-fit: cover;
            border-radius: 4px;
        }

        .portfolio-list__item strong,
        .portfolio-list__item span {
            display: block;
        }

        .portfolio-list__item span {
            color: #555;
            font-size: .9rem;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .portfolio-list__item strong {
            color: #1d3a5a;
            font-size: .9rem;
            white-space: nowrap;
        }

        .portfolio-list__item form {
            margin: 0;
        }

        .portfolio-actions {
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .portfolio-actions form {
            margin: 0;
        }

        .portfolio-actions button {
            width: auto;
            margin: 0;
            padding: .6rem .8rem;
        }

        .portfolio-list__item .edit-form {
            display: grid;
            grid-column: 1 / -1;
            grid-template-columns: 1fr 1fr auto;
            gap: .6rem;
            align-items: end;
            padding-top: .5rem;
            border-top: 1px solid #eee;
        }

        .portfolio-list__item .edit-form label {
            display: block;
            margin: 0 0 .25rem;
            font-size: .8rem;
        }

        .portfolio-list__item .edit-form input {
            width: 100%;
            margin: 0;
        }

        .portfolio-list__item .edit-form button {
            width: auto;
            margin: 0;
            padding: .6rem .8rem;
        }

        .portfolio-list__item .edit-form[hidden] {
            display: none;
        }

        .portfolio-list__item .delete {
            width: auto;
            margin: 0;
            padding: .6rem .8rem;
            background: #842029;
        }

        @media (max-width: 560px) {
            body {
                padding: 1rem .5rem;
            }

            main {
                padding: 1rem;
                border-radius: 8px;
            }

            h1 {
                font-size: 1.8rem;
            }

            h2 {
                font-size: 1.35rem;
            }

            .portfolio-list__item {
                grid-template-columns: 3.5rem minmax(3rem, auto) minmax(0, 1fr) auto;
                gap: .45rem;
                padding: .45rem;
            }

            .portfolio-list__item img {
                width: 3.5rem;
                height: 3.5rem;
            }

            .portfolio-list__item strong,
            .portfolio-list__item span {
                font-size: .75rem;
            }

            .portfolio-actions {
                gap: .25rem;
            }

            .portfolio-actions button {
                padding: .45rem .5rem;
                font-size: .75rem;
            }

            .portfolio-list__item .edit-form {
                grid-column: 1 / -1;
                grid-template-columns: 1fr;
            }

            .portfolio-list__item .edit-form button {
                width: 100%;
            }

            .portfolio-list__item .delete {
                width: auto;
                padding: .45rem .5rem;
                font-size: .75rem;
            }

            .file-picker {
                flex-wrap: wrap;
            }

            .file-picker__status {
                max-width: 100%;
            }

            .admin-portfolio-filter {
                gap: .35rem;
                margin-bottom: 1rem;
            }

            .admin-portfolio-filter button {
                padding: .55rem .7rem;
                font-size: .75rem;
            }
        }

        /* Portfolio filter styles */
        .admin-portfolio-filter {
            display: flex;
            flex-wrap: nowrap;
            justify-content: flex-start;
            width: max-content;
            max-width: 100%;
            margin-left: 0;
            margin-right: auto;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
            margin-top: 1rem;
            overflow-x: auto;
        }

        .admin-portfolio-filter button {
            font-family: "Playfair Display", Georgia, serif;
            font-weight: 600;
            font-size: .8rem;
            background: #ffffff;
            border: 1px solid #d8d8d8;
            color: #131312;
            padding: 0.85rem 1.2rem;
            border-radius: 20px;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }

        .admin-portfolio-filter button.is-active {
            background: #1d3a5a;
            color: #ffffff;
            border-color: #1d3a5a;
        }

        .admin-portfolio-filter button:hover {
            border-color: #1d3a5a;
        }

        .portfolio-list[data-filter] .portfolio-list__item {
            display: grid;
        }

        .portfolio-list[data-filter="psi"] .portfolio-list__item:not([data-category="psi"]),
        .portfolio-list[data-filter="kone"] .portfolio-list__item:not([data-category="kone"]),
        .portfolio-list[data-filter="zavody"] .portfolio-list__item:not([data-category="zavody"]) {
            display: none;
        }
    </style>
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="48x48" href="assets/favicon_io/favicon-48x48.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon_io/favicon-16x16.png">
</head>

<body>
    <main>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['csrf_token']) ?>">
            <button class="logout" type="submit" name="logout" value="1">Odhlásit</button>
        </form>
        <h1>Úprava portfolia</h1>
        <?php if ($message !== ''): ?><p class="message <?= h($messageType) ?>"><?= h($message) ?></p><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['csrf_token']) ?>">
            <label for="category">Kategorie</label>
            <select id="category" name="category" required>
                <?php foreach ($categories as $value => $label): ?><option value="<?= h($value) ?>"><?= h($label) ?></option><?php endforeach; ?>
            </select>
            <label for="caption">Popisek fotografie</label>
            <input id="caption" name="caption" type="text" maxlength="200" required>
            <label for="photo">Fotografie</label>
            <div class="file-picker">
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/gif,image/webp" required>
                <label class="file-picker__button" for="photo">Vybrat fotografii</label>
                <span class="file-picker__status">Soubor nevybrán</span>
            </div>
            <p class="hint">Povolené formáty: JPG, PNG, GIF.</p>
            <button type="submit" name="upload" value="1">Nahrát do portfolia</button>
        </form>

        <h2>Fotografie v portfoliu</h2>
        <?php if (!$portfolioItems): ?>
            <p class="hint">Zatim zde nejsou zadne fotografie.</p>
        <?php else: ?>
            <div class="admin-portfolio-filter">
                <button type="button" class="is-active" data-filter="all">Vše</button>
                <button type="button" data-filter="psi">Psi</button>
                <button type="button" data-filter="kone">Koně</button>
                <button type="button" data-filter="zavody">Závody</button>
            </div>
            <div class="portfolio-list" data-filter="all">
                <?php foreach ($portfolioItems as $item): ?>
                    <div class="portfolio-list__item" data-category="<?= h($item['category']) ?>">
                        <img src="<?= h($item['thumb']) ?>" alt="<?= h($item['caption']) ?>" loading="lazy">
                        <strong><?= h($categories[$item['category']]) ?></strong>
                        <span><?= h($item['caption']) ?></span>
                        <div class="portfolio-actions">
                            <button class="edit-toggle" type="button" data-edit-target="edit-<?= h(md5($item['thumb'])) ?>">Upravit</button>
                            <form method="post" onsubmit="return confirm('Opravdu odstranit tuto fotografii?');">
                                <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="thumb_url" value="<?= h($item['thumb']) ?>">
                                <input type="hidden" name="full_url" value="<?= h($item['full']) ?>">
                                <button class="delete" type="submit" name="delete" value="1">Odstranit</button>
                            </form>
                        </div>
                        <form id="edit-<?= h(md5($item['thumb'])) ?>" class="edit-form" method="post" enctype="multipart/form-data" hidden>
                            <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="thumb_url" value="<?= h($item['thumb']) ?>">
                            <input type="hidden" name="full_url" value="<?= h($item['full']) ?>">
                            <div>
                                <label for="caption-<?= h(md5($item['thumb'])) ?>">Popisek</label>
                                <input id="caption-<?= h(md5($item['thumb'])) ?>" name="caption" type="text" maxlength="200" value="<?= h($item['caption']) ?>" required>
                            </div>
                            <div>
                                <label for="photo-<?= h(md5($item['thumb'])) ?>">Nová fotografie (volitelné)</label>
                                <div class="file-picker">
                                    <input id="photo-<?= h(md5($item['thumb'])) ?>" name="photo" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
                                    <label class="file-picker__button" for="photo-<?= h(md5($item['thumb'])) ?>">Vybrat fotografii</label>
                                    <span class="file-picker__status">nevybrána</span>
                                </div>
                            </div>
                            <button type="submit" name="edit" value="1">Uložit úpravy</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <script>
        // Edit toggle functionality
        document.querySelectorAll('.edit-toggle').forEach(function(button) {
            button.addEventListener('click', function() {
                var form = document.getElementById(button.dataset.editTarget);
                if (!form) return;
                form.hidden = !form.hidden;
                button.textContent = form.hidden ? 'Upravit' : 'Zrušit úpravy';
                if (!form.hidden) form.querySelector('input[name="caption"]').focus();
            });
        });

        document.querySelectorAll('.file-picker input[type="file"]').forEach(function(input) {
            input.addEventListener('change', function() {
                var status = input.parentElement.querySelector('.file-picker__status');
                if (!status) return;
                status.textContent = input.files.length ? input.files[0].name : 'Nevybrán';
            });
        });

        // Portfolio filter functionality
        const filterButtons = document.querySelectorAll('.admin-portfolio-filter button');
        const portfolioList = document.querySelector('.portfolio-list');

        filterButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const filter = button.getAttribute('data-filter');

                // Update active state
                filterButtons.forEach(function(btn) {
                    btn.classList.remove('is-active');
                });
                button.classList.add('is-active');

                // Update list data-filter attribute
                if (portfolioList) {
                    portfolioList.setAttribute('data-filter', filter);
                }
            });
        });
    </script>

</body>

</html>