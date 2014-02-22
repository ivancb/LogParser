<?php

include "util.php";

if(!isset($_POST["reqid"])) // In process/store mode
{
	$origLogData = $_POST["rawdata"];
	$logdata = json_decode($origLogData);
	$parseFormat = $_POST["format"];
	$storeOutput = $_POST["storeoutput"];

	// Begin parsing the log format descriptor
	$formatChunks = array();
	$curChunk = array();
	$inStartDelimiter = true;
	$start = 0;

	for($n = 0; $n < strlen($parseFormat); $n++)
	{
		if($inStartDelimiter)
		{
			if($parseFormat[$n] == '$')
			{
				if(count($curChunk) == 2)
				{
					array_push($formatChunks, $curChunk);
					$curChunk = array();
				}

				array_push($curChunk, substr($parseFormat, $start, $n - $start));
				$start = $n + 1;
				$inStartDelimiter = false;
			}
		}
		else // Expects $<id>
		{
			if(!is_numeric($parseFormat[$n]))
			{
				array_push($curChunk, substr($parseFormat, $start, $n - $start));
				$start = $n;
				$inStartDelimiter = true;
			}
		}
	}

	// Check if we have any leftover data
	if(!$inStartDelimiter && ($start != strlen($parseFormat)))
	{
		array_push($curChunk, substr($parseFormat, $start, strlen($parseFormat)));
		array_push($formatChunks, $curChunk);
	}

	// Log format descriptor parsing completed, start parsing the actual log data now
	$parsedData = array();

	// Convert input so it uses windows style line endings
	$logdata = preg_replace('/\r\n?/', "\n", $logdata);
	$logDataArray = explode("\n", $logdata);
	$data = array();
	$noStartingDelimiter = (strlen($formatChunks[0][0]) == 0);

	foreach($logDataArray as $logEntry)
	{
		$curChunkIndex = -1;
		$valueStart = 0;
		$extractedLineData = array();

		for($n = 0; $n < strlen($logEntry); $n++)
		{
			if(($curChunkIndex + 1) >= count($formatChunks))
				break;
			
			if(ContainsStringAtIndex($logEntry, $formatChunks[$curChunkIndex + 1][0], $n))
			{
				// NOTE: Only stores data if the starting index for data's valid or if the starting chunk has no delimiter
				if($valueStart != 0)
					array_push($extractedLineData, substr($logEntry, $valueStart, $n - $valueStart));
				else if(($curChunkIndex == 0) && $noStartingDelimiter)
					array_push($extractedLineData, substr($logEntry, 0, $n));

				$chunkDelimiterLength = strlen($formatChunks[$curChunkIndex + 1][0]);
				$valueStart = $n + $chunkDelimiterLength;
				$n += $chunkDelimiterLength - 1;
				$curChunkIndex++;
			}
		}

		// Store any leftover data in an array entry
		if(($valueStart != 0) || (($curChunkIndex == 0) && $noStartingDelimiter))
			array_push($extractedLineData, substr($logEntry, $valueStart, strlen($logEntry) - $valueStart));

		array_push($data, $extractedLineData);
	}

	if($storeOutput == "true")
	{
		try
		{
			$db = new PDO('pgsql:host=<host>;dbname=<db>', "<dbuser>", "<dbpass>");

			$statement = $db->prepare("SELECT storeparsedata(?, ?, ?)");
			$statement->bindParam(1, $origLogData);
			$statement->bindParam(2, $_POST["format"]);
			$statement->bindParam(3, json_encode($data));
			$statement->execute();

			$result = $statement->fetch(PDO::FETCH_NUM);

			if(($result == null) || (count($result) == 0) )
			{
				echo json_encode(array("fail" => true, "errMsg" => "StoreParseData failed to return a valid ID."));
			}
			else
			{
				echo json_encode(array("fail" => false, "id" => $result[0]));
			}
		}
		catch(PDOException $ex)
		{
			echo json_encode(array("fail" => true, "errMsg" => $ex->getMessage()));
		}
	}
	else
		echo json_encode($data);
}
else
{
	try
	{
		$db = new PDO('pgsql:host=<host>;dbname=<db>', "<dbuser>", "<dbpass>");

		$statement = $db->prepare("SELECT * FROM retrieveparsedata(?)");
		$statement->bindParam(1, $_POST["reqid"]);
		$statement->execute();

		$result = $statement->fetch(PDO::FETCH_NUM);

		if(($result == null) || (count($result) == 0) )
		{
			echo json_encode(array("fail" => true, "errMsg" => "StoreParseData failed to return a valid ID."));
		}
		else
		{
			// TODO: make this less awful
			echo json_encode(array("fail" => false, "originput" => json_decode($result[1]), "format" => $result[2], "output" => json_decode($result[3])));
		}
	}
	catch(PDOException $ex)
	{
		echo json_encode(array("fail" => true, "errMsg" => $ex->getMessage()));
	}
}
?>