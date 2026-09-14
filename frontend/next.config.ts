import type {
  NextConfig,
} from "next";

const nextConfig:
  NextConfig = {
  async redirects() {
    return [
      {
        source:
          "/app/customers/:path*",
        destination:
          "/app/pelanggan/:path*",
        permanent: false,
      },
      {
        source:
          "/app/catalog/:path*",
        destination:
          "/app/barang-jasa/:path*",
        permanent: false,
      },
      {
        source:
          "/app/quotations/:path*",
        destination:
          "/app/penawaran/:path*",
        permanent: false,
      },
      {
        source:
          "/app/invoices/:path*",
        destination:
          "/app/tagihan/:path*",
        permanent: false,
      },
      {
        source:
          "/app/payments/:path*",
        destination:
          "/app/pembayaran/:path*",
        permanent: false,
      },
      {
        source:
          "/app/finance/:path*",
        destination:
          "/app/keuangan/:path*",
        permanent: false,
      },
      {
        source:
          "/app/settings/:path*",
        destination:
          "/app/pengaturan/:path*",
        permanent: false,
      },
      {
        source:
          "/app/actions/:path*",
        destination:
          "/app/aksi/:path*",
        permanent: false,
      },
    ];
  },
};

export default nextConfig;
