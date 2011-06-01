<?php	

require_once("response.php"); // KooKoo Library
session_start();

/* Create a KooKoo response object.
 * When KooKoo informs us of the new call we need to create a response
 * and send the response to KooKoo. */

$r = new Response();

/* Here we handle the event "NewCall"
 * which occurs when a new call is placed */

if($_REQUEST['event'] == "NewCall")
{
	$cd = new CollectDtmf();
	$cd->setTermChar("#");
	$cd->setTimeOut(4000);
	$cd->addPlayText("Enter the first 3 letters of the place followed by hash. For example, 2 6 3 hash for Ameerpet.");

	/* We handle the new call event by instructing the user to input the 
	 * first 3 letters of the desired place followed by a hash.
	 * Here we have mapped each place to a 3 digit code using the
	 * first 3 letters of the place and their corresponding number
	 * on a phone keypad */
	
	$r->addCollectDtmf($cd);
	
	$_SESSION['state'] = "calcLocation";
	
	/* Here we set the session state so that the browser knows which state the app
	 * is in. The next step is to calculate the location so "calcLocation" */
	
	$r->send();
}

	/* We handle the GotDTMF event by storing the data provided by the user 
	 * (the 3-digit code) and then using our hashmap generated to find the place */

else if($_REQUEST['event'] == "GotDTMF" && $_SESSION['state'] == "calcLocation")
{

	$code = $_REQUEST['data'];
	
	$filename = "places.txt";					// places.txt contains a mapping between the places and their 3-digit codes. More places can be added as a new line in places.txt
	$fp = fopen($filename, "r") or die("Couldn't open $filename"); 	
	while(!feof($fp)) 
	{ $line = fgets($fp); 
	$arr = explode("|",$line);
	$hashmap[$arr[0]] = @$arr[1];					// Now we have an array of places, each of whose key is the 3-digit code containing the first 3 letters
	} 
	fclose($fp); 

	$place = $hashmap[$code];					// Using the hashmap, we identify the place
	
	$cd = new CollectDtmf();
	
	if($place != "")
	{
		/* If the 3-digit code entered does not exist in our database, prompt the user to enter again. */
	
		$cd->setTermChar("#");
		$cd->setTimeOut(4000);
		$cd->addPlayText($place);
		$cd->addPlayText("Press 1 for hotels, 2 for hospitals, 3 for restaurants, followed by hash.");
	
		$_SESSION['place'] = $place;
		$_SESSION['state'] = "findEstablishment";
		
		/* Now we can move on to finding the establishments near the place 
		 * identified. */
	}
	else // Fail case: if the 3-digit code is not in database
	{
		$cd->setTermChar("#");
		$cd->setTimeOut(4000);
		$cd->addPlayText("The place you tried to enter either does not exist or is not in our database. Please enter again.");
	
		$_SESSION['state'] = "calcLocation";
	}
	
	$r->addCollectDtmf($cd);
	$r->send();
}

	/* Here we handle the GotDTMF event when the session state is "findEstablishment" */

else if($_REQUEST['event'] == "GotDTMF" && $_SESSION['state'] == "findEstablishment")
{
	$typecode= $_REQUEST['data'];

	if($typecode == "1") $type = "hotel";
	else if($typecode == "2") $type = "hospital";
	else if($typecode == "3") $type = "restaurant";
	
	
	if($type != "")
	{
		$prompt = "Here is a list of " . $type . "s around " . $_SESSION['place'] . ".";

		$r->addPlayText($prompt);
		
		/* Using Google Geocoding API we geocode the place, i.e., we convert it into latitude,longitude */
	
		$ll=getLangLat($_SESSION['place']);					

		/* Using Google Places API we look for establishments of the type specified by the user around the location specified */

		$url="https://maps.googleapis.com/maps/api/place/search/json?location=".$ll[0].",".$ll[1]."&radius=500&name=".$type."&sensor=false&key=AIzaSyCULLkTAj-5xZYMaW_ql5uoIJ3tklDx0w0";

		/* The results are returned in JSON format which we decode and then display to the user in audio form using KooKoo API */

		$json_content = file_get_contents($url);
		$data = json_decode($json_content);

		$results=$data->results;
		foreach ($results as &$value) {
		$r->addPlayText($value->name);
		}

		$r->addHangup();
	}
	else {
		$cd = new CollectDtmf();
		$cd->setTermChar("#");
		$cd->setTimeOut(4000);
		$cd->addPlayText($place);
		$cd->addPlayText("Please re-enter your choice. Enter 1 for hotels, 2 for hospitals, 3 for restaurants, followed by hash.");
		$_SESSION['state'] = "findEstablishment";
		$r->addCollectDtmf($cd);
	
	}
	$r->send();
}

function getLangLat($address){
$google_address = urlencode($address);
$geocode1 = "http://maps.google.com/maps/geo?q=$google_address&output=csv&key=AIzaSyCULLkTAj-5xZYMaW_ql5uoIJ3tklDx0w0";
$handle = @fopen($geocode1, "r");
$contents = '';
if ( $handle != "" ){
while (!feof($handle) ) {
$contents .= fread($handle, 8192);

}
fclose($handle);
}
$coord_array = explode(",",$contents);
$latlog[0] = $coord_array[2];
$latlog[1] = $coord_array[3];
return $latlog;
}

?>
