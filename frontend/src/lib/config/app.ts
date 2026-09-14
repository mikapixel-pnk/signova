export const appConfig = {
  name:
    process.env.NEXT_PUBLIC_APP_NAME ??
    "SIGNOVA",

  apiBaseUrl:
    process.env.NEXT_PUBLIC_API_BASE_URL ??
    "/api/v1",

  csrfPath:
    process.env.NEXT_PUBLIC_CSRF_PATH ??
    "/sanctum/csrf-cookie",

  environment:
    process.env.NEXT_PUBLIC_APP_ENV ??
    "local",
} as const;
