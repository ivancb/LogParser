<!DOCTYPE html>
<html>
<head>
	<title>Log Parser</title>
	<link rel="stylesheet" type="text/css" href="style.css" />
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
	<script type="text/javascript">
		$(function ()
		{
			$("#processLogForm").submit(HandleLogProcessRequest);
			$("#saveResultsBtn").click(HandleSaveResultsRequest);
			$("#resetParseBtn").click(function (e) { $('#processLogForm')[0].reset(); $("#inputContainer").show(); $("#errorMsgContainer").hide(); $("#container").hide(); });
			$("#editParseBtn").click(function (e) { $("#inputContainer").show(); $("#errorMsgContainer").hide(); $("#container").hide(); });
			$("#errorMsgContainer").hide();
			$("#container").hide();

			var getparams = <?php echo json_encode($_GET); ?>;
			if(getparams["id"] != undefined)
				SendLogDataRetrievalRequest(getparams["id"]);
		});

		// Handles a processing request, checking inputs and then firing off an ajax request to logparser.php
		// containing the data to be processed.
		function HandleLogProcessRequest(evt)
		{	
			evt.preventDefault();

			$("#errorMsgContainer").hide();
			$("#savedUrl").text("");

			// Check if the log data's in a local file or if we're sending it straight away
			var logFileInput = $("#logFileInput")[0];
			if(logFileInput.files.length > 0)
			{
				if(logFileInput.files[0].size > (1024 * 1024)) // 1MB size limit
				{
					$("#errorMsgContainer").show();
					$("#container").hide();
					$("#errMsg").text("Error: The specified file is too large (Maximum size is 1MB)");
				}
				else
				{
					var reader = new FileReader();
					reader.onerror = function (e)
						{
							$("#errorMsgContainer").show();
							$("#container").hide();
							$("#errMsg").text("Error: An error occurred while reading the specified file.");
						};
					reader.onloadend = function (e)
						{
							SendLogProcessAjaxRequest(reader.result, $("#logParseFormat").val(), false);
						};
					reader.readAsText(logFileInput.files[0]);
				}
			}
			else if(($("#logContents").val().length > 0) && ($("#logParseFormat").val().length > 0) && 
				($("#logParseFormat").val().indexOf("$1") != -1))
			{
				SendLogProcessAjaxRequest($("#logContents").val(), $("#logParseFormat").val(), false);
			}	
			else
			{
				$("#errorMsgContainer").show();
				$("#container").hide();
				$("#errMsg").text("Error: You must specify a parsing format along with the log contents or a log file.");
			}
		}

		function HandleSaveResultsRequest(evt)
		{
			$("#savedUrl").text("");
			SendLogProcessAjaxRequest($("#logContents").val(), $("#logParseFormat").val(), true);
		}

		function SendLogProcessAjaxRequest(contentsData, parseFormatData, isStoreRequest)
		{
			$.ajax({
				url: "logparser.php",
				dataType: "json",
				type: "POST",
				data: { rawdata: JSON.stringify(contentsData), format: parseFormatData, storeoutput: isStoreRequest }
			})
			.done(isStoreRequest ? LogStorageCallback : LogProcessingCallback)
			.error(function (errData) // Do we want users to see internal error data? As is, they don't but it might be useful in the future.
				{ 
					$("#inputContainer").show();
					$("#errorMsgContainer").show();
					$("#container").hide();
					$("#errMsg").text("Error: An error occurred while " + (isStoreRequest ? "storing" : "parsing") + " data.");
				});
		}

		function SendLogDataRetrievalRequest(id)
		{
			$.ajax({
				url: "logparser.php",
				dataType: "json",
				type: "POST",
				data: { reqid: id }
			})
			.done(LogRetrievalCallback)
			.error(function (errData) // Ditto from above
				{ 
					$("#inputContainer").show();
					$("#errorMsgContainer").show();
					$("#container").hide();
					$("#errMsg").text("Error: An error occurred while requesting stored data.");
				});
		}

		function LogRetrievalCallback(receivedData)
		{	
			if((receivedData == null) || receivedData["fail"] || 
				((receivedData["originput"] == undefined) && (receivedData["format"] == undefined) && (receivedData["output"] == undefined)))
			{
				$("#errorMsgContainer").show();
				$("#inputContainer").show();
				$("#container").hide();
				$("#errMsg").text((receivedData["errMsg"] == undefined) ? "Error: An unknown error occurred while retrieving the log data from the database." : receivedData["errMsg"]);
			}
			else
			{
				$("#logContents").val(receivedData["originput"]);
				$("#logParseFormat").val(receivedData["format"]);
				LogProcessingCallback(receivedData["output"]);
			}
		}

		function LogProcessingCallback(receivedData)
		{
			if(!DisplayParsedData(receivedData))
			{
				$("#errorMsgContainer").show();
				$("#inputContainer").show();
				$("#container").hide();
				$("#errMsg").text("Error: An error occurred while parsing the provided log data, please confirm that the specified parse format is correct and try again.");
			}
			else
			{
				$("#container").show();
				$("#inputContainer").hide();
				$("#errorMsgContainer").hide();
			}
		}

		function LogStorageCallback(receivedData)
		{
			if((receivedData == null) || receivedData["fail"] || (receivedData["id"] == undefined))
			{
				$("#errorMsgContainer").show();
				$("#inputContainer").show();
				$("#container").hide();
				$("#errMsg").text((receivedData["errMsg"] == undefined) ? "Error: An unknown error occurred while storing the log parse results." : receivedData["errMsg"]);
			}
			else
			{
				var idIndex = document.URL.indexOf("?id=");
				$("#savedUrl").text("Saved. Use the following address to access this set of results: " + 
					((idIndex != -1) ? document.URL.substr(0, idIndex) : document.URL) + "?id=" + receivedData["id"]);
			}
		}

		// Handles data received from the ajax call to logparser.php and uses it to update the results table and the CSV data.
		function DisplayParsedData(data)
		{
			var table = $("#resultsView");
			var csvOutTextArea = $("#resultsCSV");
			table.find("tr").remove();

			// Update the table contents
			if((data == null) || !$.isArray(data) || (data.length == 0))
			{
				return false;
			}	
			else
			{
				var hasData = false;
				var csvOutput = "";

				for(var n = 0; n < data.length; n++)
				{
					if(!$.isArray(data) || (data[n].length == 0))
						continue;
					else
					{
						var row = $("<tr>");
						hasData = true;

						for(var j = 0; j < data[n].length; j++)
						{
							$("<td>", {
								text: data[n][j]
							}).appendTo(row);

							// While optional, quoting each CSV 'field' saves us from doing
							// checks on wether the data contains commas or newlines.
							csvOutput += "\"" + data[n][j].replace("\"", "\\\"") + "\"";

							if((j + 1) < data[n].length)
								csvOutput += ",";
						}
						row.appendTo(table);
					}

					csvOutput += "\n";
				}

				csvOutTextArea.val(csvOutput);
				return hasData;
			}
		}
	</script>
</head>
<body>
<div id="inputContainer" class="section">
	<div class="sectionHeader">Log Data</div><br/>
	<form id="processLogForm">
		<div class="logContentColumn">
			<div style="text-align:center;width:100%">Insert log contents:</div>
			<textarea id="logContents" maxlength="4096" style="width:100%;resize:none" rows="12"></textarea>
		</div>
		<div class="logUploadColumn">
			<div style="text-align:center;width:100%">...or upload the log file:</div>
			<input id="logFileInput" type="file" />
		</div>
		<br/><br/>
		Log Format (use $1, $2, ..., $n to extract data):<br/>
		<input id="logParseFormat" style="min-width:300px;width:45%" type="text" />
		<br/><br/><input type="submit" value="Submit"/>&nbsp;<input type="reset" value="Clear"/>
	</form>
</div>
<div id="errorMsgContainer" class="section">
	<div id="errMsg" class="error"></div>
</div>
<div id="container" class="section">
	<div class="sectionHeader">Parsed Log Data</div><br/>
	<input type="submit" value="Reset" id="resetParseBtn">&nbsp;&nbsp;<input type="submit" value="Edit" id="editParseBtn">&nbsp;&nbsp;
	<input type="submit" value="Save Results" id="saveResultsBtn">&nbsp;&nbsp;<div id="savedUrl" style="display:inline-block"></div>
	<br/><br/>
	<table id="resultsView"></table>
	<br/>
	<div style="font-weight:bold">CSV Output:</div><br/>
	<textarea id="resultsCSV" style="width:100%;resize:none" rows="12"></textarea>
</div>
</body>
</html>