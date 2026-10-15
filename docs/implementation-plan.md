# 実装計画（承認済み：2026-10-06）

要件は [requirements.md](requirements.md) を参照してください。

## 0. 開始時点の状態（2026-10-01 調査）
- `clinic-booking/` は空のディレクトリ（既存コードなし）。Git リポジトリ化もまだ
- Node.js v24.11 / npm 11.6 / Git 2.55 はインストール済み
- **PHP・Composer は未インストール**。Docker・MySQL もなし

### 環境構築の結果（2026-10-06）
- 調べ直したところ Node.js も入っていなかったため、PHP と一緒にインストールした
- PHP 8.5.0 / Composer 2.8.12 / Laravel インストーラー 5.32：php.new で導入（`%USERPROFILE%\.config\herd-lite\bin`）
- Node.js v24.19.0 / npm 11.17.0：winget（`OpenJS.NodeJS.LTS`）で導入
- ひな形：Laravel 13.34（`composer create-project`）、Next.js 16.3.8（`create-next-app`、TypeScript・Tailwind CSS・ESLint・App Router・`src/`）
- PHP の `intl` 拡張は php.new の PHP に含まれていない。今の計画では使わないが、必要になったら相談する

## 1. 確定した仕様（要件の補足として決めた事項）
1. **メールアドレスがない患者の予約管理URL**：URL は発行するが、できるのは**内容の確認だけ**。変更・キャンセルの代わりに「お電話ください」と案内する
2. **第5水曜日**：営業する。休診にしたい日は院長が個別に設定する
3. **予約可能期間**：当日から「当日＋30日」の日付まで（例：10/1 なら 10/31 まで）
4. **直前予約の締め切り**：予約時刻の1時間前まで受け付ける（変更・キャンセルの期限と同じ）
5. **1つの枠の定員**：1名
6. **院長が登録・変更する場合**：30日以内・1時間前までの制限は適用しない。二重予約の防止と営業時間内かどうかのチェックは適用する
7. **祝日**：`azuyalabs/yasumi` で判定する。営業時間の優先順位は「日付ごとの個別設定 ＞ 祝日（休診）＞ 曜日ごとの基本設定（第N週のルールを含む）」
8. 前半枠・後半枠は、どちらか一方だけを休診にすることもできる
9. 水曜日に営業する日の営業時間は、ほかの平日と同じ（午前・午後）

## 2. 構成
```
clinic-booking/
├── backend/    Laravel（API のみ）。最新の安定版
├── frontend/   Next.js（App Router、TypeScript、Tailwind CSS）
├── docs/
└── README.md
```
- 開発用 DB は SQLite、本番は MySQL を想定（コードはどちらでも動くように書く）
- タイムゾーンは Asia/Tokyo
- Next.js の rewrites で `/api/*` と `/sanctum/*` を Laravel に転送し、ブラウザからは同じオリジンに見えるようにする
- 本番環境の案：フロントエンドは Vercel、バックエンドは国内のレンタルサーバー。常駐プロセスや Redis がなくても動く設計にする

## 3. DB 設計
### users（院長）
Laravel 標準のテーブル。新規登録 API は作らず、artisan コマンドで1件だけ作成する。パスワードは `Hash::make` でハッシュ化する。

### weekly_schedules（曜日ごとの基本設定）
| カラム | 説明 |
|---|---|
| day_of_week | tinyint, unique。0=日曜〜6=土曜 |
| is_closed | bool |
| am_start / am_end / pm_start / pm_end | time, null 可 |
| closed_weeks_of_month | string, null 可。例："1,3"（水曜日に使う） |

### business_day_overrides（日付ごとの個別設定）
| カラム | 説明 |
|---|---|
| date | date, unique |
| is_closed | bool |
| am_start / am_end / pm_start / pm_end | time, null 可 |

「通常設定に戻す」は、この行を削除して実現する。

### reservations（予約）
| カラム | 説明 |
|---|---|
| patient_name / patient_phone / patient_email（null 可） | 患者情報 |
| visit_type | `first`（初回）/ `return`（再来） |
| treatment_request | text, null 可 |
| start_at / end_at | datetime, index。end_at は start_at の30分後 |
| status | `reserved`（予約中）/ `cancelled`（キャンセル済み）。「来院済み」は end_at から判定する |
| cancelled_at / cancelled_by | `patient` / `admin` |
| source | `web` / `admin` |
| manage_token_hash | char(64), unique, null 可。予約管理トークンの SHA-256 ハッシュ値 |
| name_key / phone_key | 正規化した氏名（空白除去・全角半角の統一）と電話番号（数字のみ） |
| active_start_at | datetime, null 可, **UNIQUE**。予約中のときは start_at と同じ値、キャンセルしたら NULL |
| active_date | date, null 可。**UNIQUE(active_date, name_key, phone_key)** |

### mail_logs（メール送信ログ）
| カラム | 説明 |
|---|---|
| reservation_id | FK, cascadeOnDelete |
| type | `reserved` / `changed` / `cancelled` / `hours_changed` |
| to_email / status（`sent` / `failed`）/ error_message / sent_at | 送信結果 |

## 4. 予約の重複を防ぐ方法
- `active_start_at` の UNIQUE 制約で、1つの枠に予約中の予約が2件入るのを DB が防ぐ（NULL は重複扱いにならないので、キャンセル済みの予約は影響しない）
- 同じ日の同一人物の予約も、同じ仕組みで防ぐ（`active_date` + `name_key` + `phone_key` の UNIQUE 制約）
- 予約の作成・変更はトランザクション内で行う。一意制約に違反したら（`UniqueConstraintViolationException`）409 エラーとして分かりやすいメッセージを返す
- 予約日時の変更は同じ行を UPDATE する。新しい枠の確保と元の枠の解放が1回の更新で同時に行われる
- アプリ側の事前チェックはエラーメッセージを分かりやすくするためのもので、最終的な防御は DB の制約
- 予約中／キャンセルの切り替えはモデルのメソッドにまとめ、`active_*` カラムの更新漏れを防ぐ

### DB・モデルの詳細（ステップ1で決定：2026-10-15）
- `end_at`・`name_key`・`phone_key`・`active_start_at`・`active_date` は、Reservation モデルの `saving` イベントで保存のたびに計算し直す。キャンセルは `Reservation::cancel(CancelledBy)` で行う
- `status`・`*_key`・`active_*`・`manage_token_hash` は `$fillable` に含めない（外部から渡された値で直接書き換えられないようにする）
- 選択肢のある列（visit_type・status・source・cancelled_by、mail_logs の type・status）は文字列で保存し、PHP の Enum（`app/Enums`）でキャストする
- 日時の列は `datetime` 型にする（`timestamp` 型のタイムゾーン変換と 2038 年問題を避けるため）
- `date` 型の列（`business_day_overrides.date`・`reservations.active_date`）は Laravel の date キャストを使わず、`Y-m-d` の文字列で扱う。`time` 型の列は `H:i:s` の文字列で扱う
- 文字数の上限：氏名100文字、電話番号20文字、メールアドレス255文字
- 表記ゆれのそろえ方
  - 氏名：全角英数字→半角、半角カナ→全角カナ、英字は小文字にし、空白（全角・半角）を除く
  - 電話番号：全角数字→半角にし、数字以外を除く
  - 漢字とかなの違い（「山田太郎」と「やまだたろう」）は別人として扱う
- 院長アカウントは `php artisan admin:create` で作成する。アカウントがすでにある場合は作らない。パスワードは12文字以上
- 曜日ごとの基本設定は `WeeklyScheduleSeeder` で登録する。アプリの動作に必要なデータなので、本番環境でも実行する

## 5. API
公開 API（ログイン不要）：
```
GET   /api/availability/dates
GET   /api/availability/{date}
POST  /api/reservations
GET   /api/manage/{token}
PATCH /api/manage/{token}
POST  /api/manage/{token}/cancel
```

### 空き枠 API の詳細（ステップ2で決定：2026-10-16）
- `GET /api/availability/dates` は期間を指定せず、常に予約可能期間（今日〜今日＋30日の31日分）を返す。返す項目は `date`・`is_closed`・`available`（空きが1枠以上あるか）。院長のカレンダーは別の API（`/api/admin/calendar`）で扱う
- `GET /api/availability/{date}` は `date`・`is_closed`・`am`・`pm` を返す。`am`/`pm` は `[{ "time": "09:00", "available": true }, ...]`、休みの枠は `null`
- 枠が ○ になる条件：予約中の予約がない、予約可能期間内、予約時刻の60分以上前（ちょうど60分前は予約できる）
- 存在しない日付・予約可能期間外の日付は 422、日付の形ではない URL は 404
- ○／× 以外の情報（予約者など）は返さない
- 公開 API は同じ IP アドレスから1分あたり60回まで（`RateLimiter` の `public-api`）
- 営業時間の決定は `App\Services\BusinessHours`、○／× の判定は `App\Services\Availability` にまとめる。予約・個別設定は期間分をまとめて1回で取得する
- 枠は開始時刻から30分ずつ作り、終了時刻を超える枠は作らない（例：10:00〜12:45 → 10:00〜12:00 開始の5枠）
- 祝日は `azuyalabs/yasumi` で判定する（振替休日・国民の休日を含む）
- API のルートファイルは `php artisan install:api` で作成した。このとき Sanctum も導入される（使い始めるのはステップ7）
管理 API（`auth:sanctum` によるログインが必要）：
```
GET  /sanctum/csrf-cookie
POST /api/admin/login
POST /api/admin/logout
GET  /api/admin/me
GET/POST   /api/admin/reservations
GET/PATCH  /api/admin/reservations/{id}
POST /api/admin/reservations/{id}/cancel
POST /api/admin/reservations/{id}/resend-mail
GET  /api/admin/mail-logs
GET  /api/admin/calendar?month=YYYY-MM
POST /api/admin/business-days/{date}/impact
PUT/DELETE /api/admin/business-days/{date}
GET/PUT    /api/admin/weekly-schedules
```

## 6. 画面
患者側：
- `/reserve`：日付 → 午前／午後 → ○／× → 情報入力 → 確認 → 完了 の順に進むステップ形式
- `/reservation/[token]`：予約管理ページ
- 無効・期限切れの URL 用の画面

管理画面：
- `/admin/login`
- `/admin`（ダッシュボード）
- `/admin/reservations`（一覧）、`/admin/reservations/new`、`/admin/reservations/[id]`
- `/admin/calendar`
- `/admin/mail-logs`

## 7. メール
- Laravel の Mailable を使い、予約データのコミット後にその場で送信する（キューは使わない）。送信結果は mail_logs に記録し、失敗しても予約は成立させる
- 開発中は `MAIL_MAILER=log`（またはファイル出力）で内容を確認する。本番は Resend やレンタルサーバーの SMTP を使う
- 再送するときはトークンを作り直す（DB にはハッシュ値しか保存していないため）
- 営業時間の変更で影響を受ける予約は、メールアドレスがある患者に `hours_changed` メールを送る。管理画面では「要連絡」として表示する（新しい営業時間から外れている予約をその場で判定する）

## 8. セキュリティ
- Sanctum の SPA 認証（Cookie とセッション）と CSRF 対策。ログイン成功時にセッション ID を作り直す
- ログイン試行の制限：RateLimiter で「メールアドレス＋IP ごとに5回失敗したら一定時間ロック」
- 予約管理トークン：`random_bytes(32)` から生成し、DB には SHA-256 のハッシュ値だけを保存する。予約終了後は無効にし、`noindex`、`Referrer-Policy: no-referrer`、アクセス回数の制限をかける
- 公開 API には回数制限（throttle）をかける
- FormRequest でサーバー側の入力値チェック、`$fillable` で更新できる項目を限定、SQL は Eloquent とバインドのみで組み立てる
- XSS 対策：`dangerouslySetInnerHTML` は使わない
- ログに個人情報を出力しない。`.env` は Git に含めない。本番は `APP_DEBUG=false`

## 9. 自動削除
- Reservation モデルに `Prunable` を設定し、`end_at < now()->subYear()` の予約を削除対象にする
- `routes/console.php` に `Schedule::command('model:prune')->dailyAt('03:00')` を書く
- サーバーの cron で `php artisan schedule:run` を毎分実行する

## 10. 実装手順
| # | 内容 |
|---|---|
| 0 | 環境構築（PHP・Composer・git init・Laravel と Next.js のひな形作成・README） |
| 1 | マイグレーション、モデル、初期データ、院長アカウント作成コマンド |
| 2 | 営業時間を決める処理と空き枠 API、テスト |
| 3 | 予約作成 API（重複防止）、テスト |
| 4 | 患者側の予約画面 |
| 5 | メール送信と送信ログ |
| 6 | 予約管理 URL の API と画面 |
| 7 | 院長のログイン（Sanctum） |
| 8 | 管理画面：予約の操作 |
| 9 | 営業日カレンダー、影響の警告、営業時間変更のお知らせメール |
| 10 | 自動削除 |
| 11 | セキュリティの見直し、レスポンシブ対応、README、本番公開の準備 |
