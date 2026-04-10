<?php
// pages/manual.php
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: index.php?page=login');
    exit;
}

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

// Defino o título para o header usar
$page_title = 'Manual';
$_GET['page'] = 'manual'; // garante que o header reconhece a página

include __DIR__ . '/../layout/header.php';
?>
<!-- ═══════════════════════════════════════════════════════════════════════
     DEPENDENCIES (CDN)
═══════════════════════════════════════════════════════════════════════ -->
<!-- Quill.js Rich Text Editor -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<!-- Sortable.js (drag-and-drop for steps) -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<!-- jsPDF + html2canvas (PDF export) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<style>
    /* ═══════════ BASE ═══════════ */
    :root {
        --mnl-primary: #00695C;
        --mnl-primary-light: #E0F2F1;
        --mnl-primary-dark: #004D40;
        --mnl-accent: #00897B;
        --mnl-danger: #C62828;
        --mnl-danger-light: #FFEBEE;
        --mnl-warn: #F57F17;
        --mnl-text: #212121;
        --mnl-text-muted: #757575;
        --mnl-border: #E0E0E0;
        --mnl-bg: #F5F7F9;
        --mnl-white: #ffffff;
        --mnl-radius: 12px;
        --mnl-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        --mnl-shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .mnl-page {
        max-width: 1100px;
        margin: 0 auto;
        padding: 24px 20px 80px;
    }

    /* ═══════════ HEADER ═══════════ */
    .mnl-topbar {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 28px;
        flex-wrap: wrap;
    }

    .mnl-topbar-left {
        flex: 1;
    }

    .mnl-topbar-left h2 {
        font-size: 1.9rem;
        font-weight: 700;
        color: var(--mnl-primary-dark);
        margin: 0 0 4px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .mnl-topbar-left p {
        color: var(--mnl-text-muted);
        margin: 0;
        font-size: .95rem;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 18px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-size: .9rem;
        font-weight: 600;
        transition: all .2s;
        text-decoration: none;
    }

    .btn-primary {
        background: var(--mnl-primary);
        color: #fff;
    }

    .btn-primary:hover {
        background: var(--mnl-primary-dark);
    }

    .btn-danger {
        background: var(--mnl-danger);
        color: #fff;
    }

    .btn-danger:hover {
        opacity: .88;
    }

    .btn-outline {
        background: transparent;
        color: var(--mnl-primary);
        border: 2px solid var(--mnl-primary);
    }

    .btn-outline:hover {
        background: var(--mnl-primary-light);
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: .82rem;
        border-radius: 6px;
    }

    .btn-icon {
        padding: 7px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        background: transparent;
        transition: .2s;
    }

    .btn-icon:hover {
        background: var(--mnl-bg);
    }

    .btn-icon.danger:hover {
        background: var(--mnl-danger-light);
        color: var(--mnl-danger);
    }

    /* ═══════════ SEARCH ═══════════ */
    .mnl-search-wrap {
        position: relative;
        margin-bottom: 28px;
    }

    .mnl-search-wrap .material-icons-round {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--mnl-text-muted);
        pointer-events: none;
    }

    #mnlSearch {
        width: 100%;
        padding: 12px 16px 12px 48px;
        border: 2px solid var(--mnl-border);
        border-radius: 10px;
        font-size: 1rem;
        background: var(--mnl-white);
        color: var(--mnl-text);
        transition: border-color .2s;
        box-sizing: border-box;
    }

    #mnlSearch:focus {
        outline: none;
        border-color: var(--mnl-accent);
    }

    .mnl-search-hint {
        font-size: .82rem;
        color: var(--mnl-text-muted);
        margin-top: 6px;
    }

    /* ═══════════ GRID DE CARDS ═══════════ */
    .mnl-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .mnl-card {
        background: var(--mnl-white);
        border: 1px solid var(--mnl-border);
        border-radius: var(--mnl-radius);
        padding: 24px;
        box-shadow: var(--mnl-shadow-sm);
        transition: transform .2s, box-shadow .2s;
        cursor: pointer;
        position: relative;
    }

    .mnl-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--mnl-shadow);
    }

    .mnl-card-top {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        margin-bottom: 12px;
    }

    .mnl-card-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: var(--mnl-primary-light);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .mnl-card-icon .material-icons-round {
        color: var(--mnl-primary);
        font-size: 22px;
    }

    .mnl-card h3 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--mnl-text);
        line-height: 1.3;
        padding-right: 50px;
    }

    .mnl-card-meta {
        font-size: .8rem;
        color: var(--mnl-text-muted);
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .mnl-card-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .mnl-card-meta .material-icons-round {
        font-size: 14px;
    }

    .mnl-card-actions {
        position: absolute;
        top: 14px;
        right: 14px;
        display: flex;
        gap: 4px;
        opacity: 0;
        transition: opacity .2s;
    }

    .mnl-card:hover .mnl-card-actions {
        opacity: 1;
    }

    .mnl-empty {
        grid-column: 1 / -1;
        text-align: center;
        padding: 60px 20px;
        color: var(--mnl-text-muted);
    }

    .mnl-empty .material-icons-round {
        font-size: 56px;
        margin-bottom: 12px;
        opacity: .3;
    }

    .mnl-empty p {
        font-size: 1rem;
    }

    /* ═══════════ VIEWER ═══════════ */
    .mnl-viewer-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .45);
        z-index: 1050;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 20px;
        overflow-y: auto;
        opacity: 0;
        pointer-events: none;
        transition: opacity .25s;
    }

    .mnl-viewer-overlay.open {
        opacity: 1;
        pointer-events: all;
    }

    .mnl-viewer {
        background: var(--mnl-white);
        border-radius: 16px;
        max-width: 820px;
        width: 100%;
        padding: 36px 40px;
        position: relative;
        margin: auto;
        transform: translateY(20px);
        transition: transform .25s;
    }

    .mnl-viewer-overlay.open .mnl-viewer {
        transform: translateY(0);
    }

    .mnl-viewer-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--mnl-border);
    }

    .mnl-viewer-header h2 {
        margin: 0;
        font-size: 1.5rem;
        color: var(--mnl-primary-dark);
        flex: 1;
    }

    .mnl-viewer-toolbar {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
    }

    .mnl-step {
        margin-bottom: 32px;
        padding: 24px;
        border-radius: var(--mnl-radius);
        background: var(--mnl-bg);
        border-left: 4px solid var(--mnl-accent);
    }

    .mnl-step-num {
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--mnl-accent);
        letter-spacing: .08em;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .mnl-step-num .material-icons-round {
        font-size: 16px;
    }

    .mnl-step-content {
        font-size: .97rem;
        line-height: 1.7;
        color: var(--mnl-text);
    }

    .mnl-step-content p {
        margin: 0 0 8px;
    }

    .mnl-step-content ul,
    .mnl-step-content ol {
        margin: 0 0 8px 20px;
    }

    .mnl-step-content strong {
        color: var(--mnl-primary-dark);
    }

    /* Step image gallery */
    .mnl-img-gallery {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 16px;
    }

    .mnl-img-thumb {
        width: 100px;
        height: 80px;
        border-radius: 8px;
        object-fit: cover;
        cursor: zoom-in;
        border: 2px solid transparent;
        transition: border-color .2s, transform .2s;
    }

    .mnl-img-thumb:hover {
        border-color: var(--mnl-accent);
        transform: scale(1.04);
    }

    /* ═══════════ LIGHTBOX ═══════════ */
    .mnl-lightbox {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .92);
        backdrop-filter: blur(4px);
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        pointer-events: none;
        transition: opacity .25s;
    }

    .mnl-lightbox.open {
        opacity: 1;
        pointer-events: all;
    }

    .mnl-lb-inner {
        display: flex;
        gap: 24px;
        max-width: 1100px;
        width: 100%;
        max-height: 90vh;
        align-items: center;
    }

    .mnl-lb-img-wrap {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        min-width: 0;
    }

    .mnl-lb-img {
        max-width: 100%;
        max-height: 80vh;
        border-radius: 10px;
        object-fit: contain;
        transition: transform .3s;
        transform-origin: center center;
    }

    .mnl-lb-panel {
        width: 300px;
        flex-shrink: 0;
        background: rgba(255, 255, 255, .1);
        backdrop-filter: blur(12px);
        border-radius: 12px;
        padding: 20px;
        color: #fff;
        max-height: 80vh;
        overflow-y: auto;
    }

    .mnl-lb-panel h4 {
        margin: 0 0 12px;
        font-size: 1rem;
        opacity: .7;
        font-weight: 600;
    }

    .mnl-lb-panel .mnl-step-content {
        color: #eee;
    }

    .mnl-lb-close {
        position: fixed;
        top: 20px;
        right: 24px;
        background: rgba(255, 255, 255, .15);
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        cursor: pointer;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        transition: background .2s;
    }

    .mnl-lb-close:hover {
        background: rgba(255, 255, 255, .3);
    }

    .mnl-lb-nav {
        position: fixed;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(255, 255, 255, .15);
        border: none;
        border-radius: 50%;
        width: 44px;
        height: 44px;
        cursor: pointer;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        transition: background .2s;
        z-index: 10;
    }

    .mnl-lb-nav:hover {
        background: rgba(255, 255, 255, .3);
    }

    .mnl-lb-nav.prev {
        left: 12px;
    }

    .mnl-lb-nav.next {
        right: 12px;
    }

    .mnl-lb-zoom-hint {
        font-size: .75rem;
        opacity: .5;
        text-align: center;
        margin-top: 8px;
    }

    /* ═══════════ ADMIN MODAL ═══════════ */
    .mnl-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .55);
        z-index: 1500;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 20px;
        overflow-y: auto;
        opacity: 0;
        pointer-events: none;
        transition: opacity .25s;
    }

    .mnl-modal-overlay.open {
        opacity: 1;
        pointer-events: all;
    }

    .mnl-modal {
        background: var(--mnl-white);
        border-radius: 16px;
        max-width: 860px;
        width: 100%;
        padding: 36px 40px;
        position: relative;
        margin: auto;
        transform: translateY(20px);
        transition: transform .25s;
    }

    .mnl-modal-overlay.open .mnl-modal {
        transform: translateY(0);
    }

    .mnl-modal h2 {
        margin: 0 0 24px;
        font-size: 1.4rem;
        color: var(--mnl-primary-dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 6px;
        font-size: .9rem;
        color: var(--mnl-text);
    }

    .form-group input[type="text"] {
        width: 100%;
        padding: 11px 14px;
        border: 2px solid var(--mnl-border);
        border-radius: 8px;
        font-size: 1rem;
        color: var(--mnl-text);
        transition: border-color .2s;
        box-sizing: border-box;
    }

    .form-group input[type="text"]:focus {
        outline: none;
        border-color: var(--mnl-accent);
    }

    /* Steps editor */
    .mnl-steps-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
        margin-bottom: 16px;
    }

    .mnl-step-editor {
        border: 1px solid var(--mnl-border);
        border-radius: var(--mnl-radius);
        background: var(--mnl-bg);
        overflow: hidden;
    }

    .mnl-step-editor-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        background: var(--mnl-white);
        border-bottom: 1px solid var(--mnl-border);
        cursor: grab;
    }

    .mnl-step-editor-label {
        font-weight: 700;
        font-size: .85rem;
        color: var(--mnl-accent);
        display: flex;
        align-items: center;
        gap: 6px;
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    .mnl-step-editor-body {
        padding: 14px;
    }

    .mnl-step-imgs {
        padding: 10px 14px 14px;
    }

    .mnl-step-imgs-label {
        font-size: .82rem;
        font-weight: 600;
        color: var(--mnl-text-muted);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .mnl-step-imgs-label .material-icons-round {
        font-size: 16px;
    }

    .mnl-upload-zone {
        border: 2px dashed var(--mnl-border);
        border-radius: 8px;
        padding: 14px;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s, background .2s;
        font-size: .85rem;
        color: var(--mnl-text-muted);
    }

    .mnl-upload-zone:hover {
        border-color: var(--mnl-accent);
        background: var(--mnl-primary-light);
    }

    .mnl-upload-zone .material-icons-round {
        font-size: 28px;
        display: block;
        margin-bottom: 4px;
    }

    .mnl-imgs-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }

    .mnl-img-preview-wrap {
        position: relative;
        width: 80px;
        height: 70px;
    }

    .mnl-img-preview-wrap img {
        width: 80px;
        height: 70px;
        object-fit: cover;
        border-radius: 6px;
        border: 2px solid var(--mnl-border);
    }

    .mnl-img-preview-wrap button {
        position: absolute;
        top: -6px;
        right: -6px;
        background: var(--mnl-danger);
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 14px;
        padding: 0;
        line-height: 1;
    }

    .mnl-quill-wrap .ql-toolbar {
        border-radius: 8px 8px 0 0;
        background: #fafafa;
        border-bottom: none;
    }

    .mnl-quill-wrap .ql-container {
        border-radius: 0 0 8px 8px;
        border-top: 1px solid var(--mnl-border);
    }

    .mnl-quill-wrap .ql-container.ql-snow {
        border: 1px solid var(--mnl-border);
    }

    .mnl-quill-wrap:focus-within .ql-toolbar,
    .mnl-quill-wrap:focus-within .ql-container {
        border-color: var(--mnl-accent);
    }

    /* Step image upload area */
    .mnl-step-imgs {
        padding: 10px 14px 14px;
    }

    .mnl-step-imgs-label {
        font-size: .82rem;
        font-weight: 600;
        color: var(--mnl-text-muted);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .mnl-step-imgs-label .material-icons-round {
        font-size: 16px;
    }

    .mnl-upload-zone {
        border: 2px dashed var(--mnl-border);
        border-radius: 8px;
        padding: 14px;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s, background .2s;
        font-size: .85rem;
        color: var(--mnl-text-muted);
    }

    .mnl-upload-zone:hover {
        border-color: var(--mnl-accent);
        background: var(--mnl-primary-light);
    }

    .mnl-upload-zone .material-icons-round {
        font-size: 28px;
        display: block;
        margin-bottom: 4px;
    }

    .mnl-imgs-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }

    .mnl-img-preview-wrap {
        position: relative;
        width: 80px;
        height: 70px;
    }

    .mnl-img-preview-wrap img {
        width: 80px;
        height: 70px;
        object-fit: cover;
        border-radius: 6px;
        border: 2px solid var(--mnl-border);
    }

    .mnl-img-preview-wrap button {
        position: absolute;
        top: -6px;
        right: -6px;
        background: var(--mnl-danger);
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 14px;
        padding: 0;
        line-height: 1;
    }

    .mnl-add-step-btn {
        width: 100%;
        padding: 12px;
        border: 2px dashed var(--mnl-accent);
        border-radius: var(--mnl-radius);
        background: transparent;
        color: var(--mnl-accent);
        font-size: .9rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: background .2s;
    }

    .mnl-add-step-btn:hover {
        background: var(--mnl-primary-light);
    }

    .mnl-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--mnl-border);
    }

    /* Delete confirm */
    .mnl-confirm-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .55);
        z-index: 2500;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        pointer-events: none;
        transition: opacity .2s;
    }

    .mnl-confirm-overlay.open {
        opacity: 1;
        pointer-events: all;
    }

    .mnl-confirm-box {
        background: var(--mnl-white);
        border-radius: 14px;
        padding: 32px 36px;
        max-width: 420px;
        width: 100%;
        text-align: center;
    }

    .mnl-confirm-box .material-icons-round {
        font-size: 48px;
        color: var(--mnl-danger);
        margin-bottom: 12px;
    }

    .mnl-confirm-box h3 {
        margin: 0 0 8px;
        color: var(--mnl-text);
    }

    .mnl-confirm-box p {
        color: var(--mnl-text-muted);
        margin: 0 0 24px;
        font-size: .95rem;
    }

    .mnl-confirm-box .btns {
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    /* PDF export styles (hidden div) */
    #mnlPdfTarget {
        position: fixed;
        left: -9999px;
        top: 0;
        width: 794px;
        background: #fff;
        padding: 40px;
        font-family: Arial, sans-serif;
        color: #222;
        z-index: -1;
    }

    #mnlPdfTarget h1 {
        font-size: 22px;
        color: #004D40;
        margin-bottom: 8px;
    }

    #mnlPdfTarget hr {
        border: none;
        border-top: 2px solid var(--mnl-primary-light);
        margin-bottom: 20px;
    }

    #mnlPdfTarget .pdf-step {
        margin-bottom: 24px;
        page-break-inside: avoid;
    }

    #mnlPdfTarget .pdf-step h3 {
        font-size: 13px;
        text-transform: uppercase;
        color: var(--mnl-accent);
        margin-bottom: 8px;
        letter-spacing: .06em;
    }

    #mnlPdfTarget .pdf-step-body {
        font-size: 13px;
        line-height: 1.6;
        color: #333;
        margin-bottom: 10px;
    }

    #mnlPdfTarget .pdf-step-body p {
        margin: 0 0 6px;
    }

    #mnlPdfTarget .pdf-step-body ul,
    #mnlPdfTarget .pdf-step-body ol {
        margin: 0 0 6px 18px;
    }

    #mnlPdfTarget .pdf-img {
        max-width: 100%;
        border-radius: 6px;
        margin: 4px 0;
    }

    #mnlPdfTarget .pdf-footer {
        margin-top: 40px;
        font-size: 11px;
        color: #aaa;
        border-top: 1px solid #eee;
        padding-top: 10px;
    }

    #mnlPdfTarget ol {
        list-style-type: decimal !important;
        padding-left: 20px !important;
    }

    #mnlPdfTarget ol li {
        display: list-item !important;
        list-style-type: decimal !important;
    }

    #mnlPdfTarget ol li::before {
        display: none !important;
    }

    #mnlPdfTarget ul {
        list-style-type: disc !important;
        padding-left: 20px !important;
    }

    #mnlPdfTarget ul li {
        display: list-item !important;
        list-style-type: disc !important;
    }

    #mnlPdfTarget ul li::before {
        display: none !important;
    }

    /* Quill content render inside viewer & lightbox */
    .ql-editor {
        padding: 0 !important;
    }

    .mnl-spinner {
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 2px solid rgba(0, 0, 0, .15);
        border-top-color: currentColor;
        border-radius: 50%;
        animation: spin .7s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    @media (max-width: 600px) {
        .mnl-lb-panel {
            display: none;
        }

        .mnl-modal {
            padding: 24px 20px;
        }

        .mnl-viewer {
            padding: 24px 20px;
        }

        .mnl-topbar {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    /* Quill content render inside viewer & lightbox — scoped, NOT global */
    .mnl-step-content .ql-editor,
    .mnl-lb-panel .ql-editor {
        padding: 0 !important;
    }

    /* Quill editor inside admin modal — keep normal padding & ensure full click area */
    .mnl-quill-wrap .ql-editor {
        padding: 12px 15px;
        min-height: 150px;
    }

    .mnl-quill-wrap .ql-container.ql-snow {
        border: 1px solid var(--mnl-border);
        min-height: 150px;
    }
</style>

<?php if ($isAdmin): ?>
    <!-- ══════════════ ADMIN MODAL ══════════════ -->
    <div class="mnl-modal-overlay" id="adminModal">
        <div class="mnl-modal">
            <h2>
                <span class="material-icons-round">menu_book</span>
                <span id="modalTitle">Novo Manual</span>
            </h2>

            <input type="hidden" id="editManualId" value="">

            <div class="form-group">
                <label for="manualTitle">Título do Manual <span style="color: var(--mnl-danger)">*</span></label>
                <input type="text" id="manualTitle" placeholder="Ex: Como cadastrar um novo POS…">
            </div>

            <div class="form-group">
                <label>Passos</label>
                <div class="mnl-steps-list" id="stepsList"></div>
                <button class="mnl-add-step-btn" id="addStepBtn">
                    <span class="material-icons-round">add_circle_outline</span>
                    Adicionar Passo
                </button>
            </div>

            <div class="mnl-modal-footer">
                <button class="btn btn-outline" id="cancelModal">Cancelar</button>
                <button class="btn btn-primary" id="saveManualBtn">
                    <span class="material-icons-round">save</span>
                    Salvar Manual
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════ DELETE CONFIRM ══════════════ -->
    <div class="mnl-confirm-overlay" id="confirmDelete">
        <div class="mnl-confirm-box">
            <span class="material-icons-round">delete_forever</span>
            <h3>Excluir Manual?</h3>
            <p>Esta ação é permanente e removerá todos os passos e imagens.</p>
            <div class="btns">
                <button class="btn btn-outline" id="cancelDelete">Cancelar</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">
                    <span class="material-icons-round">delete</span>Excluir
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ══════════════ VIEWER ══════════════ -->
<div class="mnl-viewer-overlay" id="viewerOverlay">
    <div class="mnl-viewer" id="viewerBox">
        <div class="mnl-viewer-header">
            <h2 id="viewerTitle"></h2>
            <div class="mnl-viewer-toolbar">
                <button class="btn btn-outline btn-sm" id="exportPdfBtn">
                    <span class="material-icons-round" style="font-size:16px">picture_as_pdf</span>
                    Exportar PDF
                </button>
                <button class="btn-icon" id="closeViewer" title="Fechar">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
        </div>
        <div id="viewerSteps"></div>
    </div>
</div>

<!-- ══════════════ LIGHTBOX ══════════════ -->
<div class="mnl-lightbox" id="lightbox">
    <button class="mnl-lb-close" id="lbClose">✕</button>
    <button class="mnl-lb-nav prev" id="lbPrev">
        <span class="material-icons-round">chevron_left</span>
    </button>
    <div class="mnl-lb-inner">
        <div class="mnl-lb-img-wrap">
            <img src="" id="lbImg" class="mnl-lb-img" alt="">
            <div class="mnl-lb-zoom-hint">Scroll para dar zoom • Arraste para mover</div>
        </div>
        <div class="mnl-lb-panel">
            <h4>📝 Conteúdo do Passo</h4>
            <div class="mnl-step-content" id="lbStepContent"></div>
        </div>
    </div>
    <button class="mnl-lb-nav next" id="lbNext">
        <span class="material-icons-round">chevron_right</span>
    </button>
</div>

<!-- ══════════════ PDF HIDDEN DIV ══════════════ -->
<div id="mnlPdfTarget"></div>

<!-- ══════════════ MAIN PAGE ══════════════ -->
<div class="mnl-page">
    <a href="index.php?page=dashboard" class="btn btn-outline btn-sm"
        style="margin-bottom:20px;display:inline-flex;text-decoration:none !important;">
        <span class="material-icons-round" style="font-size:16px">arrow_back</span>
        Dashboard
    </a>

    <div class="mnl-topbar">
        <div class="mnl-topbar-left">
            <h2><span class="material-icons-round" style="font-size:2rem">menu_book</span> Manuais de Procedimento</h2>
            <p>Base de conhecimento passo a passo. Clique em um manual para visualizar.</p>
        </div>
        <?php if ($isAdmin): ?>
            <button class="btn btn-primary" id="newManualBtn">
                <span class="material-icons-round">add</span>
                Novo Manual
            </button>
        <?php endif; ?>
    </div>

    <div class="mnl-search-wrap">
        <span class="material-icons-round">search</span>
        <input type="text" id="mnlSearch" placeholder="Buscar por qualquer palavra em qualquer manual…">
    </div>
    <div class="mnl-search-hint" id="searchHint"></div>

    <div class="mnl-grid" id="mnlGrid">
        <div class="mnl-empty">
            <span class="material-icons-round">hourglass_empty</span>
            <p>Carregando manuais…</p>
        </div>
    </div>
</div>

<!-- ══════════════ JAVASCRIPT ══════════════ -->
<script>
    /* ─────────────────────────────────────────────────
       State
    ───────────────────────────────────────────────── */
    let allManuals = [];
    let currentManual = null;   // full manual data in viewer
    let lbImages = [];      // [{url, stepContent}]
    let lbIndex = 0;
    let lbZoom = 1;
    let lbDragging = false;
    let lbDragStart = { x: 0, y: 0 };
    let lbTranslate = { x: 0, y: 0 };
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

    /* ─────────────────────────────────────────────────
       DOM refs
    ───────────────────────────────────────────────── */
    const grid = document.getElementById('mnlGrid');
    const search = document.getElementById('mnlSearch');
    const searchHint = document.getElementById('searchHint');
    const viewerOverlay = document.getElementById('viewerOverlay');
    const viewerTitle = document.getElementById('viewerTitle');
    const viewerSteps = document.getElementById('viewerSteps');
    const lightbox = document.getElementById('lightbox');
    const lbImg = document.getElementById('lbImg');
    const lbStepContent = document.getElementById('lbStepContent');
    const pdfTarget = document.getElementById('mnlPdfTarget');

    /* ─────────────────────────────────────────────────
       API helpers
    ───────────────────────────────────────────────── */
    function apiGet(action, params = {}) {
        const url = new URL('api/manuals.php', location.href);
        url.searchParams.set('action', action);
        Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
        return fetch(url).then(r => r.json());
    }
    function apiPost(action, body) {
        return fetch(`api/manuals.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        }).then(r => r.json());
    }

    /* ─────────────────────────────────────────────────
       Render grid
    ───────────────────────────────────────────────── */
    function renderGrid(manuals) {
        if (!manuals.length) {
            grid.innerHTML = `<div class="mnl-empty">
            <span class="material-icons-round">search_off</span>
            <p>Nenhum manual encontrado.</p></div>`;
            return;
        }
        grid.innerHTML = manuals.map(m => `
        <div class="mnl-card" data-id="${m.id}" onclick="openViewer(${m.id})">
            <div class="mnl-card-top">
                <div class="mnl-card-icon">
                    <span class="material-icons-round">description</span>
                </div>
                <div>
                    <h3>${escHtml(m.title)}</h3>
                </div>
            </div>
            <div class="mnl-card-meta">
                <span><span class="material-icons-round">format_list_numbered</span>${m.step_count} passso(s)</span>
                <span><span class="material-icons-round">schedule</span>${fmtDate(m.updated_at)}</span>
            </div>
            ${isAdmin ? `<div class="mnl-card-actions" onclick="event.stopPropagation()">
                <button class="btn-icon" title="Editar" onclick="editManual(${m.id})">
                    <span class="material-icons-round" style="font-size:18px;color: var(--mnl-primary)">edit</span>
                </button>
                <button class="btn-icon danger" title="Excluir" onclick="deleteManual(${m.id}, '${escHtml(m.title)}')">
                    <span class="material-icons-round" style="font-size:18px">delete</span>
                </button>
            </div>` : ''}
        </div>
    `).join('');
    }

    /* ─────────────────────────────────────────────────
       Load all
    ───────────────────────────────────────────────── */
    async function loadManuals() {
        grid.innerHTML = `<div class="mnl-empty"><p>Carregando…</p></div>`;
        const res = await apiGet('list');
        if (res.success) { allManuals = res.data; renderGrid(allManuals); }
    }
    loadManuals();

    /* ─────────────────────────────────────────────────
       Search (debounced)
    ───────────────────────────────────────────────── */
    let searchTimer;
    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const q = search.value.trim();
        if (!q) { searchHint.textContent = ''; renderGrid(allManuals); return; }
        searchTimer = setTimeout(async () => {
            searchHint.textContent = 'Buscando…';
            const res = await apiGet('search', { q });
            if (res.success) {
                searchHint.textContent = `${res.data.length} resultado(s) para "${q}"`;
                renderGrid(res.data);
            }
        }, 400);
    });

    /* ─────────────────────────────────────────────────
       Viewer
    ───────────────────────────────────────────────── */
    async function openViewer(id) {
        viewerSteps.innerHTML = '<p style="color:#999;text-align:center;padding:32px">Carregando…</p>';
        viewerOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';

        const res = await apiGet('get', { id });
        if (!res.success) return;
        currentManual = res.data;
        viewerTitle.textContent = currentManual.title;

        lbImages = [];

        viewerSteps.innerHTML = '';
        if (!currentManual.steps.length) {
            viewerSteps.innerHTML = '<p style="color:#999;text-align:center;padding:32px">Este manual ainda não tem passos.</p>';
            return;
        }

        currentManual.steps.forEach((step, idx) => {
            const stepContent = step.content;
            const imgs = step.images || [];
            imgs.forEach(img => {
                lbImages.push({ url: 'assets/manual_imgs/' + img.filename, stepContent, stepIdx: idx + 1 });
            });

            const imgHtml = imgs.map((img, iIdx) => {
                const lbIdx = lbImages.findIndex(x => x.url === 'assets/manual_imgs/' + img.filename);
                return `<img src="assets/manual_imgs/${escHtml(img.filename)}"
                         class="mnl-img-thumb"
                         onclick="openLightbox(${lbIdx})"
                         alt="${escHtml(img.caption || 'Imagem')}"
                         title="${escHtml(img.caption || 'Clique para ampliar')}">`;
            }).join('');

            const div = document.createElement('div');
            div.className = 'mnl-step';
            div.innerHTML = `
            <div class="mnl-step-num">
                <span class="material-icons-round">chevron_right</span>
                Passo ${idx + 1}
            </div>
            <div class="mnl-step-content">${stepContent}</div>
            ${imgs.length ? `<div class="mnl-img-gallery">${imgHtml}</div>` : ''}
        `;
            viewerSteps.appendChild(div);
        });
    }

    document.getElementById('closeViewer').onclick = () => {
        viewerOverlay.classList.remove('open');
        document.body.style.overflow = '';
    };
    viewerOverlay.addEventListener('click', e => {
        if (e.target === viewerOverlay) {
            viewerOverlay.classList.remove('open');
            document.body.style.overflow = '';
        }
    });

    /* ─────────────────────────────────────────────────
       Lightbox
    ───────────────────────────────────────────────── */
    function openLightbox(idx) {
        lbIndex = idx;
        lbZoom = 1;
        lbTranslate = { x: 0, y: 0 };
        applyLbTransform();
        lightbox.classList.add('open');
        document.body.style.overflow = 'hidden';
        renderLbImage();
    }
    function renderLbImage() {
        const item = lbImages[lbIndex];
        if (!item) return;
        lbImg.src = item.url;
        lbStepContent.innerHTML = `<div style="font-size:.82rem;opacity:.6;margin-bottom:8px">Passo ${item.stepIdx}</div>` + item.stepContent;
        document.getElementById('lbPrev').style.display = lbIndex > 0 ? 'flex' : 'none';
        document.getElementById('lbNext').style.display = lbIndex < lbImages.length - 1 ? 'flex' : 'none';
    }
    document.getElementById('lbClose').onclick = closeLightbox;
    lightbox.addEventListener('click', e => { if (e.target === lightbox) closeLightbox(); });
    document.getElementById('lbPrev').onclick = () => { lbIndex--; lbZoom = 1; lbTranslate = { x: 0, y: 0 }; applyLbTransform(); renderLbImage(); };
    document.getElementById('lbNext').onclick = () => { lbIndex++; lbZoom = 1; lbTranslate = { x: 0, y: 0 }; applyLbTransform(); renderLbImage(); };
    function closeLightbox() {
        lightbox.classList.remove('open');
        lbDragging = false;
        document.body.style.overflow = viewerOverlay.classList.contains('open') ? 'hidden' : '';
    }

    // Zoom with mouse wheel
    lbImg.addEventListener('wheel', e => {
        e.preventDefault();
        lbZoom = Math.min(5, Math.max(1, lbZoom + (e.deltaY < 0 ? 0.2 : -0.2)));
        if (lbZoom === 1) lbTranslate = { x: 0, y: 0 };
        applyLbTransform();
    }, { passive: false });

    // Drag to pan when zoomed
    lbImg.addEventListener('dragstart', e => e.preventDefault());
    lbImg.addEventListener('mousedown', e => {
        if (lbZoom <= 1) return;
        e.preventDefault();
        lbDragging = true;
        lbDragStart = { x: e.clientX - lbTranslate.x, y: e.clientY - lbTranslate.y };
        lbImg.style.cursor = 'grabbing';
    });
    window.addEventListener('mousemove', e => {
        if (!lbDragging) return;
        lbTranslate = { x: e.clientX - lbDragStart.x, y: e.clientY - lbDragStart.y };
        applyLbTransform();
    });
    window.addEventListener('mouseup', () => {
        lbDragging = false;
        lbImg.style.cursor = lbZoom > 1 ? 'grab' : 'default';
    });
    window.addEventListener('blur', () => { lbDragging = false; });

    function applyLbTransform() {
        lbImg.style.transform = `scale(${lbZoom}) translate(${lbTranslate.x / lbZoom}px, ${lbTranslate.y / lbZoom}px)`;
        lbImg.style.cursor = lbZoom > 1 ? 'grab' : 'default';
    }

    // Keyboard nav
    document.addEventListener('keydown', e => {
        if (lightbox.classList.contains('open')) {
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowRight' && lbIndex < lbImages.length - 1) { lbIndex++; lbZoom = 1; lbTranslate = { x: 0, y: 0 }; applyLbTransform(); renderLbImage(); }
            if (e.key === 'ArrowLeft' && lbIndex > 0) { lbIndex--; lbZoom = 1; lbTranslate = { x: 0, y: 0 }; applyLbTransform(); renderLbImage(); }
        } else if (viewerOverlay.classList.contains('open') && e.key === 'Escape') {
            viewerOverlay.classList.remove('open');
            document.body.style.overflow = '';
        }
    });

    /* ─────────────────────────────────────────────────
       PDF Export
    ───────────────────────────────────────────────── */
    document.getElementById('exportPdfBtn').onclick = async function () {
        if (!currentManual) return;
        const btn = this;
        const target = document.getElementById('mnlPdfTarget');
        if (!btn || !target) return;

        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<span class="mnl-spinner"></span> Gerando…';
        btn.disabled = true;

        try {
            const { jsPDF } = window.jspdf;
            if (!jsPDF) throw new Error('Biblioteca jsPDF não encontrada.');

            const pdf = new jsPDF('p', 'mm', 'a4');
            const pdfW = 210, pdfH = 297, margin = 15;
            const contentW = pdfW - (margin * 2);
            let currentY = margin;

            target.style.width = '800px';

            const renderElement = async (html) => {
                target.innerHTML = html;
                const imgs = [...target.querySelectorAll('img')];
                await Promise.all(imgs.map(img => new Promise(res => {
                    if (img.complete) res();
                    else img.onload = img.onerror = res;
                })));

                const canvas = await html2canvas(target, {
                    scale: 2, useCORS: true, logging: false, backgroundColor: '#ffffff'
                });
                const imgData = canvas.toDataURL('image/jpeg', 0.95);
                return { imgData, h: (canvas.height * contentW) / canvas.width };
            };

            // 1. Header
            const headerRes = await renderElement(`
            <div style="padding: 5px 10px; border-bottom: 2px solid var(--mnl-accent);">
                <h1 style="font-size: 28px; color: #004D40; margin: 0;">${escHtml(currentManual.title)}</h1>
                <div style="font-size: 12px; color: #757575; margin-top: 5px;">
                    Manual de Procedimento • Gerado em ${new Date().toLocaleDateString('pt-BR')}
                </div>
            </div>
        `);
            pdf.addImage(headerRes.imgData, 'JPEG', margin, currentY, contentW, headerRes.h);
            currentY += headerRes.h + 5;

            // 2. Steps
            for (let i = 0; i < currentManual.steps.length; i++) {
                const step = currentManual.steps[i];
                const hasImages = step.images && step.images.length > 0;

                const stepRes = await renderElement(`
                <div style="padding: 5px 10px; font-family: sans-serif;">
                    <h3 style="font-size: 16px; color: var(--mnl-accent); text-transform: uppercase; margin-bottom: 8px; border-left: 4px solid var(--mnl-accent); padding-left: 10px;">
                        Passo ${i + 1}
                    </h3>
                    <div style="display: flex; flex-direction: row-reverse; flex-wrap: wrap; gap: 20px; align-items: flex-start;">
                        ${hasImages ? `
                        <div style="flex: 1; min-width: 300px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end;">
                            ${step.images.map(img => `
                                <img src="assets/manual_imgs/${img.filename}" style="max-width: 100%; height: auto; border-radius: 8px; border: 1px solid #eee;">
                            `).join('')}
                        </div>
                        ` : ''}
                        <div class="ql-editor" style="flex: 1.2; min-width: 300px; padding: 0; font-size: 14px; line-height: 1.6; color: #333; margin-bottom: 10px;">
                            ${step.content || ''}
                        </div>
                    </div>
                </div>
            `);

                if (currentY + stepRes.h > pdfH - margin) {
                    pdf.addPage();
                    currentY = margin;
                }
                pdf.addImage(stepRes.imgData, 'JPEG', margin, currentY, contentW, stepRes.h);
                currentY += stepRes.h + 5;
            }

            // 3. Footer
            pdf.setFontSize(10);
            pdf.setTextColor(150);
            pdf.text("Grupo Flex — Ferramentas de Suporte", pdfW / 2, pdfH - 10, { align: 'center' });

            pdf.save(`Manual_${sanitizeFilename(currentManual.title)}.pdf`);
        } catch (e) {
            console.error('PDF Export Error:', e);
            alert('Erro ao gerar PDF: ' + e.message);
        } finally {
            if (target) target.innerHTML = '';
            if (btn) {
                btn.innerHTML = '<span class="material-icons-round" style="font-size:16px">picture_as_pdf</span> Exportar PDF';
                btn.disabled = false;
            }
        }
    };

    function getImageDataUrl(url) {
        return new Promise((resolve, reject) => {
            const img = new Image(); img.crossOrigin = 'anonymous';
            img.onload = () => {
                const c = document.createElement('canvas');
                c.width = img.naturalWidth; c.height = img.naturalHeight;
                c.getContext('2d').drawImage(img, 0, 0);
                resolve(c.toDataURL('image/jpeg', .85));
            };
            img.onerror = reject;
            img.src = url + '?t=' + Date.now();
        });
    }
    function getImgNaturalDims(dataUrl) {
        return new Promise(resolve => {
            const i = new Image();
            i.onload = () => resolve({ w: i.naturalWidth, h: i.naturalHeight });
            i.src = dataUrl;
        });
    }
    function stripHtml(html) {
        const d = document.createElement('div');
        d.innerHTML = html;
        return d.textContent || d.innerText || '';
    }
    function sanitizeFilename(s) { return s.replace(/[^a-zA-Z0-9_\-\sÀ-ú]/g, '').trim().replace(/\s+/g, '_'); }

    <?php if ($isAdmin): ?>
        /* ─────────────────────────────────────────────────
           Admin — Quill instances
        ───────────────────────────────────────────────── */
        const quilts = {};   // stepIndex -> Quill instance
        const stepData = []; // [{id, pendingImages:[{file, preview}], existingImages:[{id,filename,caption}]}]
        let stepCount = 0;

        const quillOptions = {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'code-block'],
                    [{ color: [] }, { background: [] }],
                    ['link', 'clean']
                ]
            }
        };

        function addStep(data = {}) {
            const idx = stepCount++;
            const id = data.id || null;
            const imgs = data.images || [];
            stepData[idx] = { id, pendingImages: [], existingImages: imgs };

            const div = document.createElement('div');
            div.className = 'mnl-step-editor';
            div.dataset.idx = idx;
            div.innerHTML = `
        <div class="mnl-step-editor-header">
            <span class="mnl-step-editor-label drag-handle">
                <span class="material-icons-round">drag_indicator</span>
                Passo <span class="step-label">${idx + 1}</span>
            </span>
            <button class="btn-icon danger" onclick="removeStep(this)" title="Remover passo">
                <span class="material-icons-round">delete</span>
            </button>
        </div>
        <div class="mnl-step-editor-body">
            <div class="mnl-quill-wrap">
                <div id="quill-${idx}"></div>
            </div>
        </div>
        <div class="mnl-step-imgs">
            <div class="mnl-step-imgs-label">
                <span class="material-icons-round">image</span> Imagens do passo
            </div>
            <div class="mnl-imgs-preview" id="imgs-preview-${idx}"></div>
            <div class="mnl-upload-zone" onclick="document.getElementById('file-${idx}').click()">
                <span class="material-icons-round">upload</span>
                Clique para adicionar imagem (JPG, PNG, WEBP • máx 5MB)
            </div>
            <input type="file" id="file-${idx}" accept="image/*" multiple style="display:none"
                   onchange="handleImgAdd(event, ${idx})">
        </div>
    `;
            document.getElementById('stepsList').appendChild(div);

            const q = new Quill(`#quill-${idx}`, quillOptions);
            if (data.content) q.root.innerHTML = data.content;
            quilts[idx] = q;

            // Render existing images
            imgs.forEach(img => renderExistingImg(idx, img));

            renumberSteps();
        }

        function removeStep(btn) {
            const editor = btn.closest('.mnl-step-editor');
            const idx = parseInt(editor.dataset.idx);
            delete quilts[idx];
            stepData[idx] = null;
            editor.remove();
            renumberSteps();
        }

        function renumberSteps() {
            document.querySelectorAll('#stepsList .mnl-step-editor').forEach((el, i) => {
                el.querySelector('.step-label').textContent = i + 1;
            });
        }

        function handleImgAdd(event, idx) {
            const files = Array.from(event.target.files);
            files.forEach(file => {
                if (file.size > 5 * 1024 * 1024) { alert('Arquivo muito grande: ' + file.name); return; }
                const reader = new FileReader();
                reader.onload = e => {
                    const pendingIdx = stepData[idx].pendingImages.push({ file, preview: e.target.result }) - 1;
                    renderPendingImg(idx, pendingIdx, e.target.result);
                };
                reader.readAsDataURL(file);
            });
            event.target.value = '';
        }

        function renderPendingImg(stepIdx, pendingIdx, dataUrl) {
            const wrap = document.createElement('div');
            wrap.className = 'mnl-img-preview-wrap';
            wrap.dataset.pending = pendingIdx;
            wrap.innerHTML = `<img src="${dataUrl}" alt="img">
        <button onclick="removePendingImg(this, ${stepIdx}, ${pendingIdx})" title="Remover">✕</button>`;
            document.getElementById(`imgs-preview-${stepIdx}`).appendChild(wrap);
        }

        function renderExistingImg(stepIdx, img) {
            const wrap = document.createElement('div');
            wrap.className = 'mnl-img-preview-wrap';
            wrap.dataset.imgId = img.id;
            wrap.innerHTML = `<img src="assets/manual_imgs/${img.filename}" alt="${escHtml(img.caption || '')}">
        <button onclick="removeExistingImg(this, ${stepIdx}, ${img.id})" title="Remover">✕</button>`;
            document.getElementById(`imgs-preview-${stepIdx}`).appendChild(wrap);
        }

        function removePendingImg(btn, stepIdx, pendingIdx) {
            stepData[stepIdx].pendingImages[pendingIdx] = null;
            btn.closest('.mnl-img-preview-wrap').remove();
        }

        async function removeExistingImg(btn, stepIdx, imgId) {
            btn.closest('.mnl-img-preview-wrap').remove();
            stepData[stepIdx].existingImages = stepData[stepIdx].existingImages.filter(x => x.id !== imgId);
            await apiPost('delete_image', { id: imgId });
        }

        document.getElementById('addStepBtn').onclick = () => addStep();

        // Sortable
        Sortable.create(document.getElementById('stepsList'), {
            handle: '.drag-handle', animation: 150, onEnd: renumberSteps
        });

        /* ─────────────────────────────────────────────────
           Admin Modal Open/Close
        ───────────────────────────────────────────────── */
        function openAdminModal(title = 'Novo Manual', manualId = '', steps = []) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('editManualId').value = manualId;
            document.getElementById('manualTitle').value = '';
            document.getElementById('stepsList').innerHTML = '';
            Object.keys(quilts).forEach(k => delete quilts[k]);
            stepData.length = 0;
            stepCount = 0;

            if (steps.length) {
                steps.forEach(s => addStep(s));
            } else {
                addStep(); // start with one empty step
            }

            document.getElementById('adminModal').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        document.getElementById('newManualBtn').onclick = () => openAdminModal();
        document.getElementById('cancelModal').onclick = closeAdminModal;
        document.getElementById('adminModal').addEventListener('click', e => {
            if (e.target.id === 'adminModal') closeAdminModal();
        });
        function closeAdminModal() {
            document.getElementById('adminModal').classList.remove('open');
            document.body.style.overflow = '';
        }

        async function editManual(id) {
            const res = await apiGet('get', { id });
            if (!res.success) return;
            const m = res.data;
            openAdminModal('Editar Manual', m.id, m.steps);
            document.getElementById('manualTitle').value = m.title;
        }

        /* ─────────────────────────────────────────────────
           Save Manual
        ───────────────────────────────────────────────── */
        document.getElementById('saveManualBtn').onclick = async () => {
            const btn = document.getElementById('saveManualBtn');
            const title = document.getElementById('manualTitle').value.trim();
            const id = document.getElementById('editManualId').value;

            if (!title) { document.getElementById('manualTitle').focus(); return; }

            const editors = [...document.querySelectorAll('#stepsList .mnl-step-editor')];
            const steps = editors.map(el => {
                const idx = parseInt(el.dataset.idx);
                return {
                    id: stepData[idx] ? stepData[idx].id : null,
                    content: quilts[idx] ? quilts[idx].root.innerHTML : '',
                };
            });

            btn.innerHTML = '<span class="mnl-spinner"></span> Salvando…';
            btn.disabled = true;

            const saveRes = await apiPost('save', { id: id ? parseInt(id) : 0, title, steps });
            if (!saveRes.success) {
                alert('Erro: ' + saveRes.message);
                btn.innerHTML = '<span class="material-icons-round">save</span> Salvar Manual';
                btn.disabled = false;
                return;
            }

            const manualId = saveRes.id;
            const stepIds = saveRes.step_ids;

            // Upload pending images
            for (let i = 0; i < editors.length; i++) {
                const el = editors[i];
                const idx = parseInt(el.dataset.idx);
                const sd = stepData[idx];
                if (!sd) continue;
                const stepId = stepIds[i];

                for (const pending of (sd.pendingImages || [])) {
                    if (!pending) continue;
                    const fd = new FormData();
                    fd.append('action', 'upload_image');
                    fd.append('manual_id', manualId);
                    fd.append('step_id', stepId);
                    fd.append('image', pending.file);
                    await fetch('api/manuals.php?action=upload_image', { method: 'POST', body: fd }).then(r => r.json());
                }
            }

            closeAdminModal();
            await loadManuals();
            btn.innerHTML = '<span class="material-icons-round">save</span> Salvar Manual';
            btn.disabled = false;
        };

        /* ─────────────────────────────────────────────────
           Delete Manual
        ───────────────────────────────────────────────── */
        let deleteTargetId = null;
        function deleteManual(id, title) {
            deleteTargetId = id;
            document.getElementById('confirmDelete').classList.add('open');
        }
        document.getElementById('cancelDelete').onclick = () => {
            document.getElementById('confirmDelete').classList.remove('open');
        };
        document.getElementById('confirmDeleteBtn').onclick = async () => {
            if (!deleteTargetId) return;
            await apiPost('delete', { id: deleteTargetId });
            document.getElementById('confirmDelete').classList.remove('open');
            await loadManuals();
            deleteTargetId = null;
        };
    <?php endif; ?>

    /* ─────────────────────────────────────────────────
       Utils
    ───────────────────────────────────────────────── */
    function escHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fmtDate(str) {
        if (!str) return '';
        const d = new Date(str.replace(' ', 'T'));
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    /* ── Navbar shared functions (not in board.js on this page) ── */
    function toggleUserMenu() {
        document.getElementById('userDropdown').classList.toggle('open');
    }
    document.addEventListener('click', function (e) {
        const menu = document.querySelector('.user-menu');
        if (menu && !menu.contains(e.target)) {
            const dd = document.getElementById('userDropdown');
            if (dd) dd.classList.remove('open');
        }
    });
    function confirmLogout() { return confirm('Deseja sair do sistema?'); }
    function openChangePassword() {
        // Simple inline password change using the same API
        const cur = prompt('Senha atual:');
        if (!cur) return;
        const nw = prompt('Nova senha (mínimo 6 caracteres):');
        if (!nw || nw.length < 6) { alert('Senha muito curta.'); return; }
        const conf = prompt('Confirme a nova senha:');
        if (nw !== conf) { alert('As senhas não coincidem.'); return; }
        const fd = new FormData();
        fd.append('action', 'change_password');
        fd.append('current_password', cur);
        fd.append('new_password', nw);
        fetch('api/auth.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => alert(d.success ? 'Senha alterada com sucesso!' : (d.message || 'Erro ao alterar senha.')));
    }
</script>

<!-- Toast container (needed by Quill toolbar tooltips & future toasts) -->
<div id="toastContainer"
    style="position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;"></div>

</body>

</html>