import {
  Banknote,
  BookOpenText,
  Boxes,
  Building2,
  CircleDollarSign,
  CreditCard,
  FileText,
  HandCoins,
  Package,
  ReceiptText,
  Ruler,
  Settings,
  ShieldCheck,
  Store,
  Tags,
  UserRound,
  Users,
  WalletCards,
  Wrench,
} from "lucide-react";

import type {
  ModuleDefinition,
  ModuleKey,
} from "./module-types";

export const modules:
  Record<
    ModuleKey,
    ModuleDefinition
  > = {
  customers: {
    key: "customers",
    group: "master-data",
    label: "Pelanggan",
    href: "/app/pelanggan",
    tone: "violet",
    icon: Users,
    plan: "starter",
    description:
      "Kelola data pelanggan yang digunakan untuk penawaran, tagihan, dan pembayaran.",
    insight: {
      title:
        "Pelanggan adalah aset berharga",
      description:
        "Data pelanggan yang rapi membantu hubungan bisnis tetap terjaga dan mudah ditelusuri.",
    },
    footer: {
      title:
        "Data pelanggan yang rapi mempermudah transaksi",
      description:
        "Simpan data pelanggan sekali agar dapat digunakan kembali pada penawaran, tagihan, dan pembayaran.",
    },
    highlights: [
      "Data pelanggan tersimpan dalam satu tempat",
      "Digunakan kembali saat membuat penawaran dan tagihan",
      "Riwayat bisnis lebih mudah ditelusuri",
    ],
    navigation: {
      desktop: true,
      mobile: true,
      order: 10,
    },
  },

  catalog: {
    key: "catalog",
    group: "master-data",
    label: "Barang & Jasa",
    href: "/app/barang-jasa",
    tone: "cyan",
    icon: Package,
    plan: "starter",
    description:
      "Kelola barang, jasa, harga acuan, kategori, dan satuan usaha.",
    insight: {
      title:
        "Katalog rapi mempercepat transaksi",
      description:
        "Simpan barang dan jasa sekali agar dapat digunakan kembali pada penawaran dan tagihan.",
    },
    highlights: [
      "Barang dan jasa dalam satu katalog",
      "Harga acuan lebih konsisten",
      "Mempercepat pembuatan dokumen penjualan",
    ],
    navigation: {
      desktop: true,
      mobile: true,
      order: 20,
    },
  },

  categories: {
    key: "categories",
    group: "master-data",
    label: "Kategori",
    href:
      "/app/barang-jasa?bagian=kategori",
    tone: "amber",
    icon: Tags,
    plan: "starter",
    description:
      "Kelompokkan barang dan jasa agar lebih mudah ditemukan.",
    insight: {
      title:
        "Kategori membuat katalog lebih teratur",
      description:
        "Pengelompokan yang konsisten membantu pencarian dan pelaporan.",
    },
    navigation: {
      desktop: true,
      mobile: true,
      order: 30,
    },
  },

  units: {
    key: "units",
    group: "master-data",
    label: "Satuan",
    href:
      "/app/barang-jasa?bagian=satuan",
    tone: "teal",
    icon: Ruler,
    plan: "starter",
    description:
      "Kelola satuan yang dipakai pada barang, jasa, dan transaksi.",
    insight: {
      title:
        "Satuan konsisten mengurangi kesalahan",
      description:
        "Gunakan definisi satuan yang sama di seluruh transaksi SIGNOVA.",
    },
    navigation: {
      desktop: true,
      mobile: true,
      order: 40,
    },
  },

  quotations: {
    key: "quotations",
    group: "penjualan",
    label: "Penawaran",
    href: "/app/penawaran",
    tone: "teal",
    icon: BookOpenText,
    plan: "starter",
    description:
      "Buat dan pantau penawaran yang diberikan kepada pelanggan.",
    insight: {
      title:
        "Penawaran yang jelas mempercepat keputusan",
      description:
        "Susun harga dan ruang lingkup pekerjaan dengan informasi yang mudah dipahami pelanggan.",
    },
    highlights: [
      "Gunakan pelanggan dan katalog yang sudah tersimpan",
      "Riwayat revisi tetap dapat ditelusuri",
      "Siap dikembangkan ke approval dan proyek",
    ],
    navigation: {
      desktop: true,
      mobile: true,
      order: 10,
    },
  },

  invoices: {
    key: "invoices",
    group: "penjualan",
    label: "Tagihan",
    href: "/app/tagihan",
    tone: "blue",
    icon: FileText,
    plan: "starter",
    description:
      "Buat, pantau, dan kelola tagihan pelanggan.",
    insight: {
      title:
        "Tagihan teratur menjaga arus kas",
      description:
        "Ketahui tagihan yang terbit, jatuh tempo, dan masih perlu ditindaklanjuti.",
    },
    highlights: [
      "Pantau status tagihan",
      "Jatuh tempo lebih mudah dikendalikan",
      "Terhubung dengan pembayaran dan piutang",
    ],
    navigation: {
      desktop: true,
      mobile: true,
      order: 20,
    },
  },

  payments: {
    key: "payments",
    group: "penjualan",
    label: "Pembayaran",
    href: "/app/pembayaran",
    tone: "rose",
    icon: CreditCard,
    plan: "starter",
    description:
      "Kelola pembayaran pelanggan dan riwayat verifikasinya.",
    insight: {
      title:
        "Setiap pembayaran harus mudah ditelusuri",
      description:
        "Catatan pembayaran yang jelas membantu menjaga akurasi saldo dan piutang.",
    },
    highlights: [
      "Pembayaran terhubung dengan tagihan",
      "Riwayat transaksi tetap tercatat",
      "Mengurangi pengecekan manual",
    ],
    navigation: {
      desktop: true,
      mobile: true,
      order: 30,
    },
  },

  finance: {
    key: "finance",
    group: "keuangan",
    label: "Ringkasan Keuangan",
    shortLabel: "Ringkasan",
    href: "/app/keuangan",
    tone: "violet",
    icon: WalletCards,
    plan: "starter",
    capability:
      "finance.summary.view",
    description:
      "Pantau kas, pemasukan, pengeluaran, dan piutang usaha.",
    insight: {
      title:
        "Ketahui kondisi usaha dalam satu pandangan",
      description:
        "Ringkasan membantu Anda memahami posisi keuangan tanpa membuka banyak halaman.",
    },
    navigation: {
      desktop: true,
      mobile: true,
      order: 10,
    },
  },

  "cash-bank": {
    key: "cash-bank",
    group: "keuangan",
    label: "Kas & Bank",
    href:
      "/app/keuangan/kas-bank",
    tone: "green",
    icon: Building2,
    plan: "starter",
    capability:
      "finance.cash_bank.view",
    description:
      "Kelola rekening, kas tunai, dan saldo usaha.",
    insight: {
      title:
        "Ketahui posisi uang usaha setiap saat",
      description:
        "Pisahkan dan pantau sumber kas agar pergerakan uang lebih mudah dipahami.",
    },
    navigation: {
      desktop: true,
      mobile: true,
      order: 20,
    },
  },

  income: {
    key: "income",
    group: "keuangan",
    label: "Pemasukan",
    href:
      "/app/keuangan/pemasukan",
    tone: "teal",
    icon: HandCoins,
    plan: "starter",
    capability:
      "finance.income.view",
    description:
      "Catat dan pantau pemasukan usaha.",
    insight: {
      title:
        "Ketahui dari mana uang usaha masuk",
      description:
        "Pencatatan sumber pemasukan membantu Anda membaca aktivitas usaha dengan lebih jelas.",
    },
    navigation: {
      desktop: true,
      mobile: true,
      order: 30,
    },
  },

  expense: {
    key: "expense",
    group: "keuangan",
    label: "Pengeluaran",
    href:
      "/app/keuangan/pengeluaran",
    tone: "rose",
    icon: CircleDollarSign,
    plan: "starter",
    capability:
      "finance.expense.view",
    description:
      "Catat biaya dan pengeluaran usaha.",
    insight: {
      title:
        "Kendalikan biaya sebelum menjadi masalah",
      description:
        "Pengeluaran yang tercatat rapi membantu owner memahami penggunaan uang usaha.",
    },
    navigation: {
      desktop: true,
      mobile: true,
      order: 40,
    },
  },

  receivables: {
    key: "receivables",
    group: "keuangan",
    label: "Piutang",
    href:
      "/app/keuangan/piutang",
    tone: "blue",
    icon: ReceiptText,
    plan: "starter",
    capability:
      "finance.receivable.view",
    description:
      "Pantau tagihan pelanggan yang belum lunas.",
    insight: {
      title:
        "Ketahui tagihan yang masih harus ditagih",
      description:
        "Fokus pada piutang yang memerlukan perhatian tanpa memeriksa tagihan satu per satu.",
    },
    navigation: {
      desktop: true,
      mobile: true,
      order: 50,
    },
  },

  "business-settings": {
    key: "business-settings",
    group: "pengaturan",
    label: "Pengaturan Bisnis",
    href:
      "/app/pengaturan?bagian=bisnis",
    tone: "blue",
    icon: Store,
    plan: "starter",
    description:
      "Atur identitas dan informasi utama usaha.",
    insight: {
      title:
        "Identitas usaha digunakan di seluruh SIGNOVA",
      description:
        "Nama, logo, alamat, kontak, dan informasi bisnis menjadi referensi berbagai dokumen.",
    },
    navigation: {
      desktop: true,
      mobile: true,
      order: 10,
    },
  },

  "profile-settings": {
    key: "profile-settings",
    group: "pengaturan",
    label: "Profil Saya",
    href:
      "/app/pengaturan?bagian=profil",
    tone: "violet",
    icon: UserRound,
    plan: "starter",
    description:
      "Kelola informasi akun dan preferensi pribadi Anda.",
    insight: {
      title:
        "Akun pribadi terpisah dari data bisnis",
      description:
        "Informasi pengguna, keamanan akun, dan preferensi dikelola tanpa mengubah profil usaha.",
    },
    navigation: {
      desktop: true,
      mobile: true,
      order: 20,
    },
  },

  "finance-settings": {
    key: "finance-settings",
    group: "pengaturan",
    label: "Pengaturan Keuangan",
    href:
      "/app/pengaturan?bagian=keuangan",
    tone: "green",
    icon: Banknote,
    plan: "starter",
    description:
      "Atur rekening, penomoran dokumen, termin, pajak, dan template keuangan.",
    insight: {
      title:
        "Atur sekali, gunakan berulang",
      description:
        "Rekening, prefix dokumen, template, dan termin pembayaran sebaiknya tidak dimasukkan ulang setiap transaksi.",
    },
    navigation: {
      desktop: true,
      mobile: true,
      order: 30,
    },
  },

  "team-access": {
    key: "team-access",
    group: "pengaturan",
    label: "Tim & Hak Akses",
    href:
      "/app/pengaturan?bagian=tim",
    tone: "cyan",
    icon: ShieldCheck,
    plan: "starter",
    description:
      "Kelola anggota tim dan hak akses sesuai pekerjaan.",
    insight: {
      title:
        "Akses mengikuti tanggung jawab",
      description:
        "Setiap pengguna hanya mendapatkan akses yang diperlukan untuk pekerjaannya.",
    },
    navigation: {
      desktop: true,
      mobile: false,
      order: 40,
    },
  },

  subscription: {
    key: "subscription",
    group: "pengaturan",
    label: "Paket & Langganan",
    href:
      "/app/pengaturan?bagian=paket",
    tone: "violet",
    icon: Package,
    plan: "starter",
    description:
      "Lihat paket aktif, pemakaian, kuota, dan pilihan upgrade.",
    insight: {
      title:
        "SIGNOVA berkembang bersama usaha Anda",
      description:
        "Tambah kemampuan saat benar-benar dibutuhkan tanpa mengubah alur dasar bisnis.",
    },
    navigation: {
      desktop: true,
      mobile: false,
      order: 50,
    },
  },

  integrations: {
    key: "integrations",
    group: "pengaturan",
    label: "Integrasi",
    href:
      "/app/pengaturan?bagian=integrasi",
    tone: "teal",
    icon: Settings,
    plan: "starter",
    description:
      "Kelola koneksi SIGNOVA dengan layanan eksternal.",
    insight: {
      title:
        "Integrasi harus tetap sederhana",
      description:
        "Pengguna melihat status koneksi dan sinkronisasi tanpa harus memahami detail teknis provider.",
    },
    navigation: {
      desktop: true,
      mobile: false,
      order: 60,
    },
  },

  projects: {
    key: "projects",
    group: "fitur-lanjutan",
    label: "Proyek & Survei",
    href:
      "/app/pengaturan?bagian=paket&fitur=proyek",
    tone: "violet",
    icon: Building2,
    plan: "business",
    description:
      "Kelola pekerjaan dari survei hingga penyelesaian proyek.",
    insight: {
      title:
        "Kelola pekerjaan dalam satu timeline",
      description:
        "Business menyatukan survei, desain, pekerjaan, dan perkembangan proyek.",
    },
    navigation: {
      desktop: true,
      mobile: true,
      order: 10,
    },
  },

  production: {
    key: "production",
    group: "fitur-lanjutan",
    label: "Produksi & QC",
    href:
      "/app/pengaturan?bagian=paket&fitur=produksi",
    tone: "blue",
    icon: Wrench,
    plan: "business",
    description:
      "Kelola proses produksi, QC, dan pemasangan.",
    insight: {
      title:
        "Produksi lebih mudah dikendalikan",
      description:
        "Pantau pekerjaan workshop hingga pemasangan dengan jejak proses yang jelas.",
    },
    navigation: {
      desktop: true,
      mobile: true,
      order: 20,
    },
  },

  "sales-channels": {
    key: "sales-channels",
    group: "fitur-lanjutan",
    label: "Saluran Penjualan",
    href:
      "/app/pengaturan?bagian=paket&fitur=saluran-penjualan",
    tone: "cyan",
    icon: Boxes,
    plan: "business",
    description:
      "Kelola order dari WhatsApp, marketplace, dan website.",
    insight: {
      title:
        "Semua order menuju core yang sama",
      description:
        "Sumber order tetap terlihat tetapi proses bisnis tetap konsisten di SIGNOVA.",
    },
    navigation: {
      desktop: true,
      mobile: true,
      order: 30,
    },
  },

  operations: {
    key: "operations",
    group: "fitur-lanjutan",
    label: "Pembelian & Gudang",
    href:
      "/app/pengaturan?bagian=paket&fitur=operasional",
    tone: "green",
    icon: Package,
    plan: "pro",
    description:
      "Kelola pemasok, pembelian, stok, dan gudang.",
    insight: {
      title:
        "Kendalikan resource saat usaha bertumbuh",
      description:
        "Paket Pro menambahkan purchasing dan inventory tanpa memisahkan data dari proses utama.",
    },
    navigation: {
      desktop: true,
      mobile: true,
      order: 40,
    },
  },
};
