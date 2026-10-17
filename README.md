# 接骨院 予約管理システム

接骨院の Web 予約と、院長用の予約・営業日管理を行うシステムです。

- 要件定義：[docs/requirements.md](docs/requirements.md)
- 実装計画（確定仕様・DB 設計・API・画面）：[docs/implementation-plan.md](docs/implementation-plan.md)

## 構成

```
clinic-booking/
├── backend/    Laravel 13（API のみ）
├── frontend/   Next.js 16（App Router / TypeScript / Tailwind CSS）
├── docs/       要件定義・実装計画
└── README.md
```

| 項目 | 内容 |
|---|---|
| バックエンド | PHP 8.5 / Laravel 13 |
| フロントエンド | Node.js 24 / Next.js 16 / React 19 / TypeScript / Tailwind CSS 4 |
| DB | 開発：SQLite、本番：MySQL を想定 |
| タイムゾーン | Asia/Tokyo |

## 必要なもの

- PHP 8.3 以上と Composer
  - Windows の場合は [php.new](https://php.new) でインストールできます（Laravel 公式ドキュメントの手順）
- Node.js 24（LTS）と npm
- Git

## セットアップ（初回のみ）

### バックエンド（Laravel）

```bash
cd backend
composer install
cp .env.example .env          # Windows PowerShell の場合: Copy-Item .env.example .env
php artisan key:generate
php artisan migrate           # database/database.sqlite がなければ作成するか確認されます
php artisan db:seed           # 曜日ごとの基本の営業時間を登録（何度実行しても重複しません）
php artisan admin:create      # 院長のログイン用アカウントを作成（名前・メール・パスワードを聞かれます）
```

`backend/.env` で次の値を設定します（`.env.example` に初期値があります）。

| 名前 | 内容 |
|---|---|
| `CLINIC_NAME`・`CLINIC_PHONE` | メールに記載する接骨院の名前と電話番号 |
| `FRONTEND_URL` | 患者側の画面の URL（メールに載せる予約管理ページのリンクに使います） |
| `MAIL_MAILER` など `MAIL_*` | メールの送信方法。開発中は `log`（実際には送信しない） |
| `SANCTUM_STATEFUL_DOMAINS` | 管理画面の場所（例：`localhost:3000`）。ここからのリクエストにだけ、ログインのセッションと CSRF 対策を付けます |

本番環境でも、初回は次のコマンドで基本の営業時間を登録し、院長アカウントを作成してください。

```bash
php artisan db:seed --class=WeeklyScheduleSeeder --force
php artisan admin:create
```

### フロントエンド（Next.js）

```bash
cd frontend
npm install
cp .env.example .env.local    # Windows PowerShell の場合: Copy-Item .env.example .env.local
```

`.env.local` で次の値を設定します。

| 名前 | 内容 |
|---|---|
| `BACKEND_URL` | Laravel の URL（`/api` と `/sanctum` をここへ転送します） |
| `NEXT_PUBLIC_CLINIC_NAME` | 画面に表示する接骨院の名前 |
| `NEXT_PUBLIC_CLINIC_PHONE` | 画面に表示する電話番号（変更・キャンセルの問い合わせ先） |

## 開発サーバーの起動

ターミナルを2つ開き、それぞれで実行します。

```bash
# ターミナル1：Laravel（http://localhost:8000）
cd backend
php artisan serve
```

```bash
# ターミナル2：Next.js（http://localhost:3000）
cd frontend
npm run dev
```

- ブラウザでは http://localhost:3000 を開きます（患者側の予約画面 `/reserve` が表示されます）
- 予約管理ページは `/reservation/{予約完了時に発行される鍵}` です（予約完了画面と確認メールにリンクがあります）
- 管理画面は http://localhost:3000/admin です。`php artisan admin:create` で作ったアカウントでログインします
- Laravel が動いているかは http://localhost:8000/up で確認できます（ヘルスチェック用の URL）

## テスト・コードチェック

```bash
cd backend
php artisan test     # Laravel のテスト
```

```bash
cd frontend
npm run lint         # ESLint によるコードチェック
```

## 注意

- `.env` にはパスワードや秘密鍵が入るため、Git に含めません（`.gitignore` で除外しています）
- 開発中のメールは送信されず、`backend/storage/logs/mail.log` に書き出されます（`MAIL_MAILER=log`・`MAIL_LOG_CHANNEL=mail`）
- ポート 3000 がほかのアプリで使われていると、Next.js は 3001 などで起動します。その場合は `backend/.env` の `FRONTEND_URL` も合わせてください
