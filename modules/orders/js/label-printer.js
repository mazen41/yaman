/**
 * LabelPrinter - custom print library for order labels.
 *
 * Fetches a server-rendered label page, takes only its .labels container
 * plus the page's styles, and prints it through a hidden iframe.
 * The site layout, toolbar and navigation never reach the printout.
 *
 * Usage:
 *   LabelPrinter.printUrl('print_labels.php?ids=1,2,3', { width: '4in', height: '6in' });
 */
(function (global) {
    'use strict';

    const DEFAULTS = {
        width: '4in',
        height: '6in',
        title: 'Labels',
        timeout: 60000
    };

    function buildDocument(src, opts) {
        return '<!DOCTYPE html>' +
            '<html lang="' + (src.lang || 'en') + '" dir="' + (src.dir || 'ltr') + '">' +
            '<head>' +
            '<meta charset="utf-8">' +
            '<base href="' + src.base + '">' +
            '<title>' + opts.title + '</title>' +
            '<style>' +
            '@page { size: ' + opts.width + ' ' + opts.height + '; margin: 0; }' +
            'html, body { margin: 0; padding: 0; background: #fff; }' +
            '</style>' +
            src.head +
            '</head>' +
            '<body>' + src.body + '</body>' +
            '</html>';
    }

    function printHtml(html, opts) {
        return new Promise((resolve, reject) => {
            const iframe = document.createElement('iframe');
            iframe.setAttribute('aria-hidden', 'true');
            iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
            document.body.appendChild(iframe);

            let finished = false;
            const cleanup = () => {
                if (finished) return;
                finished = true;
                setTimeout(() => iframe.remove(), 500);
                resolve();
            };

            const win = iframe.contentWindow;
            const doc = win.document;
            doc.open();
            doc.write(html);
            doc.close();

            // Wait for fonts and images so the label is complete before printing
            (async () => {
                if (doc.fonts && doc.fonts.ready) {
                    await doc.fonts.ready;
                }
                await Promise.all(Array.from(doc.images).map(img =>
                    img.complete ? null : new Promise(r => { img.onload = img.onerror = r; })
                ));

                win.onafterprint = cleanup;
                setTimeout(cleanup, opts.timeout);
                win.focus();
                win.print();
            })().catch(err => {
                iframe.remove();
                reject(err);
            });
        });
    }

    /** Fetch a label page and print only its .labels container with its styles. */
    async function printUrl(url, options) {
        const opts = Object.assign({}, DEFAULTS, options || {});
        const absUrl = new URL(url, location.href).href;

        const res = await fetch(absUrl, { credentials: 'same-origin' });
        if (!res.ok) {
            throw new Error('Failed to load labels (HTTP ' + res.status + ')');
        }

        const dom = new DOMParser().parseFromString(await res.text(), 'text/html');

        // Remove toolbar buttons and scripts from the fragment
        dom.querySelectorAll('.no-print, script').forEach(n => n.remove());

        const container = dom.querySelector('.labels');
        if (!container) {
            throw new Error('No labels found in the response');
        }

        const head = Array.from(dom.head.querySelectorAll('style, link[rel="stylesheet"]'))
            .map(n => n.outerHTML)
            .join('\n');

        const html = buildDocument({
            base: absUrl,
            lang: dom.documentElement.getAttribute('lang'),
            dir: dom.documentElement.getAttribute('dir'),
            head: head,
            body: container.outerHTML
        }, opts);

        return printHtml(html, opts);
    }

    global.LabelPrinter = { printUrl: printUrl };
})(window);
