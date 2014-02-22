<?php

function ContainsStringAtIndex($str, $strToFind, $strIndex = 0)
{
	$strLength = strlen($str);
	$strFindLength = strlen($strToFind);

	if($strLength < $strFindLength)
		return false;
	else if($strLength == $strFindLength)
		return strcmp($str, $strToFind);
	else
	{
		for($n = 0; $n < $strFindLength; $n++)
		{
			if($str[$n + $strIndex] != $strToFind[$n])
				return false;
		}

		return true;
	}
}



?>