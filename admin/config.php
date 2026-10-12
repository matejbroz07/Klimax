<?php
// admin/config.php - Konfigurace a autentizace administrace KLIMAX

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ADMIN_CONFIG_FILE', __DIR__ . '/../data/admin_config.json');
define('DATA_DIR', __DIR__ . '/../data');
define('REALIZACE_JSON', DATA_DIR . '/realizace.json');
define('DOWNLOADS_JSON', DATA_DIR . '/downloads.json');
define('BRANDS_JSON', DATA_DIR . '/brands.json');
define('UPLOADS_REALISATION_DIR', __DIR__ . '/../assets/realisation');
define('UPLOADS_DOWNLOADS_DIR', __DIR__ . '/../assets/downloads');
define('UPLOADS_BRANDS_DIR', __DIR__ . '/../assets/brands');

// Ujistíme se, že složky existují
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!is_dir(UPLOADS_REALISATION_DIR)) {
    mkdir(UPLOADS_REALISATION_DIR, 0755, true);
}
if (!is_dir(UPLOADS_DOWNLOADS_DIR)) {
    mkdir(UPLOADS_DOWNLOADS_DIR, 0755, true);
}
if (!is_dir(UPLOADS_BRANDS_DIR)) {
    mkdir(UPLOADS_BRANDS_DIR, 0755, true);
}

/**
 * Získat konfiguraci (včetně hashe hesla)
 */
function get_admin_config() {
    if (!file_exists(ADMIN_CONFIG_FILE)) {
        // Výchozí konfigurace: heslo "klimax1994"
        $defaultConfig = [
            'password_hash' => password_hash('klimax1994', PASSWORD_DEFAULT),
            'site_title' => 'KLIMAX Teplice - Správa obsahu',
            'last_sync' => null
        ];
        file_put_contents(ADMIN_CONFIG_FILE, json_encode($defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaultConfig;
    }
    $content = file_get_contents(ADMIN_CONFIG_FILE);
    $data = json_decode($content, true);
    if (!is_array($data)) {
        $data = [
            'password_hash' => password_hash('klimax1994', PASSWORD_DEFAULT),
            'site_title' => 'KLIMAX Teplice - Správa obsahu',
            'last_sync' => null
        ];
    }
    return $data;
}

/**
 * Uložit konfiguraci
 */
function save_admin_config($config) {
    return file_put_contents(ADMIN_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Kontrola přihlášení
 */
function is_admin_logged_in() {
    return !empty($_SESSION['klimax_admin_logged_in']);
}

/**
 * Ověření hesla a přihlášení
 */
function admin_login($password) {
    $config = get_admin_config();
    if (password_verify($password, $config['password_hash'])) {
        $_SESSION['klimax_admin_logged_in'] = true;
        $_SESSION['klimax_admin_login_time'] = time();
        return true;
    }
    return false;
}

/**
 * Odhlášení
 */
function admin_logout() {
    unset($_SESSION['klimax_admin_logged_in']);
    unset($_SESSION['klimax_admin_login_time']);
    session_destroy();
}
