import { httpErrorFor, NetworkError, type ProblemDetails } from './errors';

export interface HttpClient {
  get(path: string, options?: RequestOptions): Promise<unknown>;
  post(path: string, body?: unknown, options?: RequestOptions): Promise<unknown>;
  put(path: string, body?: unknown, options?: RequestOptions): Promise<unknown>;
  patch(path: string, body?: unknown, options?: RequestOptions): Promise<unknown>;
  delete(path: string, options?: RequestOptions): Promise<unknown>;
  /** A GET that sends `If-None-Match` and reports a `304` instead of rejecting with it. */
  getConditional(path: string, options?: ConditionalRequestOptions): Promise<ConditionalResponse>;
}

export interface ConditionalRequestOptions extends RequestOptions {
  /** The ETag of the copy the caller holds; leave it out to always get the body. */
  etag?: string | null;
}

export type ConditionalResponse =
  { notModified: true } | { notModified: false; body: unknown; etag: string | null };

export interface RequestOptions {
  signal?: AbortSignal;
}

interface HttpClientConfig {
  /** Origin of the API. The SPA and the API share it in every environment. */
  baseUrl: string;
}

/**
 * Thin fetch wrapper. It resolves with the parsed JSON body (`undefined` when there is
 * none) and rejects with a typed HttpError or NetworkError. Responses are `unknown`:
 * each feature's infrastructure maps them to domain types.
 */
export function createHttpClient({ baseUrl }: HttpClientConfig): HttpClient {
  async function send(
    method: string,
    path: string,
    body: unknown,
    options: RequestOptions,
    headers: Headers,
  ): Promise<Response> {
    if (method !== 'GET') {
      // The API refuses state-changing requests without it (CSRF): another site cannot
      // add a custom header without a CORS preflight, which the API never allows.
      headers.set('X-Requested-With', 'XMLHttpRequest');
    }
    const init: RequestInit = { method, headers, credentials: 'same-origin' };
    if (body !== undefined) {
      headers.set('Content-Type', 'application/json');
      init.body = JSON.stringify(body);
    }
    if (options.signal !== undefined) {
      init.signal = options.signal;
    }

    try {
      return await fetch(new URL(path, baseUrl), init);
    } catch (cause) {
      if (isAbortError(cause)) {
        throw cause;
      }
      throw new NetworkError({ cause });
    }
  }

  async function request(
    method: string,
    path: string,
    body: unknown,
    options: RequestOptions = {},
  ): Promise<unknown> {
    const response = await send(method, path, body, options, jsonHeaders());
    if (!response.ok) {
      throw httpErrorFor(await problemFrom(response));
    }
    return bodyOf(response);
  }

  async function getConditional(
    path: string,
    { etag, ...options }: ConditionalRequestOptions = {},
  ): Promise<ConditionalResponse> {
    const headers = jsonHeaders();
    if (etag !== undefined && etag !== null) {
      headers.set('If-None-Match', etag);
    }
    const response = await send('GET', path, undefined, options, headers);
    if (response.status === 304) {
      return { notModified: true };
    }
    if (!response.ok) {
      throw httpErrorFor(await problemFrom(response));
    }
    return {
      notModified: false,
      body: await bodyOf(response),
      etag: response.headers.get('ETag'),
    };
  }

  return {
    get: (path, options) => request('GET', path, undefined, options),
    post: (path, body, options) => request('POST', path, body, options),
    put: (path, body, options) => request('PUT', path, body, options),
    patch: (path, body, options) => request('PATCH', path, body, options),
    delete: (path, options) => request('DELETE', path, undefined, options),
    getConditional,
  };
}

function jsonHeaders(): Headers {
  return new Headers({ Accept: 'application/json' });
}

async function bodyOf(response: Response): Promise<unknown> {
  const text = await response.text();
  return text === '' ? undefined : (JSON.parse(text) as unknown);
}

async function problemFrom(response: Response): Promise<ProblemDetails> {
  const fallback: ProblemDetails = {
    type: 'about:blank',
    title: response.statusText,
    status: response.status,
  };

  if (!(response.headers.get('Content-Type') ?? '').includes('application/problem+json')) {
    return fallback;
  }

  try {
    const body: unknown = await response.json();
    return isProblemDetails(body) ? body : fallback;
  } catch {
    return fallback;
  }
}

function isProblemDetails(value: unknown): value is ProblemDetails {
  if (typeof value !== 'object' || value === null) {
    return false;
  }
  const candidate = value as Record<string, unknown>;
  return (
    typeof candidate.type === 'string' &&
    typeof candidate.title === 'string' &&
    typeof candidate.status === 'number' &&
    (candidate.detail === undefined || typeof candidate.detail === 'string')
  );
}

// Checked by name: the AbortError class differs between runtimes (browser, Node, jsdom).
function isAbortError(error: unknown): boolean {
  return error instanceof Error && error.name === 'AbortError';
}
