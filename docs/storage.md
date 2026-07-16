# File storage

File storage (currently just [profile avatars](user-management.md#avatars)) is wired as the **default filesystem disk**, so app code never hard-codes a driver — `User::avatarDisk()` returns `config('filesystems.default')` (`FILESYSTEM_DISK`).

| Environment | Disk | Backing |
|---|---|---|
| Development | `s3` | MinIO (bundled) |
| Production | `s3` | Real AWS S3 |
| Tests | `local` | `Storage::fake()` (forced by `phpunit.xml`) |

## Local development — MinIO

Dev uploads go to **MinIO**, an S3-compatible server in the compose override — so uploads exercise **real S3 semantics** without touching AWS, and nothing leaves the machine. A one-shot `createbuckets` container provisions the app bucket (`AWS_BUCKET`, default `larnux`) on boot.

`backend/.env` sets `FILESYSTEM_DISK=s3` and points the `s3` disk at `http://minio:9000` (`AWS_ENDPOINT`, path-style, `minioadmin`/`minioadmin`). The web console is at **`http://localhost:9001`** (override with `MINIO_CONSOLE_PORT`). The `s3` driver needs `league/flysystem-aws-s3-v3` (in `composer.json`).

Set `FILESYSTEM_DISK=local` to skip MinIO entirely and store on the private disk instead.

> The **[.env force-recreate gotcha](architecture.md#the-three-env-layers)** applies: after editing `AWS_*`/`FILESYSTEM_DISK`, run `docker compose up -d --force-recreate php queue`.

## The browser never touches the object store

Crucially, the browser **never** contacts MinIO/S3 directly. The app streams files back through an authenticated API route (`Storage::disk(...)->response()`), so there's **no CORS, no public bucket**, and it works behind the [same-origin nginx](architecture.md#same-origin-nginx-the-core-decision) (which only routes `/api|sanctum|up|horizon` to PHP).

## Production

Keep `FILESYSTEM_DISK=s3`, but swap in real AWS credentials, **drop** `AWS_ENDPOINT`, set `AWS_USE_PATH_STYLE_ENDPOINT=false`, and drop the MinIO services.
