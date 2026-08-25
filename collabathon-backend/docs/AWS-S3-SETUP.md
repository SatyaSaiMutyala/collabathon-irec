# Storing uploads on S3

Every file a user uploads — property photos, floor plans, brochures, developer logos,
channel-partner profile pictures, and the whole KYC set — is written to S3 rather than
to the server's own disk. This is what has to be true for that to work.

## How it is split

Two disks, `uploads` and `secure`, defined in `config/filesystems.php`:

| Disk | Bucket prefix | What lives there | How it is read |
| --- | --- | --- | --- |
| `uploads` | `public/` | Property media, developer logos, CP profile photos | Permanent URL, cacheable |
| `secure` | `private/` | PAN, Aadhaar (scan + XML), RERA, GST, cancelled cheque, signature | Signed URL, refreshed on every view |

**Nothing in the bucket is ever deleted or expired.** There is no lifecycle rule and
no versioning; a channel partner's PAN card is still there in three years. What is
time-limited is only the *link* to a private file: the app mints a new signed URL
every time a page or screen asks for one, so admins and partners can open these
documents at any point, indefinitely. The signature lasts seven days, which is the
longest AWS will sign for — a presigned URL dated further out than a week is
rejected outright, so a private object simply cannot have a permanent address. The
week matters because the mobile app caches the profile response between launches;
it is long enough that a cached link still opens.

Which of the two a file belongs to is decided from the folder it is stored in, by
`App\Support\FileStorage`. Anything under `broker-documents/` is private; everything
else is public. Nothing else in the codebase names a disk — controllers, resources and
Blade views all go through `FileStorage`, so the rule lives in one place.

The path recorded in the database does not include the bucket prefix. A cover image is
`properties/13/abc.png` in the `properties` table and
`public/properties/13/abc.png` in the bucket; the prefix comes from the disk's `root`.
That is why switching to S3 needed no data migration.

## One-time AWS setup

### 1. The bucket

`collabathon-production-storage` in `ap-south-1`. It is **BucketOwnerEnforced**, which
means object ACLs are disabled — this is AWS's current default and the right setting.
Nothing in the app tries to set an ACL; a `PutObject` carrying `x-amz-acl: public-read`
against this bucket is rejected outright with `AccessControlListNotSupported`.

### 2. Allow a public bucket policy

**Without this step, every property image on the site and in the app will return 403.**

S3 → the bucket → **Permissions** → **Block public access (bucket settings)** → Edit.
Uncheck the two *policy* boxes; leave the two *ACL* boxes checked:

| Setting | Value |
| --- | --- |
| Block public access to buckets and objects granted through new access control lists | **on** |
| Block public access to buckets and objects granted through any access control lists | **on** |
| Block public access to buckets and objects granted through new public bucket policies | **off** |
| Block public and cross-account access to buckets and objects through any public bucket policies | **off** |

Leaving the ACL boxes on is deliberate: ACLs are disabled on this bucket anyway, and
keeping the block means no future change can accidentally make an object public by ACL.

### 3. The bucket policy

Same Permissions tab → **Bucket policy** → Edit. Add the statement below to the
existing policy (keep the `AllowSaiFullAccess` statement that is already there — add
this one alongside it in the `Statement` array).

```json
{
    "Sid": "PublicReadForUploadsPrefix",
    "Effect": "Allow",
    "Principal": "*",
    "Action": "s3:GetObject",
    "Resource": "arn:aws:s3:::collabathon-production-storage/public/*"
}
```

Note the `Resource` ends in `/public/*` and nothing else. `private/*` is not granted to
anyone, so a KYC document cannot be read without a signed URL even if someone guesses
the key.

### 4. Credentials for the app

The application currently authenticates with an IAM user's access key. Two things to
do, in order of preference:

1. **Best:** attach an IAM *role* to the EC2 instance with the policy below and remove
   `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` from `.env` entirely. The SDK picks the
   role up automatically, and there is no long-lived secret to leak or rotate.
2. **Otherwise:** create an IAM user scoped to this bucket only (same policy), and use
   its key. Do not use a key with broader access than this.

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"],
            "Resource": "arn:aws:s3:::collabathon-production-storage/*"
        },
        {
            "Effect": "Allow",
            "Action": "s3:ListBucket",
            "Resource": "arn:aws:s3:::collabathon-production-storage"
        }
    ]
}
```

The key that has been in use up to now (`AKIA3UKBETABM5MTBTPC`) has been shared over
chat and should be rotated whichever option you take.

## Server `.env`

```dotenv
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=...          # omit both if using an instance role
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=ap-south-1
AWS_BUCKET=collabathon-production-storage
AWS_USE_PATH_STYLE_ENDPOINT=false

AWS_PUBLIC_PREFIX=public
AWS_PRIVATE_PREFIX=private
AWS_URL=
```

`FILESYSTEM_DISK` is the switch. Left at `local`, both disks stay on
`storage/app/public` and development behaves exactly as it did before — which is why
local work needs no AWS credentials at all.

Then, as always after an `.env` change:

```bash
php artisan config:clear
```

## Moving the files that are already on the server

Files uploaded before the switch are still in `storage/app/public`. Copy them up:

```bash
php artisan storage:migrate-to-s3 --dry-run   # see what it would do
php artisan storage:migrate-to-s3
```

It reads the folder directly, sends each file to the disk its prefix says it belongs to
(so KYC scans that were public on the old server land in `private/`), and skips anything
already in the bucket at the same size — so it is safe to re-run and safe to interrupt.
Nothing is deleted locally. Check the site, then remove `storage/app/public` by hand
once you are satisfied.

Nothing in the database needs updating: the stored paths are unchanged.

## Worth doing next

Put **CloudFront** in front of the bucket and set `AWS_URL` to the distribution domain
plus the public prefix, e.g. `https://d111111abcdef8.cloudfront.net/public`. Public
files then serve from the CDN instead of from S3 directly — faster for users, cheaper
in transfer, and it takes the image load off the origin entirely. It requires no code
change beyond that one environment variable.

## Checking it works

```bash
php artisan tinker
>>> Storage::disk('uploads')->put('smoke-test.txt', 'hello');
>>> App\Support\FileStorage::url('smoke-test.txt');   # open it — should return "hello"
>>> Storage::disk('uploads')->delete('smoke-test.txt');
```

If that URL returns 403, step 2 or step 3 above has not been done.
