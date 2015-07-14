---
layout: page
title: "Activities"
category: specs
date: 2015-07-13 22:11:52
order: 1
---

CT offers two activities that you can use in your workflow:

   - **ValidateAsset:** This activity validate the input asset that we want to transcode. For videos, we run ffprobe on the asset and capture its metadata.
   - **TranscodeAsset:** This activity transcode an asset in another asset. For videos we use FFMpeg.

Each activities exepect a specific JSON in order to execute correctly.

### ValidateAsset

The expected JSON payload your Decider must pass as input to this activity is as follow:

```json
{
    "input_asset": {
        "type": "VIDEO",
        "bucket": "cloudtranscode-dev",
        "file": "/input/video1.mp4"
    },
    "client": {
    	 "name": "SA",
    	 "queues": {
             "input": "https://sqs.us-east-1.amazonaws.com/441276146445/nico-ct-input",
             "output": "https://sqs.us-east-1.amazonaws.com/441276146445/nico-ct-output"
         }
    }
}
```

**Those are the minimum fields this activity should receive:**

   - **input_asset**: Contains the basic information about your input asset.
   - **client**: The client is injected in your client application payload by the InputPoller. You must pass it to your activities. See for example: http://sportarchive.github.io/CloudProcessingEngine/comp/decider.html#decider-plan

**This activity should be called first for two reasons:**

   - Validate if the input asset is legit and valid
   - Gather metadata about the asset
   
<br>

> Note that the initial JSON payload you send when starting a new job must also contain the `worflow` section not mentioned here. This `workflow` section is used by the InputPoller. See: http://sportarchive.github.io/CloudProcessingEngine/comp/inputpoller.html#input-requirements

### TranscodeAsset

The expected JSON payload your Decider must pass as input to this activity is as follow:

```json
{
    "input_asset": {
        "type": "VIDEO",
        "bucket": "cloudtranscode-dev",
        "file": "/input/video1.mp4"
    },
    "input_asset_metadata": {
        [Data coming from ValidateAsset (FFProbe output)]
    },
    "output_asset": {
        [Output wanted]
    },
    "client": {
        "name": "SA",
    	"queues": {
            "input": "https://sqs.us-east-1.amazonaws.com/441276146445/nico-ct-input",
            "output": "https://sqs.us-east-1.amazonaws.com/441276146445/nico-ct-output"
        }
    }
}
```

**Those are the minimum fields this activity should receive:**

   - **input_asset**: Contains the basic information about your input asset.
   - **client**: The client is injected in your client application payload by the InputPoller. You must pass it to your activities. See for example: http://sportarchive.github.io/CloudProcessingEngine/comp/decider.html#decider-plan
   - **input_asset_metadata**: Output of FFprobe that describe the input asset
   - **output_asset**: Information about what type of resulting file we want. The format for this section is detailed later in the documentation.
