// KELIKA progressive enhancements; Bootstrap JS is loaded separately when Vite assets are not built.

// Small progressive enhancements; core navigation and forms remain usable without JS.
document.addEventListener('click', (event) => {
    const confirmTarget = event.target.closest('[data-confirm]');
    if (confirmTarget && !window.confirm(confirmTarget.dataset.confirm || 'Are you sure?')) {
        event.preventDefault();
    }
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
