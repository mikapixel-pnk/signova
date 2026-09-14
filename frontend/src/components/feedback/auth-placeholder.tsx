import {
  AuthShell,
} from "@/components/layout/auth-shell";
import {
  Card,
} from "@/components/ui/card";

type AuthPlaceholderProps = {
  title: string;
  description: string;
};

export function AuthPlaceholder({
  title,
  description,
}: AuthPlaceholderProps) {
  return (
    <AuthShell>
      <Card
        style={{
          padding: "28px",
        }}
      >
        <h1
          style={{
            marginTop: 0,
          }}
        >
          {title}
        </h1>

        <p
          style={{
            marginBottom: 0,
            color:
              "var(--foreground-muted)",
            lineHeight: 1.6,
          }}
        >
          {description}
        </p>
      </Card>
    </AuthShell>
  );
}
