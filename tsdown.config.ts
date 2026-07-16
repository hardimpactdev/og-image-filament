import { defineConfig } from "tsdown";

export default defineConfig({
    entry: ["resources/js/og-image-filament.ts"],
    deps: {
        alwaysBundle: ["html-to-image"],
        onlyBundle: false,
    },
    format: "iife",
    globalName: "OgImageFilamentBundle",
    minify: true,
    outDir: "resources/dist",
    clean: true,
    sourcemap: true,
});
