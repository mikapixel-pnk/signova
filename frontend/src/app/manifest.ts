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
        src: "/brand/signova-mark.png",
        sizes: "256x256",
        type: "image/png",
      },
    ],
  };
}
