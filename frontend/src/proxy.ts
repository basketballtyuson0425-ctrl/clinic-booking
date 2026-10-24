import { NextResponse, type NextRequest } from "next/server";

// Laravel（backend/app/Http/Middleware/TrustFrontendProxy.php）と同じ見出しの名前
const SECRET_HEADER = "x-clinic-proxy-secret";
const CLIENT_IP_HEADER = "x-clinic-client-ip";

/**
 * /api と /sanctum を Laravel へ転送する前に、患者の IP アドレスと合言葉を見出しに付ける。
 * 転送すると Laravel からは全員が Next.js のサーバーから来たように見え、
 * IP アドレスごとの回数制限が全員まとめて数えられてしまうため。
 */
export function proxy(request: NextRequest) {
  const headers = new Headers(request.headers);

  // ブラウザから送られてきた同じ名前の見出しは、偽装なので必ず消す
  headers.delete(SECRET_HEADER);
  headers.delete(CLIENT_IP_HEADER);

  const secret = process.env.CLINIC_PROXY_SECRET;
  // Vercel は x-forwarded-for を患者の本当の IP アドレスで上書きするので信用できる。
  // ほかの場所ではブラウザが送った値が残ることがあるため使わない（Laravel はつながってきた相手の IP アドレスを使う）
  const clientIp = process.env.VERCEL === "1" ? request.headers.get("x-forwarded-for")?.split(",")[0]?.trim() : undefined;

  if (secret && clientIp) {
    headers.set(SECRET_HEADER, secret);
    headers.set(CLIENT_IP_HEADER, clientIp);
  }

  // 書き換えた見出しで、next.config.ts の rewrites（Laravel への転送）へ進む
  return NextResponse.next({ request: { headers } });
}

export const config = {
  matcher: ["/api/:path*", "/sanctum/:path*"],
};
