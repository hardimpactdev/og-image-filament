declare global {
    interface Window {
        OgImageFilament?: Record<string, never>;
    }
}

window.OgImageFilament ??= {};
