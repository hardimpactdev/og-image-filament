import { toPng } from 'html-to-image';

type RenderCard = (
    card: HTMLElement,
    options: {
        cacheBust: boolean;
        canvasHeight: number;
        canvasWidth: number;
        height: number;
        pixelRatio: number;
        width: number;
    },
) => Promise<string>;

interface AlpineLike {
    data(name: string, callback: () => unknown): void;
}

interface GeneratorState {
    $root: HTMLElement;
    generating: boolean;
    error: string | null;
    generate(filename?: string): Promise<void>;
}

declare global {
    interface Window {
        Alpine?: AlpineLike;
        OgImageFilament?: {
            createOgImageDataUrl: typeof createOgImageDataUrl;
            downloadOgImage: typeof downloadOgImage;
            findCardRoot: typeof findCardRoot;
            ogImageFilename: typeof ogImageFilename;
            resolveOgImageTitle: typeof resolveOgImageTitle;
            waitForCardAssets: typeof waitForCardAssets;
        };
    }
}

export function findCardRoot(root: ParentNode = document): HTMLElement {
    const cards = root.querySelectorAll<HTMLElement>('[data-og-card]');

    if (cards.length !== 1) {
        throw new Error('The OG image preview must contain exactly one [data-og-card] element.');
    }

    return cards[0];
}

export async function waitForCardAssets(
    card: HTMLElement,
    fontsReady: PromiseLike<unknown> = document.fonts?.ready ?? Promise.resolve(),
): Promise<void> {
    await fontsReady;

    await Promise.all(
        Array.from(card.querySelectorAll('img')).map(
            (image) =>
                new Promise<void>((resolve, reject) => {
                    if (image.complete) {
                        if (image.currentSrc !== '' && image.naturalWidth === 0) {
                            reject(new Error(`The image [${image.currentSrc}] could not be loaded.`));

                            return;
                        }

                        resolve();

                        return;
                    }

                    image.addEventListener('load', () => resolve(), { once: true });
                    image.addEventListener(
                        'error',
                        () => reject(new Error(`The image [${image.currentSrc || image.src}] could not be loaded.`)),
                        { once: true },
                    );
                }),
        ),
    );
}

export async function createOgImageDataUrl(
    card: HTMLElement,
    render: RenderCard = toPng,
    fontsReady: PromiseLike<unknown> = document.fonts?.ready ?? Promise.resolve(),
): Promise<string> {
    await waitForCardAssets(card, fontsReady);

    return render(card, {
        cacheBust: true,
        canvasHeight: 630,
        canvasWidth: 1200,
        height: 630,
        pixelRatio: 1,
        width: 1200,
    });
}

export function ogImageFilename(title: string): string {
    const filename = title
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return `${filename || 'og-image'}.png`;
}

export function resolveOgImageTitle(card: HTMLElement, title?: string): string {
    return title?.trim() || card.dataset.ogTitle?.trim() || 'OG image';
}

export function downloadOgImage(dataUrl: string, title: string): void {
    const [metadata, encodedData] = dataUrl.split(',', 2);

    if (metadata === undefined || encodedData === undefined || !metadata.includes(';base64')) {
        throw new Error('The generated OG image data URL is invalid.');
    }

    const mimeType = metadata.match(/^data:([^;]+);base64$/)?.[1] ?? 'image/png';
    const binary = atob(encodedData);
    const bytes = Uint8Array.from(binary, (character) => character.charCodeAt(0));
    const objectUrl = URL.createObjectURL(new Blob([bytes], { type: mimeType }));
    const link = document.createElement('a');

    link.download = ogImageFilename(title);
    link.href = objectUrl;
    document.body.append(link);
    link.click();
    link.remove();

    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 0);
}

let alpineGeneratorRegistered = false;

function registerAlpineGenerator(): void {
    if (window.Alpine === undefined || alpineGeneratorRegistered) {
        return;
    }

    alpineGeneratorRegistered = true;

    window.Alpine.data('ogImageFilamentGenerator', () => ({
        generating: false,
        error: null,

        async generate(this: GeneratorState, filename?: string): Promise<void> {
            this.generating = true;
            this.error = null;

            try {
                const card = findCardRoot(this.$root);
                const dataUrl = await createOgImageDataUrl(card);

                downloadOgImage(dataUrl, resolveOgImageTitle(card, filename));
            } catch (error) {
                console.error(error);
                this.error = 'The PNG could not be generated. Please try again.';
            } finally {
                this.generating = false;
            }
        },
    }));
}

window.OgImageFilament = {
    createOgImageDataUrl,
    downloadOgImage,
    findCardRoot,
    ogImageFilename,
    resolveOgImageTitle,
    waitForCardAssets,
};

registerAlpineGenerator();
document.addEventListener('alpine:init', registerAlpineGenerator, { once: true });
