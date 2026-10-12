<?php
// admin/sync.php - Synchronizace JSON dat do statických HTML souborů (reference.html a pro-partnery.html)

require_once __DIR__ . '/config.php';

define('REFERENCE_HTML', __DIR__ . '/../reference.html');
define('PRO_PARTNERY_HTML', __DIR__ . '/../pro-partnery.html');
define('INDEX_HTML', __DIR__ . '/../index.html');

/**
 * Synchronizace realizací do reference.html
 */
function sync_realizace_html($items = null) {
    if ($items === null) {
        if (!file_exists(REALIZACE_JSON)) {
            return false;
        }
        $items = json_decode(file_get_contents(REALIZACE_JSON), true);
    }
    if (!is_array($items) || !file_exists(REFERENCE_HTML)) {
        return false;
    }

    $cardsHtml = "";
    if (empty($items)) {
        $cardsHtml .= "                    <div style=\"grid-column: 1 / -1; padding: 40px 0; color: var(--text-secondary); font-size: 16px; text-align: center;\">\n";
        $cardsHtml .= "                        Zatím tu nic není.\n";
        $cardsHtml .= "                    </div>\n";
    } else {
        foreach ($items as $item) {
            $brand = htmlspecialchars($item['brand'] ?? 'hisense');
            $image = htmlspecialchars($item['image'] ?? 'assets/images/gallery_server.png');
            $title = htmlspecialchars($item['title'] ?? '');
            $hasLogo = !empty($item['hasBrandLogo']) && !empty($item['brandLogo']);
            $logo = htmlspecialchars($item['brandLogo'] ?? '');
            $label = htmlspecialchars($item['brandLabel'] ?? 'Využívá:');

            $cardsHtml .= "                    <div class=\"client-card\" data-brand=\"{$brand}\">\n";
            $cardsHtml .= "                        <div class=\"client-card__image-wrapper\">\n";
            $cardsHtml .= "                            <img src=\"{$image}\" alt=\"{$title}\" class=\"client-card__image\" loading=\"lazy\">\n";
            $cardsHtml .= "                        </div>\n";
            $cardsHtml .= "                        <div class=\"client-card__content\">\n";
            $cardsHtml .= "                            <h3 class=\"client-card__title\">{$title}</h3>\n";
            if ($hasLogo) {
                $cardsHtml .= "                            <div class=\"client-card__brand\">\n";
                $cardsHtml .= "                                <span class=\"client-card__brand-label\">{$label}</span>\n";
                $cardsHtml .= "                                <img src=\"{$logo}\" alt=\"{$brand}\" class=\"client-card__brand-logo\">\n";
                $cardsHtml .= "                            </div>\n";
            }
            $cardsHtml .= "                        </div>\n";
            $cardsHtml .= "                    </div>\n";
        }
    }

    $html = file_get_contents(REFERENCE_HTML);
    $pattern = '/(<!--\s*REALIZACE_CARDS_START\s*-->).*?(<!--\s*REALIZACE_CARDS_END\s*-->)/s';
    if (!preg_match($pattern, $html)) {
        return false;
    }

    $replacement = "$1\n" . rtrim($cardsHtml) . "\n                    $2";
    $newHtml = preg_replace($pattern, $replacement, $html);

    $res = file_put_contents(REFERENCE_HTML, $newHtml) !== false;
    if ($res) {
        @chmod(REFERENCE_HTML, 0666);
    }
    return $res;
}

/**
 * Synchronizace materiálů ke stažení do pro-partnery.html
 */
function sync_downloads_html($items = null) {
    if ($items === null) {
        if (!file_exists(DOWNLOADS_JSON)) {
            return false;
        }
        $items = json_decode(file_get_contents(DOWNLOADS_JSON), true);
    }
    if (!is_array($items) || !file_exists(PRO_PARTNERY_HTML)) {
        return false;
    }

    $cardsHtml = "";
    if (empty($items)) {
        $cardsHtml .= "                    <div style=\"grid-column: 1 / -1; padding: 40px 0; color: var(--text-secondary); font-size: 16px; text-align: center;\">\n";
        $cardsHtml .= "                        Zatím tu nic není.\n";
        $cardsHtml .= "                    </div>\n";
    } else {
        foreach ($items as $idx => $item) {
            $num = $idx + 1;
            $title = htmlspecialchars($item['title'] ?? '');
            $meta = htmlspecialchars($item['meta'] ?? 'PDF');
            $fileUrl = htmlspecialchars($item['fileUrl'] ?? '');
            $href = !empty($fileUrl) ? $fileUrl : '#';
            $downloadAttr = !empty($fileUrl) ? ' download' : '';
            $targetAttr = !empty($fileUrl) ? ' target="_blank" rel="noopener noreferrer"' : '';

            // SVG ikona podle typu
            $iconSvg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>';

            $arrowSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';

            $cardsHtml .= "                    <!-- {$num} -->\n";
            $cardsHtml .= "                    <a href=\"{$href}\" class=\"download-card\"{$downloadAttr}{$targetAttr}>\n";
            $cardsHtml .= "                        <div class=\"download-card__icon\">\n";
            $cardsHtml .= "                            {$iconSvg}\n";
            $cardsHtml .= "                        </div>\n";
            $cardsHtml .= "                        <div class=\"download-card__content download-card__info\">\n";
            $cardsHtml .= "                            <h4 class=\"download-card__title download-card__name\">{$title}</h4>\n";
            $cardsHtml .= "                            <span class=\"download-card__meta\">{$meta}</span>\n";
            $cardsHtml .= "                        </div>\n";
            $cardsHtml .= "                        <div class=\"download-card__arrow download-card__action\">\n";
            $cardsHtml .= "                            {$arrowSvg}\n";
            $cardsHtml .= "                        </div>\n";
            $cardsHtml .= "                    </a>\n";
        }
    }

    $html = file_get_contents(PRO_PARTNERY_HTML);
    $pattern = '/(<!--\s*DOWNLOAD_CARDS_START\s*-->).*?(<!--\s*DOWNLOAD_CARDS_END\s*-->)/s';
    if (!preg_match($pattern, $html)) {
        return false;
    }

    $replacement = "$1\n" . rtrim($cardsHtml) . "\n                    $2";
    $newHtml = preg_replace($pattern, $replacement, $html);

    $res = file_put_contents(PRO_PARTNERY_HTML, $newHtml) !== false;
    if ($res) {
        @chmod(PRO_PARTNERY_HTML, 0666);
    }
    return $res;
}

/**
 * Synchronizace značek carouselu do index.html
 */
function sync_brands_html($items = null) {
    if ($items === null) {
        if (!file_exists(BRANDS_JSON)) {
            return false;
        }
        $items = json_decode(file_get_contents(BRANDS_JSON), true);
    }
    if (!is_array($items) || !file_exists(INDEX_HTML)) {
        return false;
    }

    $groupsHtml = "";
    if (empty($items)) {
        $groupsHtml .= "                <!-- Žádné značky v carouselu -->\n";
    } else {
        // Vygenerujeme 4 skupiny pro plynulý nekonečný marquee
        for ($g = 1; $g <= 4; $g++) {
            $ariaHidden = ($g > 1) ? ' aria-hidden="true"' : '';
            $groupComment = ($g === 1) ? "<!-- Group 1 (original) -->" : "<!-- Group {$g} (duplicate) -->";

            $groupsHtml .= "                {$groupComment}\n";
            $groupsHtml .= "                <div class=\"trusted-carousel__group\"{$ariaHidden}>\n";
            foreach ($items as $item) {
                $img = htmlspecialchars($item['image'] ?? '');
                $name = htmlspecialchars($item['name'] ?? '');
                $groupsHtml .= "                    <img src=\"{$img}\" alt=\"{$name}\">\n";
            }
            $groupsHtml .= "                </div>\n";
        }
    }

    $html = file_get_contents(INDEX_HTML);
    $pattern = '/(<!--\s*CAROUSEL_BRANDS_START\s*-->).*?(<!--\s*CAROUSEL_BRANDS_END\s*-->)/s';
    if (!preg_match($pattern, $html)) {
        return false;
    }

    $replacement = "$1\n" . rtrim($groupsHtml) . "\n                $2";
    $newHtml = preg_replace($pattern, $replacement, $html);

    $res = file_put_contents(INDEX_HTML, $newHtml) !== false;
    if ($res) {
        @chmod(INDEX_HTML, 0666);
    }
    return $res;
}

/**
 * Synchronizovat vše
 */
function sync_all() {
    $r = sync_realizace_html();
    $d = sync_downloads_html();
    $b = sync_brands_html();
    
    $config = get_admin_config();
    $config['last_sync'] = date('Y-m-d H:i:s');
    save_admin_config($config);

    return $r && $d && $b;
}
