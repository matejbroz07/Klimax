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

            if (empty($title)) {
                throw new Exception('Název realizace je povinný.');
            }

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

                $image = 'assets/realisation/' . $cleanName;
            }

            if (empty($image)) {
                $image = 'assets/images/gallery_office.png';
            }

            // Načíst stávající realizace
            $items = file_exists(REALIZACE_JSON) ? (json_decode(file_get_contents(REALIZACE_JSON), true) ?: []) : [];

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
            $found = false;
            foreach ($items as $item) {
                if ((int)$item['id'] === $id) {
                    $found = true;
                    // Poznámka: soubor na disku nemažeme automaticky, aby nedošlo k poškození odkazů
                    continue;
                }
                $filtered[] = $item;
            }

            if (!$found) {
                throw new Exception('Realizace k odstranění nebyla nalezena.');
            }

            file_put_contents(REALIZACE_JSON, json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            sync_realizace_html($filtered);

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

            if (empty($title)) {
                throw new Exception('Název souboru je povinný.');
            }

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

                $fileUrl = 'assets/downloads/' . $cleanName;
                $fileType = $ext;

                // Automatický výpočet velikosti pro meta popisek, pokud nebyl zadán specificky
                $filesizeFormatted = format_bytes($file['size']);
                $meta = strtoupper($ext) . ', ' . $filesizeFormatted;
            }

            if (empty($meta)) {
                $meta = strtoupper($fileType ?: 'PDF');
            }

            $items = file_exists(DOWNLOADS_JSON) ? (json_decode(file_get_contents(DOWNLOADS_JSON), true) ?: []) : [];

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
            $found = false;
            foreach ($items as $item) {
                if ((int)$item['id'] === $id) {
                    $found = true;
                    // Pokud soubor existuje ve složce downloads, můžeme ho volitelně smazat nebo zachovat
                    continue;
                }
                $filtered[] = $item;
            }

            if (!$found) {
                throw new Exception('Položka ke stažení k odstranění nebyla nalezena.');
            }

            file_put_contents(DOWNLOADS_JSON, json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            sync_downloads_html($filtered);

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

        case 'sync_all':
            if (sync_all()) {
                $cfg = get_admin_config();
                echo json_encode([
                    'success' => true,
                    'message' => 'Všechny HTML šablony (reference.html i pro-partnery.html) byly úspěšně vygenerovány.',
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
