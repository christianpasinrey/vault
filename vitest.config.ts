import { defineConfig } from 'vitest/config';
import { fileURLToPath, URL } from 'node:url';

// El entorno es 'node' a proposito: Node 24 expone WebCrypto de forma nativa en
// globalThis.crypto, que es la misma API que usa el navegador. Asi probamos la
// criptografia real en lugar de un mock.
export default defineConfig({
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.ts'],
    },
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
});
