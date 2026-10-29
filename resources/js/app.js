import 'bootstrap';

// Small progressive enhancements; core navigation and forms remain usable without JS.
document.addEventListener('click', (event) => {
    const confirmTarget = event.target.closest('[data-confirm]');
    if (confirmTarget && !window.confirm(confirmTarget.dataset.confirm || 'Are you sure?')) {
        event.preventDefault();
    }
});

// Media uploads: show the chosen file's name and size straight away, and flag a file over the upload limit before it
// is sent. The browser then refuses to submit it; the server's own validation stays authoritative either way.
function formatBytes(bytes) {
    if (bytes < 1024) return `${bytes} bytes`;
    if (bytes < 1048576) return `${Math.round(bytes / 1024)} KB`;
    return `${Math.round(bytes / 104857.6) / 10} MB`; // same rounding as MediaAsset::formatBytes()
}

function describeSelectedFile(input) {
    const info = document.getElementById(input.dataset.fileSize);
    if (!info) return;
    const file = input.files?.[0];
    const max = Number(input.dataset.maxBytes) || 0;
    const tooLarge = Boolean(file && max && file.size > max);
    const message = tooLarge ? `${file.name} is ${formatBytes(file.size)}, which is over the ${input.dataset.maxLabel} upload limit. Choose a smaller file.` : '';
    input.setCustomValidity(message);
    input.classList.toggle('is-invalid', tooLarge);
    info.classList.toggle('text-danger', tooLarge);
    info.textContent = !file ? '' : (tooLarge ? message : `Selected: ${file.name} · ${formatBytes(file.size)}`);
}

document.addEventListener('change', (event) => {
    if (event.target.matches?.('input[type="file"][data-file-size]')) describeSelectedFile(event.target);
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest?.('[data-upload-form]');
    const input = form?.querySelector('input[type="file"][data-file-size]');
    const file = input?.files?.[0];
    if (!file || event.defaultPrevented || !form.checkValidity()) return;
    // The upload is a normal form post (no progress events), so keep the file visible while it is sent.
    const info = document.getElementById(input.dataset.fileSize);
    if (info) info.textContent = `Uploading ${file.name} · ${formatBytes(file.size)}…`;
    form.querySelectorAll('button:not([type="button"])').forEach((button) => { button.disabled = true; });
});

// A file kept by the browser after going back to the page is described too, and its buttons work again.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-upload-form] button:disabled').forEach((button) => { button.disabled = false; });
    document.querySelectorAll('input[type="file"][data-file-size]').forEach(describeSelectedFile);
});

// Colour settings: the picker and the hex box mirror each other. Only the hex box is submitted, so the form works
// without JavaScript and the server validates exactly what was typed.
document.addEventListener('input', (event) => {
    const field = event.target.closest?.('[data-color-field]');
    if (!field) return;
    const picker = field.querySelector('[data-color-picker]');
    const hex = field.querySelector('[data-color-hex]');
    if (event.target === picker) hex.value = picker.value;
    else if (event.target === hex && /^#[0-9a-fA-F]{6}$/.test(hex.value.trim())) picker.value = hex.value.trim().toLowerCase();
});

// Range inputs with a percentage readout (e.g. the hero image overlay darkness).
document.addEventListener('input', (event) => {
    const output = event.target.dataset?.rangeOutput && document.getElementById(event.target.dataset.rangeOutput);
    if (output) output.textContent = `${event.target.value}%`;
});

const itemFieldSets = {
    hero: ['title', 'value', 'icon'],
    card_grid: ['title', 'description', 'image', 'mobile_image', 'image_alt', 'image_position', 'url', 'icon', 'color'],
    split: ['title', 'description', 'icon', 'color', 'image', 'image_alt', 'image_position', 'url'],
    feature_list: ['title', 'description', 'icon', 'color', 'url'],
    five_elements: ['title', 'keyword', 'description', 'color', 'icon', 'image', 'image_alt'],
    stats: ['value', 'label'],
    faq: ['question', 'answer'],
    gallery: ['image', 'mobile_image', 'image_alt', 'image_position', 'caption'],
    testimonials: ['quote', 'name', 'role', 'image', 'image_alt'],
};

// Character counters for text fields with data-char-count="<counter id>" (e.g. the contact message): "12 / 5000".
document.addEventListener('input', (event) => {
    const field = event.target.closest?.('[data-char-count]');
    if (!field) return;
    const counter = document.getElementById(field.dataset.charCount);
    if (counter) counter.textContent = `${field.value.length} / ${field.maxLength > 0 ? field.maxLength : ''}`.trim();
});

// Testimonial carousel arrows scroll the track by one card; the track remains natively scrollable without JS.
document.addEventListener('click', (event) => {
    const control = event.target.closest('[data-carousel-prev], [data-carousel-next]');
    if (!control) return;
    const track = control.closest('[data-carousel]')?.querySelector('[data-carousel-track]');
    const card = track?.firstElementChild;
    if (!track || !card) return;
    const step = card.getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap || '0');
    track.scrollBy({ left: control.hasAttribute('data-carousel-prev') ? -step : step, behavior: 'smooth' });
});

// Disable an arrow when the track cannot scroll further in that direction.
function updateCarouselControls(track) {
    const carousel = track.closest('[data-carousel]');
    const maxScroll = track.scrollWidth - track.clientWidth - 1;
    const prev = carousel?.querySelector('[data-carousel-prev]');
    const next = carousel?.querySelector('[data-carousel-next]');
    if (prev) prev.disabled = track.scrollLeft <= 0;
    if (next) next.disabled = track.scrollLeft >= maxScroll;
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-carousel-track]').forEach((track) => {
        updateCarouselControls(track);
        track.addEventListener('scroll', () => updateCarouselControls(track), { passive: true });
        window.addEventListener('resize', () => updateCarouselControls(track));
    });
});

// Media library picker for section images. The dialog lists images from the library (small WebP thumbnails, loaded
// a page at a time when opened); picking one fills the field's path box with the asset's own media/… path. Typed
// paths keep working, and the server checks every picked or newly entered media path against the library.
let mediaPickerInstance;
function mediaPicker() {
    if (mediaPickerInstance !== undefined) return mediaPickerInstance;
    const dialog = document.querySelector('#media-picker');
    if (!dialog || typeof dialog.showModal !== 'function') return (mediaPickerInstance = null);

    let previews = {};
    try { previews = JSON.parse(document.querySelector('#section-image-previews')?.textContent || '{}') || {}; } catch { /* Previews are optional. */ }
    const grid = dialog.querySelector('[data-picker-grid]');
    const status = dialog.querySelector('[data-picker-status]');
    const more = dialog.querySelector('[data-picker-more]');
    const search = dialog.querySelector('[data-picker-search]');
    const state = { query: '', page: 1, current: '', onPick: null, loading: false, request: 0 };

    const load = async (reset) => {
        if (state.loading && !reset) return;
        const request = ++state.request;
        if (reset) {
            state.page = 1;
            grid.replaceChildren();
        }
        state.loading = true;
        more.hidden = true;
        status.textContent = 'Loading images…';
        try {
            const url = new URL(dialog.dataset.endpoint, window.location.href);
            url.searchParams.set('page', String(state.page));
            if (state.query) url.searchParams.set('q', state.query);
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error(String(response.status));
            const result = await response.json();
            if (request !== state.request) return;
            result.data.forEach((option) => grid.append(renderOption(option)));
            state.page = result.next_page || state.page;
            more.hidden = !result.next_page;
            status.textContent = grid.children.length ? '' : (state.query ? 'No images match this search.' : 'No images in the media library yet.');
        } catch {
            if (request === state.request) status.textContent = 'Images could not be loaded. Check that you are still signed in, then try again.';
        } finally {
            if (request === state.request) state.loading = false;
        }
    };

    const renderOption = (option) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'media-picker__option';
        button.setAttribute('aria-pressed', String(option.path === state.current));
        const thumb = document.createElement('span');
        thumb.className = 'media-picker__thumb';
        if (option.thumb) {
            const img = document.createElement('img');
            img.loading = 'lazy';
            img.decoding = 'async';
            img.alt = '';
            if (option.thumb_srcset) {
                img.srcset = option.thumb_srcset;
                img.sizes = '(max-width: 575px) 45vw, 180px';
            }
            img.src = option.thumb;
            thumb.append(img);
        }
        const name = document.createElement('span');
        name.className = 'media-picker__name';
        name.textContent = option.name;
        const meta = document.createElement('span');
        meta.className = 'media-picker__meta';
        meta.textContent = [describeImage(option), option.uploaded].filter(Boolean).join(' · ');
        button.append(thumb, name, meta);
        if (option.alt) {
            const alt = document.createElement('span');
            alt.className = 'media-picker__meta';
            alt.textContent = `Alt: ${option.alt}`;
            button.append(alt);
        }
        button.title = option.path;
        button.addEventListener('click', () => {
            previews[option.path] = { source: 'library', ...option };
            const onPick = state.onPick;
            dialog.close();
            onPick?.(option);
        });
        return button;
    };

    search.addEventListener('submit', (event) => {
        event.preventDefault();
        state.query = search.elements.q.value.trim();
        load(true);
    });
    more.addEventListener('click', () => load(false));
    dialog.querySelector('[data-picker-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => { state.onPick = null; });

    return (mediaPickerInstance = {
        previews,
        open(current, onPick) {
            state.current = current;
            state.onPick = onPick;
            dialog.showModal();
            // Reloaded on every opening, so images uploaded in another tab meanwhile are listed too.
            search.elements.q.value = '';
            state.query = '';
            load(true);
        },
    });
}

function describeImage(option) {
    return [option.width && option.height ? `${option.width} × ${option.height}` : null, option.type, option.size].filter(Boolean).join(' · ');
}

/** Shows a field's current image (thumbnail, name, details) and wires its Choose / Clear buttons. */
function bindImagePick(root, pathInput, { idInput = null, optional = true } = {}) {
    const picker = mediaPicker();
    if (!picker) return;
    const thumb = root.querySelector('[data-pick-thumb]');
    const name = root.querySelector('[data-pick-name]');
    const meta = root.querySelector('[data-pick-meta]');
    const clear = root.querySelector('[data-pick-clear]');
    let picking = false;

    const show = (value) => {
        const preview = value ? (picker.previews[value] || { source: 'custom', name: value }) : null;
        thumb.replaceChildren();
        if (preview?.thumb) {
            const img = document.createElement('img');
            img.alt = '';
            img.decoding = 'async';
            if (preview.thumb_srcset) {
                img.srcset = preview.thumb_srcset;
                img.sizes = '96px';
            }
            img.src = preview.thumb;
            thumb.append(img);
        }
        name.textContent = preview ? preview.name : 'No image selected';
        meta.textContent = !preview ? 'No image selected'
            : preview.source === 'library' ? describeImage(preview)
            : preview.source === 'missing' ? 'Not an available media library image — choose another image.'
            : 'Custom path or URL';
        meta.className = preview?.source === 'missing' ? 'text-danger' : 'text-secondary';
        if (clear) clear.hidden = !optional || !value;
    };

    root.querySelector('[data-pick-controls]').hidden = false;
    root.querySelector('[data-pick-open]').addEventListener('click', () => {
        picker.open(pathInput.value.trim(), (option) => {
            pathInput.value = option.path;
            if (idInput) idInput.value = String(option.id);
            show(option.path);
            picking = true; // let other listeners (the items editor) see the change without treating it as typed
            pathInput.dispatchEvent(new Event('input', { bubbles: true }));
            picking = false;
        });
    });
    clear?.addEventListener('click', () => {
        pathInput.value = '';
        if (idInput) idInput.value = '';
        show('');
        pathInput.dispatchEvent(new Event('input', { bubbles: true }));
    });
    pathInput.addEventListener('input', () => {
        if (picking) return;
        if (idInput) idInput.value = ''; // a typed path replaces the picked image
        show(pathInput.value.trim());
    });
    show(pathInput.value.trim());
}

/** Builds the preview and buttons for an image field the items editor creates. */
function createImagePick() {
    const root = document.createElement('div');
    root.className = 'media-pick media-pick--item mt-2';
    root.innerHTML = '<div class="media-pick__current"><div class="media-pick__thumb" data-pick-thumb></div><div class="media-pick__info"><strong data-pick-name></strong><span data-pick-meta></span></div></div>'
        + '<div class="d-flex flex-wrap gap-2 mt-2" data-pick-controls hidden><button type="button" class="btn btn-sm btn-outline-secondary" data-pick-open>Choose from library</button><button type="button" class="btn btn-sm btn-outline-danger" data-pick-clear hidden>Clear</button></div>';
    return root;
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-media-pick]').forEach((root) => {
        bindImagePick(root, root.querySelector('[data-pick-path]'), { idInput: root.querySelector('[data-pick-id]'), optional: root.dataset.optional === '1' });
    });
});

function initializeSectionItemsEditor() {
    const editor = document.querySelector('#items-editor');
    if (!editor) return;

    const sectionType = editor.dataset.sectionType;
    const fields = itemFieldSets[sectionType];
    if (!fields) {
        editor.hidden = true;
        return;
    }

    const source = editor.querySelector('#items_json');
    const fallback = document.querySelector('#items_json_fallback');
    const list = editor.querySelector('#section-items-list');
    const addButton = editor.querySelector('#add-section-item');
    let items = [];

    const parseItems = (value) => {
        const parsed = JSON.parse(value || '[]');
        if (!Array.isArray(parsed) || parsed.some((item) => !item || typeof item !== 'object' || Array.isArray(item))) {
            throw new Error('Enter a JSON array of item objects.');
        }
        return parsed;
    };

    const sync = () => {
        items = Array.from(list.querySelectorAll('[data-item-row]')).map((row) => {
            const item = { ...(row._original || {}) };
            row.querySelectorAll('[data-item-field]').forEach((input) => {
                item[input.dataset.itemField] = input.value;
            });
            row._original = item;
            return item;
        });
        source.value = JSON.stringify(items, null, 2);
        if (fallback) fallback.value = source.value;
    };

    const render = () => {
        list.replaceChildren();
        items.forEach((item, index) => {
            const card = document.createElement('div');
            card.className = 'section-item-editor-card';
            card.dataset.itemRow = '1';
            card._original = { ...item };

            const heading = document.createElement('div');
            heading.className = 'd-flex justify-content-between align-items-center gap-2 mb-3';
            const title = document.createElement('strong');
            title.textContent = `${sectionType.replaceAll('_', ' ')} item ${index + 1}`;
            heading.append(title);

            const controls = document.createElement('div');
            controls.className = 'd-flex gap-1';
            [['↑', 'Move item up', -1], ['↓', 'Move item down', 1], ['Remove', 'Remove item', 'remove']].forEach(([label, aria, action]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = action === 'remove' ? 'btn btn-sm btn-outline-danger' : 'btn btn-sm btn-outline-secondary';
                button.textContent = label;
                button.setAttribute('aria-label', aria);
                button.addEventListener('click', () => {
                    sync();
                    if (action === 'remove') items.splice(index, 1);
                    else {
                        const destination = index + action;
                        if (destination < 0 || destination >= items.length) return;
                        [items[index], items[destination]] = [items[destination], items[index]];
                    }
                    render();
                });
                controls.append(button);
            });
            heading.append(controls);
            card.append(heading);

            const grid = document.createElement('div');
            grid.className = 'row g-3';
            fields.forEach((field) => {
                const wrapper = document.createElement('div');
                wrapper.className = ['description', 'answer', 'quote', 'caption'].includes(field) ? 'col-12' : 'col-md-6';
                const label = document.createElement('label');
                label.className = 'form-label';
                label.textContent = field.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
                const input = ['description', 'answer', 'quote', 'caption'].includes(field) ? document.createElement('textarea') : document.createElement('input');
                input.className = 'form-control';
                if (input.tagName === 'TEXTAREA') input.rows = field === 'quote' ? 3 : 2;
                else input.type = 'text';
                input.value = item[field] == null ? '' : String(item[field]);
                input.dataset.itemField = field;
                input.addEventListener('input', sync);
                label.append(input);
                wrapper.append(label);
                if (['image', 'mobile_image'].includes(field) && mediaPicker()) {
                    const pick = createImagePick();
                    wrapper.append(pick);
                    bindImagePick(pick, input);
                }
                grid.append(wrapper);
            });
            card.append(grid);
            list.append(card);
        });
        sync();
    };

    try {
        items = parseItems(source.value);
    } catch {
        editor.hidden = true;
        return;
    }
    render();
    editor.classList.add('js-ready');
    if (fallback) fallback.value = source.value;

    addButton?.addEventListener('click', () => {
        sync();
        items.push({});
        render();
        const firstInput = list.querySelector('[data-item-row]:last-child [data-item-field]');
        firstInput?.focus();
    });

    document.querySelector('#apply-items-json')?.addEventListener('click', () => {
        try {
            items = parseItems(fallback.value);
            render();
        } catch (error) {
            window.alert(error.message);
        }
    });

    document.querySelector('#section-editor-form')?.addEventListener('submit', sync);
}

document.addEventListener('DOMContentLoaded', initializeSectionItemsEditor);

const contentFieldSets = {
    sport: ['eyebrow', 'tagline', 'description', 'image', 'mobile_image', 'image_alt', 'image_position', 'url', 'features_json'],
    program: ['eyebrow', 'description', 'summary', 'age_range', 'icon', 'image', 'mobile_image', 'image_alt', 'image_position', 'url', 'features_json'],
    facility: ['category', 'short_label', 'icon', 'description', 'summary', 'image', 'mobile_image', 'image_alt', 'image_position', 'url'],
    event: ['eyebrow', 'date_label', 'description', 'summary', 'image', 'mobile_image', 'image_alt', 'image_position', 'location', 'url', 'registration_url', 'featured', 'demo'],
    coach: ['role', 'bio', 'qualifications', 'specialties', 'image', 'mobile_image', 'image_alt', 'image_position'],
    faq: ['question', 'answer'],
    gallery: ['image', 'mobile_image', 'image_alt', 'image_position', 'caption'],
    testimonial: ['quote', 'name', 'role', 'relationship', 'image', 'image_alt', 'demo'],
};

function initializeContentDataEditor() {
    const editor = document.querySelector('#content-data-editor');
    if (!editor) return;

    const type = editor.dataset.contentType;
    const fields = contentFieldSets[type];
    const source = editor.querySelector('#data_json');
    const fallback = document.querySelector('#data_json_fallback');
    const fieldsContainer = editor.querySelector('#content-data-fields');
    if (!fields) {
        editor.hidden = true;
        return;
    }

    let data;
    try {
        data = JSON.parse(source.value || '{}');
        if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('Content data must be a JSON object.');
    } catch {
        editor.hidden = true;
        return;
    }

    const imagePositions = ['center', 'top', 'bottom', 'left', 'right', 'center top', 'center bottom', 'left center', 'right center'];
    const longTextFields = ['description', 'summary', 'bio', 'answer', 'quote', 'caption', 'features_json'];
    const booleanFields = { featured: 'Mark as featured event', demo: 'Demo content — unverified, shown only in CMS preview' };

    const sync = () => {
        fieldsContainer.querySelectorAll('[data-content-field]').forEach((input) => {
            const field = input.dataset.contentField;
            if (field === 'features_json') {
                const parsed = JSON.parse(input.value || '[]');
                if (!Array.isArray(parsed)) throw new Error('Features must be a JSON array.');
                data.features = parsed;
            } else if (booleanFields[field]) {
                data[field] = input.checked;
            } else {
                data[field] = input.value;
            }
        });
        source.value = JSON.stringify(data, null, 2);
        if (fallback) fallback.value = source.value;
    };

    fields.forEach((field) => {
        const wrapper = document.createElement('div');
        wrapper.className = longTextFields.includes(field) ? 'col-12' : 'col-md-6';
        const label = document.createElement('label');
        label.className = 'form-label';
        label.textContent = field === 'features_json' ? 'Features (JSON array)' : field.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

        let input;
        if (booleanFields[field]) {
            const checkWrap = document.createElement('div');
            checkWrap.className = 'form-check mt-2';
            input = document.createElement('input');
            input.type = 'checkbox';
            input.className = 'form-check-input';
            input.checked = Boolean(data[field]);
            input.dataset.contentField = field;
            const checkLabel = document.createElement('label');
            checkLabel.className = 'form-check-label';
            checkLabel.textContent = booleanFields[field];
            checkLabel.prepend(input);
            checkWrap.append(checkLabel);
            wrapper.append(checkWrap);
        } else if (field === 'image_position') {
            input = document.createElement('select');
            input.className = 'form-select';
            imagePositions.forEach((position) => {
                const option = document.createElement('option');
                option.value = position;
                option.textContent = position;
                option.selected = (data.image_position || 'center') === position;
                input.append(option);
            });
            input.dataset.contentField = field;
            label.append(input);
            wrapper.append(label);
        } else {
            input = longTextFields.includes(field) ? document.createElement('textarea') : document.createElement('input');
            input.className = 'form-control';
            if (input.tagName === 'TEXTAREA') input.rows = field === 'features_json' ? 5 : 3;
            else input.type = 'text';
            if (field === 'features_json') input.value = JSON.stringify(data.features || [], null, 2);
            else input.value = data[field] == null ? '' : String(data[field]);
            input.dataset.contentField = field;
            if (['image', 'mobile_image'].includes(field)) input.placeholder = 'Upload in Media Library and paste the media/... path';
            if (field === 'url' || field === 'registration_url') input.placeholder = '/contact or https://...';
            if (field === 'date_label') input.placeholder = 'e.g. 18 Dec — used for the date badge when no start date is set';
            label.append(input);
            wrapper.append(label);
        }
        if (field === 'features_json') {
            const hint = document.createElement('div');
            hint.className = 'form-text';
            hint.textContent = 'Each feature can include title, description, and icon. Keep the JSON valid.';
            wrapper.append(hint);
        }
        fieldsContainer.append(wrapper);
        input?.addEventListener('input', () => {
            try { sync(); } catch { /* Keep the user's in-progress JSON until it becomes valid. */ }
        });
        input?.addEventListener('change', () => {
            try { sync(); } catch { /* Keep the user's in-progress JSON until it becomes valid. */ }
        });
    });

    editor.classList.add('js-ready');
    if (fallback) fallback.value = source.value;

    document.querySelector('#content-item-form')?.addEventListener('submit', (event) => {
        try { sync(); } catch (error) { event.preventDefault(); window.alert(error.message); }
    });
}

document.addEventListener('DOMContentLoaded', initializeContentDataEditor);

// Hero height: expose the distance from the top of the page to the hero (header plus any notices) so CSS can size
// the hero to the viewport space below it. Re-measured on resize, orientation change and header size changes.
const hero = document.querySelector('.kl-hero');
if (hero) {
    const setHeroOffset = () => document.documentElement.style.setProperty('--kl-hero-offset', `${Math.round(hero.getBoundingClientRect().top + window.scrollY)}px`);
    setHeroOffset();
    window.addEventListener('resize', setHeroOffset);
    window.addEventListener('orientationchange', setHeroOffset);
    window.addEventListener('load', setHeroOffset);
    if ('ResizeObserver' in window) {
        const header = document.querySelector('.site-header');
        if (header) new ResizeObserver(setHeroOffset).observe(header);
    }
}
