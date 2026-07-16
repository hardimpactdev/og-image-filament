import { describe, expect, it } from 'vitest';

describe('OG image Filament asset', () => {
    it('loads', async () => {
        await expect(import('./og-image-filament')).resolves.toBeDefined();
    });
});
