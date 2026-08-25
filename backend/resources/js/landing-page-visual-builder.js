import Sortable from 'sortablejs';

const STYLE_KEYS = [
    'color',
    'backgroundColor',
    'fontSize',
    'fontWeight',
    'fontFamily',
    'textAlign',
    'padding',
    'margin',
    'width',
    'height',
    'border',
    'borderRadius',
    'boxShadow',
    'visibility',
    'animation',
];

const CONTAINER_TYPES = new Set(['section', 'container', 'row', 'column']);

function uuid() {
    return `lp_${crypto.randomUUID?.() ?? Date.now().toString(36) + Math.random().toString(36).slice(2)}`;
}

function deepClone(value) {
    return JSON.parse(JSON.stringify(value));
}

function groupByCategory(definitions) {
    return Object.entries(definitions).reduce((acc, [type, def]) => {
        const cat = def.category || 'Other';
        if (!acc[cat]) acc[cat] = [];
        acc[cat].push({ type, ...def });
        return acc;
    }, {});
}

function findNodeContext(nodes, id, parent = null) {
    for (let i = 0; i < nodes.length; i++) {
        const node = nodes[i];
        if (node.id === id) {
            return { node, parent, index: i, list: nodes };
        }
        if (Array.isArray(node.children) && node.children.length) {
            const found = findNodeContext(node.children, id, node);
            if (found) return found;
        }
    }
    return null;
}

function removeNodeFromTree(nodes, id) {
    const ctx = findNodeContext(nodes, id);
    if (!ctx) return null;
    const [removed] = ctx.list.splice(ctx.index, 1);
    return removed;
}

function insertNode(nodes, node, parentId, index = null) {
    if (!parentId) {
        if (index === null || index >= nodes.length) {
            nodes.push(node);
        } else {
            nodes.splice(index, 0, node);
        }
        return;
    }
    const ctx = findNodeContext(nodes, parentId);
    if (!ctx || !CONTAINER_TYPES.has(ctx.node.type)) {
        nodes.push(node);
        return;
    }
    if (!Array.isArray(ctx.node.children)) ctx.node.children = [];
    if (index === null || index >= ctx.node.children.length) {
        ctx.node.children.push(node);
    } else {
        ctx.node.children.splice(index, 0, node);
    }
}

function createNode(type, definitions, defaultStyles) {
    const def = definitions[type] || {};
    return {
        id: uuid(),
        type,
        props: deepClone(def.defaultProps || {}),
        styles: deepClone(defaultStyles),
        children: CONTAINER_TYPES.has(type) ? [] : undefined,
    };
}

function nodeLabel(node, definitions) {
    const def = definitions[node.type];
    const base = def?.label || node.type;
    const text = node.props?.text || node.props?.title || node.props?.label;
    return text ? `${base}: ${String(text).slice(0, 28)}` : base;
}

function previewHtml(node) {
    const p = node.props || {};
    switch (node.type) {
        case 'heading':
            return `<${p.level || 'h2'} style="margin:0">${escapeHtml(p.text || '')}</${p.level || 'h2'}>`;
        case 'text':
            return `<p style="margin:0">${escapeHtml(p.text || '').replace(/\n/g, '<br>')}</p>`;
        case 'button':
            return `<span style="display:inline-block;padding:8px 16px;background:#f59e0b;border-radius:6px">${escapeHtml(p.text || 'Button')}</span>`;
        case 'image':
            return p.src
                ? `<img src="${escapeAttr(p.src)}" alt="${escapeAttr(p.alt || '')}" style="max-width:100%;height:auto" />`
                : '<em>Image — set URL</em>';
        case 'video':
            return p.url ? `<em>Video: ${escapeHtml(p.url)}</em>` : '<em>Video — set URL</em>';
        case 'divider':
            return '<hr style="border:0;border-top:1px solid #e5e7eb" />';
        case 'spacer':
            return `<div style="height:${p.height || '32px'};background:#f1f5f9;border-radius:4px"></div>`;
        case 'icon':
            return `<span style="font-size:${p.size || '24px'}">${escapeHtml(p.icon || '★')}</span>`;
        case 'list': {
            const tag = p.ordered ? 'ol' : 'ul';
            const items = (p.items || []).map((i) => `<li>${escapeHtml(i)}</li>`).join('');
            return `<${tag} style="margin:0;padding-left:1.25rem">${items}</${tag}>`;
        }
        case 'countdown':
            return `<strong>${escapeHtml(p.label || 'Countdown')}</strong> — ${escapeHtml(p.endAt || 'Set end date')}`;
        case 'progress_bar':
            return `<div>${escapeHtml(p.label || 'Progress')} (${p.value ?? 0}%)</div>`;
        case 'faq':
            return `<em>${(p.items || []).length} FAQ item(s)</em>`;
        case 'testimonials':
            return `<em>${(p.items || []).length} testimonial(s)</em>`;
        case 'reviews':
            return `★ ${p.rating ?? 5}/5 — ${p.count ?? 0} reviews`;
        case 'trust_badges':
            return (p.badges || []).map((b) => `<span style="margin-right:6px;padding:2px 8px;background:#f3f4f6;border-radius:999px;font-size:11px">${escapeHtml(b)}</span>`).join('');
        case 'guarantee_box':
            return `<strong>${escapeHtml(p.title || '')}</strong><br>${escapeHtml(p.text || '')}`;
        case 'html_block':
            return p.html || '<em>HTML block</em>';
        case 'custom_css':
            return '<em>Custom CSS (applied on save)</em>';
        case 'custom_js':
            return '<em>Custom JS (applied on save)</em>';
        case 'section':
        case 'container':
        case 'row':
        case 'column':
            return `<em>${node.type} — drop components here</em>`;
        default:
            return escapeHtml(p.text || node.type);
    }
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function escapeAttr(str) {
    return escapeHtml(str).replace(/'/g, '&#39;');
}

function propFieldsForType(type) {
    const common = [];
    const map = {
        heading: [
            { key: 'text', label: 'Text', kind: 'text' },
            { key: 'level', label: 'Level', kind: 'select', options: ['h1', 'h2', 'h3', 'h4'] },
        ],
        text: [{ key: 'text', label: 'Text', kind: 'textarea' }],
        button: [
            { key: 'text', label: 'Label', kind: 'text' },
            { key: 'url', label: 'URL', kind: 'text' },
            { key: 'target', label: 'Target', kind: 'select', options: ['_self', '_blank'] },
        ],
        image: [
            { key: 'src', label: 'Image URL', kind: 'text' },
            { key: 'alt', label: 'Alt text', kind: 'text' },
        ],
        video: [
            { key: 'url', label: 'Video URL', kind: 'text' },
            { key: 'provider', label: 'Provider', kind: 'select', options: ['youtube', 'file'] },
        ],
        spacer: [{ key: 'height', label: 'Height', kind: 'text' }],
        icon: [
            { key: 'icon', label: 'Icon / character', kind: 'text' },
            { key: 'size', label: 'Size', kind: 'text' },
        ],
        list: [
            { key: 'items', label: 'Items (one per line)', kind: 'lines' },
            { key: 'ordered', label: 'Ordered list', kind: 'checkbox' },
        ],
        countdown: [
            { key: 'label', label: 'Label', kind: 'text' },
            { key: 'endAt', label: 'End date/time', kind: 'text', placeholder: '2026-12-31T23:59:59' },
        ],
        progress_bar: [
            { key: 'label', label: 'Label', kind: 'text' },
            { key: 'value', label: 'Value (0-100)', kind: 'number' },
        ],
        faq: [{ key: 'items', label: 'FAQ JSON', kind: 'json', hint: '[{"q":"Question?","a":"Answer."}]' }],
        testimonials: [{ key: 'items', label: 'Testimonials JSON', kind: 'json' }],
        reviews: [
            { key: 'label', label: 'Label', kind: 'text' },
            { key: 'rating', label: 'Rating (1-5)', kind: 'number' },
            { key: 'count', label: 'Review count', kind: 'number' },
        ],
        trust_badges: [{ key: 'badges', label: 'Badges (one per line)', kind: 'lines' }],
        guarantee_box: [
            { key: 'title', label: 'Title', kind: 'text' },
            { key: 'text', label: 'Text', kind: 'textarea' },
        ],
        html_block: [{ key: 'html', label: 'HTML', kind: 'textarea' }],
        custom_css: [{ key: 'css', label: 'CSS', kind: 'textarea' }],
        custom_js: [{ key: 'js', label: 'JavaScript', kind: 'textarea' }],
        container: [{ key: 'maxWidth', label: 'Max width', kind: 'text' }],
        row: [{ key: 'gap', label: 'Gap', kind: 'text' }],
        column: [{ key: 'width', label: 'Width', kind: 'text' }],
    };
    return [...(map[type] || []), ...common];
}

export function initLandingPageVisualBuilder(root, config) {
    if (!root || root.dataset.lpInitialized === '1') return;
    root.dataset.lpInitialized = '1';

    const definitions = config.definitions || {};
    const defaultStyles = config.defaultStyles || { desktop: {}, tablet: {}, mobile: {} };
    const wire = config.livewire;

    let document = deepClone(config.document || { version: 1, settings: {}, nodes: [] });
    let viewport = config.viewport || 'desktop';
    let selectedId = config.selectedNodeId || null;
    let saveTimer = null;
    let sortables = [];

    const els = {
        palette: root.querySelector('[data-lp-palette]'),
        canvas: root.querySelector('[data-lp-canvas]'),
        dropzone: root.querySelector('[data-lp-dropzone]'),
        props: root.querySelector('[data-lp-props]'),
        status: root.querySelector('[data-lp-status]'),
        viewportButtons: root.querySelectorAll('[data-lp-viewport]'),
        preview: root.querySelector('[data-lp-preview-iframe]'),
    };

    function scheduleSave() {
        clearTimeout(saveTimer);
        if (els.status) {
            els.status.textContent = 'Saving…';
            els.status.classList.remove('is-saved');
        }
        saveTimer = setTimeout(() => {
            wire.saveBuilderDocument(document).then(() => {
                if (els.status) {
                    els.status.textContent = 'Saved';
                    els.status.classList.add('is-saved');
                }
                refreshPreview();
            });
        }, 1200);
    }

    function refreshPreview() {
        if (!els.preview) return;
        const base = els.preview.dataset.baseSrc || els.preview.getAttribute('src')?.split('?')[0];
        if (base) {
            els.preview.src = `${base}?v=${Date.now()}`;
        }
    }

    function renderPalette() {
        if (!els.palette) return;
        const grouped = groupByCategory(definitions);
        els.palette.innerHTML = Object.entries(grouped)
            .map(
                ([cat, items]) => `
            <div class="fi-lp-vb-category">
                <div class="fi-lp-vb-category-title">${escapeHtml(cat)}</div>
                ${items
                    .map(
                        (item) => `
                    <div class="fi-lp-vb-palette-item" draggable="true" data-lp-component="${item.type}">
                        <span>${escapeHtml(item.label)}</span>
                    </div>`,
                    )
                    .join('')}
            </div>`,
            )
            .join('');

        els.palette.querySelectorAll('[data-lp-component]').forEach((el) => {
            el.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/lp-component', el.dataset.lpComponent);
                e.dataTransfer.effectAllowed = 'copy';
            });
        });
    }

    function renderNode(node) {
        const isContainer = CONTAINER_TYPES.has(node.type);
        const selected = node.id === selectedId ? ' is-selected' : '';
        const children = isContainer ? node.children || [] : [];
        const childrenHtml = isContainer
            ? `<div class="fi-lp-vb-node-children fi-lp-vb-dropzone ${children.length ? '' : 'is-empty'}" data-lp-parent="${node.id}">${children.length ? '' : 'Drop components here'}</div>`
            : '';

        return `
            <div class="fi-lp-vb-node${selected}" data-lp-node="${node.id}" data-lp-type="${node.type}">
                <div class="fi-lp-vb-node-bar">
                    <span class="fi-lp-vb-node-label">${escapeHtml(nodeLabel(node, definitions))}</span>
                    <div class="fi-lp-vb-node-actions">
                        <button type="button" data-lp-action="up" title="Move up">↑</button>
                        <button type="button" data-lp-action="down" title="Move down">↓</button>
                        <button type="button" data-lp-action="duplicate" title="Duplicate">⧉</button>
                        <button type="button" data-lp-action="delete" title="Delete">✕</button>
                    </div>
                </div>
                <div class="fi-lp-vb-node-preview">${previewHtml(node)}</div>
                ${childrenHtml}
            </div>`;
    }

    function renderCanvas() {
        if (!els.dropzone) return;
        const nodes = document.nodes || [];
        els.dropzone.classList.toggle('is-empty', nodes.length === 0);
        els.dropzone.innerHTML = nodes.length
            ? nodes.map((n) => renderNode(n)).join('')
            : 'Drag components here to start building';

        if (nodes.length) {
            nodes.forEach((node) => {
                if (CONTAINER_TYPES.has(node.type) && node.children?.length) {
                    const zone = els.dropzone.querySelector(`[data-lp-parent="${node.id}"]`);
                    if (zone) {
                        zone.classList.remove('is-empty');
                        zone.innerHTML = node.children.map((c) => renderNode(c)).join('');
                        attachNestedZones(node.children, zone);
                    }
                }
            });
        }

        bindCanvasEvents();
        initSortables();
    }

    function attachNestedZones(children, parentEl) {
        children.forEach((node) => {
            if (!CONTAINER_TYPES.has(node.type) || !node.children?.length) return;
            const zone = parentEl.querySelector(`[data-lp-parent="${node.id}"]`);
            if (!zone) return;
            zone.classList.remove('is-empty');
            zone.innerHTML = node.children.map((c) => renderNode(c)).join('');
            attachNestedZones(node.children, zone);
        });
    }

    function bindCanvasEvents() {
        root.querySelectorAll('[data-lp-node]').forEach((el) => {
            el.addEventListener('click', (e) => {
                if (e.target.closest('[data-lp-action]')) return;
                selectedId = el.dataset.lpNode;
                renderCanvas();
                renderProps();
            });
        });

        root.querySelectorAll('[data-lp-action]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const nodeEl = btn.closest('[data-lp-node]');
                if (!nodeEl) return;
                const id = nodeEl.dataset.lpNode;
                const action = btn.dataset.lpAction;
                if (action === 'delete') deleteNode(id);
                if (action === 'duplicate') duplicateNode(id);
                if (action === 'up') moveNode(id, -1);
                if (action === 'down') moveNode(id, 1);
            });
        });
    }

    function deleteNode(id) {
        removeNodeFromTree(document.nodes, id);
        if (selectedId === id) selectedId = null;
        renderCanvas();
        renderProps();
        scheduleSave();
    }

    function duplicateNode(id) {
        const ctx = findNodeContext(document.nodes, id);
        if (!ctx) return;
        const copy = deepClone(ctx.node);
        const reid = (node) => {
            node.id = uuid();
            if (Array.isArray(node.children)) node.children.forEach(reid);
        };
        reid(copy);
        ctx.list.splice(ctx.index + 1, 0, copy);
        selectedId = copy.id;
        renderCanvas();
        renderProps();
        scheduleSave();
    }

    function moveNode(id, delta) {
        const ctx = findNodeContext(document.nodes, id);
        if (!ctx) return;
        const newIndex = ctx.index + delta;
        if (newIndex < 0 || newIndex >= ctx.list.length) return;
        const [item] = ctx.list.splice(ctx.index, 1);
        ctx.list.splice(newIndex, 0, item);
        renderCanvas();
        scheduleSave();
    }

    function renderProps() {
        if (!els.props) return;
        if (!selectedId) {
            els.props.innerHTML = '<div class="fi-lp-vb-empty-props">Select a component to edit its properties.</div>';
            return;
        }
        const ctx = findNodeContext(document.nodes, selectedId);
        if (!ctx) return;
        const node = ctx.node;
        const fields = propFieldsForType(node.type);

        let html = `<div class="fi-lp-vb-section-title">Content</div>`;
        fields.forEach((field) => {
            const val = node.props?.[field.key];
            html += `<div class="fi-lp-vb-field"><label>${escapeHtml(field.label)}</label>`;
            if (field.kind === 'textarea') {
                html += `<textarea data-lp-prop="${field.key}">${escapeHtml(val ?? '')}</textarea>`;
            } else if (field.kind === 'lines') {
                const lines = Array.isArray(val) ? val.join('\n') : '';
                html += `<textarea data-lp-prop="${field.key}" data-lp-lines="1">${escapeHtml(lines)}</textarea>`;
            } else if (field.kind === 'json') {
                html += `<textarea data-lp-prop="${field.key}" data-lp-json="1">${escapeHtml(JSON.stringify(val ?? [], null, 2))}</textarea>`;
            } else if (field.kind === 'select') {
                html += `<select data-lp-prop="${field.key}">${(field.options || [])
                    .map((o) => `<option value="${o}" ${val === o ? 'selected' : ''}>${o}</option>`)
                    .join('')}</select>`;
            } else if (field.kind === 'checkbox') {
                html += `<input type="checkbox" data-lp-prop="${field.key}" ${val ? 'checked' : ''} />`;
            } else if (field.kind === 'number') {
                html += `<input type="number" data-lp-prop="${field.key}" value="${val ?? ''}" />`;
            } else {
                html += `<input type="text" data-lp-prop="${field.key}" value="${escapeAttr(val ?? '')}" placeholder="${escapeAttr(field.placeholder || '')}" />`;
            }
            html += '</div>';
        });

        html += `<div class="fi-lp-vb-section-title">Style (${viewport})</div>`;
        STYLE_KEYS.forEach((key) => {
            const styles = node.styles || defaultStyles;
            const merged = { ...defaultStyles.desktop, ...(styles.desktop || {}), ...(styles[viewport] || {}) };
            const val = merged[key] ?? '';
            html += `<div class="fi-lp-vb-field"><label>${escapeHtml(key)}</label><input type="text" data-lp-style="${key}" value="${escapeAttr(val)}" /></div>`;
        });

        els.props.innerHTML = html;

        els.props.querySelectorAll('[data-lp-prop]').forEach((input) => {
            const handler = () => {
                const key = input.dataset.lpProp;
                if (input.dataset.lpJson === '1') {
                    try {
                        node.props[key] = JSON.parse(input.value || '[]');
                    } catch {
                        return;
                    }
                } else if (input.dataset.lpLines === '1') {
                    node.props[key] = input.value.split('\n').map((l) => l.trim()).filter(Boolean);
                } else if (input.type === 'checkbox') {
                    node.props[key] = input.checked;
                } else if (input.type === 'number') {
                    node.props[key] = Number(input.value);
                } else {
                    node.props[key] = input.value;
                }
                renderCanvas();
                scheduleSave();
            };
            input.addEventListener('change', handler);
            if (input.tagName === 'TEXTAREA' || input.type === 'text') {
                input.addEventListener('input', debounce(handler, 400));
            }
        });

        els.props.querySelectorAll('[data-lp-style]').forEach((input) => {
            const handler = () => {
                if (!node.styles) node.styles = deepClone(defaultStyles);
                if (!node.styles[viewport]) node.styles[viewport] = {};
                node.styles[viewport][input.dataset.lpStyle] = input.value;
                renderCanvas();
                scheduleSave();
            };
            input.addEventListener('change', handler);
            input.addEventListener('input', debounce(handler, 400));
        });
    }

    function destroySortables() {
        sortables.forEach((s) => s.destroy());
        sortables = [];
    }

    function initSortables() {
        destroySortables();
        root.querySelectorAll('.fi-lp-vb-dropzone').forEach((zone) => {
            sortables.push(
                Sortable.create(zone, {
                    group: 'lp-nodes',
                    animation: 150,
                    ghostClass: 'fi-lp-vb-sortable-ghost',
                    draggable: '.fi-lp-vb-node',
                    onAdd: (evt) => handleSort(evt),
                    onUpdate: (evt) => handleSort(evt),
                }),
            );

            zone.addEventListener('dragover', (e) => e.preventDefault());
            zone.addEventListener('drop', (e) => {
                const type = e.dataTransfer.getData('text/lp-component');
                if (!type) return;
                e.preventDefault();
                const parentId = zone.dataset.lpParent || null;
                const node = createNode(type, definitions, defaultStyles);
                const targetList = parentId
                    ? findNodeContext(document.nodes, parentId)?.node.children
                    : document.nodes;
                if (!targetList) document.nodes.push(node);
                else targetList.push(node);
                selectedId = node.id;
                renderCanvas();
                renderProps();
                scheduleSave();
            });
        });
    }

    function handleSort(evt) {
        const itemEl = evt.item;
        const nodeId = itemEl.dataset.lpNode;
        if (!nodeId) return;

        const fromParent = evt.from.dataset.lpParent || null;
        const toParent = evt.to.dataset.lpParent || null;
        const node = removeNodeFromTree(document.nodes, nodeId);
        if (!node) return;

        const targetList = toParent
            ? findNodeContext(document.nodes, toParent)?.node.children
            : document.nodes;
        const index = evt.newIndex ?? (targetList?.length || 0);
        if (targetList) {
            targetList.splice(index, 0, node);
        } else {
            document.nodes.splice(index, 0, node);
        }

        renderCanvas();
        scheduleSave();
    }

    function setViewport(next) {
        viewport = next;
        if (els.canvas) {
            els.canvas.classList.remove('is-desktop', 'is-tablet', 'is-mobile');
            els.canvas.classList.add(`is-${next}`);
        }
        els.viewportButtons?.forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.lpViewport === next);
        });
        renderProps();
    }

    els.viewportButtons?.forEach((btn) => {
        btn.addEventListener('click', () => setViewport(btn.dataset.lpViewport));
    });

    if (config.previewBaseSrc && els.preview) {
        els.preview.dataset.baseSrc = config.previewBaseSrc;
    }

    window.addEventListener('lp-preview-refresh', refreshPreview);

    renderPalette();
    setViewport(viewport);
    renderCanvas();
    renderProps();

    return {
        getDocument: () => document,
        setDocument: (doc) => {
            document = deepClone(doc);
            renderCanvas();
            renderProps();
        },
    };
}

function debounce(fn, ms) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), ms);
    };
}

window.initLandingPageVisualBuilder = initLandingPageVisualBuilder;
