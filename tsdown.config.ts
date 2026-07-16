import { defineConfig } from "tsdown";

export default defineConfig({
    entry: ["resources/js/og-image-filament.ts"],
    format: "iife",
    minify: true,
    outDir: "resources/dist",
    clean: true,
    sourcemap: true,
});
