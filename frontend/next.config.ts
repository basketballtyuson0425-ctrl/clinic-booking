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
        // すべてのページに付ける、基本のセキュリティの見出し。
        // ・ほかのサイトの中に埋め込ませない（見えない枠を重ねて押させる「クリックジャッキング」の対策）
        // ・ファイルの種類を中身から推測させない（画像のふりをしたスクリプトの実行を防ぐ）
        // ・ほかのサイトへ移るとき、URL のうちサイト名だけを伝える
        // ・カメラ・マイク・位置情報は使わないので、使えないようにしておく
        source: "/:path*",
        headers: [
          { key: "X-Frame-Options", value: "DENY" },
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=()" },
        ],
      },
      {
        // 同じ見出しが重なったときは、あとに書いたほうが使われる（予約管理ページは no-referrer になる）
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
