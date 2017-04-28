<?php
 //call library
 require_once ('lib/nusoap.php');  
 include 'pdo-mysql/db.php';
 
 //using soap_server to create server object  
 $server = new soap_server;  
 $server->configureWSDL('get','http://localhost/trycatchSOAP/server.php');
 $server->wsdl->schenaTargetNamespace='http://soapinterop.org/xsd/';

 
 $server->wsdl->addComplexType(
  'ArrayOfString',
  'complexType',
  'array',
  'sequence',
  '',
  array(
    'itemName' => array(
      'name' => 'Tag', 
      'type' => 'xsd:string',
      'minOccurs' => '1', 
      'maxOccurs' => '5'
    )
  )
);
 
 
 $server->register('register',array('ad'=>'xsd:string','soyad'=>'xsd:string','email'=>'xsd:string','kullaniciAdi'=>'xsd:string','sifre'=>'xsd:string','dogumTarihi'=>'xsd:date'),array('result'=>'xsd:json')); 
 $server->register('changeProfil',array('hash'=>'xsd:string','sifreOLD'=>'xsd:string','sifreNEW'=>'xsd:string','dogumTarihi'=>'xsd:date'),array('result'=>'xsd:json')); 
 $server->register('login',array('email'=>'xsd:string','sifre'=>'xsd:string'),array('result'=>'xsd:json')); 
 $server->register('askQuestion',array('hash'=>'xsd:string','baslik'=>'xsd:string','icerik'=>'xsd:string','tags'=>'xsd:ArrayOfString'),array('result'=>'xsd:json')); 
 $server->register('answerQuestion',array('hash'=>'xsd:string','icerik'=>'xsd:string','soruId'=>'xsd:int'),array('result'=>'xsd:json')); 
 $server->register('commentAnswer',array('hash'=>'xsd:string','icerik'=>'xsd:string','cevapId'=>'xsd:int'),array('result'=>'xsd:json')); 
 $server->register('favoriteQuestion',array('hash'=>'xsd:string','durum'=>'xsd:int','soruId'=>'xsd:int'),array('result'=>'xsd:json')); 
 $server->register('markAsTrueAnswer',array('hash'=>'xsd:string','cevapId'=>'xsd:int'),array('result'=>'xsd:json')); 
 $server->register('voteAnswer',array('hash'=>'xsd:string','durum'=>'xsd:int','cevapId'=>'xsd:int'),array('result'=>'xsd:json')); 
 $server->register('favoriteAnswer',array('hash'=>'xsd:string','durum'=>'xsd:int','cevapId'=>'xsd:int'),array('result'=>'xsd:json')); 
 
 $server->register('getLastQuestion',array(null),array('result'=>'xsd:json')); 
 $server->register('getStatistics',array(null),array('result'=>'xsd:json')); 

 
 
//
  $server->register('getQuestion',array('soruId'=>'xsd:int'),array('result'=>'xsd:json')); 
 //
 //******************************************************************* not registered functions
 function getUserIdByHash($hash)
 {
   $isValid=DB::getRow('SELECT * FROM kullanici WHERE hash=?',array($hash));
   if($isValid==null)
   {
		return new soap_fault('getUserIdByHash','','Wrong email or sifre');  
   }
   return $isValid->kullaniciId;
 }
 
 //******************************************************************* registered functions
 function changeProfil($hash,$sifreOLD,$sifreNEW,$dogumTarihi)  
 {  
   if(!$hash || !$sifreOLD ||!$sifreNEW ||!$dogumTarihi ){  
     return new soap_fault('changeProfil','','Null parameter');  
   }  
   
   $isValid=DB::getRow('SELECT * FROM kullanici WHERE kullaniciId = ? AND sifre = ?',array(getUserIdByHash($hash), $sifreOLD));
   if($isValid==null)
   {
		return new soap_fault('register','','Wrong id or sifre');  
   }else
   {
		DB::query('UPDATE kullanici SET sifre=?, dogumTarihi=? WHERE kullaniciId=?',array($sifreNEW, $dogumTarihi, getUserIdByHash($hash)));
   }
   
   $result = json_encode(DB::getRow('SELECT * FROM kullanici WHERE hash=?',array($hash)));
   return $result;  
 }  
 function askQuestion($hash,$baslik,$icerik,$tags)  
 {  
   if(!$hash || !$baslik ||!$icerik ||!$tags ){  
     return new soap_fault('askQuestion','','Null parameter');  
   }  
   if(count($tags)>5){  
     return new soap_fault('askQuestion','','More than 5 tag');  
   } 
   $isValidx = DB::getRow('SELECT * FROM soru WHERE baslik=? AND icerik=? AND kullaniciId=? LIMIT 1',array(htmlspecialchars($baslik,ENT_NOQUOTES ),htmlspecialchars($icerik,ENT_NOQUOTES ) ,getUserIdByHash($hash)));
   if($isValidx!=null)
   {
		return new soap_fault('askQuestion','','Dublicate');  
   }
	$soruId=DB::insert('INSERT INTO soru(baslik,icerik,tarih,goruntulenme,kullaniciId) values(?,?,NOW(),0,?)',array(htmlspecialchars($baslik,ENT_NOQUOTES ), htmlspecialchars($icerik,ENT_NOQUOTES ), getUserIdByHash($hash)));
    $result = DB::getRow('SELECT * FROM soru WHERE soruId=? ',array($soruId));
   
   $can_foreach = is_array($tags) || is_object($tags);
   if (!$can_foreach)
   {
		$tags=array($tags);
   }
   foreach($tags as $tag)
   {
		if($tag!="" || str_replace($tag," ","")!="" )
		{
		  $tag=htmlspecialchars($tag,ENT_NOQUOTES );
		  $isValid=DB::getRow('SELECT * FROM etiketisim WHERE isim = ?',array($tag));
		  if($isValid==null)
		  {
				DB::insert('INSERT INTO etiketisim(isim,sayi) values(?,0)',array($tag));
				$isValid=DB::getRow('SELECT * FROM etiketisim WHERE isim = ?',array($tag));
		  }
		  DB::query('UPDATE etiketisim SET sayi=? WHERE isim=?',array($isValid->sayi+1, $tag));
		  
		DB::insert('INSERT INTO etiket(etiketIsimId,soruId,tarih) values(?,?,NOW())',array($isValid->etiketIsimId, $result->soruId));
		}
   }
   $result=json_encode($result);
   return $result;  
 }
 function answerQuestion($hash,$icerik,$soruId)  
 {  
   if(!$hash || !$icerik ||!$soruId){  
     return new soap_fault('answerQuestion','','Null parameter');  
   }  
   $isValid=DB::getRow('SELECT * FROM soru WHERE soruId = ? limit 1',array($soruId));
   if($isValid==null)
   {
		return new soap_fault('answerQuestion','','Wrong soruId');  
   }else
   {
		$id=DB::insert('INSERT INTO cevap(icerik,tarih,cozum,soruId,kullaniciId) values(?,NOW(),0,?,?)',array(htmlspecialchars($icerik,ENT_NOQUOTES ), $soruId, getUserIdByHash($hash)));
   }
   $result=json_encode($isValid);
   return $result;  
 } 
 function commentAnswer($hash,$icerik,$cevapId)  
 {  
   if(!$hash || !$icerik ||!$cevapId){  
     return new soap_fault('commentAnswer','','Null parameter');  
   }  
   $isValid=DB::getRow('SELECT * FROM cevap WHERE cevapId = ?',array($cevapId));
   if($isValid==null)
   {
		return new soap_fault('commentAnswer','','Wrong cevapId');  
   }else
   {
		DB::insert('INSERT INTO yorum(icerik,cevapId,tarih,kullaniciId) values(?,?,NOW(),?)',array(htmlspecialchars($icerik,ENT_NOQUOTES ), $cevapId, getUserIdByHash($hash)));
   }
    $result=json_encode($isValid);
   return $result;  
 } 
 function favoriteQuestion($hash,$durum,$soruId)  
 {  
   if(!$hash || !$durum ||!$soruId){  
     return new soap_fault('favoriteQuestion','','Null parameter');  
   }  
   $isValid=DB::getRow('SELECT * FROM soru WHERE soruId = ?',array($soruId));
   if($isValid==null)
   {
		return new soap_fault('favoriteQuestion','','Wrong soruId');  
   }else
   {
		DB::query('INSERT INTO favorisoru(durum,kullaniciId,soruId) values(?,?,?)',array($durum, getUserIdByHash($hash), $soruId));
   }
    $result=json_encode($isValid);
   return $isValid;  
 } 
 function favoriteAnswer($hash,$durum,$cevapId)  
 {  
   if(!$hash || !$durum ||!$cevapId){  
     return new soap_fault('favoriteAnswer','','Null parameter');  
   }  
   $isValid=DB::getRow('SELECT * FROM cevap WHERE cevapId = ?',array($cevapId));
   if($isValid==null)
   {
		return new soap_fault('favoriteAnswer','','Wrong soruId');  
   }else
   {
		DB::query('INSERT INTO favoricevap(durum,kullaniciId,cevapId) values(?,?,?)',array($durum, getUserIdByHash($hash), $cevapId));
   }
    $result=json_encode($isValid);
   return $isValid;  
 } 
 function markAsTrueAnswer($hash,$cevapId)  
 {  
   if(!$hash || !$cevapId ){  
     return new soap_fault('markAsTrueAnswer','','Null parameter');  
   }  
   $cevap = DB::getRow('SELECT * cevap WHERE cevapId=?',array($cevapId));
   $soru = DB::getRow('SELECT * soru WHERE soruId=? AND kullaniciId=?',array($cevap->soruId, getUserIdByHash($hash)));
   if($soru==null)
   {
		return new soap_fault('markAsTrueAnswer','','Wrong soruId or kullaniciId');  
   }
    DB::query('UPDATE cevap SET cozum=1 WHERE cevapId=?',array($cevapId));
	 $result=json_encode($result);
   return $result;  
 }
 function voteAnswer($hash,$durum,$cevapId)  
 {  
   if(!$hash || !$cevapId || !$durum ){  
     return new soap_fault('voteAnswer','','Null parameter');  
   }  
   $cevap = DB::getRow('SELECT * cevap WHERE cevapId=?',array($cevapId));
   if($cevap==null)
   {
		return new soap_fault('voteAnswer','','Wrong cevapId');  
   }
    $isValid = DB::getRow('SELECT * cevapoy WHERE cevapId=? AND kullaniciId=?',array($cevapId,getUserIdByHash($hash)));
   if($cevap!=null)
   {
		return new soap_fault('voteAnswer','','Dublicate vote');  
   }
   
    DB::query('INSERT INTO cevapoy(durum,cevapId,kullaniciId) values(?,?,?)',array($durum,$cevapId,getUserIdByHash($hash)));
	 $result=json_encode($result);
   return $result;  
 }
 function getUserDetails($hash)  
 {  
   if(!$hash){  
     return new soap_fault('getUserDetails','','Null parameter');  
   }  
   
   $isValid=DB::getRow('SELECT (kullaniciId,ad,soyad,email,kayitTarihi,dogumTarihi) FROM kullanici WHERE kullaniciId = ?',array(getUserIdByHash($hash)));
   if($isValid==null)
   {
		return new soap_fault('getUserDetails','','Wrong id');  
   }
    $result=json_encode($isValid);
   return $isValid;  
 }
 
 //******************************************************************* sessionless functions
  function login($email,$sifre)  
 {  
   if(!$email || !$sifre ){  
     return new soap_fault('login','','Null parameter');  
   }  
   
   $isValid=DB::getRow('SELECT (hash) FROM kullanici WHERE email = ? AND sifre = ?',array($email, $sifre));
   if($isValid==null)
   {
		return new soap_fault('login','','Wrong email or sifre');  
   }
   
   $result = json_encode($isValid);
   return $result;  
 }
 function register($ad,$soyad,$email,$kullaniciAdi,$sifre,$dogumTarihi)  
 {  
   if(!$ad || !$soyad ||!$email ||!$kullaniciAdi ||!$sifre ||!$dogumTarihi ){  
     return new soap_fault('register','','Null parameter');  
   }  
   
   $isUsed=DB::getRow('SELECT * FROM kullanici WHERE email = ? OR kullaniciAdi = ?',array($email, $kullaniciAdi));
   if($isUsed!=null)
   {
		return new soap_fault('register','','Used email or username');  
   }else
   {
		DB::query('INSERT INTO kullanici(ad,soyad,email,kullaniciAdi,sifre,kayitTarihi,dogumTarihi,hash) values(?,?,?,?,?,NOW(),?,?)',array($ad, $soyad, $email, $kullaniciAdi, $sifre, $dogumTarihi,md5($email."trycatchmd5hash".$sifre)));
   }
   
   $result = json_encode(DB::getRow('SELECT (hash) FROM kullanici WHERE email = ? AND sifre = ?',array($email, $sifre)));
   return  $result;  
 }
 function getLastQuestion()  
 {  
   $isValid=DB::get('SELECT soru.soruId,soru.baslik,soru.icerik,soru.tarih,soru.goruntulenme,soru.kullaniciId, kullanici.kullaniciAdi, count(cevap.cevapId) as cevapsay FROM soru LEFT join cevap on soru.soruId=cevap.soruId LEFT join kullanici on soru.kullaniciId=kullanici.kullaniciId group by soru.soruId ORDER BY soru.tarih DESC');
   if($isValid==null)
   {
		return new soap_fault('getLastQuestion','','Something went wrong please be patient');  
   }
   foreach($isValid as $x)
   {
		$tags=DB::get('SELECT etiketId,soruId,tarih,etiketisim.isim,etiketisim.etiketisimId,etiketisim.sayi from etiket LEFT join etiketisim on etiketisim.etiketisimId=etiket.etiketisimId where soruId=?',array($x->soruId));
		$x->etiket= $tags;
   }
   $result = json_encode($isValid);
   return $result;  
 }
  function getStatistics()  
 {  
   $isValid=DB::get('SELECT ( SELECT COUNT(*) FROM soru ) AS soruSay,( SELECT COUNT(*) FROM kullanici ) AS kullaniciSay, ( SELECT COUNT(*) FROM cevap ) AS cevapSay FROM dual');
   if($isValid==null)
   {
		return new soap_fault('getStatistics','','Something went wrong please be patient');  
   }
   
   $result = json_encode($isValid);
   return $result;  
 }
 
 //
 function getQuestion($soruId)
 {
	if($soruId==null)
	{
		return new soap_fault('getQuestion','','Null Paramater');  
	}
	
  $isValid=DB::get('SELECT soru.soruId,soru.baslik,soru.icerik,soru.tarih,soru.goruntulenme,soru.kullaniciId, kullanici.kullaniciAdi, count(cevap.cevapId) as cevapsay FROM soru LEFT join cevap on soru.soruId=cevap.soruId LEFT join kullanici on soru.kullaniciId=kullanici.kullaniciId Where soru.soruId=? group by soru.soruId',array($soruId));
   if($isValid==null)
   {
		return new soap_fault('getQuestion','','Something went wrong please be patient');  
   }
   $tags=DB::get('SELECT etiketId,soruId,tarih,etiketisim.isim,etiketisim.etiketisimId,etiketisim.sayi from etiket LEFT join etiketisim on etiketisim.etiketisimId=etiket.etiketisimId where soruId=?',array($soruId));
   $isValid[0]->etiket=$tags;
   
   $answers=DB::get('SELECT cevap.cevapId,cevap.icerik,cevap.tarih,cevap.cozum,cevap.kullaniciId,cevap.soruId,kullanici.kullaniciId,kullanici.kullaniciAdi from cevap Left join kullanici on kullanici.kullaniciId=cevap.kullaniciId where soruId=?',array($soruId));
   $isValid[0]->answers= $answers;
   
   $can_foreach = is_array($isValid[0]->answers) || is_object($isValid[0]->answers);
   if (!$can_foreach)
   {
		$isValid[0]->answers=array($isValid[0]->answers);
   }
   foreach($isValid[0]->answers as $answer)
   {
    $comments=DB::get('SELECT yorum.yorumId,yorum.icerik,yorum.tarih,yorum.kullaniciId,yorum.cevapId,kullanici.kullaniciId,kullanici.kullaniciAdi from yorum Left join kullanici on kullanici.kullaniciId=yorum.kullaniciId where cevapId=?',array($answer->cevapId));
    $answer->comments = $comments;
   }
   
   DB::query("UPDATE soru SET goruntulenme = goruntulenme + 1 WHERE soruId = ?",array($soruId));
   
   $result = json_encode($isValid);
   return $result;  
	
 }
 //
 
 // create HTTP listener  
 if ( !isset( $HTTP_RAW_POST_DATA ) ) $HTTP_RAW_POST_DATA =file_get_contents( 'php://input' );
 $server->service($HTTP_RAW_POST_DATA);
 exit();  
?> 

