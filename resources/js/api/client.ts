/**
 * Thin wrapper over fetch for the vault API.
 *
 * Everything travels on the same origin with the session cookie, so there are no
 * bearer tokens to store anywhere. The status code is preserved on the error so
 * callers can tell a 409 (someone else edited the item) from a 401 (the session
 * is gone) without parsing messages.
 */

export class ApiError extends Error {
    constructor(
        message: string,
        readonly status: number,
        readonly body: unknown = null,
    ) {
        super(message);
        this.name = 'ApiError';
    }

    /** The item was modified by another session since it was loaded. */
    get isConflict(): boolean {
        return this.status === 409;
    }

    /** The session expired or was never established. */
    get isUnauthenticated(): boolean {
        return this.status === 401 || this.status === 419;
    }
}

function csrfToken(): string {
    const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));

    return cookie === undefined ? '' : decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
}

async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
    const response = await fetch(path, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
            ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    const payload = response.status === 204 ? null : await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(messageFrom(payload, response.status), response.status, payload);
    }

    return payload as T;
}

function messageFrom(payload: unknown, status: number): string {
    if (payload !== null && typeof payload === 'object') {
        const { message, errors } = payload as { message?: unknown; errors?: Record<string, string[]> };

        // Validation responses carry the useful text inside `errors`; the
        // top-level message is a generic "The given data was invalid".
        const firstError = errors === undefined ? undefined : Object.values(errors)[0]?.[0];

        if (typeof firstError === 'string' && firstError !== '') return firstError;
        if (typeof message === 'string' && message !== '') return message;
    }

    return `The request failed (HTTP ${status}).`;
}

export const api = {
    get: <T>(path: string) => request<T>('GET', path),
    post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
    put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
    del: <T>(path: string) => request<T>('DELETE', path),
};
