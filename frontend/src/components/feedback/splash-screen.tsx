"use client";

import Image from "next/image";

import styles from "./splash-screen.module.css";

export function SplashScreen() {
  return (
    <main className={styles.screen}>
      <div className={styles.content}>
        <div className={styles.mark}>
          <Image
            src="/brand/signova-mark.png"
            alt=""
            width={256}
            height={256}
            priority
            className={styles.markImage}
          />
        </div>

        <div>
          <h1 className={styles.name}>
            SIGNOVA
          </h1>

          <p className={styles.tagline}>
            Bisnis lebih sederhana.
          </p>
        </div>

        <div
          className={styles.loader}
          aria-hidden="true"
        />
      </div>
    </main>
  );
}
