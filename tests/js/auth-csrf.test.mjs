import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

test('authentication requests use the current cookie after session token rotation', async () => {
    const requests = [];
    class XMLHttpRequest {
        headers = {};
        open(method, url) { this.method = method; this.url = url; }
        setRequestHeader(name, value) { this.headers[name] = value; }
        getAllResponseHeaders() { return ''; }
        onloadend = null;
        send() {
            requests.push(this);
            this.status = 200;
            this.statusText = 'OK';
            this.responseText = '{}';
            this.onloadend();
        }
    }

    const document = {
        cookie: 'XSRF-TOKEN=initial-token',
        querySelector: () => ({ getAttribute: () => 'initial-token' }),
    };
    const context = vm.createContext({
        window: { location: { href: 'http://localhost/login' } },
        document, navigator: { userAgent: 'test' }, XMLHttpRequest,
        URL, URLSearchParams, setTimeout, clearTimeout, console,
    });
    vm.runInContext(readFileSync(new URL('../../node_modules/axios/dist/axios.js', import.meta.url), 'utf8'), context);
    const bootstrap = readFileSync(new URL('../../resources/js/bootstrap.js', import.meta.url), 'utf8');
    vm.runInContext(bootstrap.replace("import axios from 'axios';", ''), context);

    for (const [path, token] of [
        ['/login', 'initial-token'],
        ['/logout', 'after-login-token'],
        ['/login', 'after-logout-token'],
    ]) {
        document.cookie = `XSRF-TOKEN=${token}`;
        await context.window.axios.post(path, {});
        const { headers } = requests.at(-1);
        assert.equal(headers['X-XSRF-TOKEN'], token);
        assert.equal(headers['X-CSRF-TOKEN'], undefined);
        assert.equal(headers['X-Requested-With'], 'XMLHttpRequest');
    }
});
