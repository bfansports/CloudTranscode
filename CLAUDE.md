# CloudTranscode

## What This Is

CloudTranscode is bFAN's distributed media transcoding pipeline. It's a set of PHP-based activity workers that poll AWS Step Functions for transcoding jobs, then execute FFmpeg (for video) or ImageMagick (for images) to transcode media files and upload results to S3. The architecture allows horizontal scaling by running multiple workers in ECS containers.

## Tech Stack

- **Language**: PHP 7+ (legacy codebase, but clean)
- **Container**: Docker (ECS deployment)
- **FFmpeg**: 4.2 (video/image processing)
- **ImageMagick**: convert commands for image transcoding
- **AWS Services**: Step Functions (SFN), S3, ECS, EC2, IAM
- **SDK**: CloudProcessingEngine-SDK (bFAN fork) for activity polling and lifecycle
- **Dependencies**: AWS SDK for PHP 3.x, JSON Schema validation

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

- **Activity polling**: Workers use long-polling to fetch tasks from AWS SFN
- **Sequential output processing**: One TranscodeAssetActivity worker processes all outputs in the `output_assets` array sequentially, not in parallel. To parallelize, split the workflow.
- **Stateless workers**: Workers are horizontally scalable Docker containers. State lives in S3 and SFN.
- **Preset-based transcoding**: FFmpeg commands can be templated using presets (e.g., `360p-4.3-generic`)
- **Custom FFmpeg commands**: JSON input supports raw FFmpeg command strings for advanced use cases
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
- Docker image built from `Dockerfile` and pushed to ECR: `501431420968.dkr.ecr.eu-west-1.amazonaws.com/sportarc/cloudtranscode:4.2`
- ECS cluster runs workers as tasks
- Each worker polls a specific SFN activity ARN

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

## Gotchas

- **Sequential processing**: TranscodeAssetActivity processes all outputs sequentially. For parallel transcoding of multiple outputs, you must split the workflow or run multiple workers with separate SFN tasks.
- **Docker base image dependency**: This repo depends on two SportArchive Docker images (`sportarc/ffmpeg`, `sportarc/cloudtranscode-base`). If those images are updated, rebuild this image.
- **FFmpeg version**: Locked to 4.2. Upgrading FFmpeg requires updating the base image.
- **Client interface requirement**: For production use, you MUST implement a custom client interface class and extend the Dockerfile to include it. Without it, workers run but don't notify client apps of progress/completion.
- **AWS SFN long polling**: Workers block on GetActivityTask calls (long polling). If AWS SFN is unavailable, workers will hang until timeout.
- **Temp disk space**: Transcoding uses local disk for temporary files. Ensure ECS instances or Docker volumes have sufficient space for large video files.
- **Presets location**: The `presets/` directory in this repo may be deprecated. Check if CloudTranscode-FFMpeg-presets is the canonical source.

<!-- Ask: What happens if a worker crashes mid-transcode? Does SFN retry, or is the task lost? Are there heartbeat intervals configured? -->
<!-- Ask: How are FFmpeg presets loaded — from this repo's presets/ dir, or from CloudTranscode-FFMpeg-presets? -->
<!-- Ask: What's the relationship between this repo and CloudTranscode-Lambda? When is Lambda used vs ECS workers? -->