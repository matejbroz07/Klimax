/**
 * admin/admin.js - Správa obsahu KLIMAX (Realizace & Ke stažení)
 */

document.addEventListener('DOMContentLoaded', () => {
    // Globální stav
    const state = {
        realizace: [],
        downloads: [],
        brands: [],
        availableLogos: [],
        config: {},
        activeTab: 'realizace',
        realFilterBrand: 'all',
        realSearchQuery: '',
        downSearchQuery: '',
        brandSearchQuery: ''
    };

    // Inicializace elementů
    const elements = {
        tabBtns: document.querySelectorAll('.admin-tab'),
        tabPanels: document.querySelectorAll('.tab-panel'),
        realGrid: document.getElementById('realizace-grid'),
        downList: document.getElementById('downloads-list'),
        brandsGrid: document.getElementById('brands-grid'),
        realCountBadge: document.getElementById('real-count-badge'),
        downCountBadge: document.getElementById('down-count-badge'),
        brandCountBadge: document.getElementById('brand-count-badge'),
        statRealCount: document.getElementById('stat-real-count'),
        statDownCount: document.getElementById('stat-down-count'),
        statBrandCount: document.getElementById('stat-brand-count'),
        statLastSync: document.getElementById('stat-last-sync'),
        lastSyncHeader: document.getElementById('last-sync-header'),
        btnSyncAll: document.getElementById('btn-sync-all'),
        btnSyncManual: document.getElementById('btn-sync-manual'),
        btnLogout: document.getElementById('btn-logout'),
        
        // Filtry a vyhledávání
        realSearch: document.getElementById('real-search'),
        downSearch: document.getElementById('down-search'),
        brandSearch: document.getElementById('brand-search'),
        realFilterPills: document.querySelectorAll('.filter-pill[data-brand]'),
        
        // Modály
        modalReal: document.getElementById('modal-realizace'),
        modalDown: document.getElementById('modal-download'),
        modalBrand: document.getElementById('modal-brand'),
        modalConfirm: document.getElementById('modal-confirm'),
        
        // Formuláře
        formReal: document.getElementById('form-realizace'),
        formDown: document.getElementById('form-download'),
        formBrand: document.getElementById('form-brand'),
        formPassword: document.getElementById('form-password'),
        
        // Tlačítka přidání
        btnAddReal: document.getElementById('btn-add-realizace'),
        btnAddDown: document.getElementById('btn-add-download'),
        btnAddBrand: document.getElementById('btn-add-brand')
    };

    // ==========================================
    // NOTIFIKACE (TOASTS)
    // ==========================================
    function showToast(message, type = 'success') {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;

        const iconSvg = type === 'success' 
            ? `<svg class="toast__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>`
            : `<svg class="toast__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>`;

        toast.innerHTML = `${iconSvg}<span>${escapeHtml(message)}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(30px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ==========================================
    // MODÁLY OTEVŘÍT / ZAVŘÍT
    // ==========================================
    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.modal__close, [data-modal-close]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const modal = e.target.closest('.modal-overlay');
            closeModal(modal);
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal(overlay);
        });
    });

    // ==========================================
    // PŘEPÍNÁNÍ ZÁLOŽEK
    // ==========================================
    elements.tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            elements.tabBtns.forEach(b => b.classList.remove('active'));
            elements.tabPanels.forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            const targetPanel = document.getElementById(`panel-${tabId}`);
            if (targetPanel) targetPanel.classList.add('active');
            state.activeTab = tabId;
        });
    });

    // ==========================================
    // NAČTENÍ DAT Z API
    // ==========================================
    async function loadData() {
        try {
            const res = await fetch('api.php?action=get_data');
            if (res.status === 401) {
                window.location.reload();
                return;
            }
            const data = await res.json();
            if (data.success) {
                state.realizace = data.realizace || [];
                state.downloads = data.downloads || [];
                state.brands = data.brands || [];
                state.availableLogos = data.availableLogos || [];
                state.config = data.config || {};

                updateStats();
                renderRealizace();
                renderDownloads();
                renderBrands();
            } else {
                showToast(data.message || 'Chyba při načítání dat', 'error');
            }
        } catch (err) {
            console.error(err);
            showToast('Chyba komunikace se serverem.', 'error');
        }
    }

    function updateStats() {
        if (elements.realCountBadge) elements.realCountBadge.textContent = state.realizace.length;
        if (elements.downCountBadge) elements.downCountBadge.textContent = state.downloads.length;
        if (elements.brandCountBadge) elements.brandCountBadge.textContent = state.brands.length;
        if (elements.statRealCount) elements.statRealCount.textContent = state.realizace.length;
        if (elements.statDownCount) elements.statDownCount.textContent = state.downloads.length;
        if (elements.statBrandCount) elements.statBrandCount.textContent = state.brands.length;

        const lastSync = state.config.last_sync ? state.config.last_sync : 'Zatím neproběhla';
        if (elements.statLastSync) elements.statLastSync.textContent = lastSync;
        if (elements.lastSyncHeader) elements.lastSyncHeader.textContent = lastSync;
    }

    // ==========================================
    // VYKRESLENÍ REALIZACÍ
    // ==========================================
    function renderRealizace() {
        if (!elements.realGrid) return;

        let filtered = state.realizace.filter(item => {
            const matchBrand = state.realFilterBrand === 'all' || (item.brand || '').toLowerCase() === state.realFilterBrand;
            const matchSearch = !state.realSearchQuery || (item.title || '').toLowerCase().includes(state.realSearchQuery.toLowerCase());
            return matchBrand && matchSearch;
        });

        if (filtered.length === 0) {
            elements.realGrid.innerHTML = `
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <div class="empty-state__icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                    </div>
                    <h3 class="empty-state__title">Žádné realizace k zobrazení</h3>
                    <p class="empty-state__desc">Zkuste upravit filtr nebo vyhledávání, nebo přidejte novou realizaci.</p>
                </div>
            `;
            return;
        }

        elements.realGrid.innerHTML = filtered.map(item => {
            const brand = escapeHtml(item.brand || 'hisense');
            const title = escapeHtml(item.title || 'Bez názvu');
            const image = escapeHtml(item.image || 'assets/images/gallery_office.png');
            const hasLogo = !!item.hasBrandLogo && !!item.brandLogo;
            const logo = escapeHtml(item.brandLogo || '');
            const label = escapeHtml(item.brandLabel || 'Využívá:');

            return `
                <div class="real-card" data-id="${item.id}">
                    <div class="real-card__image-wrap">
                        <img src="../${image}" alt="${title}" class="real-card__image" loading="lazy" onerror="this.src='../assets/images/gallery_office.png'">
                        <span class="real-card__brand-tag tag--${brand}">${brand}</span>
                    </div>
                    <div class="real-card__body">
                        <h4 class="real-card__title">${title}</h4>
                        ${hasLogo ? `
                            <div class="real-card__brand-preview">
                                <span class="real-card__brand-label">${label}</span>
                                <img src="../${logo}" alt="${brand}" class="real-card__brand-logo-img">
                            </div>
                        ` : ''}
                    </div>
                    <div class="real-card__footer">
                        <button class="btn btn--secondary btn--sm btn-edit-real" data-id="${item.id}" title="Upravit realizaci">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                            Upravit
                        </button>
                        <button class="btn btn--danger btn--sm btn--icon-only btn-del-real" data-id="${item.id}" data-title="${title}" title="Smazat">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        // Event listenery pro tlačítka v kartách
        elements.realGrid.querySelectorAll('.btn-edit-real').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                const item = state.realizace.find(r => r.id === id);
                if (item) openRealizaceModal(item);
            });
        });

        elements.realGrid.querySelectorAll('.btn-del-real').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                const title = btn.getAttribute('data-title');
                confirmDelete('realizace', id, title);
            });
        });
    }

    // Filtry pro realizace
    elements.realFilterPills.forEach(pill => {
        pill.addEventListener('click', () => {
            elements.realFilterPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            state.realFilterBrand = pill.getAttribute('data-brand');
            renderRealizace();
        });
    });

    if (elements.realSearch) {
        elements.realSearch.addEventListener('input', (e) => {
            state.realSearchQuery = e.target.value.trim();
            renderRealizace();
        });
    }

    // ==========================================
    // VYKRESLENÍ SOUBORŮ KE STAŽENÍ
    // ==========================================
    function renderDownloads() {
        if (!elements.downList) return;

        let filtered = state.downloads.filter(item => {
            return !state.downSearchQuery || (item.title || '').toLowerCase().includes(state.downSearchQuery.toLowerCase());
        });

        if (filtered.length === 0) {
            elements.downList.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state__icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    </div>
                    <h3 class="empty-state__title">Žádné soubory ke stažení</h3>
                    <p class="empty-state__desc">Zatím nebyly nahrány žádné soubory pro partnery.</p>
                </div>
            `;
            return;
        }

        elements.downList.innerHTML = filtered.map(item => {
            const title = escapeHtml(item.title || 'Soubor bez názvu');
            const meta = escapeHtml(item.meta || 'PDF');
            const fileUrl = item.fileUrl ? escapeHtml(item.fileUrl) : '';
            const hasLink = !!fileUrl;

            return `
                <div class="download-item" data-id="${item.id}">
                    <div class="download-item__icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </div>
                    <div class="download-item__info">
                        <h4 class="download-item__title">${title}</h4>
                        <div class="download-item__meta">
                            <span>${meta}</span>
                            ${hasLink ? `<span style="color: var(--accent-blue); font-size: 11px;">(${fileUrl})</span>` : '<span style="color: var(--accent-amber); font-size: 11px;">(Zatím nenahrán soubor)</span>'}
                        </div>
                    </div>
                    <div class="download-item__actions">
                        ${hasLink ? `
                            <a href="../${fileUrl}" target="_blank" download class="btn btn--secondary btn--sm" title="Stáhnout soubor">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                Stáhnout
                            </a>
                        ` : ''}
                        <button class="btn btn--secondary btn--sm btn-edit-down" data-id="${item.id}" title="Upravit">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                            Upravit
                        </button>
                        <button class="btn btn--danger btn--sm btn--icon-only btn-del-down" data-id="${item.id}" data-title="${title}" title="Smazat">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        // Event listenery
        elements.downList.querySelectorAll('.btn-edit-down').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                const item = state.downloads.find(d => d.id === id);
                if (item) openDownloadModal(item);
            });
        });

        elements.downList.querySelectorAll('.btn-del-down').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                const title = btn.getAttribute('data-title');
                confirmDelete('download', id, title);
            });
        });
    }

    if (elements.downSearch) {
        elements.downSearch.addEventListener('input', (e) => {
            state.downSearchQuery = e.target.value.trim();
            renderDownloads();
        });
    }

    // ==========================================
    // VYKRESLENÍ ZNAČEK V CAROUSELU
    // ==========================================
    function renderBrands() {
        if (!elements.brandsGrid) return;

        let filtered = state.brands.filter(item => {
            if (!state.brandSearchQuery) return true;
            return (item.name || '').toLowerCase().includes(state.brandSearchQuery.toLowerCase());
        });

        if (filtered.length === 0) {
            elements.brandsGrid.innerHTML = `
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <div class="empty-state__icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                    <h3 class="empty-state__title">Žádné značky k zobrazení</h3>
                    <p class="empty-state__desc">Zkuste upravit vyhledávání nebo přidejte novou značku do carouselu.</p>
                </div>
            `;
            return;
        }

        elements.brandsGrid.innerHTML = filtered.map((item, index) => {
            const id = item.id;
            const name = escapeHtml(item.name || 'Bez názvu');
            const image = escapeHtml(item.image || '');
            const isFirst = index === 0;
            const isLast = index === filtered.length - 1;

            return `
                <div class="brand-card" data-id="${id}">
                    <div class="brand-card__preview">
                        <span class="brand-card__order-badge">#${index + 1}</span>
                        <img src="../${image}" alt="${name}" class="brand-card__img" loading="lazy" onerror="this.style.opacity='0.2'">
                    </div>
                    <div class="brand-card__body">
                        <div>
                            <h4 class="brand-card__name" title="${name}">${name}</h4>
                            <div class="brand-card__file" title="${image}">${image}</div>
                        </div>
                        <div class="brand-card__footer">
                            <div class="brand-card__reorder">
                                <button type="button" class="btn btn--secondary btn-move-brand-up" data-id="${id}" ${isFirst ? 'disabled' : ''} title="Posunout v carouselu dopředu">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"></polyline></svg>
                                </button>
                                <button type="button" class="btn btn--secondary btn-move-brand-down" data-id="${id}" ${isLast ? 'disabled' : ''} title="Posunout v carouselu dozadu">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </button>
                            </div>
                            <div class="brand-card__actions">
                                <button type="button" class="btn btn--secondary btn-edit-brand" data-id="${id}" title="Upravit značku">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    Upravit
                                </button>
                                <button type="button" class="btn btn--danger btn--icon-only btn-del-brand" data-id="${id}" data-name="${name}" title="Smazat">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        // Event listenery pro tlačítka v kartách
        elements.brandsGrid.querySelectorAll('.btn-edit-brand').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                const item = state.brands.find(b => b.id === id);
                if (item) openBrandModal(item);
            });
        });

        elements.brandsGrid.querySelectorAll('.btn-del-brand').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                const name = btn.getAttribute('data-name');
                confirmDelete('brand', id, name);
            });
        });

        elements.brandsGrid.querySelectorAll('.btn-move-brand-up').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                moveBrand(id, -1);
            });
        });

        elements.brandsGrid.querySelectorAll('.btn-move-brand-down').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                moveBrand(id, 1);
            });
        });
    }

    async function moveBrand(id, direction) {
        const index = state.brands.findIndex(b => b.id === id);
        if (index === -1) return;
        const targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= state.brands.length) return;

        // Prohodit v poli
        const temp = state.brands[index];
        state.brands[index] = state.brands[targetIndex];
        state.brands[targetIndex] = temp;

        renderBrands();

        try {
            const formData = new FormData();
            formData.append('action', 'reorder_brands');
            state.brands.forEach(b => formData.append('ids[]', b.id));

            const res = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
            } else {
                showToast(data.message || 'Chyba při změně pořadí', 'error');
                await loadData();
            }
        } catch (err) {
            console.error(err);
            showToast('Chyba při komunikaci se serverem', 'error');
            await loadData();
        }
    }

    if (elements.brandSearch) {
        elements.brandSearch.addEventListener('input', (e) => {
            state.brandSearchQuery = e.target.value.trim();
            renderBrands();
        });
    }

    // ==========================================
    // MODÁL: REALIZACE (PŘIDAT / UPRAVIT)
    // ==========================================
    const realIdInput = document.getElementById('real-id');
    const realTitleInput = document.getElementById('real-title');
    const realBrandSelect = document.getElementById('real-brand');
    const realImageInput = document.getElementById('real-image-url');
    const realFileInput = document.getElementById('real-image-file');
    const realImagePreview = document.getElementById('real-image-preview');

    function openRealizaceModal(item = null) {
        const modalTitle = document.getElementById('modal-realizace-title');
        elements.formReal.reset();

        if (item) {
            modalTitle.textContent = 'Upravit realizaci';
            realIdInput.value = item.id;
            realTitleInput.value = item.title || '';
            realBrandSelect.value = (item.brand || 'hisense').toLowerCase();
            realImageInput.value = item.image || '';

            if (realImagePreview) {
                realImagePreview.innerHTML = `
                    <div class="file-preview">
                        <img src="../${item.image}" alt="Náhled" class="file-preview__img">
                        <span class="file-preview__name">${item.image}</span>
                    </div>
                `;
            }
        } else {
            modalTitle.textContent = 'Přidat novou realizaci';
            realIdInput.value = '';
            realTitleInput.value = '';
            realBrandSelect.value = 'hisense';
            realImageInput.value = '';
            if (realImagePreview) realImagePreview.innerHTML = '';
        }

        openModal(elements.modalReal);
    }

    if (elements.btnAddReal) {
        elements.btnAddReal.addEventListener('click', () => openRealizaceModal(null));
    }

    // Náhled vybraného obrázku
    if (realFileInput) {
        realFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file && realImagePreview) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    realImagePreview.innerHTML = `
                        <div class="file-preview">
                            <img src="${event.target.result}" alt="Náhled" class="file-preview__img">
                            <span class="file-preview__name">${escapeHtml(file.name)} (${(file.size / 1024 / 1024).toFixed(2)} MB)</span>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Odeslání formuláře realizace
    if (elements.formReal) {
        elements.formReal.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = elements.formReal.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Ukládám...';

            try {
                const formData = new FormData(elements.formReal);
                formData.append('action', 'save_realizace');

                const res = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(elements.modalReal);
                    await loadData();
                } else {
                    showToast(data.message || 'Chyba při ukládání', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Chyba při komunikaci se serverem', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    // ==========================================
    // MODÁL: KE STAŽENÍ (PŘIDAT / UPRAVIT)
    // ==========================================
    const downIdInput = document.getElementById('down-id');
    const downTitleInput = document.getElementById('down-title');
    const downMetaInput = document.getElementById('down-meta');
    const downFileTypeSelect = document.getElementById('down-file-type');
    const downFileInput = document.getElementById('down-file');
    const downUrlInput = document.getElementById('down-file-url');
    const downFilePreview = document.getElementById('down-file-preview');

    function openDownloadModal(item = null) {
        const modalTitle = document.getElementById('modal-download-title');
        elements.formDown.reset();

        if (item) {
            modalTitle.textContent = 'Upravit soubor ke stažení';
            downIdInput.value = item.id;
            downTitleInput.value = item.title || '';
            downMetaInput.value = item.meta || '';
            downFileTypeSelect.value = (item.fileType || 'pdf').toLowerCase();
            downUrlInput.value = item.fileUrl || '';

            if (downFilePreview) {
                downFilePreview.innerHTML = item.fileUrl ? `
                    <div class="file-preview">
                        <span class="file-preview__name">Stávající soubor: ${escapeHtml(item.fileUrl)}</span>
                    </div>
                ` : '';
            }
        } else {
            modalTitle.textContent = 'Přidat nový soubor ke stažení';
            downIdInput.value = '';
            downTitleInput.value = '';
            downMetaInput.value = '';
            downFileTypeSelect.value = 'pdf';
            downUrlInput.value = '';
            if (downFilePreview) downFilePreview.innerHTML = '';
        }

        openModal(elements.modalDown);
    }

    if (elements.btnAddDown) {
        elements.btnAddDown.addEventListener('click', () => openDownloadModal(null));
    }

    // Auto-detekce při výběru souboru
    if (downFileInput) {
        downFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const ext = file.name.split('.').pop().toLowerCase();
                const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
                
                // Předvyplnit název, pokud je prázdný
                if (!downTitleInput.value) {
                    const nameWithoutExt = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                    downTitleInput.value = nameWithoutExt.replace(/[-_]/g, ' ');
                }

                // Nastavit typ souboru a meta
                if (['pdf', 'zip', 'rar', 'doc', 'docx', 'xls', 'xlsx'].includes(ext)) {
                    downFileTypeSelect.value = ext;
                }
                downMetaInput.value = `${ext.toUpperCase()}, ${sizeMb} MB`;

                if (downFilePreview) {
                    downFilePreview.innerHTML = `
                        <div class="file-preview">
                            <span class="file-preview__name">${escapeHtml(file.name)} (${sizeMb} MB)</span>
                        </div>
                    `;
                }
            }
        });
    }

    // Odeslání formuláře ke stažení
    if (elements.formDown) {
        elements.formDown.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = elements.formDown.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Ukládám...';

            try {
                const formData = new FormData(elements.formDown);
                formData.append('action', 'save_download');

                const res = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(elements.modalDown);
                    await loadData();
                } else {
                    showToast(data.message || 'Chyba při ukládání souboru', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Chyba při komunikaci se serverem', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    // ==========================================
    // MODÁL: ZNAČKA V CAROUSELU (PŘIDAT / UPRAVIT)
    // ==========================================
    const brandIdInput = document.getElementById('brand-id');
    const brandNameInput = document.getElementById('brand-name');
    const brandImageUrlInput = document.getElementById('brand-image-url');
    const brandFileInput = document.getElementById('brand-image-file');
    const brandImagePreview = document.getElementById('brand-image-preview');

    function openBrandModal(item = null) {
        const modalTitle = document.getElementById('modal-brand-title');
        elements.formBrand.reset();

        if (item) {
            modalTitle.textContent = 'Upravit značku v carouselu';
            brandIdInput.value = item.id;
            brandNameInput.value = item.name || '';
            brandImageUrlInput.value = item.image || '';

            if (brandImagePreview) {
                brandImagePreview.innerHTML = item.image ? `
                    <div class="file-preview">
                        <img src="../${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}" class="file-preview__img">
                        <span class="file-preview__name">${escapeHtml(item.image)}</span>
                    </div>
                ` : '';
            }
        } else {
            modalTitle.textContent = 'Přidat novou značku do carouselu';
            brandIdInput.value = '';
            brandNameInput.value = '';
            brandImageUrlInput.value = '';
            if (brandImagePreview) brandImagePreview.innerHTML = '';
        }

        openModal(elements.modalBrand);
    }

    if (elements.btnAddBrand) {
        elements.btnAddBrand.addEventListener('click', () => openBrandModal(null));
    }

    // Náhled vybraného souboru loga
    if (brandFileInput) {
        brandFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file && brandImagePreview) {
                if (!brandNameInput.value) {
                    const nameWithoutExt = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                    brandNameInput.value = nameWithoutExt.replace(/[-_]/g, ' ').toUpperCase();
                }
                const reader = new FileReader();
                reader.onload = (event) => {
                    brandImagePreview.innerHTML = `
                        <div class="file-preview">
                            <img src="${event.target.result}" alt="Náhled" class="file-preview__img">
                            <span class="file-preview__name">${escapeHtml(file.name)} (${(file.size / 1024 / 1024).toFixed(2)} MB)</span>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Odeslání formuláře značky
    if (elements.formBrand) {
        elements.formBrand.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = elements.formBrand.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Ukládám...';

            try {
                const formData = new FormData(elements.formBrand);
                formData.append('action', 'save_brand');

                const res = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(elements.modalBrand);
                    await loadData();
                } else {
                    showToast(data.message || 'Chyba při ukládání značky', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Chyba při komunikaci se serverem', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    // ==========================================
    // POTVRZENÍ SMAZÁNÍ
    // ==========================================
    let pendingDelete = null;

    function confirmDelete(type, id, title) {
        pendingDelete = { type, id };
        const desc = document.getElementById('modal-confirm-desc');
        if (desc) {
            desc.innerHTML = `Opravdu si přejete smazat <strong>"${escapeHtml(title)}"</strong>?<br><span style="color: var(--text-muted); font-size: 13px;">Tato akce je nevratná a automaticky aktualizuje stránku webu.</span>`;
        }
        openModal(elements.modalConfirm);
    }

    const btnConfirmDelete = document.getElementById('btn-confirm-delete');
    if (btnConfirmDelete) {
        btnConfirmDelete.addEventListener('click', async () => {
            if (!pendingDelete) return;
            const { type, id } = pendingDelete;
            btnConfirmDelete.disabled = true;

            try {
                let action = 'delete_realizace';
                if (type === 'download') action = 'delete_download';
                if (type === 'brand') action = 'delete_brand';

                const formData = new FormData();
                formData.append('action', action);
                formData.append('id', id);

                const res = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(elements.modalConfirm);
                    await loadData();
                } else {
                    showToast(data.message || 'Chyba při mazání', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Chyba při komunikaci se serverem', 'error');
            } finally {
                btnConfirmDelete.disabled = false;
                pendingDelete = null;
            }
        });
    }

    // ==========================================
    // SYNCHRONIZACE VŠECH HTML ŠABLON
    // ==========================================
    async function triggerSyncAll(btn) {
        const originalText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<svg class="spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg> Synchronizuji...`;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'sync_all');

            const res = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                showToast(data.message, 'success');
                state.config.last_sync = data.last_sync;
                updateStats();
            } else {
                showToast(data.message || 'Synchronizace selhala', 'error');
            }
        } catch (err) {
            console.error(err);
            showToast('Chyba při komunikaci se serverem', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    }

    if (elements.btnSyncAll) {
        elements.btnSyncAll.addEventListener('click', () => triggerSyncAll(elements.btnSyncAll));
    }
    if (elements.btnSyncManual) {
        elements.btnSyncManual.addEventListener('click', () => triggerSyncAll(elements.btnSyncManual));
    }

    // ==========================================
    // ZMĚNA HESLA
    // ==========================================
    if (elements.formPassword) {
        elements.formPassword.addEventListener('submit', async (e) => {
            e.preventDefault();
            const curr = document.getElementById('pass-current').value;
            const pNew = document.getElementById('pass-new').value;
            const pConf = document.getElementById('pass-confirm').value;

            if (pNew !== pConf) {
                showToast('Nová hesla se neshodují!', 'error');
                return;
            }

            if (pNew.length < 6) {
                showToast('Nové heslo musí mít alespoň 6 znaků.', 'error');
                return;
            }

            const submitBtn = elements.formPassword.querySelector('button[type="submit"]');
            submitBtn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'change_password');
                formData.append('current_password', curr);
                formData.append('new_password', pNew);

                const res = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    elements.formPassword.reset();
                } else {
                    showToast(data.message || 'Chyba při změně hesla', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Chyba při komunikaci se serverem', 'error');
            } finally {
                submitBtn.disabled = false;
            }
        });
    }

    // ==========================================
    // ODHLÁŠENÍ
    // ==========================================
    if (elements.btnLogout) {
        elements.btnLogout.addEventListener('click', async () => {
            try {
                await fetch('api.php?action=logout');
                window.location.reload();
            } catch (err) {
                window.location.reload();
            }
        });
    }

    // Počáteční načtení dat
    loadData();
});
