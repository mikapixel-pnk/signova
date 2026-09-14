"use client";

import {
  BarChart3,
  FileText,
  Smartphone,
} from "lucide-react";
import {
  useRouter,
} from "next/navigation";
import {
  useState,
  type ComponentType,
} from "react";

import {
  Button,
} from "@/components/ui/button";
import {
  markOnboardingCompleted,
} from "@/lib/auth/onboarding";
import {
  cx,
} from "@/lib/utils";

import styles from "./welcome.module.css";

type Slide = {
  title: string;
  description: string;
  icon: ComponentType<{
    size?: number;
  }>;
};

const slides: Slide[] = [
  {
    title:
      "Kelola bisnis tanpa ribet.",
    description:
      "Pelanggan, barang dan jasa, serta aktivitas penting tersusun dalam satu aplikasi yang mudah dipahami.",
    icon: Smartphone,
  },
  {
    title:
      "Tagihan dan pembayaran lebih rapi.",
    description:
      "Buat Tagihan, pantau Piutang, dan catat pembayaran dengan alur yang sederhana dan jelas.",
    icon: FileText,
  },
  {
    title:
      "Lihat kondisi usaha lebih cepat.",
    description:
      "Pemasukan, Pengeluaran, Kas & Bank, serta ringkasan bisnis siap membantu keputusan harian.",
    icon: BarChart3,
  },
];

export default function WelcomePage() {
  const router = useRouter();

  const [index, setIndex] =
    useState(0);

  const slide = slides[index];

  const finish = () => {
    markOnboardingCompleted();
    router.replace("/login");
  };

  const next = () => {
    if (
      index ===
      slides.length - 1
    ) {
      finish();
      return;
    }

    setIndex(
      (current) =>
        current + 1,
    );
  };

  const Icon = slide.icon;

  return (
    <main className={styles.page}>
      <div className={styles.top}>
        <button
          type="button"
          className={styles.skip}
          onClick={finish}
        >
          Lewati
        </button>
      </div>

      <section
        className={styles.content}
      >
        <div
          className={
            styles.illustration
          }
        >
          <Icon size={62} />
        </div>

        <h1 className={styles.title}>
          {slide.title}
        </h1>

        <p
          className={
            styles.description
          }
        >
          {slide.description}
        </p>
      </section>

      <footer
        className={styles.footer}
      >
        <div className={styles.dots}>
          {slides.map(
            (_, dotIndex) => (
              <button
                key={dotIndex}
                type="button"
                aria-label={
                  `Buka halaman ${
                    dotIndex + 1
                  }`
                }
                className={cx(
                  styles.dot,
                  dotIndex ===
                    index &&
                    styles.dotActive,
                )}
                onClick={() =>
                  setIndex(
                    dotIndex,
                  )
                }
              />
            ),
          )}
        </div>

        <Button
          fullWidth
          onClick={next}
        >
          {index ===
          slides.length - 1
            ? "Mulai"
            : "Lanjut"}
        </Button>
      </footer>
    </main>
  );
}
