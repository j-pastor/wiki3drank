<?php
	// INIT VARS AND CHECK WHEN THE USER CONNECT TO SIMPLE OR FULL VERSIÓN
	$p="";
	$x="";
	if (!isset($_GET["m"])) {
		$h="fast version";
		$message="This page calculates Wiki3DRank using a method adapted to improve the speed of data collection. This version considers the 35 Wikipedias with the highest number of articles to calculate N<sub>Words</sub>. A <strong><a href=\"?m=full\">version that uses all Wikipedias</strong></a> to calculate N<sub>Words</sub> is also available.";
	} else {
		$message="This page calculates Wiki3DRank using a method that uses all Wikipedias to calculate N<sub>Words</sub>. There is a <a href=\".\"><strong>version that improve the speed of data collection</strong></a> and uses the 35 Wikipedias with the highest number of articles to calculate N<sub>Words</sub>.";
		$p="?m=full";
		$h="full version";
		$x=" full";
	}
	
	$conf["wikidata_endpoint"]="https://query.wikidata.org/sparql";
	$components=array("nwikis"=>"N<sub>Wikis</sub>","nprops"=>"N<sub>props</sub>","nuprops"=>"N<sub>uprops</sub>","ninprops"=>"N<sub>inprops</sub>",
						"nuinprops"=>"N<sub>uinprops</sub>","nidprops"=>"N<sub>Idprops</sub>","nwords"=>"N<sub>Words</sub>",
						"nwords_wm"=>"N<sub>Words_wm</sub>","nsections"=>"N<sub>sections</sub>","nrefs"=>"N<sub>regs</sub>",
						"nurefs"=>"N<sub>Urefs</sub>","nlext"=>"N<sub>Lext</sub>","nlout"=>"N<sub>Lout</sub>","nlin"=>"N<sub>Lin</sub>","wiki3drank"=>"wiki3DRank");
	
	if (isset($_POST["list_components_display"])) {
		$list_components_display = $_POST["list_components_display"];
	} else {
		$list_components_display = array("nwikis","nwords","nprops","nwords_wm");
	}
	if (isset($_POST["list_components_wiki3drank"])) {
		$list_components_wiki3drank = $_POST["list_components_wiki3drank"];
	} else {
		$list_components_wiki3drank = array("nwikis","nwords","nprops");
	}

	// SPARQL QUERIES FUNCTION
	function sparql_query($endpoint,$query,$format) {
		$fields = [
			"query"=>$query,
			"format" =>$format
		];
		$postfields = http_build_query($fields);	
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL,$endpoint);
		curl_setopt($ch,CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36");
		curl_setopt($ch,CURLOPT_POST, true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$postfields);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
		return(json_decode(curl_exec($ch), true));
	}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Wiki3DRank calculation (<?php echo $h;?>)</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<script type="text/javascript" language="javascript" src="https://code.jquery.com/jquery-3.7.0.js"></script>
		<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css" />
		<link rel="stylesheet" href="styles.css">
		<!-- PLOTLY, JQUERY, DATATABLES AND OTHERS JAVASCRIPT CDNs ARE REQUIRED -->
  		<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.js"></script>
		<script src='https://cdn.plot.ly/plotly-2.27.0.min.js'></script>
		<script src='https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js'></script>
		<script src='https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js'></script>
		<script src='https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js'></script>
		<script src='https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js'></script>
		<script src='https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js'></script>
		<script src='https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js'></script>
    <head>

    <body>
        <h1>Wiki3DRank calculation (<?php echo $h;?>)</h1>
		<p class="info"><?php echo $message; ?></p>
        <form action="<?php echo $p;?>" method="POST">
        	<label for="newitems">Enter items (separed with spaces): </label>
        	<input name="new_items" id="newitems" type="text">
        	<input name="button_add" type="submit" value="Add">

	<?php
		// RETRIEVE PREVIOUS CALCULATION DATA
		if (isset($_POST["calculation_items"])) {
			$calculation=unserialize($_POST["calculation_items"]);
		} else {
			$calculation=array();
		}

		// DELETE ITEMS FROM CALCULATION LIST
		if (isset($_POST["button_delete"]) and isset($_POST["list_delete"])) {
			foreach ($_POST["list_delete"] as $item) {
				unset($calculation[$item]);
			}
		}

		// ADD NEW ITEMS TO CALCULATION LIST AND CHECK IF EXIST IN WIKIDATA
		if (isset($_POST["button_add"]) and !empty($_POST["new_items"])) {
			$new_items=preg_replace('!\s+!', ' ', trim(str_replace("wd:","",$_POST["new_items"])));
			$list_new_items=explode(" ",$new_items);
			$new_string_items="";
			foreach ($list_new_items as $new) {
				$sparql_query="ASK {wd:".$new." ?p ?o .}";
				$result=sparql_query($conf["wikidata_endpoint"],$sparql_query,"json");

				if ($result["boolean"]) {
					$command="python3 wiki3drank.py ".str_replace(" ",",",$new).$x;
					$output=shell_exec($command);
					$data=json_decode($output, true);
					$calculation=array_merge($calculation,$data);
				} else {
					echo "<p class=\"alert\">Item <strong>".$new."</strong> not found in Wikidata</p>";
				}
			}
		}
	?>

	<?php
		// WIKI3DRANK CALCULATION AND CREATION OF FIELDSET WITH LIST OF ITEMS
		foreach ($calculation as $item=>$item_data) {
			$sum_components=0;
			foreach ($_POST["list_components_wiki3drank"] as $component) {
				$sum_components+=pow(log(1+$item_data[$component]),2);
			}
			$calculation[$item]["wiki3drank"]=sqrt($sum_components);
		}
		$array_wiki3drank=array_column($calculation,"wiki3drank");
		array_multisort($array_wiki3drank, SORT_DESC,$calculation);

		if (sizeof($calculation)>0) {
			echo "<fieldset><legend>Select item(s) to delete</legend>";
		
			foreach ($calculation as $item=>$item_data) {
				echo "<label class=\"item\" for=\"".$item."\"><input type=\"checkbox\" name=\"list_delete[]\" id=\"".$item."\" value=\"".$item."\">".$item." (".$item_data["label_en"].")</label>\n";
			} 
			echo '<input id="button_delete" name="button_delete" type="submit" value="Delete">\n</fieldset>\n\n';
		}
	?>
		<!-- HIDDEN FIELDS WITH RETRIEVED DATA ITEMS -->
		<input type="hidden" name="calculation_items" value='<?php echo serialize($calculation); ?>'>

		<!-- CHECKBOXES TO SELECT WIKI3DRANK COMPONENTS AND COLUMNS OF DATA TABLE -->
		<fieldset>
			<legend>Wiki3DRank components</legend>
			<p>Select components to display</p>
			<?php
				foreach ($components as $key=>$label) {
					$checked="";
					if (in_array($key,$list_components_display)) {$checked=" checked";}
					if ($key!="wiki3drank") {echo "<label for=\"d_".$key."\"><input type=\"checkbox\" name=\"list_components_display[]\" id=\"d_".$key."\" value=\"".$key."\"".$checked.">".$label."</label>\n";}
				}
			?>
			<hr>
			<p>Select components to calculate Wiki3Drank</p>
			<?php
				foreach ($components as $key=>$label) {
					$checked="";
					if (in_array($key,$list_components_wiki3drank)) {$checked=" checked";}
					if ($key!="wiki3drank") {echo "<label for=\"c_".$key."\"><input type=\"checkbox\" name=\"list_components_wiki3drank[]\" id=\"c_".$key."\" value=\"".$key."\"".$checked.">".$label."</label>\n";}
				}
			?>			
			<input name="button_calculate" id="button_calculate" type="submit" value="Recalculate" <?php if (sizeof($calculation)<1) {echo "disabled";} ?>>
		</fieldset>

    </form>
		
		
	<!-- DATA TABLE -->
	<?php
		// GENERATE DATA TABLE 
		if (sizeof($calculation)>0) {
			echo "<table class=\"display stripe responsive\" id=\"results\">";
			echo "<thead><tr><th>Item</th><th>Label</th>";
			$total_list_components = array_unique(array_merge($list_components_display,$list_components_wiki3drank));
			foreach ($total_list_components as $component) {
				$css_class="";
				if (in_array($component,$list_components_wiki3drank)) {$css_class=" class=\"wiki3drank\"";}
				echo "<th".$css_class.">".$components[$component]."</th>";
			}
			echo "<th>".$components["wiki3drank"]."</th>";
			echo "</tr>\n</thead>\n<tbody>\n";
			
			foreach ($calculation as $item=>$item_data)  {
				echo "<tr><td>".$item."</td><td>".$item_data["label_en"]."</td>";
				foreach ($total_list_components as $component) {
					echo "<td>".round($item_data[$component],5)."</td>";
				}
				echo "<td>".round($item_data["wiki3drank"],5)."</td></tr>\n";
			}
			echo "</tbody>\n</table>";
		}
	?>
	<script type="text/javascript">
		$(document).ready( function () {
			var indexLastColumn = $("#results").find('tr')[0].cells.length-1;
			$('#results').DataTable({"autoWidth": true, "dom":'Bfrtip', "buttons":['copy','csv','excel','pdf','print'], "order":[[indexLastColumn,'desc']]});
		} );
	</script>


	<!-- STACK BAR DIAGRAM -->
 	<div id='myDiv'><!-- Plotly chart will be drawn inside this DIV --></div>
	<script>
		<?php
			// DIAGRAM INITIALIZATION
			$number=0;
			unset($sum_components);
			foreach ($_POST["list_components_wiki3drank"] as $component) {
				$number+=1;
				foreach ($calculation as $item=>$item_data) {
					$sum_components[$item]=0;
					foreach ($_POST["list_components_wiki3drank"] as $c) {
						$sum_components[$item]+=log(1+$item_data[$c]);
					}
					$trace[$number]["x"][]=$item." (".$item_data["label_en"].")";
					$trace[$number]["type"]="bar";
					$trace[$number]["y"][]=round((log(1+$item_data[$component])*$item_data["wiki3drank"])/$sum_components[$item],5);
					$trace[$number]["name"]=$components[$component];
					$name_trace[$number]="trace".$number;
				}
			}

			// DATA TRACES (EVERY ITEM A TRACE)
			foreach ($trace as $number=>$trace_data) {
				echo "\n\nvar trace".$number." = {\n";
				echo "x: ['".implode("','",$trace_data["x"])."'],\n";
				echo "y: [".implode(",",$trace_data["y"])."],\n";
				echo "name: '".$trace_data["name"]."',\n";
				echo "type: '".$trace_data["type"]."',\n";
				echo "};\n\n";
			}
			echo "var data = [".implode(",",$name_trace)."];\n\n";
			echo "var config = {responsive: true};"
		?>
		var layout = {barmode: 'stack'};
		Plotly.newPlot('myDiv', data, layout, config);
	</script>
    </body>
</html>

