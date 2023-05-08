<?php

$redirection_url = "http://smid.myscriptcase.com/scriptcase9/app/SMID_LIV_4/function_get_activity/";
$client_id = "1d747195-177c-4afe-a930-820b089e8c8e";
$client_secret = "JYl0uvzry2S5Y4Qi3y9pvArM";
$phone_id = "5939fa47-367d-4011-9517-8534847db57d";

try {
    
    if (!isset($_GET['code'])) { 

        $remote_url="https://authentication.logmeininc.com/oauth/authorize?response_type=code&client_id=$client_id&redirect_uri=$redirection_url";
        
        header('Location: '.$remote_url);
        exit();
        
    } else {
        
        $auth_code = $_GET['code'];     
                
        $token = get_token($auth_code, $client_id, $client_secret, $redirection_url);   
    
        $result = get_activity_list($token, $phone_id);
        
        print_r($result);
    
    }   
} catch(Exception $e) {
    var_dump($e->getMessage()); 
}

function get_token($auth_code, $client_id, $client_secret, $redirection_url)    {
    
    $auth_base64 = base64_encode($client_id.":".$client_secret);
    
    $curl = curl_init();        

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://authentication.logmeininc.com/oauth/token',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => "grant_type=authorization_code&code=$auth_code&redirect_uri=$redirection_url&client_id=$client_id",
      CURLOPT_HTTPHEADER => array(
        "Authorization: Basic $auth_base64",
        "Content-Type: application/x-www-form-urlencoded"
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    
    $res_json = json_decode($response);
    
    return ((array)$res_json)['access_token'];
}



function get_activity_list($token, $phone_id) { 

    $curl = curl_init();
    
    $startTime  = gmdate("Y-m-d\T00:00:00\Z");
    $endTime    = gmdate("Y-m-d\T23:59:59\Z");

    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api.jive.com/call-reports/v1/reports/phone-number-activity/$phone_id?startTime=$startTime&endTime=$endTime",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        "Authorization: Bearer $token"
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
        
    $today = date("l, F j, Y");
    echo "<h1 style='margin-top:30px; text-align:center'>Report Activity PhoneNumber
            &nbsp; on $today
          </h1>
        <table style='width: 60%; padding: 2px 5px; margin: auto; font-family: math;'>
            <thead style='background-color: gray; color: white; padding: 0px 10px;'>
                <tr style='line-height: 2rem;'>
                <th>Telefone</th>
                <th>Começar Tempo</th>
                <th>Status</th>
                </tr>
            </thead>
            <tbody>";
    
    $res_json = (array)json_decode($response);

    $nodata = true;
    foreach($res_json["items"] as $item) {
        
        $startTime  = convertTimeFormat($item->startTime);
        $number     = intval(removeLocalNumber($item->caller->number));
        
        $status = insert_to_db($startTime, $number);
        
        echo "<tr style='line-height:2rem'>
                <td style='text-align: center;'>$number</td>
                <td style='text-align: center;'>$startTime</td>
                <td style='text-align: center;'>$status</td>
              </tr>";
        $nodata = false; 
    }
    if ($nodata) {
        echo "<tr style='line-height:3rem'><td col-span=3>Não há dados de chamada até agora.</td></tr>";
    }
    echo "<tbody></table>";
    
}


function convertTimeFormat($datetime) {
    return date("Y-m-d H:i:s", strtotime($datetime));
}

function removeLocalNumber($number){
    $localNumber = "+55";
    return str_replace($localNumber, "", $number);
}

function insert_to_db($startTime, $number) {

    $status = "";
    
    try {
        $tb_leads           = "leads";              // fone1, criado_em, starttime
        $tb_lead_duplicados = "lead_duplicados";    // leads_id, duplicado_em, starttime

        // check if $number already exists in "leads" table
        // Check for record
        $check_sql = "SELECT id FROM $tb_leads WHERE fone1 = $number";
        sc_lookup(rs, $check_sql);
        
        if (!isset({rs[0][0]})) {
            // $number does not exist in "leads" table so insert into it
            // insert into "leads" table
            $insert_sql = "INSERT INTO $tb_leads (fone1, starttime) VALUES ('$number', '$startTime')";
            sc_exec_sql($insert_sql);
            $status = "novo fone";
        } else {
            // $number exists in "leads" table so insert into "leads_duplicados" table
            $id = {rs[0][0]};
            
            // Check for record
            $check_sql = "SELECT id FROM $tb_lead_duplicados 
                          WHERE leads_id = $id AND starttime like '$startTime'" ;
            
            sc_lookup(rs1, $check_sql);

            if (!isset({rs1[0][0]})) {      
                
                $insert_sql = "INSERT INTO $tb_lead_duplicados (leads_id, starttime) VALUES ($id, '$startTime')";
                
                sc_exec_sql($insert_sql);
                $status = "fone duplicado";
            } else {
                $status = "chamada existente";
            }
        }
    } catch(Exception $e) {
        $status = "erro";
        var_dump($e->getMessage());
    }       
    return $status;
}