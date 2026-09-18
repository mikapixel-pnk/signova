/*
 * Platform attribution pada halaman publik.
 *
 * Bukan Business-configurable data.
 * Deployment dapat mengganti URL tanpa mengubah komponen UI.
 */
const DEFAULT_SIGNOVA_MARKETING_URL =
  "https://signova.id";

export const SIGNOVA_MARKETING_URL =
  process.env
    .NEXT_PUBLIC_SIGNOVA_MARKETING_URL
    ?.trim() ||
  DEFAULT_SIGNOVA_MARKETING_URL;
