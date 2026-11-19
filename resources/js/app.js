import './bootstrap';

/**
 * Universal clipboard copy helper supporting secure HTTPS context
 * and fallback for non-HTTPS / Intranet HTTP IPs (e.g. http://192.168.1.100:8085).
 */
window.copyToClipboard = function (text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }

    return new Promise((resolve, reject) => {
        try {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            textArea.setAttribute('readonly', '');
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            const successful = document.execCommand('copy');
            document.body.removeChild(textArea);
            if (successful) {
                resolve();
            } else {
                reject(new Error('Copy command failed'));
            }
        } catch (err) {
            reject(err);
        }
    });
};

/**
 * Per-Department QR Drop-Box modal Alpine controller.
 * Keeps all SVG/PNG/Clipboard/Print logic out of HTML attributes so it never
 * suffers from HTML entity decoding issues or Livewire DOM morph resets.
 */
window.tantouDeptQrDropBox = function (config) {
    const escapeHtml = (value) =>
        String(value ?? '').replace(/[&<>"']/g, (char) => {
            switch (char) {
                case '&':
                    return '&amp;';
                case '<':
                    return '&lt;';
                case '>':
                    return '&gt;';
                case '"':
                    return '&quot;';
                case "'":
                    return '&#39;';
                default:
                    return char;
            }
        });

    const triggerDownload = (href, filename, revokeAfterMs = 0) => {
        const link = document.createElement('a');
        link.href = href;
        link.download = filename;
        link.rel = 'noopener';
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        link.remove();

        if (revokeAfterMs > 0) {
            setTimeout(() => {
                try {
                    URL.revokeObjectURL(href);
                } catch (_) {
                    // Ignore revoke errors
                }
            }, revokeAfterMs);
        }
    };

    const serializeQrSvg = (svgEl) => {
        const serializer = new XMLSerializer();
        let svgData = serializer.serializeToString(svgEl);

        if (!svgData.includes('xmlns="http://www.w3.org/2000/svg"')) {
            svgData = svgData.replace(
                '<svg',
                '<svg xmlns="http://www.w3.org/2000/svg"'
            );
        }

        return svgData;
    };

    return {
        copied: false,
        svgDownloaded: false,
        pngDownloaded: false,
        printing: false,
        statusMessage: '',
        errorMessage: '',
        qrUrl: config?.url || '',
        deptName: config?.deptName || '',
        deptSlug: config?.deptSlug || 'department',
        deptColor: config?.deptColor || '#6366f1',
        workspaceName: config?.workspaceName || '',
        _statusTimer: null,

        setFeedback(message) {
            this.errorMessage = '';
            this.statusMessage = message;
            if (this._statusTimer) {
                clearTimeout(this._statusTimer);
            }
            this._statusTimer = setTimeout(() => {
                this.statusMessage = '';
            }, 2600);
        },

        setError(message) {
            this.statusMessage = '';
            this.errorMessage = message;
        },

        getSvgElement() {
            return (
                document.getElementById('dept-qr-code-svg') ||
                this.$refs?.qrContainer?.querySelector('svg') ||
                null
            );
        },

        async copyLink() {
            this.errorMessage = '';
            try {
                if (!this.qrUrl) {
                    throw new Error('Drop-Box URL is empty.');
                }

                if (typeof window.copyToClipboard === 'function') {
                    await window.copyToClipboard(this.qrUrl);
                } else if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(this.qrUrl);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = this.qrUrl;
                    ta.style.position = 'fixed';
                    ta.style.left = '-999999px';
                    ta.setAttribute('readonly', '');
                    document.body.appendChild(ta);
                    ta.focus();
                    ta.select();
                    const ok = document.execCommand('copy');
                    ta.remove();
                    if (!ok) {
                        throw new Error('Clipboard copy failed.');
                    }
                }

                this.copied = true;
                this.setFeedback('Link copied to clipboard.');
                setTimeout(() => {
                    this.copied = false;
                }, 2000);
            } catch (err) {
                this.setError(
                    'Could not copy automatically. Please select and copy the URL manually.'
                );
            }
        },

        downloadSvg() {
            this.errorMessage = '';
            try {
                const svgEl = this.getSvgElement();
                if (!svgEl) {
                    throw new Error('QR SVG element not found.');
                }

                const svgData = serializeQrSvg(svgEl);
                const blob = new Blob([svgData], {
                    type: 'image/svg+xml;charset=utf-8',
                });
                const url = URL.createObjectURL(blob);
                const filename = `qr-dropbox-${this.deptSlug || 'department'}.svg`;

                triggerDownload(url, filename, 1500);

                this.svgDownloaded = true;
                this.setFeedback(`Downloaded ${filename}`);
                setTimeout(() => {
                    this.svgDownloaded = false;
                }, 2000);
            } catch (err) {
                this.setError('Failed to download SVG QR code.');
            }
        },

        downloadPng() {
            this.errorMessage = '';
            try {
                const svgEl = this.getSvgElement();
                if (!svgEl) {
                    throw new Error('QR SVG element not found.');
                }

                const svgData = serializeQrSvg(svgEl);
                const filename = `qr-dropbox-${this.deptSlug || 'department'}.png`;
                const canvasSize = 1200;

                const renderToPng = (img) => {
                    const canvas = document.createElement('canvas');
                    canvas.width = canvasSize;
                    canvas.height = canvasSize;
                    const ctx = canvas.getContext('2d');
                    if (!ctx) {
                        throw new Error('Canvas 2D context unavailable.');
                    }

                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, canvasSize, canvasSize);
                    ctx.imageSmoothingEnabled = false;
                    ctx.drawImage(img, 0, 0, canvasSize, canvasSize);

                    if (typeof canvas.toBlob === 'function') {
                        canvas.toBlob((pngBlob) => {
                            if (!pngBlob) {
                                const dataUrl = canvas.toDataURL('image/png');
                                triggerDownload(dataUrl, filename);
                            } else {
                                const pngUrl = URL.createObjectURL(pngBlob);
                                triggerDownload(pngUrl, filename, 1500);
                            }
                            this.pngDownloaded = true;
                            this.setFeedback(`Downloaded ${filename}`);
                            setTimeout(() => {
                                this.pngDownloaded = false;
                            }, 2000);
                        }, 'image/png');
                    } else {
                        const dataUrl = canvas.toDataURL('image/png');
                        triggerDownload(dataUrl, filename);
                        this.pngDownloaded = true;
                        this.setFeedback(`Downloaded ${filename}`);
                        setTimeout(() => {
                            this.pngDownloaded = false;
                        }, 2000);
                    }
                };

                const svgBlob = new Blob([svgData], {
                    type: 'image/svg+xml;charset=utf-8',
                });
                const blobUrl = URL.createObjectURL(svgBlob);
                const img = new Image();

                img.onload = () => {
                    try {
                        URL.revokeObjectURL(blobUrl);
                        renderToPng(img);
                    } catch (err) {
                        this.setError('Failed to generate PNG image.');
                    }
                };

                img.onerror = () => {
                    URL.revokeObjectURL(blobUrl);
                    try {
                        const fallbackImg = new Image();
                        fallbackImg.onload = () => {
                            try {
                                renderToPng(fallbackImg);
                            } catch (err) {
                                this.setError('Failed to generate PNG image.');
                            }
                        };
                        fallbackImg.onerror = () => {
                            this.setError('Failed to render QR SVG to PNG.');
                        };
                        const base64 = window.btoa(
                            unescape(encodeURIComponent(svgData))
                        );
                        fallbackImg.src = `data:image/svg+xml;base64,${base64}`;
                    } catch (err) {
                        this.setError('Failed to render QR SVG to PNG.');
                    }
                };

                img.src = blobUrl;
            } catch (err) {
                this.setError('Failed to download PNG QR code.');
            }
        },

        printPoster() {
            this.errorMessage = '';
            try {
                const svgEl = this.getSvgElement();
                if (!svgEl) {
                    throw new Error('QR SVG element not found.');
                }

                const svgMarkup = serializeQrSvg(svgEl);
                const safeWorkspace = escapeHtml(this.workspaceName);
                const safeDept = escapeHtml(this.deptName);
                const safeColor = escapeHtml(this.deptColor);
                const safeUrl = escapeHtml(this.qrUrl);

                const printWin = window.open(
                    '',
                    '_blank',
                    'width=720,height=900'
                );

                if (printWin && !printWin.closed) {
                    printWin.document.open();
                    printWin.document.write(`<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>QR Drop-Box — ${safeDept} (${safeWorkspace})</title>
<style>
  @page { size: A5 portrait; margin: 12mm; }
  * { box-sizing: border-box; }
  body { margin: 0; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #ffffff; color: #09090b; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
  .poster { width: 100%; max-width: 520px; border: 3px solid #09090b; border-radius: 24px; padding: 32px 28px; text-align: center; background: #ffffff; }
  .brand { display: inline-flex; align-items: center; gap: 10px; padding: 6px 14px; border-radius: 999px; background: #09090b; color: #ffffff; font-weight: 800; font-size: 13px; letter-spacing: 0.04em; text-transform: uppercase; }
  .brand-kanji { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background: #6366f1; color: #ffffff; font-size: 13px; }
  .workspace { margin-top: 14px; font-size: 14px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.08em; }
  .dept { margin: 8px 0 18px; font-size: 34px; line-height: 1.15; font-weight: 900; color: #09090b; word-break: break-word; }
  .accent-bar { width: 72px; height: 6px; border-radius: 999px; margin: 0 auto 22px; background: ${safeColor}; }
  .qr-box { display: inline-block; padding: 14px; border: 2px solid #e2e8f0; border-radius: 18px; background: #ffffff; }
  .qr-box svg { display: block; width: 300px; height: 300px; }
  .instructions { margin-top: 22px; padding-top: 18px; border-top: 2px dashed #cbd5e1; }
  .inst-en { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 8px; line-height: 1.4; }
  .inst-el { font-size: 14px; font-weight: 600; color: #334155; margin: 0; line-height: 1.4; }
  .url { margin-top: 14px; font-size: 10px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: #64748b; word-break: break-all; }
  @media print { body { padding: 0; min-height: auto; } .poster { border-width: 2.5px; } }
</style>
</head>
<body>
  <div class="poster">
    <div class="brand"><span class="brand-kanji">担当</span><span>Tantou · QR Drop-Box</span></div>
    <div class="workspace">${safeWorkspace}</div>
    <h1 class="dept">${safeDept}</h1>
    <div class="accent-bar"></div>
    <div class="qr-box">${svgMarkup}</div>
    <div class="instructions">
      <p class="inst-en">Scan with your phone camera to submit an IT / Support request — No login required</p>
      <p class="inst-el">Σαρώστε με την κάμερα του κινητού σας για να υποβάλετε αίτημα IT / Υποστήριξης — Χωρίς σύνδεση</p>
    </div>
    <div class="url">${safeUrl}</div>
  </div>
  <script>
    window.addEventListener('load', function () {
      setTimeout(function () {
        window.focus();
        window.print();
      }, 150);
    });
  </script>
</body>
</html>`);
                    printWin.document.close();
                    this.setFeedback('Opened printable QR poster.');
                    return;
                }

                // Popup-blocker fallback: print dedicated @media print poster container
                const fallbackEl = document.getElementById(
                    'dept-qr-print-fallback'
                );
                if (!fallbackEl) {
                    throw new Error(
                        'Popup was blocked and print fallback container is missing.'
                    );
                }

                const cleanup = () => {
                    document.body.classList.remove('tantou-printing-qr-poster');
                    window.removeEventListener('afterprint', cleanup);
                };

                document.body.classList.add('tantou-printing-qr-poster');
                window.addEventListener('afterprint', cleanup);
                this.setFeedback('Opening print dialog...');
                setTimeout(() => {
                    window.print();
                    setTimeout(cleanup, 1500);
                }, 100);
            } catch (err) {
                this.setError(
                    'Could not open print dialog. Please check popup permissions or download SVG/PNG.'
                );
            }
        },
    };
};
