<?php
require 'vendor/autoload.php';
use SevenShores\Hubspot\Http\Client;
use SevenShores\Hubspot\Resources\Contacts;

/* ------------------------------ Define variables ------------------------------------- */
$names = ['FirstName', 'LastName','Email', 'Address', 'City', 'State', 'ZipCode', 'Phone', 'ChaseDataID'];
$pro_names = ['firstname', 'lastname', 'email', 'address', 'city', 'state', 'zip', 'phone', 'ChaseDataID'];
$options = [0, 0, 0, 0, 0, 0, 0, 0, 1];


if ($_SERVER['REQUEST_METHOD'] == 'GET') $_DT = $_GET;
else if ($_SERVER['REQUEST_METHOD'] == 'POST') $_DT = $_POST;
else echo "Method is not allowed!";


$SecurityCode = get_value($_DT, 'SecurityCode', true);
$GroupId = get_value($_DT, 'GroupId', true);

$SearchField = get_value($_DT, 'SearchField');
$SearchValue = get_value($_DT, 'SearchValue');

$showlead = get_value($_DT, 'showlead');

/*--------------------------- Define functions ------------------------------------------*/

// get value by name from array
function get_value($arr, $name, $optional=false){
	if (isset($arr[$name])) return $arr[$name];
	else if ($optional) {
		echo $name . " was missing! <br>";
		exit;
	}
	else return '';
}

// get json text only from message content
function get_err_message($msg){
	$tmps = explode('{', $msg);
	return str_replace($tmps[0], '', $msg);
}

// create property array for hubspot property from array
function adjust_data($arr){
	$property = array();
	foreach ($arr as $key => $value) {
		if ( $value != '')
		array_push($property, array(
			'property' => $key,
        	'value' => $value
		));
	}
	return $property;
}

// send request to updatelead.php
function send_request($hub_id, $SecurityCode, $GroupId, $ChaseDataID){
	$URL = 	"https://www.chasedatacorp.com/HttpImport/UpdateLead.php?GroupId=" . $GroupId .
			"&SecurityCode=" . $SecurityCode . 
			"&SearchField=LeadId&Identifier=" . $ChaseDataID . 
			"&adv_HubspotID=" . $hub_id;

	$ch = curl_init($URL);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	$res = curl_exec($ch);
	curl_close($ch);
	return $res;
}

// print array data
function debug($arr){
	echo "<pre>";
	print_r($arr);
	echo "</pre>";
}

// get contact by phone
function getByPhone($conts, $phone){
	if (count($conts) > 0){
		foreach ($conts as $con) {
			if ($con->properties->phone->value == $phone) return $con->vid;
		}
	}
	return null;
}

// search contacts
function check_contact(){
	global $contacts, $SearchField, $SearchValue;
	$vid = null;
	try{
		$con = null;
		switch ($SearchField) {
			case 'HubspotID':
				$con = $contacts->getById($SearchValue);
				$vid = $con->vid;
				break;
			case 'Phone':
				$conts = $contacts->search($SearchValue)->contacts;
				$vid = getByPhone($conts, $SearchValue);
				break;
			case 'Email':
				$con = $contacts->getByEmail($SearchValue);
				$vid = $con->vid;
				break;
			default:
				# code...
				break;
		}
		return $vid;
	}catch(Exception $e) {}

	return $vid;
}

// create new contact
function create_contact($property){
	global $contacts, $SecurityCode, $GroupId, $data, $showlead;

	try{
		$response = $contacts->create($property);
		echo (send_request($response->data->vid, $SecurityCode, $GroupId, $data['ChaseDataID']));
		
		if ( $showlead == 1){
			header("Location: ". get_object_vars($response->data)['profile-url']);
			exit;
		}
	}
	catch(Exception $e) {
		$message = $e->getMessage();
		$res = json_decode(get_err_message($message));
		echo $res->message. "<br>";
		echo "Created Result: Failure";
		exit;
	}
	echo "<br>Created Result: OK";
}


// update contact
function update_contact($vid, $property){
	global $contacts, $SecurityCode, $GroupId, $data, $showlead;
	
	try{
		$response = $contacts->update($vid, $property);
		echo (send_request($vid, $SecurityCode, $GroupId, $data['ChaseDataID']));		
	}
	catch(Exception $e) {
		$message = $e->getMessage();
		$res = json_decode(get_err_message($message));
		echo $res->message. "<br>";
		echo "Updated Result: Failure";
		exit;
	}
	echo "<br>Updated Result: OK";
}


/*------------------------------------------------------------------------*/

$data = array();
for ( $i = 0 ; $i < count($names); $i++){
	$data[$pro_names[$i]] = get_value($_DT, $names[$i], $options[$i]);
}

// ApiKey
$apikey = get_value($_DT, 'ApiKey', true);

$client = new Client(['key' => $apikey]);
$contacts = new Contacts($client);
$property = adjust_data($data);


// check if contact is existed or not
if ($SearchField != '' and $SearchValue !=''){
	$vid = check_contact();
	if ( $vid ){
		// update
		update_contact($vid, $property);
	}else{
		// create new
		create_contact($property);
	}
}else{
	// create new
	create_contact($property);
}