export type ApiMeta = {
  request_id?: string;
  [key: string]: unknown;
};

export type ApiSuccessResponse<T> = {
  success: true;
  data: T;
  meta: ApiMeta;
  message?: string;
};

export type ApiErrorDetails =
  Record<string, unknown>;

export type ApiErrorPayload = {
  success?: false;

  error?: {
    code?: string;
    message?: string;
    details?: ApiErrorDetails;
  };

  meta?: ApiMeta;

  /*
   * Laravel ValidationException masih dipakai
   * oleh beberapa endpoint auth.
   */
  message?: string;

  errors?: Record<
    string,
    string[]
  >;
};

export class ApiClientError extends Error {
  readonly status: number;

  readonly code:
    | string
    | null;

  readonly details:
    ApiErrorDetails;

  readonly requestId:
    | string
    | null;

  readonly fieldErrors:
    Record<
      string,
      string[]
    >;

  constructor(
    message: string,
    status: number,
    options: {
      code?: string | null;
      details?: ApiErrorDetails;
      requestId?: string | null;
      fieldErrors?: Record<
        string,
        string[]
      >;
    } = {},
  ) {
    super(message);

    this.name =
      "ApiClientError";

    this.status =
      status;

    this.code =
      options.code ??
      null;

    this.details =
      options.details ??
      {};

    this.requestId =
      options.requestId ??
      null;

    this.fieldErrors =
      options.fieldErrors ??
      {};
  }

  fieldError(
    field: string,
  ): string | null {
    return (
      this.fieldErrors[
        field
      ]?.[0] ??
      null
    );
  }
}
