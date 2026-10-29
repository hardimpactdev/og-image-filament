import assert from 'node:assert/strict';
import fs from 'node:fs';
import { spawnSync } from 'node:child_process';
export function render({ data, background }) {
    assert.equal(process.env.OG_TEST_SECRET, undefined);
    if (data.title === 'timeout') { while (true) { /* parent deadline kills this process */ } }
    if (data.title === 'oversized-output') { process.stdout.write(Buffer.alloc(5 * 1024 * 1024)); process.exitCode = 0; return null; }
    if (data.title === 'invalid-output') { process.stdout.write('not a PNG'); process.exit(0); }
    if (data.title === 'permissions') {
        assert.throws(() => fs.readFileSync('/etc/passwd'), { code: 'ERR_ACCESS_DENIED' });
        assert.throws(() => fs.writeFileSync('unexpected-file', 'bad'), { code: 'ERR_ACCESS_DENIED' });
        assert.throws(() => spawnSync('node', ['--version']), { code: 'ERR_ACCESS_DENIED' });
        assert.equal(process.permission.has('net'), false);
    }
    return { type: 'div', props: { style: { width: 1200, height: 630, display: 'flex', fontFamily: 'Fixture', fontSize: 40 }, children: [
        { type: 'img', props: { width: 1200, height: 630, src: background, style: { position: 'absolute' } } }, data.title,
    ] } };
}
