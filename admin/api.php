<?php
// admin/api.php - REST API pro administraci KLIMAX
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sync.php';

// Zpracování akce
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Veřejné akce bez nutnosti přihlášení
if ($action === 'login') {
    $password = $_POST['password'] ?? '';
    if (admin_login($password)) {
        echo json_encode(['success' => true, 'message' => 'Přihlášení proběhlo úspěšně.']);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Nesprávné administrační heslo.']);
    }
    exit;
}

if ($action === 'logout') {
    admin_logout();
    echo json_encode(['success' => true, 'message' => 'Odhlášení úspěšné.']);
    exit;
}

if ($action === 'check_auth') {
    $isLoggedIn = is_admin_logged_in();
    $config = get_admin_config();
    echo json_encode([
        'success' => true,
        'authenticated' => $isLoggedIn,
        'site_title' => $config['site_title'] ?? 'KLIMAX Administrace',
        'last_sync' => $config['last_sync'] ?? null
    ]);
    exit;
}

// Pro všechny ostatní akce vyžadujeme přihlášení
if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Neautorizovaný přístup. Přihlaste se prosím.']);
    exit;
}

/**
 * Pomocná funkce pro bezpečný název souboru
 */
function sanitize_filename($filename) {
    $info = pathinfo($filename);
    $ext = strtolower($info['extension'] ?? '');
    $name = $info['filename'] ?? 'file';
    
    // Převod na ASCII slug
    $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($name));
    $name = preg_replace('/-+/', '-', $name);
    $name = trim($name, '-');
    if (empty($name)) {
        $name = 'file_' . time();
    }
    
    return $name . '.' . $ext;
}

/**
 * Formátování velikosti souboru v lidsky čitelném formátu
 */
function format_bytes($bytes, $precision = 1) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Bezpečně smaže soubor ze složky uploadů, pokud již není referencován v poli zbývajících položek
 */
function delete_unused_upload_file($filePath, $allowedDir, array $remainingItems, $itemField = 'image') {
    if (empty($filePath)) {
        return false;
    }

    // Normalizujeme cestu k souboru
    $normalizedTarget = ltrim(str_replace('\\', '/', $filePath), '/');
    $basename = basename($normalizedTarget);

    // Zkontrolujeme, zda stejný název souboru nebo cestu nepoužívá jiná existující položka
    foreach ($remainingItems as $item) {
        $itemPath = ltrim(str_replace('\\', '/', $item[$itemField] ?? ''), '/');
        if (!empty($itemPath)) {
            if ($itemPath === $normalizedTarget || basename($itemPath) === $basename) {
                // Soubor je stále používán jinou položkou, nemazat!
                return false;
            }
        }
    }

    // Zkontrolujeme, zda soubor leží ve složce $allowedDir
    $realAllowedDir = realpath($allowedDir);
    if (!$realAllowedDir) {
        return false;
    }

    $baseProjectDir = realpath(__DIR__ . '/..');
    $fullPath = $baseProjectDir ? ($baseProjectDir . '/' . $normalizedTarget) : null;

    // Pokud $fullPath neexistuje, zkusíme přímo v $allowedDir podle basename
    if (!$fullPath || !file_exists($fullPath)) {
        $fullPath = $realAllowedDir . '/' . $basename;
    }

    if (!file_exists($fullPath) || !is_file($fullPath)) {
        return false;
    }

    $realFilePath = realpath($fullPath);
    // Bezpečnostní pojistka: Cesta souboru MUSÍ začínat cestou povoleného adresáře uploadu
    if ($realFilePath && strpos($realFilePath, $realAllowedDir) === 0) {
        return @unlink($realFilePath);
    }

    return false;
}

/**
 * Optimalizace nahraného obrázku (zmenšení obřích rozměrů, komprese, odstranění metadat)
 */
function optimize_uploaded_image($filePath, $maxDimension = 1920, $jpegQuality = 85) {
    if (!file_exists($filePath) || !function_exists('getimagesize')) {
        return false;
    }

    $imageInfo = @getimagesize($filePath);
    if (!$imageInfo) {
        return false;
    }

    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $mime = $imageInfo['mime'] ?? '';

    // Podporujeme JPEG a PNG pro GD
    if ($mime !== 'image/jpeg' && $mime !== 'image/png') {
        return false;
    }

    $needsResize = ($width > $maxDimension || $height > $maxDimension);
    $filesize = @filesize($filePath);

    // Pokud je menší než maxDimension a velikost je rozumná (< 500 KB), není třeba reenkódovat
    if (!$needsResize && $filesize < 512 * 1024 && $mime === 'image/jpeg') {
        return false;
    }

    $src = null;
    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
        $src = @imagecreatefromjpeg($filePath);
    } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
        $src = @imagecreatefrompng($filePath);
    }

    if (!$src) {
        return false;
    }

    // Orientace podle EXIF (u fotek z mobilů)
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        try {
            $exif = @exif_read_data($filePath);
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $src = imagerotate($src, 180, 0);
                        break;
                    case 6:
                        $src = imagerotate($src, -90, 0);
                        $w = $width; $width = $height; $height = $w;
                        break;
                    case 8:
                        $src = imagerotate($src, 90, 0);
                        $w = $width; $width = $height; $height = $w;
                        break;
                }
            }
        } catch (Exception $e) {
            // Ignorovat případnou chybu v EXIF hlavičce
        }
    }

    $newWidth = $width;
    $newHeight = $height;

    if ($needsResize) {
        if ($width > $height) {
            $newWidth = $maxDimension;
            $newHeight = (int)round(($height / $width) * $maxDimension);
        } else {
            $newHeight = $maxDimension;
            $newWidth = (int)round(($width / $height) * $maxDimension);
        }
    }

    $dst = imagecreatetruecolor($newWidth, $newHeight);

    if ($mime === 'image/png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    if ($mime === 'image/jpeg') {
        imagejpeg($dst, $filePath, $jpegQuality);
    } elseif ($mime === 'image/png') {
        imagepng($dst, $filePath, 8);
    }

    imagedestroy($src);
    imagedestroy($dst);
    return true;
}

/**
 * Prohledá složky uploadů a smaže všechny soubory, které už nejsou přiřazeny v žádném JSONu
 */
function cleanup_unused_upload_files() {
    $realizace = file_exists(REALIZACE_JSON) ? (json_decode(file_get_contents(REALIZACE_JSON), true) ?: []) : [];
    $downloads = file_exists(DOWNLOADS_JSON) ? (json_decode(file_get_contents(DOWNLOADS_JSON), true) ?: []) : [];
    $brands = file_exists(BRANDS_JSON) ? (json_decode(file_get_contents(BRANDS_JSON), true) ?: []) : [];

    $activeRealFiles = [];
    foreach ($realizace as $r) {
        if (!empty($r['image'])) {
            $activeRealFiles[basename($r['image'])] = true;
        }
    }

    $activeDownloadFiles = [];
    foreach ($downloads as $d) {
        if (!empty($d['fileUrl'])) {
            $activeDownloadFiles[basename($d['fileUrl'])] = true;
        }
    }

    $activeBrandFiles = [];
    foreach ($brands as $b) {
        if (!empty($b['image'])) {
            $activeBrandFiles[basename($b['image'])] = true;
        }
    }

    $deletedCount = 0;
    $freedBytes = 0;
    $deletedFiles = [];

    // 1. UPLOADS_REALISATION_DIR
    if (is_dir(UPLOADS_REALISATION_DIR)) {
        $files = scandir(UPLOADS_REALISATION_DIR);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || $f === '.gitkeep' || $f === '.gitignore') continue;
            $path = UPLOADS_REALISATION_DIR . '/' . $f;
            if (is_file($path) && !isset($activeRealFiles[$f])) {
                $size = filesize($path);
                if (@unlink($path)) {
                    $deletedCount++;
                    $freedBytes += $size;
                    $deletedFiles[] = 'assets/realisation/' . $f;
                }
            }
        }
    }

    // 2. UPLOADS_DOWNLOADS_DIR
    if (is_dir(UPLOADS_DOWNLOADS_DIR)) {
        $files = scandir(UPLOADS_DOWNLOADS_DIR);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || $f === '.gitkeep' || $f === '.gitignore') continue;
            $path = UPLOADS_DOWNLOADS_DIR . '/' . $f;
            if (is_file($path) && !isset($activeDownloadFiles[$f])) {
                $size = filesize($path);
                if (@unlink($path)) {
                    $deletedCount++;
                    $freedBytes += $size;
                    $deletedFiles[] = 'assets/downloads/' . $f;
                }
            }
        }
    }

    // 3. UPLOADS_BRANDS_DIR
    if (is_dir(UPLOADS_BRANDS_DIR)) {
        $files = scandir(UPLOADS_BRANDS_DIR);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || $f === '.gitkeep' || $f === '.gitignore') continue;
            $path = UPLOADS_BRANDS_DIR . '/' . $f;
            if (is_file($path) && !isset($activeBrandFiles[$f])) {
                $size = filesize($path);
                if (@unlink($path)) {
                    $deletedCount++;
                    $freedBytes += $size;
                    $deletedFiles[] = 'assets/brands/' . $f;
                }
            }
        }
    }

    return [
        'count' => $deletedCount,
        'freedBytes' => $freedBytes,
        'freedFormatted' => format_bytes($freedBytes),
        'files' => $deletedFiles
    ];
}

// Obsluha chráněných akcí
try {
    switch ($action) {
        case 'get_data':
            $realizace = [];
            if (file_exists(REALIZACE_JSON)) {
                $realizace = json_decode(file_get_contents(REALIZACE_JSON), true) ?: [];
            }

            $downloads = [];
            if (file_exists(DOWNLOADS_JSON)) {
                $downloads = json_decode(file_get_contents(DOWNLOADS_JSON), true) ?: [];
            }

            $brands = [];
            if (file_exists(BRANDS_JSON)) {
                $brands = json_decode(file_get_contents(BRANDS_JSON), true) ?: [];
            }

            $config = get_admin_config();

            // Dostupné loga značek
            $availableLogos = [
                ['name' => 'Žádné logo', 'path' => ''],
                ['name' => 'Hisense', 'path' => 'assets/images/Hisense.webp'],
                ['name' => 'Toshiba', 'path' => 'assets/images/Toshiba.webp'],
                ['name' => 'Hokkaido', 'path' => 'assets/images/Hokkaido.webp'],
                ['name' => 'Multiwarm', 'path' => 'assets/images/Multiwarm.webp'],
                ['name' => 'Hisense HVAC', 'path' => 'assets/images/Hisense-HVAC.webp']
            ];

            echo json_encode([
                'success' => true,
                'realizace' => $realizace,
                'downloads' => $downloads,
                'brands' => $brands,
                'availableLogos' => $availableLogos,
                'config' => [
                    'site_title' => $config['site_title'] ?? 'KLIMAX Administrace',
                    'last_sync' => $config['last_sync'] ?? null
                ]
            ]);
            break;

        case 'save_realizace':
            $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
            $title = trim($_POST['title'] ?? '');
            $brand = trim(strtolower($_POST['brand'] ?? 'hisense'));
            
            // Automaticky přiřadíme popisek a logo podle vybrané značky (dle požadavku)
            $brandLabel = 'Využívá:';
            $brandLogoMap = [
                'hisense' => 'assets/images/Hisense.webp',
                'toshiba' => 'assets/images/Toshiba.webp',
                'hokkaido' => 'assets/images/Hokkaido.webp',
                'multiwarm' => 'assets/images/Multiwarm.webp'
            ];
            $brandLogo = $brandLogoMap[$brand] ?? '';
            $hasBrandLogo = !empty($brandLogo);
            
            $image = trim($_POST['image'] ?? '');
            $removeImage = !empty($_POST['remove_image']) && $_POST['remove_image'] === '1';

            if (empty($title)) {
                throw new Exception('Název realizace je povinný.');
            }

            // Načíst stávající realizace
            $items = file_exists(REALIZACE_JSON) ? (json_decode(file_get_contents(REALIZACE_JSON), true) ?: []) : [];

            // Zjistíme, zda upravujeme existující položku
            $existingItem = null;
            if ($id !== null && $id > 0) {
                foreach ($items as $it) {
                    if ((int)$it['id'] === $id) {
                        $existingItem = $it;
                        break;
                    }
                }
            }

            $newImageUploaded = false;
            // Zpracování uploadu obrázku
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['image_file'];
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowedExts)) {
                    throw new Exception('Nepovolený formát obrázku. Povolené formáty: ' . implode(', ', $allowedExts));
                }

                $cleanName = sanitize_filename($file['name']);
                $targetFile = UPLOADS_REALISATION_DIR . '/' . $cleanName;
                
                // Pokud už existuje, přidáme časové razítko
                if (file_exists($targetFile)) {
                    $cleanName = pathinfo($cleanName, PATHINFO_FILENAME) . '_' . time() . '.' . $ext;
                    $targetFile = UPLOADS_REALISATION_DIR . '/' . $cleanName;
                }

                if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
                    throw new Exception('Nahrávání souboru na server selhalo.');
                }
                @chmod($targetFile, 0666);

                // Optimalizace obrázku (zmenšení rozměrů a optimalizace kvality)
                optimize_uploaded_image($targetFile, 1920, 85);
                @chmod($targetFile, 0666);

                $image = 'assets/realisation/' . $cleanName;
                $newImageUploaded = true;
            }

            if ($removeImage) {
                $image = 'assets/images/gallery_office.png';
            } elseif (empty($image)) {
                $image = 'assets/images/gallery_office.png';
            }

            // Pokud se změnil / nahrál nový obrázek nebo byl odebrán, smažeme starý nahraný soubor z disku
            if ($existingItem && !empty($existingItem['image'])) {
                $oldImage = $existingItem['image'];
                if ($oldImage !== $image && ($newImageUploaded || $removeImage)) {
                    delete_unused_upload_file($oldImage, UPLOADS_REALISATION_DIR, $items, 'image');
                }
            }

            $savedItem = null;
            if ($id !== null && $id > 0) {
                // Úprava existujícího
                $found = false;
                foreach ($items as &$item) {
                    if ((int)$item['id'] === $id) {
                        $item['title'] = $title;
                        $item['brand'] = $brand;
                        $item['image'] = $image;
                        $item['hasBrandLogo'] = $hasBrandLogo;
                        $item['brandLogo'] = $brandLogo;
                        $item['brandLabel'] = $brandLabel;
                        $savedItem = $item;
                        $found = true;
                        break;
                    }
                }
                unset($item);
                if (!$found) {
                    throw new Exception('Položka s ID ' . $id . ' nebyla nalezena.');
                }
            } else {
                // Vytvoření nového
                $maxId = 0;
                foreach ($items as $item) {
                    if ((int)$item['id'] > $maxId) {
                        $maxId = (int)$item['id'];
                    }
                }
                $newId = $maxId + 1;
                $savedItem = [
                    'id' => $newId,
                    'title' => $title,
                    'brand' => $brand,
                    'image' => $image,
                    'hasBrandLogo' => $hasBrandLogo,
                    'brandLogo' => $brandLogo,
                    'brandLabel' => $brandLabel
                ];
                // Novou položku vložíme na začátek seznamu
                array_unshift($items, $savedItem);
            }

            // Uložit JSON
            file_put_contents(REALIZACE_JSON, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            @chmod(REALIZACE_JSON, 0666);

            // Automatická synchronizace HTML
            sync_realizace_html($items);

            echo json_encode([
                'success' => true,
                'message' => 'Realizace byla úspěšně uložena a stránka reference.html synchronizována.',
                'item' => $savedItem
            ]);
            break;

        case 'delete_realizace':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Neplatné ID realizace.');
            }

            $items = file_exists(REALIZACE_JSON) ? (json_decode(file_get_contents(REALIZACE_JSON), true) ?: []) : [];
            $filtered = [];
            $deletedItem = null;
            foreach ($items as $item) {
                if ((int)$item['id'] === $id) {
                    $deletedItem = $item;
                    continue;
                }
                $filtered[] = $item;
            }

            if (!$deletedItem) {
                throw new Exception('Realizace k odstranění nebyla nalezena.');
            }

            file_put_contents(REALIZACE_JSON, json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            @chmod(REALIZACE_JSON, 0666);
            sync_realizace_html($filtered);

            // Smazat nahraný soubor na disku ze složky assets/realisation, pokud již není používán jinou realizací
            if (!empty($deletedItem['image'])) {
                delete_unused_upload_file($deletedItem['image'], UPLOADS_REALISATION_DIR, $filtered, 'image');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Realizace byla úspěšně smazána a reference.html aktualizována.'
            ]);
            break;

        case 'reorder_realizace':
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                throw new Exception('Neplatná data pro změnu pořadí.');
            }

            $items = file_exists(REALIZACE_JSON) ? (json_decode(file_get_contents(REALIZACE_JSON), true) ?: []) : [];
            $indexed = [];
            foreach ($items as $item) {
                $indexed[(int)$item['id']] = $item;
            }

            $newItems = [];
            foreach ($ids as $id) {
                $id = (int)$id;
                if (isset($indexed[$id])) {
                    $newItems[] = $indexed[$id];
                    unset($indexed[$id]);
                }
            }
            // Zbytek položek, pokud nějaké nebyly v poli
            foreach ($indexed as $item) {
                $newItems[] = $item;
            }

            file_put_contents(REALIZACE_JSON, json_encode($newItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            sync_realizace_html($newItems);

            echo json_encode(['success' => true, 'message' => 'Pořadí realizací bylo uloženo.']);
            break;

        case 'save_download':
            $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
            $title = trim($_POST['title'] ?? '');
            $meta = trim($_POST['meta'] ?? '');
            $fileType = trim(strtolower($_POST['fileType'] ?? 'pdf'));
            $fileUrl = trim($_POST['fileUrl'] ?? '');
            $removeFile = !empty($_POST['remove_file']) && $_POST['remove_file'] === '1';

            if (empty($title)) {
                throw new Exception('Název souboru je povinný.');
            }

            $items = file_exists(DOWNLOADS_JSON) ? (json_decode(file_get_contents(DOWNLOADS_JSON), true) ?: []) : [];

            $existingItem = null;
            if ($id !== null && $id > 0) {
                foreach ($items as $it) {
                    if ((int)$it['id'] === $id) {
                        $existingItem = $it;
                        break;
                    }
                }
            }

            $newFileUploaded = false;
            // Zpracování uploadu souboru
            if (isset($_FILES['download_file']) && $_FILES['download_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['download_file'];
                $allowedExts = ['pdf', 'zip', 'rar', '7z', 'doc', 'docx', 'xls', 'xlsx', 'pptx', 'txt'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowedExts)) {
                    throw new Exception('Nepovolený formát souboru. Povolené formáty: ' . implode(', ', $allowedExts));
                }

                $cleanName = sanitize_filename($file['name']);
                $targetFile = UPLOADS_DOWNLOADS_DIR . '/' . $cleanName;

                if (file_exists($targetFile)) {
                    $cleanName = pathinfo($cleanName, PATHINFO_FILENAME) . '_' . time() . '.' . $ext;
                    $targetFile = UPLOADS_DOWNLOADS_DIR . '/' . $cleanName;
                }

                if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
                    throw new Exception('Nahrání souboru na server selhalo.');
                }
                @chmod($targetFile, 0666);

                $fileUrl = 'assets/downloads/' . $cleanName;
                $fileType = $ext;
                $newFileUploaded = true;

                // Automatický výpočet velikosti pro meta popisek, pokud nebyl zadán specificky
                $filesizeFormatted = format_bytes($file['size']);
                $meta = strtoupper($ext) . ', ' . $filesizeFormatted;
            }

            if ($removeFile) {
                $fileUrl = '';
            }

            // Smazání starého souboru ze složky downloads při nahrazení nebo odebrání
            if ($existingItem && !empty($existingItem['fileUrl'])) {
                $oldFile = $existingItem['fileUrl'];
                if ($oldFile !== $fileUrl && ($newFileUploaded || $removeFile)) {
                    delete_unused_upload_file($oldFile, UPLOADS_DOWNLOADS_DIR, $items, 'fileUrl');
                }
            }

            if (empty($meta)) {
                $meta = strtoupper($fileType ?: 'PDF');
            }

            $savedItem = null;
            if ($id !== null && $id > 0) {
                $found = false;
                foreach ($items as &$item) {
                    if ((int)$item['id'] === $id) {
                        $item['title'] = $title;
                        $item['meta'] = $meta;
                        $item['fileUrl'] = $fileUrl;
                        $item['fileType'] = $fileType;
                        $savedItem = $item;
                        $found = true;
                        break;
                    }
                }
                unset($item);
                if (!$found) {
                    throw new Exception('Soubor s ID ' . $id . ' nebyl nalezen.');
                }
            } else {
                $maxId = 0;
                foreach ($items as $item) {
                    if ((int)$item['id'] > $maxId) {
                        $maxId = (int)$item['id'];
                    }
                }
                $newId = $maxId + 1;
                $savedItem = [
                    'id' => $newId,
                    'title' => $title,
                    'meta' => $meta,
                    'fileUrl' => $fileUrl,
                    'fileType' => $fileType
                ];
                $items[] = $savedItem;
            }

            file_put_contents(DOWNLOADS_JSON, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            @chmod(DOWNLOADS_JSON, 0666);
            sync_downloads_html($items);

            echo json_encode([
                'success' => true,
                'message' => 'Soubor ke stažení byl úspěšně uložen a stránka pro-partnery.html synchronizována.',
                'item' => $savedItem
            ]);
            break;

        case 'delete_download':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Neplatné ID položky ke stažení.');
            }

            $items = file_exists(DOWNLOADS_JSON) ? (json_decode(file_get_contents(DOWNLOADS_JSON), true) ?: []) : [];
            $filtered = [];
            $deletedItem = null;
            foreach ($items as $item) {
                if ((int)$item['id'] === $id) {
                    $deletedItem = $item;
                    continue;
                }
                $filtered[] = $item;
            }

            if (!$deletedItem) {
                throw new Exception('Položka ke stažení k odstranění nebyla nalezena.');
            }

            file_put_contents(DOWNLOADS_JSON, json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            @chmod(DOWNLOADS_JSON, 0666);
            sync_downloads_html($filtered);

            // Smazat soubor ze složky assets/downloads na disku, pokud není používán jinou položkou
            if (!empty($deletedItem['fileUrl'])) {
                delete_unused_upload_file($deletedItem['fileUrl'], UPLOADS_DOWNLOADS_DIR, $filtered, 'fileUrl');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Soubor ke stažení byl úspěšně smazán a pro-partnery.html aktualizována.'
            ]);
            break;

        case 'reorder_downloads':
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                throw new Exception('Neplatná data pro změnu pořadí.');
            }

            $items = file_exists(DOWNLOADS_JSON) ? (json_decode(file_get_contents(DOWNLOADS_JSON), true) ?: []) : [];
            $indexed = [];
            foreach ($items as $item) {
                $indexed[(int)$item['id']] = $item;
            }

            $newItems = [];
            foreach ($ids as $id) {
                $id = (int)$id;
                if (isset($indexed[$id])) {
                    $newItems[] = $indexed[$id];
                    unset($indexed[$id]);
                }
            }
            foreach ($indexed as $item) {
                $newItems[] = $item;
            }

            file_put_contents(DOWNLOADS_JSON, json_encode($newItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            sync_downloads_html($newItems);

            echo json_encode(['success' => true, 'message' => 'Pořadí souborů ke stažení bylo uloženo.']);
            break;

        case 'save_brand':
            $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
            $name = trim($_POST['name'] ?? '');
            $image = trim($_POST['image'] ?? $_POST['imageUrl'] ?? '');
            $removeImage = !empty($_POST['remove_image']) && $_POST['remove_image'] === '1';

            if (empty($name)) {
                throw new Exception('Název značky je povinný.');
            }

            $items = file_exists(BRANDS_JSON) ? (json_decode(file_get_contents(BRANDS_JSON), true) ?: []) : [];

            $existingItem = null;
            if ($id !== null && $id > 0) {
                foreach ($items as $it) {
                    if ((int)$it['id'] === $id) {
                        $existingItem = $it;
                        break;
                    }
                }
            }

            $newImageUploaded = false;
            // Zpracování uploadu loga
            $brandUpload = $_FILES['brand_file'] ?? $_FILES['brand_image'] ?? null;
            if ($brandUpload && $brandUpload['error'] === UPLOAD_ERR_OK) {
                $file = $brandUpload;
                $allowedExts = ['webp', 'png', 'svg', 'jpg', 'jpeg', 'gif'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowedExts)) {
                    throw new Exception('Nepovolený formát obrázku. Povolené formáty: ' . implode(', ', $allowedExts));
                }

                $cleanName = sanitize_filename($file['name']);
                $targetFile = UPLOADS_BRANDS_DIR . '/' . $cleanName;

                if (file_exists($targetFile)) {
                    $cleanName = pathinfo($cleanName, PATHINFO_FILENAME) . '_' . time() . '.' . $ext;
                    $targetFile = UPLOADS_BRANDS_DIR . '/' . $cleanName;
                }

                if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
                    throw new Exception('Nahrávání loga značky na server selhalo.');
                }
                @chmod($targetFile, 0666);

                // Optimalizace loga značky (max 800px)
                optimize_uploaded_image($targetFile, 800, 90);
                @chmod($targetFile, 0666);

                $image = 'assets/brands/' . $cleanName;
                $newImageUploaded = true;
            }

            if ($removeImage) {
                $image = '';
            }

            if (empty($image)) {
                throw new Exception('Musíte nahrát logo nebo zadat cestu k obrázku.');
            }

            // Smazání starého loga ze složky assets/brands při nahrazení
            if ($existingItem && !empty($existingItem['image'])) {
                $oldImage = $existingItem['image'];
                if ($oldImage !== $image && $newImageUploaded) {
                    delete_unused_upload_file($oldImage, UPLOADS_BRANDS_DIR, $items, 'image');
                }
            }

            $savedItem = null;
            if ($id !== null && $id > 0) {
                $found = false;
                foreach ($items as &$item) {
                    if ((int)$item['id'] === $id) {
                        $item['name'] = $name;
                        $item['image'] = $image;
                        $savedItem = $item;
                        $found = true;
                        break;
                    }
                }
                unset($item);
                if (!$found) {
                    throw new Exception('Značka s ID ' . $id . ' nebyla nalezena.');
                }
            } else {
                $maxId = 0;
                foreach ($items as $item) {
                    if ((int)$item['id'] > $maxId) {
                        $maxId = (int)$item['id'];
                    }
                }
                $newId = $maxId + 1;
                $savedItem = [
                    'id' => $newId,
                    'name' => $name,
                    'image' => $image
                ];
                $items[] = $savedItem;
            }

            file_put_contents(BRANDS_JSON, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            @chmod(BRANDS_JSON, 0666);
            sync_brands_html($items);

            echo json_encode([
                'success' => true,
                'message' => 'Značka byla úspěšně uložena a carousel v index.html aktualizován.',
                'item' => $savedItem
            ]);
            break;

        case 'delete_brand':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Neplatné ID značky.');
            }

            $items = file_exists(BRANDS_JSON) ? (json_decode(file_get_contents(BRANDS_JSON), true) ?: []) : [];
            $filtered = [];
            $deletedItem = null;
            foreach ($items as $item) {
                if ((int)$item['id'] === $id) {
                    $deletedItem = $item;
                    continue;
                }
                $filtered[] = $item;
            }

            if (!$deletedItem) {
                throw new Exception('Značka k odstranění nebyla nalezena.');
            }

            file_put_contents(BRANDS_JSON, json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            @chmod(BRANDS_JSON, 0666);
            sync_brands_html($filtered);

            // Smazat nahrané logo ze složky assets/brands na disku, pokud již není používáno jinou značkou
            if (!empty($deletedItem['image'])) {
                delete_unused_upload_file($deletedItem['image'], UPLOADS_BRANDS_DIR, $filtered, 'image');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Značka byla úspěšně smazána a carousel v index.html aktualizován.'
            ]);
            break;

        case 'reorder_brands':
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                throw new Exception('Neplatná data pro změnu pořadí.');
            }

            $items = file_exists(BRANDS_JSON) ? (json_decode(file_get_contents(BRANDS_JSON), true) ?: []) : [];
            $indexed = [];
            foreach ($items as $item) {
                $indexed[(int)$item['id']] = $item;
            }

            $newItems = [];
            foreach ($ids as $id) {
                $id = (int)$id;
                if (isset($indexed[$id])) {
                    $newItems[] = $indexed[$id];
                    unset($indexed[$id]);
                }
            }
            foreach ($indexed as $item) {
                $newItems[] = $item;
            }

            file_put_contents(BRANDS_JSON, json_encode($newItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            sync_brands_html($newItems);

            echo json_encode(['success' => true, 'message' => 'Pořadí značek v carouselu bylo uloženo.']);
            break;

        case 'sync_all':
            if (sync_all()) {
                $cfg = get_admin_config();
                echo json_encode([
                    'success' => true,
                    'message' => 'Všechny HTML šablony (reference.html, pro-partnery.html i index.html) byly úspěšně vygenerovány.',
                    'last_sync' => $cfg['last_sync']
                ]);
            } else {
                throw new Exception('Při synchronizaci HTML došlo k chybě. Zkontrolujte oprávnění souborů.');
            }
            break;

        case 'change_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';

            if (empty($newPassword) || strlen($newPassword) < 6) {
                throw new Exception('Nové heslo musí mít alespoň 6 znaků.');
            }

            $config = get_admin_config();
            if (!password_verify($currentPassword, $config['password_hash'])) {
                throw new Exception('Stávající heslo není správné.');
            }

            $config['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            save_admin_config($config);

            echo json_encode([
                'success' => true,
                'message' => 'Administrační heslo bylo úspěšně změněno.'
            ]);
            break;

        case 'cleanup_orphaned_files':
            $result = cleanup_unused_upload_files();
            echo json_encode([
                'success' => true,
                'message' => $result['count'] > 0
                    ? ('Úspěšně vyčištěno ' . $result['count'] . ' nepoužívaných souborů (uvolněno ' . $result['freedFormatted'] . ').')
                    : 'Všechny složky jsou čisté, žádné nepotřebné soubory nebyly nalezeny.',
                'stats' => $result
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Neznámá akce: ' . htmlspecialchars($action)]);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
