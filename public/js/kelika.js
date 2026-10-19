// KELIKA progressive enhancements; Bootstrap JS is loaded separately when Vite assets are not built.

// Small progressive enhancements; core navigation and forms remain usable without JS.
document.addEventListener('click', (event) => {
    const confirmTarget = event.target.closest('[data-confirm]');
    if (confirmTarget && !window.confirm(confirmTarget.dataset.confirm || 'Are you sure?')) {
        event.preventDefault();
    }
});

const itemFieldSets = {
    card_grid: ['title', 'description', 'image', 'mobile_image', 'image_alt', 'image_position', 'url', 'icon', 'color'],
    feature_list: ['title', 'description', 'icon', 'color'],
    five_elements: ['name', 'subtitle', 'description', 'color', 'icon'],
    stats: ['value', 'label'],
    faq: ['question', 'answer'],
    gallery: ['image', 'mobile_image', 'image_alt', 'caption'],
    testimonials: ['quote', 'name', 'role', 'image', 'image_alt'],
};

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
    program: ['eyebrow', 'description', 'summary', 'age_range', 'image', 'mobile_image', 'image_alt', 'image_position', 'url', 'features_json'],
    facility: ['category', 'description', 'summary', 'image', 'mobile_image', 'image_alt', 'image_position', 'url'],
    event: ['eyebrow', 'description', 'summary', 'image', 'mobile_image', 'image_alt', 'image_position', 'location', 'url', 'registration_url', 'featured'],
    coach: ['role', 'bio', 'qualifications', 'specialties', 'image', 'mobile_image', 'image_alt', 'image_position'],
    faq: ['question', 'answer'],
    gallery: ['image', 'mobile_image', 'image_alt', 'caption'],
    testimonial: ['quote', 'name', 'role', 'relationship', 'image', 'image_alt'],
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

    const sync = () => {
        fieldsContainer.querySelectorAll('[data-content-field]').forEach((input) => {
            const field = input.dataset.contentField;
            if (field === 'features_json') {
                const parsed = JSON.parse(input.value || '[]');
                if (!Array.isArray(parsed)) throw new Error('Features must be a JSON array.');
                data.features = parsed;
            } else if (field === 'featured') {
                data.featured = input.checked;
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
        if (field === 'featured') {
            const checkWrap = document.createElement('div');
            checkWrap.className = 'form-check mt-2';
            input = document.createElement('input');
            input.type = 'checkbox';
            input.className = 'form-check-input';
            input.checked = Boolean(data.featured);
            input.dataset.contentField = field;
            const checkLabel = document.createElement('label');
            checkLabel.className = 'form-check-label';
            checkLabel.textContent = 'Mark as featured event';
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
