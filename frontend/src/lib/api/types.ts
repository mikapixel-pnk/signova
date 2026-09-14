export type ApiErrorPayload = {
  error?: {
    code?: string;
    message?: string;
    meta?: Record<string, unknown>;
  };
};

export class ApiClientError extends Error {
  readonly status: number;
  readonly code: string | null;
  readonly meta: Record<string, unknown>;

  constructor(
    message: string,
    status: number,
    code: string | null = null,
    meta: Record<string, unknown> = {},
  ) {
    super(message);

    this.name = "ApiClientError";
    this.status = status;
    this.code = code;
    this.meta = meta;
  }
}
