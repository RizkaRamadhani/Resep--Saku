'use strict';

const API_URL = 'api';
const TOKEN_KEY = 'resep_saku_token';
let activeCategory = '';
let savedRecipeIds = new Set();
let searchTimer;
let recipeRequestId = 0;

function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[character]);
}

function displayCategory(category) {
    return category === 'Camilan' ? 'Camilan/Dessert' : category;
}

function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}

async function requestJson(path, options = {}) {
    if (window.location.protocol === 'file:') {
        throw new Error('Buka aplikasi melalui Laragon: http://localhost:8080/Resep--Saku/');
    }

    const headers = new Headers(options.headers || {});
    if (options.body && !(options.body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
    }

    const token = getToken();
    if (token) headers.set('Authorization', `Bearer ${token}`);

    let response;
    try {
        response = await fetch(`${API_URL}/${path}`, { ...options, headers });
    } catch {
        throw new Error('Server tidak dapat dihubungi. Periksa koneksi lalu coba lagi.');
    }

    let result;
    try {
        result = await response.json();
    } catch {
        throw new Error('Respons backend tidak valid.');
    }

    const failed = !response.ok ||
        (result.success === false) ||
        (result.status && result.status !== 'berhasil');
    if (failed) {
        if (response.status === 401 && token) {
            localStorage.removeItem(TOKEN_KEY);
            localStorage.removeItem('resep_saku_user');
            savedRecipeIds.clear();
        }
        throw new Error(result.message || result.pesan || 'Permintaan gagal diproses.');
    }
    return result;
}

function normalizeRecipe(recipe) {
    const image = recipe.gambar_url || recipe.gambar || '';
    const imageUrl = image
        ? (/^(https?:)?\/\//i.test(image) || image.startsWith('/') || image.startsWith('assets/') || image.startsWith('uploads/')
            ? image
            : `assets/img/${image}`)
        : 'assets/img/placeholder.svg';

    return {
        id: String(recipe.id_resep ?? recipe.id ?? ''),
        title: recipe.judul_resep ?? recipe.nama ?? 'Resep tanpa judul',
        image: imageUrl,
        time: recipe.waktu_estimasi != null
            ? `${recipe.waktu_estimasi} menit`
            : (recipe.waktu || 'Waktu belum tersedia'),
        ingredientsCount: recipe.jumlah_bahan ?? recipe.bahan_count ?? null,
        difficulty: recipe.tingkat_kesulitan ?? recipe.tingkat ?? 'Mudah',
        category: recipe.kategori ?? '',
        description: recipe.deskripsi ?? '',
    };
}

function renderRecipeCard(recipe) {
    const isSaved = savedRecipeIds.has(recipe.id);
    return `
        <article class="recipe-card">
            <a class="recipe-card-link" href="detail.html?id=${encodeURIComponent(recipe.id)}" aria-label="Lihat resep ${escapeHTML(recipe.title)}">
                <img src="${escapeHTML(recipe.image)}" alt="${escapeHTML(recipe.title)}" loading="lazy" onerror="this.onerror=null;this.src='assets/img/placeholder.svg'">
                <h4>${escapeHTML(recipe.title)}</h4>
                <p>${escapeHTML(recipe.time)}${recipe.ingredientsCount == null ? '' : ` • ${Number(recipe.ingredientsCount)} bahan`}</p>
            </a>
            <span class="difficulty">${escapeHTML(recipe.difficulty)}</span>
            <button type="button" class="bookmark-btn${isSaved ? ' saved' : ''}" data-bookmark-id="${escapeHTML(recipe.id)}" aria-label="${isSaved ? 'Hapus dari' : 'Simpan ke'} resep tersimpan" aria-pressed="${isSaved}">
                <i class="${isSaved ? 'fas' : 'far'} fa-heart" aria-hidden="true"></i>
            </button>
        </article>`;
}

function bindRecipeCards(container) {
    container.querySelectorAll('.bookmark-btn').forEach((button) => {
        button.addEventListener('click', () => toggleBookmark(button.dataset.bookmarkId, button));
    });
}

async function loadBookmarks() {
    if (!getToken()) {
        savedRecipeIds.clear();
        return;
    }
    const result = await requestJson('bookmark/list.php');
    savedRecipeIds = new Set((result.data || []).map((recipe) => String(recipe.id_resep)));
    document.querySelectorAll('.bookmark-btn').forEach((button) => {
        const isSaved = savedRecipeIds.has(button.dataset.bookmarkId);
        button.classList.toggle('saved', isSaved);
        button.setAttribute('aria-pressed', String(isSaved));
        button.setAttribute('aria-label', `${isSaved ? 'Hapus dari' : 'Simpan ke'} resep tersimpan`);
        const icon = button.querySelector('i');
        if (icon) icon.className = `${isSaved ? 'fas' : 'far'} fa-heart`;
    });
}

async function loadRecipes(keyword = '', category = '') {
    const requestId = ++recipeRequestId;
    const loading = document.getElementById('loadingState');
    const container = document.getElementById('recipesContainer');
    const empty = document.getElementById('emptyState');
    const error = document.getElementById('errorState');
    if (!container) return;

    if (loading) loading.classList.remove('hidden');
    container.classList.add('hidden');
    if (empty) empty.classList.add('hidden');
    if (error) error.classList.add('hidden');

    const params = new URLSearchParams();
    if (keyword) params.set('q', keyword);
    if (category) params.set('kategori', category);

    try {
        const result = await requestJson(`search.php?${params.toString()}`);
        if (requestId !== recipeRequestId) return;
        const recipes = (result.data || []).map(normalizeRecipe);
        if (loading) loading.classList.add('hidden');
        const count = document.querySelector('.recipe-count');
        if (count) count.textContent = `${result.total ?? result.jumlah ?? recipes.length} resep`;
        const heading = document.querySelector('.section-header h3');
        if (heading) heading.textContent = keyword
            ? `Hasil untuk “${keyword}”`
            : category
                ? `Resep ${displayCategory(category)}`
                : 'Resep untuk kamu';
        if (!recipes.length) {
            if (empty) empty.classList.remove('hidden');
            return;
        }

        container.innerHTML = recipes.map(renderRecipeCard).join('');
        container.classList.remove('hidden');
        bindRecipeCards(container);
    } catch (requestError) {
        if (requestId !== recipeRequestId) return;
        if (loading) loading.classList.add('hidden');
        if (error) {
            error.classList.remove('hidden');
            const text = error.querySelector('p');
            if (text) text.textContent = requestError.message;
        }
    }
}

function bukaDetail(id) {
    window.location.href = `detail.html?id=${encodeURIComponent(id)}`;
}

async function toggleBookmark(id, button) {
    if (!getToken()) {
        window.location.href = 'login.html';
        return;
    }
    button.disabled = true;
    try {
        const result = await requestJson('bookmark/toggle.php', {
            method: 'POST',
            body: JSON.stringify({ id_resep: Number(id) }),
        });
        if (result.bookmarked) savedRecipeIds.add(String(id));
        else savedRecipeIds.delete(String(id));
        const isSaved = Boolean(result.bookmarked);
        button.classList.toggle('saved', isSaved);
        button.setAttribute('aria-pressed', String(isSaved));
        button.setAttribute('aria-label', `${isSaved ? 'Hapus dari' : 'Simpan ke'} resep tersimpan`);
        const icon = button.querySelector('i');
        if (icon) icon.className = `${isSaved ? 'fas' : 'far'} fa-heart`;
        const savedContainer = document.getElementById('savedRecipes');
        if (savedContainer && !isSaved) {
            button.closest('.recipe-card')?.remove();
            if (!savedContainer.querySelector('.recipe-card')) {
                savedContainer.classList.add('hidden');
                document.getElementById('emptySaved')?.classList.remove('hidden');
            }
        }
    } catch (error) {
        window.alert(error.message);
    } finally {
        button.disabled = false;
    }
}

function showDetailError(message) {
    const content = document.querySelector('.detail-content');
    if (!content) return;
    content.innerHTML = `
        <div class="state-container">
            <div class="state-icon error"><i class="fas fa-exclamation-circle"></i></div>
            <h3>Resep tidak dapat dimuat</h3>
            <p>${escapeHTML(message)}</p>
            <a href="index.html" class="btn-primary">Kembali ke beranda</a>
        </div>`;
}

async function loadDetail(id) {
    try {
        const result = await requestJson(`detail.php?id=${encodeURIComponent(id)}`);
        const detail = result.data || result;
        const recipe = normalizeRecipe(detail.resep || {});
        const ingredients = detail.bahan || [];
        const instructions = detail.instruksi || detail.langkah || [];
        const title = document.getElementById('recipeTitle');
        const description = document.getElementById('recipeDesc');
        const time = document.getElementById('recipeTime');
        const count = document.getElementById('recipeIngredientsCount');
        const image = document.getElementById('recipeImage');
        const difficulty = document.getElementById('recipeDifficulty');
        const list = document.getElementById('ingredientsList');
        const steps = document.getElementById('stepsList');

        if (title) title.textContent = recipe.title;
        if (description) description.textContent = recipe.description || 'Resep pilihan hari ini.';
        if (time) time.textContent = recipe.time;
        if (count) count.textContent = `${ingredients.length} bahan`;
        if (image) {
            image.src = recipe.image;
            image.alt = recipe.title;
            image.onerror = () => { image.src = 'assets/img/placeholder.svg'; };
        }
        if (difficulty) difficulty.textContent = recipe.difficulty;
        const subtitle = document.querySelector('.detail-content .section-subtitle');
        if (subtitle) subtitle.textContent = `${ingredients.length} bahan`;
        if (list) {
            list.innerHTML = ingredients.map((item) => `
                <li><span>${escapeHTML(item.nama_bahan ?? item.nama ?? '')}</span>
                    <span>${escapeHTML(item.takaran ?? item.jumlah ?? '-')}</span></li>`).join('');
        }
        if (steps) {
            steps.innerHTML = instructions.map((step) => `
                <li>${escapeHTML(step.deskripsi_langkah ?? step.keterangan ?? step.nama ?? '')}</li>`).join('');
        }
    } catch (error) {
        showDetailError(error.message);
    }
}

function renderSavedRecipes(recipes) {
    const container = document.getElementById('savedRecipes');
    const empty = document.getElementById('emptySaved');
    if (!container || !empty) return;

    const query = (document.getElementById('savedSearch')?.value || '').trim().toLocaleLowerCase('id');
    const filtered = recipes
        .map(normalizeRecipe)
        .filter((recipe) => recipe.title.toLocaleLowerCase('id').includes(query));
    if (!filtered.length) {
        container.classList.add('hidden');
        empty.classList.remove('hidden');
        const heading = empty.querySelector('h3');
        const message = empty.querySelector('p');
        if (heading) heading.textContent = query ? 'Resep tidak ditemukan' : 'Belum ada resep tersimpan';
        if (message) message.textContent = query ? 'Coba kata kunci lain.' : 'Yuk, temukan resep yang ingin kamu coba.';
        return;
    }

    empty.classList.add('hidden');
    container.classList.remove('hidden');
    container.innerHTML = filtered.map(renderRecipeCard).join('');
    bindRecipeCards(container);
}

async function initSavedPage() {
    const empty = document.getElementById('emptySaved');
    const container = document.getElementById('savedRecipes');
    if (!empty || !container) return;
    if (!getToken()) {
        empty.classList.remove('hidden');
        const heading = empty.querySelector('h3');
        const message = empty.querySelector('p');
        if (heading) heading.textContent = 'Masuk untuk melihat resep tersimpan';
        if (message) message.textContent = 'Simpanan resep terhubung ke akun kamu.';
        const link = empty.querySelector('a');
        if (link) {
            link.href = 'login.html';
            link.textContent = 'Masuk';
        }
        return;
    }

    try {
        const result = await requestJson('bookmark/list.php');
        const recipes = result.data || [];
        savedRecipeIds = new Set(recipes.map((recipe) => String(recipe.id_resep)));
        renderSavedRecipes(recipes);
        const search = document.getElementById('savedSearch');
        if (search) search.addEventListener('input', () => renderSavedRecipes(recipes));
    } catch (error) {
        empty.classList.remove('hidden');
        const heading = empty.querySelector('h3');
        const message = empty.querySelector('p');
        if (heading) heading.textContent = 'Simpanan tidak dapat dimuat';
        if (message) message.textContent = error.message;
    }
}

function showFormMessage(form, message, isError = false) {
    let status = form.querySelector('[data-form-message]');
    if (!status) {
        status = document.createElement('p');
        status.dataset.formMessage = 'true';
        status.setAttribute('role', 'status');
        status.className = 'auth-link';
        form.appendChild(status);
    }
    status.textContent = message;
    status.style.color = isError ? 'var(--error-text)' : 'inherit';
}

function setFormBusy(form, busy) {
    const submit = form.querySelector('[type="submit"]');
    if (!submit) return;
    if (busy) {
        submit.dataset.originalText = submit.textContent;
        submit.disabled = true;
        submit.textContent = 'Memproses...';
    } else {
        submit.disabled = false;
        submit.textContent = submit.dataset.originalText || submit.textContent;
    }
}

function initAccountNavigation() {
    if (!getToken()) return;
    let user = null;
    try {
        user = JSON.parse(localStorage.getItem('resep_saku_user') || 'null');
    } catch {
        localStorage.removeItem('resep_saku_user');
    }

    document.querySelectorAll('.nav-item[href="login.html"]').forEach((link) => {
        if (user?.role === 'admin') {
            link.href = 'admin.html';
            const label = link.querySelector('span');
            if (label) label.textContent = 'Admin';
            const icon = link.querySelector('i');
            if (icon) icon.className = 'fas fa-user-shield';
            return;
        }

        link.href = '#logout';
        const label = link.querySelector('span');
        if (label) label.textContent = 'Keluar';
        link.addEventListener('click', async (event) => {
            event.preventDefault();
            try {
                await requestJson('auth/logout.php', { method: 'POST' });
                localStorage.removeItem(TOKEN_KEY);
                localStorage.removeItem('resep_saku_user');
                savedRecipeIds.clear();
                window.location.href = 'index.html';
            } catch (error) {
                window.alert(error.message);
            }
        });
    });
}

function showAdminMessage(message, isError = false, addLoginLink = false) {
    const status = document.getElementById('adminStatus');
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('error', isError);
    status.classList.remove('hidden');
    if (addLoginLink) {
        const link = document.createElement('a');
        link.href = 'login.html';
        link.className = 'admin-text-link';
        link.textContent = ' Masuk';
        status.appendChild(link);
    }
}

function appendAdminFieldRow(type, data = {}) {
    const isIngredient = type === 'ingredients';
    const container = document.getElementById(isIngredient ? 'adminIngredients' : 'adminInstructions');
    const template = document.getElementById(isIngredient ? 'ingredientRowTemplate' : 'instructionRowTemplate');
    if (!container || !template) return;

    const row = template.content.firstElementChild.cloneNode(true);
    if (isIngredient) {
        row.querySelector('[data-field="name"]').value = data.nama_bahan || '';
        row.querySelector('[data-field="amount"]').value = data.takaran || '';
    } else {
        row.querySelector('[data-field="description"]').value =
            data.deskripsi_langkah ?? data.keterangan ?? '';
    }
    container.appendChild(row);
    updateAdminStepNumbers();
}

function updateAdminStepNumbers() {
    document.querySelectorAll('#adminInstructions .step-number').forEach((number, index) => {
        number.textContent = String(index + 1);
    });
}

function resetAdminRecipeForm() {
    const form = document.getElementById('adminRecipeForm');
    const ingredients = document.getElementById('adminIngredients');
    const instructions = document.getElementById('adminInstructions');
    if (!form || !ingredients || !instructions) return;

    form.reset();
    document.getElementById('adminRecipeId').value = '';
    ingredients.replaceChildren();
    instructions.replaceChildren();
    appendAdminFieldRow('ingredients');
    appendAdminFieldRow('instructions');
    document.getElementById('recipeFormTitle').textContent = 'Tambah resep';
    document.getElementById('saveRecipeBtn').innerHTML = '<i class="fas fa-floppy-disk"></i> Simpan resep';
    document.getElementById('cancelEditBtn').classList.add('hidden');
    document.getElementById('adminUploadGroup').classList.remove('hidden');
}

function renderAdminRecipes() {
    const list = document.getElementById('adminRecipeList');
    const count = document.getElementById('adminRecipeCount');
    const query = (document.getElementById('adminSearch')?.value || '').trim().toLocaleLowerCase('id');
    if (!list || !count) return;

    const filtered = adminRecipes.filter((recipe) =>
        String(recipe.judul_resep || '').toLocaleLowerCase('id').includes(query)
    );
    count.textContent = `${filtered.length} dari ${adminRecipes.length} resep`;
    if (!filtered.length) {
        list.innerHTML = '<p class="admin-empty">Resep tidak ditemukan.</p>';
        return;
    }

    list.innerHTML = filtered.map((recipe) => `
        <article class="admin-recipe-item">
            <div>
                <h3>${escapeHTML(recipe.judul_resep)}</h3>
                <p>${escapeHTML(displayCategory(recipe.kategori || ''))} · ${escapeHTML(recipe.waktu_estimasi)} menit</p>
            </div>
            <div class="admin-recipe-actions">
                <button type="button" class="icon-action" data-edit-recipe="${escapeHTML(recipe.id_resep)}" aria-label="Ubah ${escapeHTML(recipe.judul_resep)}"><i class="fas fa-pen"></i></button>
                <button type="button" class="icon-action remove-row-btn" data-delete-recipe="${escapeHTML(recipe.id_resep)}" aria-label="Hapus ${escapeHTML(recipe.judul_resep)}"><i class="fas fa-trash"></i></button>
            </div>
        </article>`).join('');
}

let adminRecipes = [];

async function loadAdminRecipes() {
    const list = document.getElementById('adminRecipeList');
    if (list) list.innerHTML = '<p class="admin-empty">Memuat resep...</p>';
    const result = await requestJson('search.php');
    adminRecipes = result.data || [];
    renderAdminRecipes();
}

async function editAdminRecipe(id) {
    const result = await requestJson(`detail.php?id=${encodeURIComponent(id)}`);
    const detail = result.data || result;
    const recipe = detail.resep || {};

    resetAdminRecipeForm();
    document.getElementById('adminRecipeId').value = recipe.id_resep;
    document.getElementById('adminTitle').value = recipe.judul_resep || '';
    document.getElementById('adminTime').value = recipe.waktu_estimasi ?? 0;
    document.getElementById('adminCategory').value = recipe.kategori || 'Makan';
    document.getElementById('adminDifficulty').value = recipe.tingkat_kesulitan || 'Mudah';
    document.getElementById('adminDescription').value = recipe.deskripsi || '';
    document.getElementById('adminImageUrl').value = recipe.gambar_url || '';

    const ingredients = document.getElementById('adminIngredients');
    const instructions = document.getElementById('adminInstructions');
    ingredients.replaceChildren();
    instructions.replaceChildren();
    (detail.bahan || []).forEach((item) => appendAdminFieldRow('ingredients', item));
    (detail.instruksi || detail.langkah || []).forEach((item) => appendAdminFieldRow('instructions', item));
    if (!ingredients.children.length) appendAdminFieldRow('ingredients');
    if (!instructions.children.length) appendAdminFieldRow('instructions');

    document.getElementById('recipeFormTitle').textContent = 'Ubah resep';
    document.getElementById('saveRecipeBtn').innerHTML = '<i class="fas fa-floppy-disk"></i> Simpan perubahan';
    document.getElementById('cancelEditBtn').classList.remove('hidden');
    document.getElementById('adminUploadGroup').classList.remove('hidden');
    document.getElementById('adminRecipeForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function initAdminPage() {
    const workspace = document.getElementById('adminWorkspace');
    const form = document.getElementById('adminRecipeForm');
    if (!workspace || !form) return;

    document.getElementById('adminLogout').addEventListener('click', async () => {
        try {
            await requestJson('auth/logout.php', { method: 'POST' });
            localStorage.removeItem(TOKEN_KEY);
            localStorage.removeItem('resep_saku_user');
            savedRecipeIds.clear();
            window.location.href = 'login.html';
        } catch (error) {
            window.alert(error.message);
        }
    });

    document.querySelectorAll('[data-add-row]').forEach((button) => {
        button.addEventListener('click', () => appendAdminFieldRow(button.dataset.addRow));
    });
    document.getElementById('adminIngredients').addEventListener('click', (event) => {
        if (event.target.closest('.remove-row-btn')) {
            event.target.closest('.admin-ingredient-row').remove();
            if (!document.querySelector('#adminIngredients .admin-ingredient-row')) appendAdminFieldRow('ingredients');
        }
    });
    document.getElementById('adminInstructions').addEventListener('click', (event) => {
        if (event.target.closest('.remove-row-btn')) {
            event.target.closest('.admin-instruction-row').remove();
            if (!document.querySelector('#adminInstructions .admin-instruction-row')) appendAdminFieldRow('instructions');
        }
    });
    document.getElementById('adminRecipeList').addEventListener('click', async (event) => {
        const editButton = event.target.closest('[data-edit-recipe]');
        const deleteButton = event.target.closest('[data-delete-recipe]');
        try {
            if (editButton) {
                await editAdminRecipe(editButton.dataset.editRecipe);
            } else if (deleteButton) {
                const recipe = adminRecipes.find((item) => String(item.id_resep) === deleteButton.dataset.deleteRecipe);
                if (!recipe || !window.confirm(`Hapus resep "${recipe.judul_resep}"?`)) return;
                await requestJson('admin/delete_resep.php', {
                    method: 'POST',
                    body: JSON.stringify({ id_resep: Number(deleteButton.dataset.deleteRecipe) }),
                });
                showAdminMessage('Resep berhasil dihapus.');
                if (document.getElementById('adminRecipeId').value === deleteButton.dataset.deleteRecipe) {
                    resetAdminRecipeForm();
                }
                await loadAdminRecipes();
            }
        } catch (error) {
            showAdminMessage(error.message, true);
        }
    });
    document.getElementById('adminSearch').addEventListener('input', renderAdminRecipes);
    document.getElementById('refreshRecipes').addEventListener('click', () => {
        loadAdminRecipes().catch((error) => showAdminMessage(error.message, true));
    });
    document.getElementById('resetRecipeForm').addEventListener('click', resetAdminRecipeForm);
    document.getElementById('cancelEditBtn').addEventListener('click', resetAdminRecipeForm);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const recipeId = document.getElementById('adminRecipeId').value;
        const ingredients = Array.from(document.querySelectorAll('#adminIngredients .admin-ingredient-row'), (row) => ({
            nama_bahan: row.querySelector('[data-field="name"]').value.trim(),
            takaran: row.querySelector('[data-field="amount"]').value.trim(),
        }));
        const instructions = Array.from(document.querySelectorAll('#adminInstructions .admin-instruction-row'), (row, index) => ({
            langkah_ke: index + 1,
            deskripsi_langkah: row.querySelector('[data-field="description"]').value.trim(),
        }));
        const payload = {
            judul_resep: document.getElementById('adminTitle').value.trim(),
            waktu_estimasi: Number(document.getElementById('adminTime').value),
            kategori: document.getElementById('adminCategory').value,
            tingkat_kesulitan: document.getElementById('adminDifficulty').value,
            deskripsi: document.getElementById('adminDescription').value.trim(),
            bahan: ingredients,
            instruksi: instructions,
        };
        const imageUrl = document.getElementById('adminImageUrl').value.trim();
        const imageFile = document.getElementById('adminImageFile').files[0];
        if (imageUrl) payload.gambar_url = imageUrl;
        const requestPayload = recipeId
            ? { ...payload, id_resep: Number(recipeId) }
            : payload;
        let requestBody = JSON.stringify(requestPayload);
        if (imageFile) {
            const multipart = new FormData();
            multipart.append('payload', JSON.stringify(requestPayload));
            multipart.append('gambar', imageFile);
            requestBody = multipart;
        }

        setFormBusy(form, true);
        try {
            if (recipeId) {
                await requestJson('admin/update_resep.php', {
                    method: 'POST',
                    body: requestBody,
                });
            } else {
                await requestJson('admin/add_resep.php', {
                    method: 'POST',
                    body: requestBody,
                });
            }
            showAdminMessage(recipeId ? 'Resep berhasil diperbarui.' : 'Resep berhasil ditambahkan.');
            resetAdminRecipeForm();
            await loadAdminRecipes();
        } catch (error) {
            showAdminMessage(error.message, true);
        } finally {
            setFormBusy(form, false);
        }
    });

    resetAdminRecipeForm();
    document.getElementById('adminRecipeList').innerHTML = '<p class="admin-empty">Memuat resep...</p>';
    if (!getToken()) {
        showAdminMessage('Silakan masuk dengan akun administrator untuk membuka halaman ini.', true, true);
        return;
    }

    requestJson('auth/me.php')
        .then((result) => {
            if (result.data?.role !== 'admin') {
                showAdminMessage('Akun ini tidak memiliki akses administrator.', true);
                return;
            }
            document.getElementById('adminStatus').classList.add('hidden');
            document.getElementById('adminLogout').classList.remove('hidden');
            workspace.classList.remove('hidden');
            return loadAdminRecipes();
        })
        .catch((error) => showAdminMessage(error.message, true, error.message.includes('Token')));
}

function initAuthPage() {
    const toggleButton = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    if (toggleButton && passwordInput) {
        toggleButton.addEventListener('click', () => {
            const visible = passwordInput.type === 'password';
            passwordInput.type = visible ? 'text' : 'password';
            toggleButton.setAttribute('aria-label', visible ? 'Sembunyikan password' : 'Tampilkan password');
        });
    }

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            setFormBusy(loginForm, true);
            try {
                const result = await requestJson('auth/login.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        username: document.getElementById('username').value.trim(),
                        password: passwordInput.value,
                    }),
                });
                localStorage.setItem(TOKEN_KEY, result.token);
                localStorage.setItem('resep_saku_user', JSON.stringify(result.user));
                window.location.href = 'index.html';
            } catch (error) {
                showFormMessage(loginForm, error.message, true);
            } finally {
                setFormBusy(loginForm, false);
            }
        });
    }

    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const password = document.getElementById('reg-password').value;
            const confirmation = document.getElementById('confirm-password').value;
            if (password !== confirmation) {
                showFormMessage(registerForm, 'Konfirmasi password tidak sama.', true);
                return;
            }
            setFormBusy(registerForm, true);
            try {
                const result = await requestJson('auth/register.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        username: document.getElementById('reg-username').value.trim(),
                        password,
                    }),
                });
                showFormMessage(registerForm, `${result.message} Mengalihkan ke halaman masuk...`);
                window.setTimeout(() => { window.location.href = 'login.html'; }, 900);
            } catch (error) {
                showFormMessage(registerForm, error.message, true);
            } finally {
                setFormBusy(registerForm, false);
            }
        });
    }
}

function initHomePage() {
    const search = document.getElementById('searchInput');
    const clear = document.getElementById('clearSearch');
    const retry = document.getElementById('retryBtn');

    if (search) {
        search.addEventListener('input', () => {
            if (clear) clear.hidden = !search.value;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadRecipes(search.value.trim(), activeCategory), 300);
        });
    }
    if (clear) {
        clear.addEventListener('click', () => {
            search.value = '';
            clear.hidden = true;
            loadRecipes('', activeCategory);
            search.focus();
        });
    }
    if (retry) retry.addEventListener('click', () => loadRecipes(search?.value.trim() || '', activeCategory));
    document.querySelectorAll('.nav-item[href="#searchInput"]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            search?.focus();
            search?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    document.querySelectorAll('.tab-btn').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach((tab) => tab.classList.remove('active'));
            button.classList.add('active');
            activeCategory = button.dataset.category === 'semua' ? '' : button.dataset.category;
            loadRecipes(search?.value.trim() || '', activeCategory);
        });
    });

    if (getToken()) {
        loadBookmarks().catch((error) => console.error('Gagal memuat status bookmark:', error));
    }
    loadRecipes();
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('adminRecipeForm')) initAdminPage();
    if (document.getElementById('recipesContainer')) {
        initHomePage();
    }

    if (document.getElementById('recipeTitle')) {
        const id = new URLSearchParams(window.location.search).get('id');
        if (id) loadDetail(id);
        else showDetailError('ID resep tidak ditemukan.');
    }

    if (document.getElementById('savedRecipes')) initSavedPage();
    initAccountNavigation();
    initAuthPage();
});
