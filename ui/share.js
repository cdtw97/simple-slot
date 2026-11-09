import { element, money, dialog } from './view.js';

function cardDetails(record) {
    const daily = record.mode === 'daily';
    const date = daily ? record.dailyDate : record.completedAt?.slice(0, 10);
    const mode = daily ? 'Daily Challenge' : 'Score Attack';
    const attempt = daily ? (record.official ? 'Official' : 'Practice') : '';
    const heading = [mode, attempt, date].filter(Boolean).join(' · ');
    const text = [
        'CDTW Games · Simple Slot',
        heading,
        money(record.scoreUnits) + ' points',
        'Corner Rush bonuses: ' + record.bonuses + ' · Respins used: ' + record.respinsUsed,
        'Fruit Arcade · Rules ' + record.rulesVersion,
    ].join('\n');
    return { mode, date, attempt, heading, text };
}
function drawCard(record, details) {
    const canvas = element('canvas');
    canvas.width = 1000;
    canvas.height = 608;
    canvas.className = 'share-canvas';
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', details.text);
    const ctx = canvas.getContext('2d');
    if (!ctx) return null;
    function fill(color, x, y, w, h) {
        ctx.fillStyle = color;
        ctx.fillRect(x, y, w, h);
    }
    function type(value, font, color, x, y, maxWidth = 910) {
        ctx.font = font;
        ctx.fillStyle = color;
        ctx.fillText(String(value), x, y, maxWidth);
    }
    fill('#183e33', 0, 0, 1000, 608);
    fill('#f9f2df', 20, 20, 960, 568);
    fill('#285d4c', 20, 20, 960, 100);
    type('CDTW GAMES', '800 29px "Barlow Condensed", sans-serif', '#f9f2df', 52, 81);
    type('FRUIT ARCADE', 'italic 800 57px "Barlow Condensed", sans-serif', '#ffd78b', 308, 88);
    for (let i = 0; i < 3; i++)
        fill(['#e58b50', '#f5b85e', '#f6d892'][i], 795 + i * 14, 46 + i * 15, 133 - i * 19, 9);
    type(details.heading, '700 25px "DM Sans", sans-serif', '#315346', 52, 177);
    type(money(record.scoreUnits), '800 112px "Barlow Condensed", sans-serif', '#183e33', 48, 296);
    type('FINAL SCORE', '700 19px "DM Sans", sans-serif', '#586857', 54, 334);
    fill('#c8cab5', 52, 364, 896, 2);
    type('CORNER RUSH BONUSES', '700 18px "DM Sans", sans-serif', '#586857', 52, 404);
    type('RESPINS USED', '700 18px "DM Sans", sans-serif', '#586857', 530, 404);
    type(record.bonuses, '800 58px "Barlow Condensed", sans-serif', '#183e33', 52, 468);
    type(record.respinsUsed, '800 58px "Barlow Condensed", sans-serif', '#183e33', 530, 468);
    fill('#e6e4ce', 20, 510, 960, 78);
    type('SIMPLE SLOT', '800 28px "Barlow Condensed", sans-serif', '#315346', 52, 560);
    type('Rules ' + record.rulesVersion, '400 20px "DM Sans", sans-serif', '#4b5b4c', 706, 558, 236);
    return canvas;
}
export async function shareCard(record) {
    const details = cardDetails(record);
    const preview = element('div');
    const actions = element('div', 'share-actions');
    const status = element('p', 'share-status');
    status.setAttribute('role', 'status');
    const copy = element('button', 'basic', 'Copy result');
    const manual = element('textarea', 'share-text');
    manual.value = details.text;
    manual.readOnly = true;
    manual.hidden = true;
    manual.setAttribute('aria-label', 'Scorecard text to copy');
    copy.onclick = async () => {
        try {
            await navigator.clipboard.writeText(details.text);
            status.textContent = 'Scorecard text copied.';
        } catch {
            manual.hidden = false;
            manual.focus();
            manual.select();
            status.textContent = 'Select Copy from your browser or keyboard to copy this result.';
        }
    };
    actions.append(copy);
    dialog('Share scorecard', preview, actions, manual, status);
    await document.fonts.ready;
    if (!preview.isConnected) return;
    const canvas = drawCard(record, details);
    if (!canvas) {
        preview.append(element('p', null, details.text));
        return;
    }
    preview.append(canvas);
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
    if (!blob || !actions.isConnected) return;
    const filename =
        'simple-slot-' +
        record.mode +
        '-' +
        (details.date || record.id) +
        (details.attempt ? '-' + details.attempt.toLowerCase() : '') +
        '.png';
    const download = element('button', 'basic', 'Download PNG');
    download.id = 'download-card';
    download.onclick = () => {
        const url = URL.createObjectURL(blob);
        const link = element('a');
        link.href = url;
        link.download = filename;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 30000);
        status.textContent = 'PNG download started.';
    };
    actions.prepend(download);
    if (!navigator.share) return;
    const file = new File([blob], filename, { type: 'image/png' });
    let shareData = { title: 'Simple Slot scorecard', text: details.text };
    if (navigator.canShare?.({ files: [file] })) shareData = { ...shareData, files: [file] };
    const native = element('button', 'basic', 'Share…');
    native.onclick = async () => {
        try {
            await navigator.share(shareData);
            status.textContent = 'Share sheet closed.';
        } catch (e) {
            status.textContent =
                e.name === 'AbortError'
                    ? 'Sharing canceled.'
                    : 'Sharing is unavailable here. Download the image or copy the text.';
        }
    };
    actions.prepend(native);
}
