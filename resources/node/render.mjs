// A fresh process renders one code-owned template. No browser, network or writes.
import { readFile, realpath, stat } from 'node:fs/promises';
import { createRequire } from 'node:module';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const LIMITS = { label: 80, title: 512, description: 2000, url: 2048 };
const fail = (message) => { throw new Error(message); };

async function file(root, name, maximum) {
    if (typeof name !== 'string' || !/^[a-zA-Z0-9][a-zA-Z0-9_./-]*$/.test(name)) fail('Invalid bundled asset.');
    const resolved = await realpath(path.resolve(root, name));
    if (!resolved.startsWith(root + path.sep)) fail('Asset escapes the template directory.');
    const info = await stat(resolved);
    if (!info.isFile() || info.size > maximum) fail('Asset exceeds its size budget.');
    return readFile(resolved);
}

async function main() {
    if (Number(process.versions.node.split('.')[0]) < 24) fail('Node.js 24+ is required.');
    const [rootArgument, template, modules] = process.argv.slice(2);
    const root = await realpath(rootArgument);
    if (!/^[a-z0-9][a-z0-9-]{0,63}$/.test(template ?? '')) fail('Invalid template ID.');

    const chunks = [];
    let size = 0;
    for await (const chunk of process.stdin) {
        size += chunk.length;
        if (size > 16 * 1024) fail('Input exceeds its size budget.');
        chunks.push(chunk);
    }
    const data = JSON.parse(new TextDecoder('utf-8', { fatal: true }).decode(Buffer.concat(chunks)));
    if (!data || Array.isArray(data) || typeof data !== 'object'
        || Object.keys(data).some((key) => !Object.hasOwn(LIMITS, key))) fail('Expected OG text fields.');
    for (const [key, limit] of Object.entries(LIMITS)) {
        if (typeof data[key] !== 'string' || [...data[key]].length > limit
            || /[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/u.test(data[key])) fail(`Invalid text field: ${key}.`);
    }

    const manifest = JSON.parse(await file(root, 'manifest.json', 64 * 1024));
    if (!manifest.templates || !Object.hasOwn(manifest.templates, template)) fail('Unknown template ID.');
    if (!Array.isArray(manifest.fonts) || manifest.fonts.length < 1 || manifest.fonts.length > 8) fail('Invalid font manifest.');
    const background = await file(root, manifest.templates[template].background, 3 * 1024 * 1024);
    if (background.length < 24 || background.subarray(0, 8).toString('hex') !== '89504e470d0a1a0a'
        || background.readUInt32BE(16) !== 1200 || background.readUInt32BE(20) !== 630) fail('Background must be a 1200 by 630 PNG.');
    const fonts = await Promise.all(manifest.fonts.map(async (font) => {
        if (typeof font.name !== 'string' || !/^[a-zA-Z0-9 -]{1,80}$/.test(font.name)) fail('Invalid font name.');
        return { name: font.name, weight: 400, style: 'normal', data: await file(root, font.file, 1024 * 1024) };
    }));

    // Runtime dependencies are installed and locked by the consuming application.
    const require = createRequire(path.join(modules, '_og-runtime.cjs'));
    const { default: satori } = require('satori');
    const resvgDirectory = path.dirname(require.resolve('@resvg/resvg-wasm'));
    const { initWasm, Resvg } = await import(pathToFileURL(path.join(resvgDirectory, 'index.mjs')).href);
    await initWasm(await readFile(path.join(resvgDirectory, 'index_bg.wasm')));
    globalThis.fetch = () => fail('Network access is disabled for OG rendering.');
    const { render } = await import(pathToFileURL(path.join(root, 'template.mjs')).href);
    const tree = render({ template, data, background: `data:image/png;base64,${background.toString('base64')}` });
    const svg = await satori(tree, { width: 1200, height: 630, fonts });
    if (Buffer.byteLength(svg) > 8 * 1024 * 1024) fail('SVG exceeds its size budget.');
    const renderer = new Resvg(svg, { font: { loadSystemFonts: false } });
    const image = renderer.render();
    try {
        const png = image.asPng();
        if (png.length > 4 * 1024 * 1024) fail('PNG exceeds its size budget.');
        process.stdout.write(png);
    } finally {
        image.free();
        renderer.free();
    }
}

try {
    await main();
} catch (error) {
    process.stderr.write(`${String(error.message).slice(0, 2000)}\n`);
    process.exitCode = 1;
}
