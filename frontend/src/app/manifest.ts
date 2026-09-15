import type {
  MetadataRoute,
} from "next";

export default function manifest():
  MetadataRoute.Manifest {
  return {
    name: "SIGNOVA",
    short_name: "SIGNOVA",
    description:
      "Platform operasional bisnis yang sederhana, terhubung, dan mobile-first.",
    start_url: "/app",
    display: "standalone",
    background_color: "#f7f9fc",
    theme_color: "#2868f0",
    lang: "id",
    icons: [
      {
        src: "/brand/signova-192.png",
        sizes: "192x192",
        type: "image/png",
        purpose: "any",
      },
      {
        src: "/brand/signova-512.png",
        sizes: "512x512",
        type: "image/png",
        purpose: "any",
      },
    ],
  };
}
