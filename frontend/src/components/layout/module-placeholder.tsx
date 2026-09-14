import { ArrowLeft } from "lucide-react";

type ModulePlaceholderProps = {
  title: string;
  description: string;
};

export function ModulePlaceholder({
  title,
  description,
}: ModulePlaceholderProps) {
  return (
    <section
      style={{
        display: "grid",
        gap: 18,
      }}
    >
      <a
        href="/app"
        style={{
          display: "inline-flex",
          alignItems: "center",
          gap: 7,
          color: "var(--color-primary)",
          textDecoration: "none",
          fontSize: 14,
          fontWeight: 650,
        }}
      >
        <ArrowLeft size={17} />
        Beranda
      </a>

      <div>
        <p
          style={{
            margin: "0 0 7px",
            color: "var(--color-primary)",
            fontSize: 13,
            fontWeight: 700,
          }}
        >
          SIGNOVA
        </p>

        <h1
          style={{
            margin: 0,
            fontSize: "clamp(26px, 4vw, 36px)",
          }}
        >
          {title}
        </h1>

        <p
          style={{
            maxWidth: 580,
            color: "var(--color-muted-foreground)",
            lineHeight: 1.6,
          }}
        >
          {description}
        </p>
      </div>
    </section>
  );
}
