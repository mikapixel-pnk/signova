export default function Home() {
  return (
    <main
      style={{
        minHeight: "100vh",
        display: "grid",
        placeItems: "center",
        padding: "24px",
      }}
    >
      <section
        style={{
          width: "min(100%, 520px)",
          padding: "32px",
          borderRadius: "var(--radius-lg)",
          background: "var(--surface)",
          boxShadow: "var(--shadow-soft)",
          border: "1px solid var(--border)",
        }}
      >
        <div
          style={{
            color: "var(--primary-strong)",
            fontWeight: 800,
            letterSpacing: "-0.02em",
            fontSize: "28px",
          }}
        >
          SIGNOVA
        </div>

        <h1
          style={{
            margin: "20px 0 8px",
            fontSize: "24px",
          }}
        >
          Frontend Foundation
        </h1>

        <p
          style={{
            margin: 0,
            color: "var(--foreground-muted)",
            lineHeight: 1.6,
          }}
        >
          Next.js foundation aktif.
          Splash, autentikasi, tenant app,
          platform admin, dan PWA akan
          dibangun pada fase berikutnya.
        </p>
      </section>
    </main>
  );
}
