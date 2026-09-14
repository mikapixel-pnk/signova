import {
  Suspense,
} from "react";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  SettingsPage,
} from "@/components/settings/settings-page";

export default function Page() {
  return (
    <TenantShell>
      <Suspense>
        <SettingsPage />
      </Suspense>
    </TenantShell>
  );
}
