import {
  actions,
} from "@/config/actions";

import type {
  ActionDefinition,
  ActionKey,
} from "@/config/actions";

export function getAction(
  key: ActionKey,
): ActionDefinition {
  return actions[key];
}

export function getQuickActions():
  ActionDefinition[] {
  return Object.values(
    actions,
  ).sort(
    (a, b) =>
      a.order -
      b.order,
  );
}
