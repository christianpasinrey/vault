import { defineConfig } from 'vitest/config';
import { fileURLToPath, URL } from 'node:url';

// The 'node' environment is deliberate: Node 24 exposes WebCrypto natively on
// globalThis.crypto, the very same API the browser uses. That way we exercise
// the real cryptography instead of a mock.
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
