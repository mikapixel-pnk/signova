"use client";

import {
  useEffect,
} from "react";
import {
  useRouter,
} from "next/navigation";

import {
  SplashScreen,
} from "@/components/feedback/splash-screen";
import {
  hasCompletedOnboarding,
} from "@/lib/auth/onboarding";

export default function Home() {
  const router = useRouter();

  useEffect(() => {
    const timer = window.setTimeout(
      () => {
        const desktop =
          window.matchMedia(
            "(min-width: 900px)",
          ).matches;

        if (desktop) {
          router.replace("/login");
          return;
        }

        router.replace(
          hasCompletedOnboarding()
            ? "/login"
            : "/welcome",
        );
      },
      950,
    );

    return () => {
      window.clearTimeout(timer);
    };
  }, [router]);

  return <SplashScreen />;
}
