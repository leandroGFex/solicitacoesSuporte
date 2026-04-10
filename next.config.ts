import type { NextConfig } from "next";
import path from "path";

const nextConfig: NextConfig = {
  turbopack: {
    // Definimos explicitamente a raiz do projeto para o Turbopack (Next.js 16)
    root: path.join(__dirname),
  },
};

export default nextConfig;
