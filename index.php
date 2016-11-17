<?php
$serverName = "IT-MD\GOURAV"; //serverName\instanceName
$connectionInfo = array( "Database"=>"DrillDownTest", "UID"=>"sa", "PWD"=>"gourav@1234");
$conn = sqlsrv_connect( $serverName, $connectionInfo);

if( $conn ) {
     // echo "Connection established.<br />";


//configurable parameters

$tableName = "[Drilldowntest].[dbo].[Product_performance_Final]";
$dimNames = array("ProductCategoryName","ProductSubCategoryName","Year","Month","DayOfWeek");
$dimIndexList = array("ProductCategoryName","ProductSubCategoryName","Year", "MonthIndex","DayofWeekIndex");
$aggList = array("SUM(Sales_Amt)","AVG(Sales_Qty)");
$aliasList = array("TotalSalesAmt","AvgSales");

function callback($a,$b){
	return $a." '".$b."'";
}

$metricList = implode(", ",array_map("callback",$aggList,$aliasList));

$numOfDim = count($dimNames);
$numOfMetrics = count($aggList);

$currDimIndex = 0;




function generateJSON($dimLevel, $param){
	//dimlevel value starts from 0 
	//number of elemenst in array $param = $dimlevel

	if($dimLevel < $GLOBALS['numOfDim']){
		$whereList = "1=1";
		for($i=0; $i<$dimLevel; $i++){
			$whereList .= " and ".$GLOBALS['dimNames'][$i]."=".$param[$i];
		}
		$selectList = $GLOBALS['dimNames'][$dimLevel];
		$groupByList = $GLOBALS['dimNames'][$dimLevel].", ".$GLOBALS["dimIndexList"][$dimLevel];
		$orderByLIst = $GLOBALS["dimIndexList"][$dimLevel];
		$sql = "SELECT ".$selectList.", ".$GLOBALS['metricList']." FROM ".$GLOBALS['tableName']." WHERE ".$whereList." GROUP BY ".$groupByList." ORDER BY ".$orderByLIst;

		$params = array();
		$options =  array( "Scrollable" => SQLSRV_CURSOR_KEYSET );

		$finalJSON = null;

		$result = sqlsrv_query( $GLOBALS['conn'], $sql, $params, $options);

		if (sqlsrv_num_rows( $result ) > 0) {
    		// output data of each row
			$array['cols'][] = array('label' => $selectList, 'type' => 'string');
			for($i=0; $i<$GLOBALS['numOfMetrics']; $i++){
				$array['cols'][] = array('label' => $GLOBALS['aliasList'][$i], 'type' => 'number');
			}
			
    		while($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    			$temp = null;
    			$temp[] = array('v'=>$row[$selectList]);
    			for($i=0; $i<$GLOBALS['numOfMetrics']; $i++){
    				$temp[] = array('v'=>(int)$row[$GLOBALS['aliasList'][$i]]);
    			}
    		    $array['rows'][] = array('c' => $temp);
    		}
    		// echo json_encode($array);
    		$finalJSON["data"] = $array;
    			if($dimLevel+1 < $GLOBALS['numOfDim']){
    			sqlsrv_fetch_array ($result, SQLSRV_FETCH_ASSOC, SQLSRV_SCROLL_ABSOLUTE, -1);
   				while($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)){
   					$temp = null;
   					$temp = $param;
   					$temp[] = "'".$row[$selectList]."'";
					$finalJSON[$row[$selectList]] = generateJSON((int)$dimLevel+1,$temp);
   				}
    		}
			return $finalJSON;
		} 
		else {
		    return "no results found";
		}
	}
	else{
		return json_decode ("{}");
	}
}

echo json_encode(generateJSON(0,array()));


}else{
     echo "Connection could not be established.<br />";
     die( print_r( sqlsrv_errors(), true));
}





?>