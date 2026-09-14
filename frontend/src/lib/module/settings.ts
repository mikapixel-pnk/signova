import type {
  ModuleDefinition,
  ModuleKey,
} from "@/config/module-types";

import {
  getModule,
  getModulesByGroup,
} from "./registry";

export type SettingsSectionKey =
  | "bisnis"
  | "profil"
  | "keuangan"
  | "tim"
  | "paket"
  | "integrasi";

const settingsSectionMap:
  Record<
    SettingsSectionKey,
    ModuleKey
  > = {
  bisnis:
    "business-settings",

  profil:
    "profile-settings",

  keuangan:
    "finance-settings",

  tim:
    "team-access",

  paket:
    "subscription",

  integrasi:
    "integrations",
};

export function isSettingsSectionKey(
  value:
    string | null | undefined,
): value is SettingsSectionKey {
  if (!value) {
    return false;
  }

  return Object.prototype.hasOwnProperty.call(
    settingsSectionMap,
    value,
  );
}

export function getSettingsSection(
  value?:
    string | null,
): ModuleDefinition {
  const sectionKey:
    SettingsSectionKey =
    isSettingsSectionKey(value)
      ? value
      : "bisnis";

  const moduleKey =
    settingsSectionMap[
      sectionKey
    ];

  return getModule(
    moduleKey,
  );
}

export function getSettingsSections():
  ModuleDefinition[] {
  return getModulesByGroup(
    "pengaturan",
  );
}
