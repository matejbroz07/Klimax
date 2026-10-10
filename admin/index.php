<?php
// admin/index.php - Hlavní rozhraní administrace KLIMAX
require_once __DIR__ . '/config.php';

// Zajistíme, aby URL končilo lomítkem / pokud jde o přístup přes složku /admin bez lomítka
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '';
if (!empty($path) && substr($path, -1) !== '/' && substr($path, -4) !== '.php') {
    $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: ' . $path . '/' . $qs, true, 301);
    exit;
}

$loginError = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['login_submit'])) {
    $password = $_POST['password'] ?? '';
    if (admin_login($password)) {
        header('Location: index.php');
        exit;
    } else {
        $loginError = 'Nesprávné heslo administrace. Zkuste to prosím znovu.';
    }
}

$isLoggedIn = is_admin_logged_in();

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KLIMAX – Administrace webu</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
<?php
// Přímé vložení stylů pro 100% spolehlivost i při chybějícím MIME type nebo kešování
if (file_exists(__DIR__ . '/admin.css')) {
    echo file_get_contents(__DIR__ . '/admin.css');
}
?>
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
    <!-- =======================================================
         PŘIHLAŠOVACÍ OBRAZOVKA
         ======================================================= -->
    <div class="login-wrapper">
        <div class="login-card">
            <img src="../assets/images/Klimax-black.webp" alt="KLIMAX Logo" class="login-card__logo">
            <h1 class="login-card__title">Správa obsahu webu</h1>
            <p class="login-card__subtitle">Zadejte heslo pro přístup do administrace realizací a souborů ke stažení.</p>

            <?php if (!empty($loginError)): ?>
                <div style="background: #fef2f2; border: 1px solid #fca5a5; color: #ef4444; padding: 12px 16px; border-radius: 6px; font-size: 13px; margin-bottom: 20px; text-align: left;">
                    <?= htmlspecialchars($loginError) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php">
                <div class="form-group" style="text-align: left;">
                    <label for="password" class="form-label">Administrátorské heslo</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Zadejte heslo..." required autofocus autocomplete="current-password">
                    <div class="form-hint">Výchozí přístupové heslo: <code>klimax1994</code></div>
                </div>

                <button type="submit" name="login_submit" class="btn btn--primary" style="width: 100%; padding: 12px; margin-top: 10px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
                    Přihlásit se
                </button>
            </form>

            <div style="margin-top: 24px;">
                <a href="../index.html" style="font-size: 13px; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Zpět na web Klimax
                </a>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- =======================================================
         HLAVNÍ ADMINISTRACE
         ======================================================= -->
    <header class="admin-header">
        <div class="admin-header__inner">
            <div class="admin-header__brand">
                <img src="../assets/images/Klimax-black.webp" alt="KLIMAX" class="admin-header__logo">
                <span class="admin-header__badge">Administrace</span>
            </div>

            <div class="admin-header__actions">
                <button type="button" class="btn btn--secondary btn--sm" id="btn-sync-all" title="Synchronizovat změny do HTML">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg>
                    Synchronizovat HTML
                </button>
                <a href="../reference.html" target="_blank" class="btn btn--secondary btn--sm" title="Zobrazit web v novém okně">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                    Zobrazit web
                </a>
                <button type="button" class="btn btn--danger btn--sm" id="btn-logout" title="Odhlásit se">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Odhlásit
                </button>
            </div>
        </div>
    </header>

    <main class="admin-container">
        <!-- Přehledové statistiky -->
        <div class="admin-stats">
            <div class="stat-box">
                <div class="stat-box__icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                </div>
                <div>
                    <div class="stat-box__label">Aktivní realizace</div>
                    <div class="stat-box__value" id="stat-real-count">0</div>
                    <div class="stat-box__sub">reference.html</div>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-box__icon stat-box__icon--teal">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </div>
                <div>
                    <div class="stat-box__label">Materiály ke stažení</div>
                    <div class="stat-box__value" id="stat-down-count">0</div>
                    <div class="stat-box__sub">pro-partnery.html</div>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-box__icon stat-box__icon--green">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <div>
                    <div class="stat-box__label">Poslední synchronizace HTML</div>
                    <div class="stat-box__value" style="font-size: 17px; margin-top: 4px;" id="stat-last-sync">-</div>
                    <div class="stat-box__sub">Synchronizováno se soubory HTML</div>
                </div>
            </div>
        </div>

        <!-- Záložky -->
        <nav class="admin-tabs">
            <button class="admin-tab active" data-tab="realizace">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                Realizace a reference
                <span class="admin-tab__count" id="real-count-badge">0</span>
            </button>
            <button class="admin-tab" data-tab="downloads">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Materiály ke stažení (Pro partnery)
                <span class="admin-tab__count" id="down-count-badge">0</span>
            </button>
            <button class="admin-tab" data-tab="settings">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Nastavení a synchronizace
            </button>
        </nav>

        <!-- =======================================================
             PANEL 1: REALIZACE
             ======================================================= -->
        <section class="tab-panel active" id="panel-realizace">
            <div class="panel-controls">
                <div class="panel-controls__left">
                    <div class="search-input-wrapper">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" id="real-search" class="search-input" placeholder="Hledat v realizacích...">
                    </div>
                    <div class="filter-pills">
                        <button class="filter-pill active" data-brand="all">Všechny</button>
                        <button class="filter-pill" data-brand="hisense">Hisense</button>
                        <button class="filter-pill" data-brand="toshiba">Toshiba</button>
                        <button class="filter-pill" data-brand="hokkaido">Hokkaido</button>
                    </div>
                </div>
                <button type="button" class="btn btn--primary" id="btn-add-realizace">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Přidat novou realizaci
                </button>
            </div>

            <div class="realizace-grid" id="realizace-grid">
                <!-- Dynamicky vykresleno pomocí JS -->
            </div>
        </section>

        <!-- =======================================================
             PANEL 2: KE STAŽENÍ (PRO PARTNERY)
             ======================================================= -->
        <section class="tab-panel" id="panel-downloads">
            <div class="panel-controls">
                <div class="panel-controls__left">
                    <div class="search-input-wrapper">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" id="down-search" class="search-input" placeholder="Hledat v materiálech ke stažení...">
                    </div>
                </div>
                <button type="button" class="btn btn--primary" id="btn-add-download">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Přidat soubor ke stažení
                </button>
            </div>

            <div class="downloads-list" id="downloads-list">
                <!-- Dynamicky vykresleno pomocí JS -->
            </div>
        </section>

        <!-- =======================================================
             PANEL 3: NASTAVENÍ A SYNCHRONIZACE
             ======================================================= -->
        <section class="tab-panel" id="panel-settings">
            <div class="settings-grid">
                <!-- Karta Synchronizace -->
                <div class="settings-card">
                    <h3 class="settings-card__title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg>
                        Generování a synchronizace HTML
                    </h3>
                    <p class="settings-card__desc">
                        Při každém přidání, úpravě nebo smazání se automaticky vygeneruje aktuální HTML kód do souborů <code>reference.html</code> a <code>pro-partnery.html</code>. Zde můžete provést ruční přegenerování kdykoliv.
                    </p>
                    <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 20px;">
                        <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 4px; font-weight: 500;">Poslední synchronizace:</div>
                        <div style="font-size: 15px; font-weight: 600; color: var(--accent-teal);" id="last-sync-header">-</div>
                    </div>
                    <button type="button" class="btn btn--primary" id="btn-sync-manual">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg>
                        Přegenerovat HTML stránky
                    </button>
                </div>

                <!-- Karta Změna hesla -->
                <div class="settings-card">
                    <h3 class="settings-card__title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        Změna administrátorského hesla
                    </h3>
                    <p class="settings-card__desc">
                        Zde můžete změnit heslo pro vstup do této administrace.
                    </p>
                    <form id="form-password">
                        <div class="form-group">
                            <label for="pass-current" class="form-label">Současné heslo</label>
                            <input type="password" id="pass-current" class="form-input" required autocomplete="current-password">
                        </div>
                        <div class="form-group">
                            <label for="pass-new" class="form-label">Nové heslo (min. 6 znaků)</label>
                            <input type="password" id="pass-new" class="form-input" required minlength="6" autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label for="pass-confirm" class="form-label">Potvrzení nového hesla</label>
                            <input type="password" id="pass-confirm" class="form-input" required minlength="6" autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn--secondary">Uložit nové heslo</button>
                    </form>
                </div>

                <!-- Karta Nápověda a architektura -->
                <div class="settings-card" style="grid-column: 1 / -1;">
                    <h3 class="settings-card__title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        Jak administrace funguje (Architektura a nasazení)
                    </h3>
                    <div style="font-size: 14px; color: var(--text-secondary); line-height: 1.7;">
                        <p style="margin-bottom: 12px;">
                            Web KLIMAX funguje jako moderní statický web s vysokou rychlostí a dokonalou indexací pro vyhledávače (SEO). Tato administrace běží na PHP a data ukládá jak do JSON databáze (<code>data/realizace.json</code>, <code>data/downloads.json</code>), tak je <strong>okamžitě propisuje do čistého HTML</strong> v <code>reference.html</code> a <code>pro-partnery.html</code>.
                        </p>
                        <p style="margin-bottom: 12px;">
                            Nahrané fotografie realizací se automaticky ukládají do <code>assets/realisation/</code> a soubory ke stažení (katalogy, ceníky, technické listy) do <code>assets/downloads/</code>.
                        </p>
                        <p>
                            Díky tomu jsou všechny změny 100% kompatibilní jak s lokálním testováním na XAMPP, tak s publikací přes Git na GitHub Pages nebo jakýkoliv webhosting s podporou PHP.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- =======================================================
         MODÁL: PŘIDAT / UPRAVIT REALIZACI
         ======================================================= -->
    <div class="modal-overlay" id="modal-realizace">
        <div class="modal">
            <div class="modal__header">
                <h3 class="modal__title" id="modal-realizace-title">Přidat realizaci</h3>
                <button type="button" class="modal__close" data-modal-close aria-label="Zavřít">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <form id="form-realizace">
                <input type="hidden" id="real-id" name="id">
                <input type="hidden" id="real-image-url" name="image">
                <input type="hidden" id="real-brand-logo" name="brandLogo" value="assets/images/Hisense.webp">

                <div class="modal__body">
                    <div class="form-group">
                        <label for="real-title" class="form-label">Název realizace / Klient *</label>
                        <input type="text" id="real-title" name="title" class="form-input" placeholder="např. OMV Česká republika, Hotel JALTA..." required>
                    </div>

                    <div class="form-group">
                        <label for="real-brand" class="form-label">Značka (Kategorie filtru & Logo) *</label>
                        <select id="real-brand" name="brand" class="form-select">
                            <option value="hisense">Hisense</option>
                            <option value="toshiba">Toshiba</option>
                            <option value="hokkaido">Hokkaido</option>
                            <option value="multiwarm">Multiwarm</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fotografie realizace</label>
                        <div class="dropzone">
                            <div class="dropzone__icon">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                            </div>
                            <div class="dropzone__text">Vyberte nebo přetáhněte fotografii</div>
                            <div class="dropzone__sub">JPG, PNG, WebP (doporučeno min. 800×600 px)</div>
                            <input type="file" id="real-image-file" name="image_file" accept="image/*">
                        </div>
                        <div id="real-image-preview"></div>
                    </div>
                </div>

                <div class="modal__footer">
                    <button type="button" class="btn btn--secondary" data-modal-close>Zrušit</button>
                    <button type="submit" class="btn btn--primary">Uložit realizaci</button>
                </div>
            </form>
        </div>
    </div>

    <!-- =======================================================
         MODÁL: PŘIDAT / UPRAVIT SOUBOR KE STAŽENÍ
         ======================================================= -->
    <div class="modal-overlay" id="modal-download">
        <div class="modal">
            <div class="modal__header">
                <h3 class="modal__title" id="modal-download-title">Přidat soubor ke stažení</h3>
                <button type="button" class="modal__close" data-modal-close aria-label="Zavřít">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <form id="form-download">
                <input type="hidden" id="down-id" name="id">
                <input type="hidden" id="down-file-url" name="fileUrl">

                <div class="modal__body">
                    <div class="form-group">
                        <label for="down-title" class="form-label">Název dokumentu / souboru *</label>
                        <input type="text" id="down-title" name="title" class="form-input" placeholder="např. Katalog Hisense 2025, Ceník Toshiba..." required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nahrát soubor (PDF, ZIP, DOCX...)</label>
                        <div class="dropzone">
                            <div class="dropzone__icon">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            </div>
                            <div class="dropzone__text">Klikněte nebo přetáhněte soubor</div>
                            <div class="dropzone__sub">PDF, ZIP, RAR, DOCX, XLSX (max 50 MB)</div>
                            <input type="file" id="down-file" name="download_file">
                        </div>
                        <div id="down-file-preview"></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="down-file-type" class="form-label">Typ souboru</label>
                            <select id="down-file-type" name="fileType" class="form-select">
                                <option value="pdf">PDF dokument</option>
                                <option value="zip">ZIP archív</option>
                                <option value="docx">Word (DOCX)</option>
                                <option value="xlsx">Excel (XLSX)</option>
                                <option value="other">Jiný formát</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="down-meta" class="form-label">Popisek (formát & velikost)</label>
                            <input type="text" id="down-meta" name="meta" class="form-input" placeholder="např. PDF, 12.4 MB">
                            <div class="form-hint">Při nahrání souboru se vyplní automaticky.</div>
                        </div>
                    </div>
                </div>

                <div class="modal__footer">
                    <button type="button" class="btn btn--secondary" data-modal-close>Zrušit</button>
                    <button type="submit" class="btn btn--primary">Uložit soubor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- =======================================================
         MODÁL: POTVRZENÍ SMAZÁNÍ
         ======================================================= -->
    <div class="modal-overlay" id="modal-confirm">
        <div class="modal" style="max-width: 440px;">
            <div class="modal__header">
                <h3 class="modal__title" style="color: var(--accent-red);">Potvrdit smazání</h3>
                <button type="button" class="modal__close" data-modal-close aria-label="Zavřít">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <div class="modal__body">
                <p id="modal-confirm-desc" style="font-size: 15px; color: var(--text-primary); line-height: 1.6;"></p>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" data-modal-close>Zrušit</button>
                <button type="button" class="btn btn--danger" id="btn-confirm-delete">Ano, smazat</button>
            </div>
        </div>
    </div>

    <script src="admin.js"></script>
<?php endif; ?>

</body>
</html>
