import type { NextConfig } from "next";

// Laravel（バックエンド）の URL。.env.local の BACKEND_URL で変更できる
const backendUrl = process.env.BACKEND_URL ?? "http://127.0.0.1:8000";

const nextConfig: NextConfig = {
  // ブラウザからは同じサイトにあるように見せたまま、/api と /sanctum を Laravel へ転送する。
  // 別サイトへの通信にしないことで、CORS の設定が不要になり、院長ログインの Cookie も扱いやすくなる
  async rewrites() {
    return [
      { source: "/api/:path*", destination: `${backendUrl}/api/:path*` },
      { source: "/sanctum/:path*", destination: `${backendUrl}/sanctum/:path*` },
    ];
  },

  async headers() {
    return [
      {
        // 予約管理ページの URL には鍵が入っているので、
        // ・リンク先のサイトに URL を伝えない（no-referrer）
        // ・検索エンジンに載せない（noindex）
        source: "/reservation/:path*",
        headers: [
          { key: "Referrer-Policy", value: "no-referrer" },
          { key: "X-Robots-Tag", value: "noindex, nofollow" },
        ],
      },
    ];
  },
};

export default nextConfig;
