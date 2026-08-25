window.landingPageBuilder = function landingPageBuilder(config) {

    return {

        tabs: [

            { id: 'html', label: 'HTML', language: 'html', ref: 'editorHtml' },

            { id: 'css', label: 'CSS', language: 'css', ref: 'editorCss' },

            { id: 'js', label: 'JavaScript', language: 'javascript', ref: 'editorJs' },

            { id: 'header', label: 'Header scripts', language: 'html', ref: 'editorHeader' },

            { id: 'footer', label: 'Footer scripts', language: 'html', ref: 'editorFooter' },

        ],

        activeTab: 'html',

        editors: {},

        saveStatus: 'idle',

        debounceTimer: null,

        debounceMs: config.debounceMs || 30000,

        assetsUrl: config.assetsUrl || null,

        monacoVsPath: config.monacoVsPath || 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs',

        assets: [],

        assetsLoading: false,

        editorError: null,

        useFallback: false,



        waitForAssets() {

            if (typeof window.landingPageBuilder === 'function' && typeof window.require === 'function') {

                return Promise.resolve();

            }



            if (typeof window.ensureLandingPageBuilderAssets === 'function') {

                return window.ensureLandingPageBuilderAssets();

            }



            return new Promise((resolve) => {

                document.addEventListener('landing-page-builder:assets-ready', () => resolve(), { once: true });

            });

        },



        init() {

            this.refreshPreview();



            this.waitForAssets()

                .then(() => this.bootEditors())

                .catch((error) => {

                    console.error('[landing-page-builder]', error);

                    this.enableFallback(error?.message || 'Editor failed to load. Using plain text fields.');

                });



            if (this.assetsUrl) {

                this.loadAssets();

            }

        },



        bootEditors() {

            return this.loadMonaco()

                .then(() => {

                    this.tabs.forEach((tab) => {

                        const host = this.$refs[tab.ref];

                        if (! host) {

                            return;

                        }



                        this.editors[tab.id] = monaco.editor.create(host, {

                            value: this.initialValue(tab.id),

                            language: tab.language,

                            theme: document.documentElement.classList.contains('dark') ? 'vs-dark' : 'vs',

                            automaticLayout: true,

                            minimap: { enabled: false },

                            wordWrap: 'on',

                            fontSize: 13,

                        });



                        this.editors[tab.id].onDidChangeModelContent(() => {

                            this.scheduleAutoSave();

                            this.refreshPreview();

                        });

                    });



                    this.refreshPreview();

                })

                .catch((error) => {

                    this.enableFallback(error?.message || 'Monaco editor unavailable.');

                });

        },



        enableFallback(message) {

            this.editorError = message;

            this.useFallback = true;



            const map = {

                html: this.$refs.fallbackHtml,

                css: this.$refs.fallbackCss,

            };



            Object.entries(map).forEach(([key, el]) => {

                if (el) {

                    el.value = this.initialValue(key);

                }

            });



            this.refreshPreview();

        },



        syncFallback() {

            if (! this.useFallback) {

                return;

            }



            if (this.$refs.fallbackHtml) {

                this.fallbackValues = this.fallbackValues || {};

                this.fallbackValues.html = this.$refs.fallbackHtml.value;

            }



            if (this.$refs.fallbackCss) {

                this.fallbackValues = this.fallbackValues || {};

                this.fallbackValues.css = this.$refs.fallbackCss.value;

            }

        },



        loadAssets() {

            if (! this.assetsUrl) {

                return;

            }



            this.assetsLoading = true;



            fetch(this.assetsUrl, {

                headers: { Accept: 'application/json' },

                credentials: 'same-origin',

            })

                .then((response) => (response.ok ? response.json() : Promise.reject()))

                .then((payload) => {

                    this.assets = payload.assets ?? [];

                })

                .catch(() => {

                    this.assets = [];

                })

                .finally(() => {

                    this.assetsLoading = false;

                });

        },



        insertAsset(asset) {

            const editor = this.editors.html;

            if (! editor || ! asset?.snippet) {

                return;

            }



            const snippet = asset.snippet + '\n';

            const selection = editor.getSelection();

            const op = {

                range: selection,

                text: snippet,

                forceMoveMarkers: true,

            };



            editor.executeEdits('asset-insert', [op]);

            editor.focus();

            this.scheduleAutoSave();

            this.refreshPreview();

        },



        initialValue(tabId) {

            const map = {

                html: config.initial.html,

                css: config.initial.css,

                js: config.initial.js,

                header: config.initial.header,

                footer: config.initial.footer,

            };



            return map[tabId] ?? '';

        },



        loadMonaco() {

            return new Promise((resolve, reject) => {

                if (window.monaco?.editor) {

                    resolve();

                    return;

                }



                if (typeof window.require !== 'function') {

                    reject(new Error('Monaco loader did not initialize (check CDN / network).'));

                    return;

                }



                const vsPath = this.monacoVsPath.replace(/\/$/, '');



                require.config({ paths: { vs: vsPath } });



                const timeout = setTimeout(() => {

                    reject(new Error('Monaco editor timed out while loading.'));

                }, 20000);



                require(['vs/editor/editor.main'], () => {

                    clearTimeout(timeout);

                    resolve();

                }, (error) => {

                    clearTimeout(timeout);

                    reject(error);

                });

            });

        },



        switchTab(tabId) {

            this.activeTab = tabId;

            const editor = this.editors[tabId];

            if (editor) {

                setTimeout(() => editor.layout(), 50);

            }

        },



        getValues() {

            if (this.useFallback) {

                const fallback = this.fallbackValues || {};



                return {

                    html: fallback.html ?? this.$refs.fallbackHtml?.value ?? this.initialValue('html'),

                    css: fallback.css ?? this.$refs.fallbackCss?.value ?? this.initialValue('css'),

                    js: this.initialValue('js'),

                    header: this.initialValue('header'),

                    footer: this.initialValue('footer'),

                };

            }



            return {

                html: this.editors.html?.getValue() ?? this.initialValue('html'),

                css: this.editors.css?.getValue() ?? this.initialValue('css'),

                js: this.editors.js?.getValue() ?? this.initialValue('js'),

                header: this.editors.header?.getValue() ?? this.initialValue('header'),

                footer: this.editors.footer?.getValue() ?? this.initialValue('footer'),

            };

        },



        scheduleAutoSave() {

            clearTimeout(this.debounceTimer);

            this.saveStatus = 'idle';

            this.debounceTimer = setTimeout(() => this.autoSave(), this.debounceMs);

        },



        autoSave() {

            const values = this.getValues();

            this.saveStatus = 'saving';



            this.$wire.autoSave(

                values.html,

                values.css,

                values.js,

                values.header,

                values.footer,

            ).then(() => {

                this.saveStatus = 'saved';

            }).catch(() => {

                this.saveStatus = 'idle';

            });

        },



        saveVersion() {

            const values = this.getValues();

            this.saveStatus = 'saving';



            this.$wire.saveVersion(

                values.html,

                values.css,

                values.js,

                values.header,

                values.footer,

            ).then(() => {

                this.saveStatus = 'saved';

            }).catch(() => {

                this.saveStatus = 'idle';

            });

        },



        refreshPreview() {

            const values = this.getValues();

            const doc = [

                '<!DOCTYPE html><html><head><meta charset="utf-8">',

                values.header,

                '<style>',

                values.css,

                '</style></head><body>',

                values.html,

                '<script>',

                values.js,

                '<\/script>',

                values.footer,

                '</body></html>',

            ].join('\n');



            const frame = this.$refs.previewFrame;

            if (frame) {

                frame.srcdoc = doc;

            }

        },

    };

};


