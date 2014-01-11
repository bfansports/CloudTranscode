<?php

// This class serves as a skeletton for classes impleting actual activity
class GridXValidateTranscodedAssetActivity extends GridXBasicActivity
{
	// Perform the activity
	public function do_activity($task)
	{
		global $swf;

		echo "[INFO] [GridXValidateInputAndAssetActivity] Validate transcoded asset !\n";

		$result = "Result TASK 3";

		return $result;
	}
}

