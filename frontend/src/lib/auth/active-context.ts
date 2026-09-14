export type SelectedAccessContext =
  | {
      type: "PLATFORM";
      tenantId: null;
    }
  | {
      type: "TENANT";
      tenantId: string;
    };

let selectedContext:
  | SelectedAccessContext
  | null = null;

export function setSelectedContext(
  value: SelectedAccessContext,
): void {
  selectedContext = value;
}

export function getSelectedContext():
  | SelectedAccessContext
  | null {
  return selectedContext;
}

export function clearSelectedContext(): void {
  selectedContext = null;
}
