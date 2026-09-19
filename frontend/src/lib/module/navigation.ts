import type {
  ComponentType,
} from "react";

import type {
  ModuleDefinition,
  ModuleGroupKey,
  ModuleTone,
} from "@/config/module-types";

import {
  getDesktopModules,
  getMobileModules,
  getNavigationGroups,
} from "./registry";

export type NavigationSurface =
  | "desktop"
  | "mobile";

export type NavigationModelGroup = {
  key: ModuleGroupKey;
  label: string;
  description: string;
  tone: ModuleTone;

  icon:
    ComponentType<{
      size?: number;
      strokeWidth?: number;
    }>;

  order: number;

  defaultOpen:
    boolean;

  modules:
    ModuleDefinition[];
};

export function getNavigationModel(
  surface:
    NavigationSurface,
  capabilityCodes:
    readonly string[] = [],
): NavigationModelGroup[] {
  const capabilities =
    new Set(
      capabilityCodes,
    );

  return getNavigationGroups()
    .map(
      (group) => ({
        ...group,

        defaultOpen:
          group.defaultOpen ??
          false,

        modules:
          (
            surface ===
            "desktop"
              ? getDesktopModules(
                  group.key,
                )
              : getMobileModules(
                  group.key,
                )
          ).filter(
            (moduleDef) =>
              !moduleDef.capability ||
              capabilities.has(
                moduleDef.capability,
              ),
          ),
      }),
    )
    .filter(
      (group) =>
        group.modules.length >
        0,
    );
}
