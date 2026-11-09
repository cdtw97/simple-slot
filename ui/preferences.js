import {byId,element,art,dialog} from './view.js';

const defaults = {mute:false, volume:.35, fast:false, motion:'system'};
let stored = {};
try { stored = JSON.parse(localStorage.getItem('simple-slot.preferences') || '{}') || {}; } catch {}
export const prefs = {...defaults,...stored};
export const reduced = () => prefs.motion === 'reduce' || (prefs.motion === 'system' && matchMedia('(prefers-reduced-motion: reduce)').matches);
let context;
export function wakeAudio() {
    if (prefs.mute || document.hidden) return;
    try {
        const Audio = window.AudioContext || window.webkitAudioContext;
        if (!Audio) return;
        context ??= new Audio();
        if (context.state !== 'running') context.resume().catch(() => {});
    } catch {}
}
const cues = {spin:[180,270],stop:[310],win:[395,498],large:[392,494,587,784],lamp:[523,660],entry:[392,494,587,698],complete:[698,587,784]};
export function sound(name) {
    if (!context || context.state !== 'running' || prefs.mute || document.hidden || !prefs.volume) return;
    const frequencies = cues[name] || [];
    frequencies.forEach((frequency,i) => {
        const start = context.currentTime + i * .075;
        const duration = name === 'stop' ? .045 : .12;
        const osc = context.createOscillator(), gain = context.createGain();
        osc.type = name === 'spin' || name === 'stop' ? 'triangle' : 'sine';
        osc.frequency.value = frequency;
        gain.gain.setValueAtTime(.0001,start);
        gain.gain.exponentialRampToValueAtTime(Math.max(.0002,.14 * prefs.volume),start + .012);
        gain.gain.exponentialRampToValueAtTime(.0001,start + duration);
        osc.connect(gain).connect(context.destination);
        osc.start(start);
        osc.stop(start + duration + .01);
    });
}
document.addEventListener('visibilitychange',() => {
    if (document.hidden && context?.state === 'running') context.suspend().catch(() => {});
});
function save() {
    document.documentElement.classList.toggle('reduce-motion',reduced());
    try {localStorage.setItem('simple-slot.preferences',JSON.stringify(prefs));} catch {}
    const button = byId('mute-button');
    button.replaceChildren(art(prefs.mute ? 'mute' : 'volume',true));
    button.setAttribute('aria-label',prefs.mute ? 'Unmute sound' : 'Mute sound');
    button.setAttribute('aria-pressed',String(prefs.mute));
}
byId('mute-button').onclick = () => {prefs.mute = !prefs.mute; save(); wakeAudio();};
function setting(label, input) {
    const row = element('label','setting',label); row.append(input); return row;
}
function check(key) {
    const input = element('input'); input.type = 'checkbox'; input.checked = prefs[key];
    input.onchange = () => {prefs[key] = input.checked; save();};
    return input;
}
byId('settings-button').onclick = () => {
    const volume = element('input'); volume.type = 'range'; volume.min = 0; volume.max = 100; volume.value = prefs.volume * 100;
    volume.oninput = () => {prefs.volume = Number(volume.value)/100;save();};
    const motion = element('select');
    for (const [val,title] of [['system','Use device setting'],['reduce','Reduced motion'],['full','Full motion']]) {
        const option = element('option',null,title); option.value = val; motion.append(option);
    }
    motion.value = prefs.motion;
    motion.onchange = () => {prefs.motion = motion.value; save();};
    dialog('Cabinet settings',setting('Mute sound',check('mute')),setting('Volume',volume),setting('Fast spins',check('fast')),setting('Motion',motion));
};
save();
matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change',save);
