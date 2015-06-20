# Flow.Flysystem

This package connects the Flow Framework to Flysystem-enabled backends.

**It is currently work-in-progress, so use at your own risk.**

It is an inofficial fork of https://git.typo3.org/Packages/TYPO3.Flow.Flysystem.git -- because this version did
not work for me in Flow 3.0.

Based on the official version, I added the following parts:

* made the code work with Flow 3.0
* added a README
* directly added an Amazon S3 Target

Currently, the following Flysystem targets are supported:

* Amazon S3 API v3 as Resource Publishing Target, Not as Storage


## Amazon S3 as Publishing Target

```
TYPO3:
  Flow:
    resource:
      targets:
        s3Target:
          target: 'TYPO3\Flow\Flysystem\Resource\Target\S3Target'
          targetOptions:
            driverOptions:
              s3key: your-aws-access-key
              s3secret: your-aws-access-secret
              s3region: aws-region - example: eu-central-1
              s3bucket: NAME-OF-S3-BUCKET
              s3prefix: name-of-prefix-in-s3-bucket
```

After you created the bucket, go to "Permissions -> Edit Bucket Policy" and use the following policy which allows
read-only access for everybody:

```
{
	"Version": "2012-10-17",
	"Statement": [
		{
			"Sid": "AddPerm",
			"Effect": "Allow",
			"Principal": "*",
			"Action": [
				"s3:GetObject"
			],
			"Resource": [
				"arn:aws:s3:::NAME-OF-S3-BUCKET/*"
			]
		}
	]
}
``` 
 
If you want to create an extra AWS IAM user for writing (which is recommended), first create the bucket in S3,
then go to IAM, create a new user and add an IAM policy of the following form:

```
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "....",
            "Effect": "Allow",
            "Action": [
                "s3:DeleteObject",
                "s3:GetObject",
                "s3:ListBucket",
                "s3:PutObject"
            ],
            "Resource": [
                "arn:aws:s3:::NAME-OF-S3-BUCKET/*",
                "arn:aws:s3:::NAME-OF-S3-BUCKET"
            ]
        }
    ]
}
```
