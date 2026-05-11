/**
 * Pro Media Cropper - Shared State
 *
 * A single mutable object imported by all modules.
 * Mutate properties directly; changes are visible everywhere.
 */

export const state = {
    cropper:        null,
    isLocked:       true,
    currentMeta:    {},
    stockPage:      1,
    stockLoading:   false,
    currentBlobUrl: null,
    exportW:        0,
    exportH:        0,

    // AI mode
    aiMode:         false,
    aiSourceBlob:   null,
    aiResultB64:    null,
};

/** Cached element references — populated by initElements() */
export let els = {};

/**
 * Query helper scoped to rootEl.
 * Call initElements() first so `els` is populated,
 * then prefer `els.foo` over repeated DOM queries.
 */
export function q(id, rootEl = document) {
    return rootEl.querySelector(`#${id}`);
}

/**
 * Build the `els` map once at startup.
 * Add entries here as new elements are needed.
 */
export function initElements(rootEl) {
    const qr = (id) => rootEl.querySelector(`#${id}`);
    els = {
        rootEl,
        canvas:          qr('pmc-canvas'),
        img:             qr('pmc-image'),
        loader:          qr('pmc-loading'),
        filenameInput:   qr('pmc-filename'),
        aiPromptInput:   qr('pmc-ai-prompt'),
        statusCont:      qr('pmc-status-container'),
        attrLine:        qr('pmc-attribution'),
        presetSel:       qr('pmc-ratio-preset'),
        previewLabel:    qr('pmc-preview-label'),
        saveBtn:         qr('pmc-save-btn'),
        aiBtn:           qr('pmc-ai-btn'),
        aiOverlay:       qr('pmc-ai-overlay'),
        aiGenerate:      qr('pmc-ai-generate'),
        aiAccept:        qr('pmc-ai-accept'),
        aiCancel:        qr('pmc-ai-cancel'),
        aiSourceImg:     rootEl.querySelector('#pmc-ai-source-img'),
        aiResultImg:     rootEl.querySelector('#pmc-ai-result-img'),
        aiPlaceholder:   qr('pmc-ai-placeholder'),
        aiSpinner:       qr('pmc-ai-spinner'),
        aiSpinnerMsg:    rootEl.querySelector('#pmc-ai-spinner .pmc-ai-spinner-msg'),
        aiDims:          qr('pmc-ai-dims'),
        aiLeftWrap:      qr('pmc-ai-left-wrap'),
        aiRightWrap:     qr('pmc-ai-right-wrap'),
        modeLocked:      qr('mode-locked'),
        modePillar:      qr('mode-pillar'),
        pillarControls:  qr('pillarbox-controls'),
        modeSelect:      qr('pmc-mode'),
        blurWrap:        qr('blur-wrap'),
        blurInput:       qr('pmc-blur'),
        colorPickerWrap: qr('color-picker-wrap'),
        colorInput:      qr('pmc-color'),
        stockBtn:        qr('pmc-stock-btn'),
        searchModal:     qr('pmc-search-modal'),
        stockQuery:      qr('pmc-stock-query'),
        stockProvider:   qr('pmc-stock-provider'),
        stockResults:    qr('pmc-stock-results'),
        fileInput:       qr('pmc-file-input'),
        pasteBtn:        qr('pmc-paste-btn'),
        libraryBtn:      qr('pmc-library-btn'),
        eyedropperBtn:   qr('pmc-eyedropper-btn'),
    };
    els.ctx = els.canvas.getContext('2d');
}
