const svgNS = 'http://www.w3.org/2000/svg';
export const byId = (id) => document.getElementById(id);
export const money = (units) =>
    (units / 100).toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
export const signed = (units) => (units > 0 ? '+' : '') + money(units);
export function element(tag, className, content) {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (content !== undefined) el.textContent = content;
    return el;
}
export function art(id, isIcon = false) {
    const svg = document.createElementNS(svgNS, 'svg');
    svg.classList.add(isIcon ? 'icon' : 'fruit');
    svg.setAttribute('aria-hidden', 'true');
    const use = document.createElementNS(svgNS, 'use');
    use.setAttribute('href', 'assets/' + (isIcon ? 'icons' : 'fruit') + '.svg#' + id);
    svg.append(use);
    return svg;
}
export function dialog(title, ...children) {
    byId('dialog-title').textContent = title;
    byId('dialog-body').replaceChildren(...children);
    if (!byId('dialog').open) byId('dialog').showModal();
}
byId('dialog-close').onclick = () => byId('dialog').close();
byId('dialog').addEventListener('click', (event) => {
    if (event.target !== byId('dialog')) return;
    const rect = byId('dialog').getBoundingClientRect();
    if (
        event.clientX < rect.left ||
        event.clientX > rect.right ||
        event.clientY < rect.top ||
        event.clientY > rect.bottom
    )
        byId('dialog').close();
});
export function notice(message) {
    byId('notice').textContent = message;
    byId('notice').hidden = !message;
}
