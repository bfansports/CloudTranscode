<?php

// This class serves as a skeletton for classes impleting actual activity
class ValidateTranscodedAssetActivity extends BasicActivity
{
	// Perform the activity
	public function do_activity($task)
	{
		global $swf;

		echo "[INFO] [ValidateInputAndAssetActivity] Validate transcoded asset !\n";

		$result = "Result TASK 3";

		return $result;
	}
}

