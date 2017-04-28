<?php  

require_once ('lib/nusoap.php');  
//Give it value at parameter  
//register($ad,$soyad,$email,$kullaniciAdi,$sifre,$dogumTarihi)  
$param = array( 'ad' => 'askj','soyad' => 'lkn','email' => 'ddas','kullaniciAdi' => 'dads','sifre' => 'admn','dogumTarihi' => '1999-12-12');     
//Create object that referer a web services  
$client = new soapclient('http://localhost/trycatchSOAP/server.php');      
//Call a function at server and send parameters too  
$response = $client->call('register',$param);      
 //Process result  
if($client->fault)  
 {  
   echo "FAULT: <p>Code: (".$client->faultcode."</p>";  

   echo "String: ".$client->faultstring;  
 }  
 else 
 {  
   var_dump( $response);  
 }  
?> 

