const { addFilter } = wp.hooks;

function openPmcModal(onSelect) {
    if (document.getElementById('pmc-modal-container')) return;

    const container = document.createElement('div');
    container.id = 'pmc-modal-container';
    container.innerHTML = `
        <div id="pmc-modal-backdrop"></div>
        <div id="pmc-modal-dialog">
            <div id="pmc-modal-header">
                <h2>Pro Media Cropper</h2>
                <div style="display:flex; gap:8px;">
                    <button id="pmc-modal-insert" class="pmc-modal-btn-primary" disabled>
                        Crop &amp; Insert
                    </button>
                    <button id="pmc-modal-close" class="pmc-modal-btn">✕</button>
                </div>
            </div>
            <div id="pmc-modal-body"></div>
        </div>
    `;
    document.body.appendChild(container);

    const body = container.querySelector('#pmc-modal-body');
    const tpl  = document.getElementById('pmc-ui-template');
    if (!tpl) { console.error('PMC: #pmc-ui-template not found'); return; }

    const wrap = document.createElement('div');
    wrap.innerHTML = tpl.innerHTML;
    body.append(...wrap.children);

    window.pmcInit(body);

    const saveBtn   = body.querySelector('#pmc-save-btn');
    const insertBtn = container.querySelector('#pmc-modal-insert');

    saveBtn.style.display = 'none';

    const observer = new MutationObserver(() => {
        insertBtn.disabled = saveBtn.disabled;
    });
    observer.observe(saveBtn, { attributes: true, attributeFilter: ['disabled'] });

    function close() {
        observer.disconnect();
        window.pmcDestroy?.(body);
        document.body.removeChild(container);
    }

    container.querySelector('#pmc-modal-close').onclick    = close;
    container.querySelector('#pmc-modal-backdrop').onclick = close;

    insertBtn.onclick = () => {
        insertBtn.disabled    = true;
        insertBtn.textContent = 'Saving…';
        window.pmcExportAndSave()
            .then((attachment) => {
                onSelect({
                    id:      attachment.id,
                    url:     attachment.source_url,
                    alt:     attachment.alt_text          || '',
                    caption: attachment.caption?.rendered || '',
                });
                close();
            })
            .catch((err) => {
                console.error('PMC insert failed', err);
                insertBtn.disabled    = false;
                insertBtn.textContent = 'Crop & Insert';
            });
    };
}

// ── MediaUpload filter ────────────────────────────────────────────────────────

// Known modalClass values WordPress sets per context:
const IMAGE_MODAL_CLASSES = new Set([
    'editor-post-featured-image__media-modal',  // Featured image panel
    // core/image block doesn't set modalClass, so we identify it by
    // allowedTypes + absence of other signals — see guard below
]);

addFilter(
    'editor.MediaUpload',
    'pro-media-cropper/media-upload',
    (OriginalMediaUpload) => {
        return function PmcMediaUpload(props) {
            const {
                render,
                onSelect,
                allowedTypes = [],
                multiple     = false,
                modalClass,
            } = props;

            const isFeaturedImage = modalClass === 'editor-post-featured-image__media-modal';
            const isImageOnly = allowedTypes.length === 1 && allowedTypes[0] === 'image';
            const isRichTextToolbar = render?.toString().includes('RichTextToolbarButton');
            const shouldInject = render && !multiple && isImageOnly &&
                (isFeaturedImage || !isRichTextToolbar);

            if (!shouldInject) {
                return wp.element.createElement(OriginalMediaUpload, props);
            }
            
            const label = isFeaturedImage ? 'Use Pro Cropper' : 'Pro Cropper';

            return wp.element.createElement(
                OriginalMediaUpload,
                {
                    ...props,
                    render: (originalProps) => wp.element.createElement(
                        wp.element.Fragment,
                        null,
                        render(originalProps),
                        wp.element.createElement(
                            'button',
                            {
                                key:       'pmc-trigger',
                                className: 'components-button block-editor-media-placeholder__button is-next-40px-default-size is-secondary',
                                onClick:   (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    openPmcModal(onSelect);
                                },
                            },
                            label
                        )
                    ),
                }
            );
        };
    }
);