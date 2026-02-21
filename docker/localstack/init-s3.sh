#!/bin/sh
set -eu

bucket_name="${AWS_BUCKET:-docintern-dev}"

if awslocal s3api head-bucket --bucket "${bucket_name}" >/dev/null 2>&1; then
    echo "Bucket ${bucket_name} already exists"
else
    awslocal s3 mb "s3://${bucket_name}"
    echo "Created bucket ${bucket_name}"
fi

awslocal s3api put-bucket-versioning \
    --bucket "${bucket_name}" \
    --versioning-configuration Status=Enabled

echo "Enabled versioning on ${bucket_name}"
