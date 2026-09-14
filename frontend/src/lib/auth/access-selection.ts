import type {
  PlatformAccess,
  TenantAccess,
} from "@/types/auth";

export type PendingAccessSelection = {
  platform: PlatformAccess;
  tenants: TenantAccess[];
};

let pendingAccess:
  | PendingAccessSelection
  | null = null;

export function setPendingAccessSelection(
  value: PendingAccessSelection,
): void {
  pendingAccess = value;
}

export function getPendingAccessSelection():
  | PendingAccessSelection
  | null {
  return pendingAccess;
}

export function clearPendingAccessSelection(): void {
  pendingAccess = null;
}
