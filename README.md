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
| DB | 開発：SQLite、本番：MySQL 8 |
| タイムゾーン | Asia/Tokyo |

ブラウザは Next.js の画面だけにアクセスします。`/api` と `/sanctum` へのリクエストは、Next.js が Laravel へ転送します（`frontend/next.config.ts`）。同じサイトへの通信になるため、CORS の設定は不要で、院長ログインの Cookie もそのまま使えます。

```
ブラウザ ──→ Next.js（画面）──/api・/sanctum を転送──→ Laravel（API・DB・メール）
```

## 必要なもの

- PHP 8.3 以上と Composer
  - Windows の場合は [php.new](https://php.new) でインストールできます（Laravel 公式ドキュメントの手順）
- Node.js 24（LTS）と npm
- Git

## セットアップ（開発環境・初回のみ）

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
| `CLINIC_PROXY_SECRET` | Next.js との合言葉（本番用。開発では空のままでかまいません。[本番環境](#本番環境)を参照） |

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
| `CLINIC_PROXY_SECRET` | Laravel との合言葉（本番用。開発では空のままでかまいません） |

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
php artisan test        # Laravel のテスト
vendor/bin/pint --test  # コードの書き方のチェック
composer audit          # 使っている部品の既知の脆弱性のチェック
```

```bash
cd frontend
npm run lint            # ESLint によるコードチェック
npx tsc --noEmit        # TypeScript の型チェック
npm audit               # 使っている部品の既知の脆弱性のチェック
```

## 本番環境

想定している構成は次のとおりです。

| 役割 | 置き場所 | 備考 |
|---|---|---|
| 画面（`frontend/`） | Vercel | 接骨院の業務で使うため、**商用利用ができる有料プラン（Pro）**が必要です（無料の Hobby プランは商用利用が禁止されています） |
| API・DB（`backend/`） | 国内のレンタルサーバー | PHP 8.3 以上・MySQL 8・SSH・cron が使えること。常駐プロセスや Redis は不要です |

以下、`https://clinic.example.jp` を画面（Vercel）、`https://api.clinic.example.jp` を API（レンタルサーバー）の URL として説明します。実際の URL に置き換えてください。どちらも HTTPS にします。

### 1. 合言葉を作る

Next.js と Laravel の両方に、同じ合言葉（長いランダムな文字列）を設定します。Next.js は、Vercel の上で動くとき、患者の IP アドレスをこの合言葉つきで Laravel に渡します（`frontend/src/proxy.ts`）。Laravel は合言葉が正しいときだけ、その IP アドレスをアクセス回数の制限に使います（`backend/app/Http/Middleware/TrustFrontendProxy.php`）。

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

合言葉は Git やチャットに書かず、Vercel とレンタルサーバーの `.env` にだけ保存してください。

### 2. API（レンタルサーバー）

1. MySQL のデータベースとユーザーを作ります（文字コードは `utf8mb4`）
2. `backend/` をサーバーに置き、**公開フォルダ（ドキュメントルート）を `backend/public` にします**。`backend/.env` や `backend/storage` が Web から見えないようにするためです
3. サーバー上で次を実行します

```bash
cd backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# ここで .env を下の表のとおりに書き換える
php artisan migrate --force
php artisan db:seed --class=WeeklyScheduleSeeder --force   # 曜日ごとの基本の営業時間（アプリの動作に必要）
php artisan admin:create                                   # 院長アカウント（1件だけ作れます）
php artisan optimize                                       # 設定・ルートなどをまとめて高速化
```

4. `backend/storage` と `backend/bootstrap/cache` に、Web サーバーが書き込めるようにします

`backend/.env` の本番の値：

| 名前 | 本番の値 | 理由・補足 |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | エラーの詳細（設定値やファイルの場所）を画面に出さないため。**必ず false** |
| `APP_URL` | `https://api.clinic.example.jp` | |
| `LOG_LEVEL` | `info` | 開発用の細かいログ（debug）は残さない。自動削除の件数（info）とメール送信の失敗（warning）は残す。どちらも個人情報は書かない |
| `DB_CONNECTION` | `mysql` | `DB_HOST`・`DB_PORT`・`DB_DATABASE`・`DB_USERNAME`・`DB_PASSWORD` も設定する |
| `SESSION_DRIVER` | `database` | |
| `SESSION_SECURE_COOKIE` | `true` | ログインの Cookie を HTTPS の通信でだけ送るため |
| `SANCTUM_STATEFUL_DOMAINS` | `clinic.example.jp` | 画面のドメイン（`https://` は付けない）。ここからのリクエストにだけ、ログインのセッションと CSRF 対策を付ける |
| `FRONTEND_URL` | `https://clinic.example.jp` | メールに載せる予約管理ページのリンクに使う |
| `CLINIC_NAME`・`CLINIC_PHONE` | 接骨院の名前と電話番号 | メールに記載する |
| `CLINIC_PROXY_SECRET` | 1 で作った合言葉 | |
| `MAIL_MAILER` | `smtp` | `MAIL_HOST`・`MAIL_PORT`・`MAIL_USERNAME`・`MAIL_PASSWORD`・`MAIL_SCHEME`（`smtps` など）を、レンタルサーバーやメール送信サービスの案内どおりに設定する |
| `MAIL_FROM_ADDRESS` | 接骨院のメールアドレス | 送信元。送信ドメイン認証（SPF・DKIM）を設定したドメインのアドレスにすると、迷惑メールに入りにくくなる |

`.env` を書き換えたあとは、`php artisan optimize` をもう一度実行してください（設定をまとめ直すため）。

MySQL では、同じ日の同じ人の予約を防ぐ列（`name_key`・`phone_key`）を、1文字ずつ厳密に比べる照合順序にしています。初回の `migrate` のあと、次の SQL で `Collation` が `utf8mb4_bin` になっていることを確認してください。

```sql
SHOW FULL COLUMNS FROM reservations WHERE Field IN ('name_key', 'phone_key');
```

### 3. 自動削除のための cron

終了から1年を過ぎた予約は、毎日 03:00 に自動で削除されます（`routes/console.php`）。この予定を動かすため、サーバーの cron に、次の1行を**毎分**実行するよう登録します。

```
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

- `/path/to/backend` は、サーバー上の `backend` フォルダの場所に置き換えます
- レンタルサーバーによっては、`php` を `/usr/bin/php8.3` のように版つきの場所で書く必要があります（サーバーの案内を確認してください）
- 登録した予定は `php artisan schedule:list` で確認できます。削除した日は、件数だけが `storage/logs/laravel.log` に記録されます

### 4. 画面（Vercel）

1. Vercel でこのリポジトリを読み込み、**Root Directory を `frontend`** にします
2. Environment Variables に次を設定します（Production）

| 名前 | 値 |
|---|---|
| `BACKEND_URL` | `https://api.clinic.example.jp` |
| `NEXT_PUBLIC_CLINIC_NAME` | 接骨院の名前 |
| `NEXT_PUBLIC_CLINIC_PHONE` | 電話番号 |
| `CLINIC_PROXY_SECRET` | 1 で作った合言葉（`NEXT_PUBLIC_` を付けない。付けるとブラウザに渡ってしまう） |

3. デプロイしたあと、独自ドメイン（`clinic.example.jp`）を設定します

`NEXT_PUBLIC_` で始まる値は、ビルドのときに画面のプログラムへ埋め込まれます。変えたときは、Vercel で再デプロイしてください。

### 5. 公開前の確認

- [ ] `https://api.clinic.example.jp/up` が表示される（Laravel が動いている）
- [ ] `https://clinic.example.jp/reserve` で日付の一覧が表示される（Next.js から Laravel へ転送できている）
- [ ] テスト用の予約を入れ、確認メールが届く。予約管理ページから変更・キャンセルできる
- [ ] `https://clinic.example.jp/admin` でログインでき、予約一覧・カレンダーが表示される
- [ ] `https://api.clinic.example.jp/.env` などが表示されない（公開フォルダが `backend/public` になっている）
- [ ] テスト用の予約を削除（キャンセル）した

## 運用

### 院長のパスワードを忘れたとき

管理画面には、パスワードを再設定する機能はありません（メールを乗っ取られたときに、管理画面まで乗っ取られる入口を作らないため）。サーバー上で次を実行し、新しいパスワード（12文字以上）を設定してください。ログイン中の端末は、すべてログアウトされます。

```bash
cd backend
php artisan admin:password
```

### 更新するとき

```bash
cd backend
git pull                                   # またはファイルを置き換える
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
```

画面（Vercel）は、リポジトリに push すると自動でデプロイされます。

### データの保存期間

- 予約（キャンセル済みも含む）とメールの送信記録は、予約の終了時刻から1年を過ぎると自動で削除されます（個人情報を必要以上に持ち続けないため）
- DB のバックアップを取る場合は、バックアップにも同じ保存期間の考え方を当てはめてください

## 注意

- `.env` にはパスワードや秘密鍵が入るため、Git に含めません（`.gitignore` で除外しています）
- 開発中のメールは送信されず、`backend/storage/logs/mail.log` に書き出されます（`MAIL_MAILER=log`・`MAIL_LOG_CHANNEL=mail`）
- ポート 3000 がほかのアプリで使われていると、Next.js は 3001 などで起動します。その場合は `backend/.env` の `FRONTEND_URL` も合わせてください
- テストの実行中は、ログを残しません（`backend/phpunit.xml` の `LOG_CHANNEL=null`）
