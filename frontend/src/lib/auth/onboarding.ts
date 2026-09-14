const ONBOARDING_KEY =
  "signova.onboarding.completed";

export function hasCompletedOnboarding(): boolean {
  if (typeof window === "undefined") {
    return false;
  }

  return (
    window.localStorage.getItem(
      ONBOARDING_KEY,
    ) === "1"
  );
}

export function markOnboardingCompleted(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(
    ONBOARDING_KEY,
    "1",
  );
}
