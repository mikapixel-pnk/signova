export const appConfig = {
  name: process.env.NEXT_PUBLIC_APP_NAME ?? "SIGNOVA",

  apiBaseUrl:
    process.env.NEXT_PUBLIC_API_BASE_URL ??
    "/api/v1",

  environment:
    process.env.NEXT_PUBLIC_APP_ENV ??
    "local",
} as const;
