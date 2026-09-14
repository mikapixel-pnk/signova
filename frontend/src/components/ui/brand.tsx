import Image from "next/image";

import styles from "./brand.module.css";

export function Brand() {
  return (
    <div
      className={styles.brand}
      aria-label="SIGNOVA"
    >
      <span className={styles.mark}>
        <Image
          src="/brand/signova-mark.png"
          alt=""
          fill
          priority
          sizes="44px"
        />
      </span>

      <span className={styles.name}>
        SIGNOVA
      </span>
    </div>
  );
}
