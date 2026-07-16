import { afterEach, describe, expect, it, vi } from 'vitest';

import {
    createOgImageDataUrl,
    downloadOgImage,
    findCardRoot,
    ogImageFilename,
    resolveOgImageTitle,
    waitForCardAssets,
} from './og-image-filament';

afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
    vi.useRealTimers();
});

describe('OG image browser export', () => {
    it('requires exactly one OG card root', () => {
        document.body.innerHTML = '<div></div>';

        expect(() => findCardRoot()).toThrow('exactly one [data-og-card]');

        document.body.innerHTML = '<article data-og-card></article><article data-og-card></article>';

        expect(() => findCardRoot()).toThrow('exactly one [data-og-card]');
    });

    it('waits for fonts and images before capture', async () => {
        const card = document.createElement('article');
        const image = document.createElement('img');
        const ready = vi.fn();

        Object.defineProperty(image, 'complete', { value: false });
        card.append(image);

        const waiting = waitForCardAssets(card, Promise.resolve()).then(ready);

        await Promise.resolve();

        expect(ready).not.toHaveBeenCalled();

        image.dispatchEvent(new Event('load'));
        await waiting;

        expect(ready).toHaveBeenCalledOnce();
    });

    it('renders at exact Open Graph dimensions', async () => {
        const card = document.createElement('article');
        const render = vi.fn().mockResolvedValue('data:image/png;base64,preview');

        await createOgImageDataUrl(card, render, Promise.resolve());

        expect(render).toHaveBeenCalledWith(card, {
            cacheBust: true,
            canvasHeight: 630,
            canvasWidth: 1200,
            height: 630,
            pixelRatio: 1,
            width: 1200,
        });
    });

    it('creates a stable PNG filename', () => {
        expect(ogImageFilename(' A proper OG image! ')).toBe('a-proper-og-image.png');
        expect(ogImageFilename('')).toBe('og-image.png');
    });

    it('uses the preview title when the browser event omits its filename', () => {
        const card = document.createElement('article');

        card.dataset.ogTitle = 'Current preview title';

        expect(resolveOgImageTitle(card)).toBe('Current preview title');
        expect(resolveOgImageTitle(card, 'Livewire title')).toBe('Livewire title');
    });

    it('downloads through a temporary Blob URL and revokes it', async () => {
        vi.useFakeTimers();

        const link = document.createElement('a');
        vi.spyOn(link, 'click').mockImplementation(() => undefined);
        vi.spyOn(link, 'remove').mockImplementation(() => undefined);
        vi.spyOn(document, 'createElement').mockReturnValue(link);
        const append = vi.spyOn(document.body, 'append');
        const createObjectURL = vi
            .spyOn(URL, 'createObjectURL')
            .mockReturnValue('blob:og-image');
        const revokeObjectURL = vi
            .spyOn(URL, 'revokeObjectURL')
            .mockImplementation(() => undefined);

        downloadOgImage(
            'data:image/png;base64,iVBORw0KGgo=',
            'A proper OG image',
        );

        expect(link.download).toBe('a-proper-og-image.png');
        expect(link.href).toBe('blob:og-image');
        expect(append).toHaveBeenCalledWith(link);
        expect(link.click).toHaveBeenCalledOnce();
        expect(link.remove).toHaveBeenCalledOnce();
        expect(createObjectURL).toHaveBeenCalledOnce();
        expect(revokeObjectURL).not.toHaveBeenCalled();

        await vi.runAllTimersAsync();

        expect(revokeObjectURL).toHaveBeenCalledWith('blob:og-image');
    });
});
