/** RFC 9457 problem details, as returned by the API (docs/architecture.md#http-api-draft). */
export interface ProblemDetails {
  type: string;
  title: string;
  status: number;
  detail?: string;
}

/**
 * Base of every error answered by the API. Never show `problem.detail` to the user:
 * it is English technical text. Pick Spanish copy from translation files instead.
 */
export class HttpError extends Error {
  readonly status: number;
  readonly problem: ProblemDetails;

  constructor(problem: ProblemDetails) {
    super(`${String(problem.status)} ${problem.title}`);
    this.name = new.target.name;
    this.status = problem.status;
    this.problem = problem;
  }
}

export class UnauthorizedError extends HttpError {}
export class NotFoundError extends HttpError {}
export class ConflictError extends HttpError {}
export class ValidationError extends HttpError {}
/** Rate-limited (login, 2FA, profile changes): the API sends `Retry-After`. */
export class TooManyRequestsError extends HttpError {}
export class ServerError extends HttpError {}

/** The request never got a response (offline, DNS, connection reset...). */
export class NetworkError extends Error {
  constructor(options?: ErrorOptions) {
    super('The request did not reach the server.', options);
    this.name = 'NetworkError';
  }
}

export function httpErrorFor(problem: ProblemDetails): HttpError {
  switch (problem.status) {
    case 401:
      return new UnauthorizedError(problem);
    case 404:
      return new NotFoundError(problem);
    case 409:
      return new ConflictError(problem);
    case 422:
      return new ValidationError(problem);
    case 429:
      return new TooManyRequestsError(problem);
    default:
      return problem.status >= 500 ? new ServerError(problem) : new HttpError(problem);
  }
}

/** The API answered successfully, but the body does not have the expected shape. */
export class UnexpectedResponseError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'UnexpectedResponseError';
  }
}
