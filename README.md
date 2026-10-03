## 勤怠管理アプリ

一般ユーザーの勤怠登録・修正申請と、管理者による勤怠管理・修正申請の承認を行う勤怠管理アプリです。
メール認証、CSV出力、勤怠統計レポート、公開API機能も実装しています。

## 環境構築

### 1. リポジトリをクローン

'''bash
git clone https://github.com/shoko-kurokawa/attendance-app
'''

その後プロジェクトディレクトリへ移動します
'''bash
cd attendance-app
'''

### 2. Composerパッケージをインストール

'''bash
docker run --rm \
 -u "$(id -u):$(id -g)" \
 -v "$(pwd):/var/www/html" \
 -w /var/www/html \
 laravelsail/php85-composer:latest \
 composer install --ignore-platform-reqs
'''

### 3. 環境変数ファイルを作成

'''bash
cp .env.example .env
'''

'.env'のデータベース設定を'compose.yaml'のMySQL環境に合わせて設定します。

例：

'''env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
'''

### 4. Dockerコンテナを起動

'''bash
./vendor/bin/sail up -d
'''

### 5. アプリケーションキーを生成

'''bash
./vendor/bin/sail artisan key:generate
'''

### 6. マイグレーションとシーディングを実行

'''bash
./vendor/bin/sail artisan migrate:fresh --seed
'''

## 使用技術

- PHP 8.2
- Laravel 10.x
- MySQL 8.4
- Laravel Fortify
- Laravel Sanctum
- Laravel Sail
- Mailpit
- phpMyAdmin
- Docker

## ER図

<img width="3036" alt="ER図" src="https://github.com/user-attachments/assets/c936d7ef-6c66-48b7-a5dc-fcee1abb4251" />

## URL

- アプリケーション: http://localhost
- 一般ユーザーログイン: http://localhost/login
- 一般ユーザー会員登録: http://localhost/register
- 管理者ログイン: http://localhost/admin/login
- phpMyAdmin: http://localhost:8080
- Mailpit: http://localhost:8025

※'.env'で各転送ポートを変更している場合は、設定したポートを使用してください。

## テストユーザー

sail artisan migrate:fresh --seed 実行後、以下のユーザーでログインできます。

### 一般ユーザー1

- メールアドレス: user1@example.com
- パスワード: password

### 一般ユーザー2

- メールアドレス: user2@example.com
- パスワード: password

### 管理者ユーザー

- メールアドレス: user3@example.com
- パスワード: password

## 主な機能

### 一般ユーザー

- 会員登録・ログイン・ログアウト
- メール認証・認証メール再送
- 出勤・退勤打刻
- 休憩開始・終了打刻
- 月次勤怠一覧
- 勤怠詳細
- 勤怠修正申請
- 修正申請一覧
- マイ勤怠レポート

### 管理者ユーザー

- 管理者ログイン・ログアウト
- 日次勤怠一覧
- 勤怠詳細・修正
- スタッフ一覧
- スタッフ別月次勤怠一覧
- CSV出力
- 修正申請一覧
- 修正申請承認

### 公開API

API v1として勤怠情報の取得・登録・更新・削除を提供しています。

'''text
GET /api/v1/attendance-records
GET /api/v1/attendance-records/{attendanceRecord}
POST /api/v1/attendance-records
PUT /api/v1/attendance-records/{attendanceRecord}
PATCH /api/v1/attendance-records/{attendanceRecord}
DELETE /api/v1/attendance-records/{attendanceRecord}
'''

GETは認証不要です。
POST、PUT、PATCH、DELETEにはLaravel Sanctumによる認証が必要です。

## テスト

以下のコマンドでテストを実行できます。

'''bash
./vendor/bin/sail artisan test
'''
