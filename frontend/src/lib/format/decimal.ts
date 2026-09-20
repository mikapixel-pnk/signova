const DECIMAL_PATTERN =
  /^([+-]?)(?:(\d+)(?:\.(\d*))?|\.(\d+))$/;


function powerOfTen(
  scale: number,
): bigint {
  let result =
    BigInt(1);

  for (
    let index = 0;
    index < scale;
    index += 1
  ) {
    result *=
      BigInt(10);
  }

  return result;
}


function toScaledBigInt(
  value: string,
  scale: number,
): bigint | null {
  const match =
    DECIMAL_PATTERN.exec(
      value.trim(),
    );

  if (!match) {
    return null;
  }

  const negative =
    match[1] === "-";

  const whole =
    match[2] ?? "0";

  const fraction =
    match[3] ??
    match[4] ??
    "";

  const kept =
    fraction
      .slice(0, scale)
      .padEnd(
        scale,
        "0",
      );

  let units =
    BigInt(
      `${whole}${kept}` ||
      "0",
    );

  const roundingDigit =
    fraction.charAt(scale);

  if (
    roundingDigit !== "" &&
    roundingDigit >= "5"
  ) {
    units +=
      BigInt(1);
  }

  return negative
    ? -units
    : units;
}


function rescaleHalfUp(
  units: bigint,
  fromScale: number,
  toScale: number,
): bigint {
  if (fromScale === toScale) {
    return units;
  }

  if (fromScale < toScale) {
    return (
      units *
      powerOfTen(
        toScale - fromScale,
      )
    );
  }

  const divisor =
    powerOfTen(
      fromScale - toScale,
    );

  const negative =
    units < BigInt(0);

  const absolute =
    negative
      ? -units
      : units;

  let result =
    absolute / divisor;

  const remainder =
    absolute % divisor;

  if (
    remainder * BigInt(2) >=
    divisor
  ) {
    result +=
      BigInt(1);
  }

  return negative
    ? -result
    : result;
}


function fromScaledBigInt(
  units: bigint,
  scale: number,
): string {
  const negative =
    units < BigInt(0);

  const absolute =
    negative
      ? -units
      : units;

  if (scale === 0) {
    return `${
      negative ? "-" : ""
    }${absolute.toString()}`;
  }

  const raw =
    absolute
      .toString()
      .padStart(
        scale + 1,
        "0",
      );

  const splitAt =
    raw.length - scale;

  return `${
    negative ? "-" : ""
  }${raw.slice(
    0,
    splitAt,
  )}.${raw.slice(splitAt)}`;
}


export function normalizeDecimalString(
  value: string,
  scale: number,
): string {
  const units =
    toScaledBigInt(
      value,
      scale,
    );

  return fromScaledBigInt(
    units ?? BigInt(0),
    scale,
  );
}


export function multiplyDecimalStrings(
  left: string,
  leftScale: number,
  right: string,
  rightScale: number,
  resultScale: number,
): string {
  const leftUnits =
    toScaledBigInt(
      left,
      leftScale,
    ) ?? BigInt(0);

  const rightUnits =
    toScaledBigInt(
      right,
      rightScale,
    ) ?? BigInt(0);

  const product =
    leftUnits *
    rightUnits;

  return fromScaledBigInt(
    rescaleHalfUp(
      product,
      leftScale + rightScale,
      resultScale,
    ),
    resultScale,
  );
}


export function sumDecimalStrings(
  values: string[],
  scale: number,
): string {
  let total =
    BigInt(0);

  for (const value of values) {
    total +=
      toScaledBigInt(
        value,
        scale,
      ) ?? BigInt(0);
  }

  return fromScaledBigInt(
    total,
    scale,
  );
}


export function isPositiveDecimal(
  value: string,
  scale: number,
): boolean {
  const units =
    toScaledBigInt(
      value,
      scale,
    );

  return (
    units !== null &&
    units > BigInt(0)
  );
}


export function isNonNegativeDecimal(
  value: string,
): boolean {
  const match =
    DECIMAL_PATTERN.exec(
      value.trim(),
    );

  if (!match) {
    return false;
  }

  if (match[1] !== "-") {
    return true;
  }

  const digits =
    `${
      match[2] ?? "0"
    }${
      match[3] ??
      match[4] ??
      ""
    }`;

  return !/[1-9]/.test(
    digits,
  );
}


export function formatIdrDecimal(
  value:
    | string
    | null
    | undefined,
): string {
  const normalized =
    normalizeDecimalString(
      value ?? "0",
      2,
    );

  const negative =
    normalized.startsWith("-");

  const unsigned =
    negative
      ? normalized.slice(1)
      : normalized;

  const [
    whole,
    fraction = "",
  ] =
    unsigned.split(".");

  const grouped =
    whole.replace(
      /\B(?=(\d{3})+(?!\d))/g,
      ".",
    );

  const visibleFraction =
    fraction.replace(
      /0+$/,
      "",
    );

  return `${
    negative ? "-" : ""
  }Rp${grouped}${
    visibleFraction
      ? `,${visibleFraction}`
      : ""
  }`;
}
