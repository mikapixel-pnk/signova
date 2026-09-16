export type SelectedAccessContext =
  | {
      type: "PLATFORM";
      tenantId: null;
      businessId: null;
    }
  | {
      type: "TENANT";
      tenantId: string;
      businessId: string;
    };

const STORAGE_KEY =
  "signova.active-context.v2";

const LEGACY_STORAGE_KEY =
  "signova.active-context.v1";

let selectedContext:
  | SelectedAccessContext
  | null = null;

function isValidContext(
  value: unknown,
): value is SelectedAccessContext {
  if (
    !value ||
    typeof value !== "object"
  ) {
    return false;
  }

  const candidate =
    value as {
      type?: unknown;
      tenantId?: unknown;
      businessId?: unknown;
    };

  if (
    candidate.type ===
      "PLATFORM"
  ) {
    return (
      candidate.tenantId ===
        null &&
      candidate.businessId ===
        null
    );
  }

  if (
    candidate.type ===
      "TENANT"
  ) {
    return (
      typeof candidate.tenantId ===
        "string" &&
      candidate.tenantId.length >
        0 &&
      typeof candidate.businessId ===
        "string" &&
      candidate.businessId.length >
        0
    );
  }

  return false;
}

function readStoredContext():
  | SelectedAccessContext
  | null {
  if (
    typeof window ===
    "undefined"
  ) {
    return null;
  }

  try {
    const raw =
      window.localStorage.getItem(
        STORAGE_KEY,
      );

    if (!raw) {
      return null;
    }

    const parsed =
      JSON.parse(raw);

    return isValidContext(
      parsed,
    )
      ? parsed
      : null;
  } catch {
    return null;
  }
}

export function setSelectedContext(
  value: SelectedAccessContext,
): void {
  selectedContext =
    value;

  if (
    typeof window !==
    "undefined"
  ) {
    window.localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify(
        value,
      ),
    );

    window.localStorage.removeItem(
      LEGACY_STORAGE_KEY,
    );
  }
}

export function getSelectedContext():
  | SelectedAccessContext
  | null {
  if (selectedContext) {
    return selectedContext;
  }

  selectedContext =
    readStoredContext();

  return selectedContext;
}

export function clearSelectedContext(): void {
  selectedContext =
    null;

  if (
    typeof window !==
    "undefined"
  ) {
    window.localStorage.removeItem(
      STORAGE_KEY,
    );

    window.localStorage.removeItem(
      LEGACY_STORAGE_KEY,
    );
  }
}
