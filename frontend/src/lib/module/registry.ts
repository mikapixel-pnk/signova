import {
  modules,
} from "@/config/modules";

import {
  navigationGroups,
} from "@/config/navigation-groups";

import type {
  ModuleDefinition,
  ModuleGroupKey,
  ModuleKey,
  ModulePlan,
} from "@/config/module-types";

export function getModule(
  key: ModuleKey,
): ModuleDefinition {
  return modules[key];
}

export function getModulesByGroup(
  group:
    ModuleGroupKey,
): ModuleDefinition[] {
  return Object.values(
    modules,
  )
    .filter(
      (module) =>
        module.group ===
        group,
    )
    .sort(
      (a, b) =>
        (
          a.navigation
            ?.order ?? 999
        ) -
        (
          b.navigation
            ?.order ?? 999
        ),
    );
}

export function getNavigationGroups() {
  return navigationGroups
    .slice()
    .sort(
      (a, b) =>
        a.order -
        b.order,
    );
}

export function getDesktopModules(
  group:
    ModuleGroupKey,
) {
  return getModulesByGroup(
    group,
  ).filter(
    (module) =>
      module.navigation
        ?.desktop !==
      false,
  );
}

export function getMobileModules(
  group:
    ModuleGroupKey,
) {
  return getModulesByGroup(
    group,
  ).filter(
    (module) =>
      module.navigation
        ?.mobile !==
      false,
  );
}

const planWeight:
  Record<
    ModulePlan,
    number
  > = {
  starter: 10,
  business: 20,
  pro: 30,
};

export function planIncludes(
  currentPlan:
    ModulePlan,
  requiredPlan:
    ModulePlan,
): boolean {
  return (
    planWeight[
      currentPlan
    ] >=
    planWeight[
      requiredPlan
    ]
  );
}

export function modulePlanLabel(
  plan: ModulePlan,
): "Business" | "Pro" | null {
  if (
    plan ===
    "starter"
  ) {
    return null;
  }

  return plan ===
    "business"
    ? "Business"
    : "Pro";
}
