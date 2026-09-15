export function formatCurrency(
  value:
    | number
    | string
    | null
    | undefined,
  currency = "IDR",
): string {
  const numeric =
    Number(value ?? 0);

  return new Intl.NumberFormat(
    "id-ID",
    {
      style: "currency",
      currency,
      maximumFractionDigits:
        currency === "IDR"
          ? 0
          : 2,
    },
  ).format(
    Number.isFinite(numeric)
      ? numeric
      : 0,
  );
}
