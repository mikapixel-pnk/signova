"use client";

import {
  KeyRound,
  LockKeyhole,
  Mail,
  MessageCircle,
} from "lucide-react";
import Link from "next/link";
import {
  useRouter,
} from "next/navigation";
import {
  FormEvent,
  useState,
} from "react";

import {
  AuthShell,
} from "@/components/layout/auth-shell";
import {
  Brand,
} from "@/components/ui/brand";
import {
  Button,
} from "@/components/ui/button";
import {
  Card,
} from "@/components/ui/card";
import {
  Input,
} from "@/components/ui/input";

import {
  ApiClientError,
} from "@/lib/api/types";
import {
  login,
} from "@/lib/auth/login";
import {
  setPendingAccessSelection,
} from "@/lib/auth/access-selection";
import {
  setSelectedContext,
} from "@/lib/auth/active-context";
import {
  setAccessToken,
} from "@/lib/auth/session";

import styles from "./login.module.css";

function loginErrorMessage(
  error: unknown,
): string {
  if (
    error instanceof ApiClientError
  ) {
    if (error.status === 429) {
      return "Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.";
    }

    return error.message;
  }

  return "Tidak dapat masuk. Silakan coba lagi.";
}

export default function LoginPage() {
  const router = useRouter();

  const [email, setEmail] =
    useState("");

  const [password, setPassword] =
    useState("");

  const [loading, setLoading] =
    useState(false);

  const [error, setError] =
    useState<string | null>(null);

  async function handleSubmit(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    setError(null);
    setLoading(true);

    try {
      const response =
        await login({
          email,
          password,
          device_name:
            "signova-web",
        });

      setAccessToken(
        response.data.token,
      );

      if (
        response.data
          .requires_context_selection
      ) {
        setPendingAccessSelection(
          response.data.access,
        );

        router.replace(
          "/select-context",
        );

        return;
      }

      if (
        response.data
          .default_context
          ?.type === "PLATFORM"
      ) {
        setSelectedContext({
          type: "PLATFORM",
          tenantId: null,
        });

        router.replace(
          "/admin",
        );

        return;
      }

      if (
        response.data
          .default_context
          ?.type === "TENANT"
      ) {
        setSelectedContext({
          type: "TENANT",
          tenantId:
            response.data
              .default_context
              .tenant_id,
        });

        router.replace(
          "/app",
        );

        return;
      }

      setError(
        "Akun belum memiliki konteks akses yang dapat digunakan.",
      );
    } catch (caught) {
      setError(
        loginErrorMessage(
          caught,
        ),
      );
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthShell>
      <Card className={styles.card}>
        <div className={styles.cardBrand}>
          <Brand />
        </div>
        <header
          className={styles.header}
        >
          <h1
            className={styles.title}
          >
            Masuk ke SIGNOVA
          </h1>

          <p
            className={
              styles.description
            }
          >
            Masuk untuk melanjutkan
            pengelolaan bisnis Anda.
          </p>
        </header>

        {error ? (
          <div
            className={styles.error}
            role="alert"
          >
            {error}
          </div>
        ) : null}

        <form
          className={styles.form}
          onSubmit={handleSubmit}
        >
          <Input
            label="Email"
            type="email"
            autoComplete="email"
            placeholder="nama@bisnis.com"
            value={email}
            onChange={(event) =>
              setEmail(
                event.target.value,
              )
            }
            leadingIcon={
              <Mail size={18} />
            }
            required
          />

          <Input
            label="Kata Sandi"
            type="password"
            autoComplete="current-password"
            placeholder="Masukkan kata sandi"
            value={password}
            onChange={(event) =>
              setPassword(
                event.target.value,
              )
            }
            leadingIcon={
              <LockKeyhole
                size={18}
              />
            }
            required
          />

          <div
            className={
              styles.passwordRow
            }
          >
            <Link
              className={
                styles.link
              }
              href="/forgot-password"
            >
              Lupa Kata Sandi?
            </Link>
          </div>

          <Button
            type="submit"
            fullWidth
            disabled={loading}
          >
            {loading
              ? "Memproses..."
              : "Masuk"}
          </Button>
        </form>

        <div
          className={
            styles.divider
          }
        >
          atau
        </div>

        <div
          className={
            styles.actions
          }
        >
          <Button
            type="button"
            variant="secondary"
            fullWidth
            leadingIcon={
              <MessageCircle
                size={18}
              />
            }
            onClick={() =>
              router.push(
                "/otp-login",
              )
            }
          >
            Masuk dengan OTP WhatsApp
          </Button>

          <Button
            type="button"
            variant="ghost"
            fullWidth
            leadingIcon={
              <KeyRound
                size={18}
              />
            }
            onClick={() =>
              router.push(
                "/ana-login",
              )
            }
          >
            Masuk dengan ANA
          </Button>
        </div>

        <p className={styles.footer}>
          Belum punya akun?{" "}
          <Link
            className={styles.link}
            href="/register"
          >
            Daftar
          </Link>
        </p>

        <p className={styles.note}>
          OTP WhatsApp dan ANA SSO
          merupakan metode autentikasi
          terpisah.
        </p>
      </Card>
    </AuthShell>
  );
}
