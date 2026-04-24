# CloudTranscode

## What This Is

CloudTranscode is bFAN's distributed media transcoding pipeline. It's a set of PHP-based activity workers that poll AWS Step Functions for transcoding jobs, then execute FFmpeg (for video) or ImageMagick (for images) to transcode media files and upload results to S3. The architecture allows horizontal scaling by running multiple workers in ECS containers.

## Tech Stack

- **Language**: PHP 7+ (legacy codebase, but clean)
- **Container**: Docker (ECS deployment)
- **FFmpeg**: 4.2 (video/image processing) — **EOL, from 2019. See Security Findings.**
- **ImageMagick**: convert commands for image transcoding
- **AWS Services**: Step Functions (SFN), S3, ECS, EC2, IAM
- **Orchestration**: AWS Step Functions state machines (see `state_machines/`)
- **SDK**: CloudProcessingEngine-SDK (bFAN fork) for activity polling and lifecycle
- **Dependencies**: AWS SDK for PHP 3.x, JSON Schema validation
- **Monitoring**: None configured — no CloudWatch alarms, dashboards, or custom metrics. See Security Findings.

## Quick Start

```bash
# Setup
make  # Installs composer dependencies

# Run activities locally (requires AWS credentials and SFN ARNs)
./src/activities/ValidateAssetActivity.php -A arn:aws:states:REGION:ACCOUNT:activity:ValidateAsset
./src/activities/TranscodeAssetActivity.php -A arn:aws:states:REGION:ACCOUNT:activity:TranscodeAsset

# Run in Docker (recommended)
docker build -t cloudtranscode:local .
docker run cloudtranscode:local ValidateAssetActivity -A <arn>
docker run cloudtranscode:local TranscodeAssetActivity -A <arn>

# Run tests
<!-- Ask: Does this repo have tests? If so, what command runs them? -->
```

## Project Structure

- `src/activities/` — Activity workers (ValidateAssetActivity, TranscodeAssetActivity, BasicActivity base class)
- `src/activities/transcoders/` — Transcoder implementations (video, image, thumbnail)
- `src/scripts/` — Utility scripts
- `src/utils/` — Helper classes
- `state_machines/` — AWS Step Functions state machine JSON definitions
- `input_samples/` — Example JSON input payloads for testing workflows
- `presets/` — FFmpeg preset configurations (may be deprecated; check CloudTranscode-FFMpeg-presets repo)
- `benchmark/` — FFmpeg performance benchmarks on AWS EC2 instances
- `Dockerfile` — Base image for ECS workers
- `bootstrap.sh` — Docker entrypoint script
- `Makefile` — Composer dependency installation

## Dependencies

**Internal:**
- CloudProcessingEngine-SDK (bFAN fork) — activity polling, client interface callbacks, lifecycle management

**External:**
- AWS S3 — input/output media storage
- AWS Step Functions — task orchestration and distribution
- FFmpeg 4.2 — video/audio/image transcoding (bundled in Docker base image)
- ImageMagick — image manipulation (bundled in Docker base image)

**Docker base images:**
- `sportarc/ffmpeg:4.2` — FFmpeg binaries
- `sportarc/cloudtranscode-base:4.2` — PHP + FFmpeg + ImageMagick base

## API / Interface

**Input**: JSON payloads posted to AWS Step Functions (see `input_samples/` for examples). Structure:
- `input_asset` — source file (S3 bucket, key, type)
- `output_assets[]` — array of desired outputs (type, bucket, path, codec/size/preset, watermark, etc.)

**Output**: JSON result returned from Step Functions to client app. Includes transcoded file S3 locations, metadata, errors.

**Client Integration**: Implement `CpeClientInterface.php` from CloudProcessingEngine-SDK to receive callbacks:
- `onStart` — workflow initiated
- `onHeartbeat` — worker is alive
- `onFail` — transcoding failed
- `onSuccess` — workflow completed
- `onTranscodeDone` — one output asset completed

Pass custom client class to activity workers via `-C <client class path>` option. For Docker, extend the base image and copy client classes into it.

## Key Patterns

- **Step Functions orchestration**: Workflow is defined in `state_machines/` JSON. SFN distributes tasks to activity workers, handles retries and failure routing. This is the control plane — workers are the data plane.
- **Activity polling**: Workers use long-polling to fetch tasks from AWS SFN
- **Sequential output processing**: One TranscodeAssetActivity worker processes all outputs in the `output_assets` array sequentially, not in parallel. This is a **performance bottleneck** — a 10-output job ties up one worker for the full duration. To parallelize, split the workflow into separate SFN executions or use Map states.
- **Stateless workers**: Workers are horizontally scalable Docker containers. State lives in S3 and SFN.
- **Preset-based transcoding**: FFmpeg commands can be templated using presets (e.g., `360p-4.3-generic`)
- **Custom FFmpeg commands**: JSON input supports raw FFmpeg command strings for advanced use cases. **WARNING: command injection risk — see Security Findings.**
- **Watermarking**: Overlay images on video with custom position, opacity, size
- **HTTP input**: Workers can pull source files from HTTP/S URLs instead of S3

## Environment

**Required AWS credentials** (IAM role or env vars):
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_DEFAULT_REGION`

**Required IAM permissions:**
- Step Functions: `states:GetActivityTask`, `states:SendTaskSuccess`, `states:SendTaskFailure`, `states:SendTaskHeartbeat`
- S3: `s3:GetObject`, `s3:PutObject`, `s3:PutObjectAcl` on input/output buckets

**Runtime**: PHP 7+, FFmpeg 4.2, ImageMagick (all bundled in Docker image)

<!-- Ask: Are there any environment variables or config files for controlling worker behavior (timeouts, concurrency, temp directories, etc.)? -->

## Deployment

**Current setup:**
- Docker image built from `Dockerfile` and pushed to ECR (eu-west-1)
- ECS cluster runs workers as tasks
- Each worker polls a specific SFN activity ARN
- **Note**: AWS account ID `501431420968` is hardcoded in the Dockerfile/configs. Use an environment variable or SSM parameter instead.

**Deployment steps:**
1. Build Docker image: `docker build -t <ecr-repo>:tag .`
2. Push to ECR
3. Update ECS task definition with new image tag
4. Deploy new ECS service revision

<!-- Ask: Is there a CI/CD pipeline for this repo (GitHub Actions, CodePipeline, etc.)? What triggers deployments (manual, PR merge, tags)? -->

## Testing

<!-- Ask: Does this repo have unit tests, integration tests, or manual test procedures? What's the test coverage strategy? -->

**Manual testing:**
- Use `input_samples/` JSON files to initiate test workflows via AWS SDK
- Monitor Step Functions console for workflow execution
- Check S3 output buckets for transcoded files
- Review CloudWatch Logs for worker output

## Security Findings

> AI audit — 2026-02-17. These findings should be tracked as issues and resolved.

### CRITICAL — Command Injection via FFmpeg/ImageMagick

The transcoder code passes user-supplied JSON parameters (codec, size, preset names, custom command strings) into FFmpeg and ImageMagick shell commands **without escaping or sanitization**. A crafted `output_assets` payload could inject arbitrary shell commands.

**Affected files**: `src/activities/transcoders/` — anywhere parameters are interpolated into shell commands.

**Fix**: Use `escapeshellarg()` on every user-supplied parameter before interpolation. Better: build argument arrays and use `proc_open()` instead of `exec()`/`shell_exec()` with string concatenation. Validate inputs against an allowlist of known codecs, presets, and sizes.

### HIGH — No Rate Limiting

There is no throttling on Step Functions task submission. A misconfigured client or runaway automation can flood the pipeline with jobs, exhausting ECS capacity and S3 write throughput.

**Fix**: Add SFN execution concurrency limits, or use an SQS queue with a controlled consumer rate in front of the pipeline.

### MEDIUM — Hardcoded AWS Account ID

AWS account ID `501431420968` appears in ECR URIs and potentially in SFN ARNs throughout the codebase. This leaks infrastructure details and makes multi-account deployment impossible.

**Fix**: Replace with environment variables, SSM parameters, or CDK/CloudFormation references.

### MEDIUM — FFmpeg 4.2 (2019) — End of Life

FFmpeg 4.2 is from August 2019 and no longer receives security patches. Known CVEs in older FFmpeg versions include heap overflows in demuxers and decoders that can be triggered by malformed input media.

**Fix**: Upgrade the `sportarc/ffmpeg` and `sportarc/cloudtranscode-base` Docker images to FFmpeg 6.x or 7.x. Test transcoding presets for compatibility.

### MEDIUM — No Temp File Encryption

Transcoding temp files (downloaded source, intermediate outputs) are stored on local ECS disk unencrypted. If the disk is an EBS volume, data at rest is exposed unless the volume itself has encryption enabled.

**Fix**: Ensure ECS instances use encrypted EBS volumes. For sensitive media, consider encrypting temp files at the application level or using instance store with dm-crypt.

### LOW — No CloudWatch Monitoring

No CloudWatch alarms, custom metrics, or dashboards are configured. Worker failures, SFN execution errors, and S3 throughput issues are invisible without manual console checks.

**Fix**: Add CloudWatch alarms for SFN execution failures, ECS task stopped events, and worker heartbeat gaps. Publish custom metrics for transcode duration, queue depth, and error rates.

## Gotchas

- **Sequential processing bottleneck**: TranscodeAssetActivity processes all outputs sequentially. A single job with many outputs blocks the worker. Split into parallel SFN branches or use Map states.
- **Docker base image dependency**: This repo depends on two SportArchive Docker images (`sportarc/ffmpeg`, `sportarc/cloudtranscode-base`). If those images are updated, rebuild this image.
- **FFmpeg version**: Locked to 4.2 (2019, EOL). Upgrading FFmpeg requires updating the base image and retesting all presets.
- **Client interface requirement**: For production use, you MUST implement a custom client interface class and extend the Dockerfile to include it. Without it, workers run but don't notify client apps of progress/completion.
- **AWS SFN long polling**: Workers block on GetActivityTask calls (long polling). If AWS SFN is unavailable, workers will hang until timeout.
- **Temp disk space**: Transcoding uses local disk for temporary files. Ensure ECS instances or Docker volumes have sufficient space for large video files. Temp files are **not encrypted** at the application level.
- **Presets location**: The `presets/` directory in this repo may be deprecated. Check if CloudTranscode-FFMpeg-presets is the canonical source.
- **Hardcoded account ID**: `501431420968` is baked into ECR URIs and possibly SFN ARNs. Must be parameterized for multi-account use.

<!-- Ask: What happens if a worker crashes mid-transcode? Does SFN retry, or is the task lost? Are there heartbeat intervals configured? -->
<!-- Ask: How are FFmpeg presets loaded — from this repo's presets/ dir, or from CloudTranscode-FFMpeg-presets? -->
<!-- Ask: What's the relationship between this repo and CloudTranscode-Lambda? When is Lambda used vs ECS workers? -->
