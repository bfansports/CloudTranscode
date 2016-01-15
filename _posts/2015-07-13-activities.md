---
layout: page
title: "Activities"
category: top
date: 2015-07-13 22:11:52
order: 1
---

CT offers two activities that you can use in your workflow:

   - **ValidateAsset:** This activity probes an input asset. For videos, we run ffprobe and capture its metadata.
   - **TranscodeAsset:** This activity transcodes an asset into another asset. For videos we use FFMpeg.

Each activities expect a specific JSON payload in order to execute correctly.

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
	
   - [**input_asset**](/CloudTranscode/specs/input.html): Contains the basic information about your input asset. The ValidateAsset activity only supports the `buket/file` input parameters. It doesn't support `http`. 
   - **client**: The client is injected in your workflow input paylad by the InputPoller. **You must pass it to all your activities.** See for example: http://sportarchive.github.io/CloudProcessingEngine/comp/decider.html#decider-plan

**This activity should always be first in a workflow for two reasons:**

   - It validates if the input asset is legit and valid
   - It gathers metadata about the asset
   
<br>

> **IMPORTANT:** Note that the initial JSON payload you send when starting a new job must also contain the `worflow` section. It is not mentioned here as it is not required by the activities themselves but by the InputPoller which receives your commands from SQS. See: http://sportarchive.github.io/CloudProcessingEngine/comp/inputpoller.html#input-requirements

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

   - [**input_asset**](/CloudTranscode/specs/input.html): Contains the basic information about your input asset.
   - **client**: The client is injected in your client application payload by the InputPoller. You must pass it to your activities. See for example: http://sportarchive.github.io/CloudProcessingEngine/comp/decider.html#decider-plan
   - **input_asset_metadata**: Metadata that will be passed along to the Transcoder. For Videos, pass the output of FFprobe that describe the input asset. This can be capture in your decider plan after the ValidateAsset acitivty and passed on to the TranscodeActivity. See the ct_plan_simple.xml in the Decider project (docs/examples/).
   - [**output_asset**](/CloudTranscode/specs/output.html): Information about what type of resulting file we want. What do you want to transcode?

Now you have the information you need to start your own job. Check the specs in the documentation to know the options you have at your disposal to transcode the files you want.

