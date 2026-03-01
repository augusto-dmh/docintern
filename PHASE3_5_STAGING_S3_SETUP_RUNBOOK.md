# Phase 3.5 Staging S3 Setup Runbook

This runbook captures the exact sequence used to enable and validate AWS S3 access for staging in Phase 3.5.

## Scope

- Environment: `staging`
- IAM user: `docintern-staging`
- IAM group: `docintern-staging-operators`
- IAM policy: `DocinternStagingOperatorPolicy`
- S3 bucket: `docintern-staging`
- AWS region: `us-east-2`

## 1) Create staging IAM group

Create IAM group:

- Group name: `docintern-staging-operators`

Attach one customer-managed policy to this group (next section).

## 2) Create customer-managed IAM policy

Create policy:

- Policy name: `DocinternStagingOperatorPolicy`
- Description: `Staging operator permissions for Docintern S3, Textract, and Secrets Manager`

Use this policy JSON (replace account/region values if needed):

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "S3ListConsole",
      "Effect": "Allow",
      "Action": [
        "s3:ListAllMyBuckets",
        "s3:GetAccountPublicAccessBlock"
      ],
      "Resource": "*"
    },
    {
      "Sid": "S3CreateBucketGlobal",
      "Effect": "Allow",
      "Action": [
        "s3:CreateBucket"
      ],
      "Resource": "*"
    },
    {
      "Sid": "S3BucketConfigForStaging",
      "Effect": "Allow",
      "Action": [
        "s3:DeleteBucket",
        "s3:GetBucketLocation",
        "s3:ListBucket",
        "s3:PutBucketPublicAccessBlock",
        "s3:GetBucketPublicAccessBlock",
        "s3:PutBucketVersioning",
        "s3:GetBucketVersioning",
        "s3:PutEncryptionConfiguration",
        "s3:GetEncryptionConfiguration",
        "s3:PutBucketPolicy",
        "s3:GetBucketPolicy",
        "s3:GetBucketOwnershipControls",
        "s3:PutBucketOwnershipControls"
      ],
      "Resource": "arn:aws:s3:::docintern-staging"
    },
    {
      "Sid": "S3ObjectOpsForSmokeTests",
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject",
        "s3:AbortMultipartUpload"
      ],
      "Resource": "arn:aws:s3:::docintern-staging/*"
    },
    {
      "Sid": "TextractLiveOcr",
      "Effect": "Allow",
      "Action": [
        "textract:DetectDocumentText"
      ],
      "Resource": "*"
    },
    {
      "Sid": "SecretsManagerForStagingOnly",
      "Effect": "Allow",
      "Action": [
        "secretsmanager:CreateSecret",
        "secretsmanager:PutSecretValue",
        "secretsmanager:UpdateSecret",
        "secretsmanager:DeleteSecret",
        "secretsmanager:RestoreSecret",
        "secretsmanager:DescribeSecret",
        "secretsmanager:GetSecretValue"
      ],
      "Resource": "arn:aws:secretsmanager:us-east-2:042196391234:secret:docintern/staging/*"
    }
  ]
}
```

For live classification, store `OPENAI_API_KEY` in Secrets Manager under a staging-scoped secret (for example `docintern/staging/openai`) and inject it into app/worker runtime as `OPENAI_API_KEY`.

## 3) Create staging IAM user and attach group

Create user:

- Username: `docintern-staging`
- Add to group: `docintern-staging-operators`
- Enable console access if needed for manual UI checks

Then create one access key for local application tests:

- Use case: `Local code`
- Store key ID and secret safely

## 4) Sign in as staging IAM user

Use IAM user login URL:

- `https://042196391234.signin.aws.amazon.com/console`

Sign in with:

- Account ID: `042196391234`
- IAM username: `docintern-staging`
- IAM password

## 5) Create and harden S3 bucket

In S3 (as `docintern-staging`), create bucket:

- Bucket name: `docintern-staging`
- Region: `us-east-2`
- Bucket type: `General purpose`

Set these controls:

- Block all public access: `ON`
- Object ownership: `ACLs disabled (Bucket owner enforced)`
- Bucket versioning: `Enabled`
- Default encryption: `Enabled` (SSE-S3 is sufficient for staging)

## 6) Manual S3 console smoke test

In S3 console:

- Open bucket `docintern-staging`
- Upload a small test file (for example `healthchecks/manual-ui.txt`)
- Confirm upload succeeds

## 7) Laravel-to-S3 smoke test from local app container

Export real staging credentials in your shell first:

```bash
export AWS_ACCESS_KEY_ID='AKIA...'
export AWS_SECRET_ACCESS_KEY='...'
export AWS_DEFAULT_REGION='us-east-2'
export AWS_BUCKET='docintern-staging'
```

Run:

```bash
docker compose exec \
  -e FILESYSTEM_DISK=s3 \
  -e AWS_ACCESS_KEY_ID \
  -e AWS_SECRET_ACCESS_KEY \
  -e AWS_DEFAULT_REGION \
  -e AWS_BUCKET \
  -e AWS_ENDPOINT_URL= \
  -e AWS_USE_PATH_STYLE_ENDPOINT=false \
  app php artisan tinker --execute='
config()->set("filesystems.default","s3");
config()->set("filesystems.disks.s3.key", getenv("AWS_ACCESS_KEY_ID"));
config()->set("filesystems.disks.s3.secret", getenv("AWS_SECRET_ACCESS_KEY"));
config()->set("filesystems.disks.s3.region", getenv("AWS_DEFAULT_REGION"));
config()->set("filesystems.disks.s3.bucket", getenv("AWS_BUCKET"));
config()->set("filesystems.disks.s3.endpoint", null);
config()->set("filesystems.disks.s3.use_path_style_endpoint", false);
$key = "manual-tests/laravel-".now()->timestamp.".txt";
$body = "uploaded by laravel at ".now()->toIso8601String();
\Illuminate\Support\Facades\Storage::disk("s3")->put($key, $body);
dump([
  "key" => $key,
  "exists" => \Illuminate\Support\Facades\Storage::disk("s3")->exists($key),
  "content" => \Illuminate\Support\Facades\Storage::disk("s3")->get($key),
]);
'
```

Successful output pattern:

- `"exists" => true`
- `"content" => "uploaded by laravel at ..."`

## 8) Cleanup test object

Delete exact uploaded key:

```bash
docker compose exec \
  -e FILESYSTEM_DISK=s3 \
  -e AWS_ACCESS_KEY_ID \
  -e AWS_SECRET_ACCESS_KEY \
  -e AWS_DEFAULT_REGION \
  -e AWS_BUCKET \
  -e AWS_ENDPOINT_URL= \
  -e AWS_USE_PATH_STYLE_ENDPOINT=false \
  app php artisan tinker --execute='
$key = "manual-tests/laravel-REPLACE_WITH_TIMESTAMP.txt";
config()->set("filesystems.default","s3");
config()->set("filesystems.disks.s3.key", getenv("AWS_ACCESS_KEY_ID"));
config()->set("filesystems.disks.s3.secret", getenv("AWS_SECRET_ACCESS_KEY"));
config()->set("filesystems.disks.s3.region", getenv("AWS_DEFAULT_REGION"));
config()->set("filesystems.disks.s3.bucket", getenv("AWS_BUCKET"));
config()->set("filesystems.disks.s3.endpoint", null);
config()->set("filesystems.disks.s3.use_path_style_endpoint", false);
\Illuminate\Support\Facades\Storage::disk("s3")->delete($key);
dump(["deleted_key" => $key]);
'
```

## 9) Troubleshooting used during setup

- `InvalidAccessKeyId`: wrong key ID, placeholder value, or inactive/deleted key.
- `SignatureDoesNotMatch`: secret does not match key ID, or malformed copy/paste.
- `UnableToCheckDirectoryExistence`: disk config mismatch during check; set endpoint to `null`, `use_path_style_endpoint=false`, then retry.
- S3 console shows no buckets: ensure `s3:ListAllMyBuckets` and `s3:GetAccountPublicAccessBlock` are allowed.
- Cannot view Object Ownership: add `s3:GetBucketOwnershipControls` permission.

## 10) Security note

- Access keys shown in terminal/chat must be rotated immediately.
- Keep only active keys in secret storage; never commit them to git.
