/**
 * LabelPrinter - custom label library.
 * downloadUrl(): fetches a label page, renders only its .labels container
 * with the page's styles in a hidden frame, and saves it as a PDF.
 *
 * Requires html2pdf.js to be loaded first (see index.php).
 *
 * Usage:
 *   LabelPrinter.downloadUrl('print_labels.php?ids=1,2,3', {
 *       width: '4in', height: '6in', filename: 'labels.pdf'
 *   });
 */
(function (global) {
    'use strict';

    const DEFAULTS = {
        width: '4in',
        height: '6in',
        filename: 'labels.pdf',
        timeout: 15000
    };

    function buildDocument(src, opts) {
        return '<!DOCTYPE html>' +
            '<html lang="' + (src.lang || 'en') + '" dir="' + (src.dir || 'ltr') + '">' +
            '<head>' +
            '<meta charset="utf-8">' +
            '<base href="' + src.base + '">' +
            '<style>' +
            '@page { size: ' + opts.width + ' ' + opts.height + '; margin: 0; }' +
            'html, body { margin: 0; padding: 0; background: #fff; }' +
            '</style>' +
            src.head +
            '</head>' +
            '<body>' + src.body + '</body>' +
            '</html>';
    }

    /** Renders HTML in a hidden iframe and waits for fonts and images. */
    async function renderFrame(html) {
        const iframe = document.createElement('iframe');
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = 'position:fixed;left:-10000px;top:0;width:4in;height:6in;border:0;';
        document.body.appendChild(iframe);

        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(html);
        doc.close();

        if (doc.fonts && doc.fonts.ready) {
            await doc.fonts.ready;
        }
        await Promise.all(Array.from(doc.images).map(img =>
            img.complete ? null : new Promise(r => { img.onload = img.onerror = r; })
        ));
        return iframe;
    }

    /** Fetch a label page, render its labels, and download them as a PDF. */
    async function downloadUrl(url, options) {
        if (typeof html2pdf === 'undefined') {
            throw new Error('html2pdf.js is not loaded');
        }

        const opts = Object.assign({}, DEFAULTS, options || {});
        const absUrl = new URL(url, location.href).href;

        const res = await fetch(absUrl, { credentials: 'same-origin' });
        if (!res.ok) {
            throw new Error('Failed to load labels (HTTP ' + res.status + ')');
        }

        const dom = new DOMParser().parseFromString(await res.text(), 'text/html');
        dom.querySelectorAll('.no-print, script').forEach(n => n.remove());

        const container = dom.querySelector('.labels');
        if (!container) {
            throw new Error('No labels found in the response');
        }

        const head = Array.from(dom.head.querySelectorAll('style, link[rel="stylesheet"]'))
            .map(n => n.outerHTML)
            .join('\n');

        const iframe = await renderFrame(buildDocument({
            base: absUrl,
            lang: dom.documentElement.getAttribute('lang'),
            dir: dom.documentElement.getAttribute('dir'),
            head: head,
            body: container.outerHTML
        }, opts));

        try {
            const target = iframe.contentWindow.document.querySelector('.labels');
            const widthIn = parseFloat(opts.width) || 4;
            const heightIn = parseFloat(opts.height) || 6;

            await html2pdf()
                .set({
                    margin: 0,
                    filename: opts.filename,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true, letterRendering: true },
                    jsPDF: { unit: 'in', format: [widthIn, heightIn], orientation: 'portrait' },
                    pagebreak: { mode: ['css', 'legacy'] }
                })
                .from(target)
                .save();
        } finally {
            setTimeout(() => iframe.remove(), 500);
        }
    }

    global.LabelPrinter = { downloadUrl: downloadUrl };
})(window);
