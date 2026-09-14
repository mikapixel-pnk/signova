import {
  CircleDollarSign,
  FilePlus2,
  HandCoins,
  PackagePlus,
  UserPlus,
} from "lucide-react";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

const actions = [
  {
    label: "Buat Tagihan",
    description:
      "Buat tagihan baru untuk pelanggan.",
    icon: FilePlus2,
  },
  {
    label: "Tambah Pelanggan",
    description:
      "Simpan pelanggan baru.",
    icon: UserPlus,
  },
  {
    label: "Tambah Barang & Jasa",
    description:
      "Tambah item katalog usaha.",
    icon: PackagePlus,
  },
  {
    label: "Catat Pemasukan",
    description:
      "Catat pemasukan usaha.",
    icon: HandCoins,
  },
  {
    label: "Catat Pengeluaran",
    description:
      "Catat biaya atau pengeluaran usaha.",
    icon: CircleDollarSign,
  },
];

export default function ActionsPage() {
  return (
    <TenantShell>
      <section
        style={{
          display: "grid",
          gap: 20,
        }}
      >
        <header>
          <p
            style={{
              margin: "0 0 6px",
              color:
                "var(--color-primary)",
              fontSize: 12,
              fontWeight: 700,
            }}
          >
            Aksi Cepat
          </p>

          <h1
            style={{
              margin: 0,
              fontSize:
                "clamp(26px, 6vw, 36px)",
            }}
          >
            Mau melakukan apa?
          </h1>

          <p
            style={{
              margin: "7px 0 0",
              color:
                "var(--color-muted-foreground)",
            }}
          >
            Pilih pekerjaan yang ingin Anda mulai.
          </p>
        </header>

        <div
          style={{
            display: "grid",
            gap: 10,
          }}
        >
          {actions.map((item) => {
            const Icon = item.icon;

            return (
              <button
                key={item.label}
                type="button"
                style={{
                  display: "grid",
                  gridTemplateColumns:
                    "48px 1fr",
                  alignItems: "center",
                  gap: 13,
                  width: "100%",
                  border:
                    "1px solid var(--color-border)",
                  borderRadius: 17,
                  background:
                    "var(--color-card)",
                  padding: 13,
                  color:
                    "var(--color-foreground)",
                  font: "inherit",
                  textAlign: "left",
                }}
              >
                <span
                  style={{
                    display: "grid",
                    width: 48,
                    height: 48,
                    placeItems: "center",
                    borderRadius: 14,
                    background:
                      "color-mix(in srgb, var(--color-primary) 10%, transparent)",
                    color:
                      "var(--color-primary)",
                  }}
                >
                  <Icon size={21} />
                </span>

                <span>
                  <strong
                    style={{
                      display: "block",
                      fontSize: 14,
                    }}
                  >
                    {item.label}
                  </strong>

                  <small
                    style={{
                      display: "block",
                      marginTop: 4,
                      color:
                        "var(--color-muted-foreground)",
                      fontSize: 11,
                    }}
                  >
                    {item.description}
                  </small>
                </span>
              </button>
            );
          })}
        </div>
      </section>
    </TenantShell>
  );
}
