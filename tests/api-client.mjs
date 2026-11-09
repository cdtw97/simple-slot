import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';

export async function request(url, cookies, payload) {
    const headers = {Cookie: [...cookies].map(([key, value]) => `${key}=${value}`).join('; ')};
    if (payload) headers['Content-Type'] = 'application/json';
    const response = await fetch(url, {
        method: payload ? 'POST' : 'GET',
        headers,
        body: payload && JSON.stringify(payload),
    });
    for (const cookie of response.headers.getSetCookie()) {
        const [pair] = cookie.split(';');
        const index = pair.indexOf('=');
        cookies.set(pair.slice(0, index), pair.slice(index + 1));
    }
    return {status: response.status, ...await response.json()};
}

export function successful(response) {
    assert.equal(response.status, 200, JSON.stringify(response));
    return response.state;
}

export function action(model, name, options = {}) {
    return {
        mode: model.state.mode, action: name, csrf: model.csrf,
        actionId: randomUUID(), expectedRevision: model.state.revision, ...options,
    };
}
