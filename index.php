<?php
//https://github.com/geraldofcastro/CanarySMS.git
class gammu {
	var $gammu = "/usr/bin/gammu";
	var $datetime_format = 'Y-m-d H:i:s';
	function __construct($gammu_bin_location='',$gammu_config_file='',$gammu_config_section='')
	{
		$this->gammu = $gammu_bin_location ? $gammu_bin_location : '/usr/bin/gammu';
		if (!file_exists($this->gammu)) {
			$this->error("Can not found <b><u>{$this->gammu}</u></b> or Gammu is not installed\r\n");
		} else {
			$this->gammu = $gammu_config_file != '' ? $this->gammu." -c {$gammu_config_file}" : $this->gammu;
			$this->gammu = $gammu_config_section != '' ? $this->gammu." -s ". (int) $gammu_config_section ."" : $this->gammu;
		}
	}
	function gammu_exec($options='--identify',$break=0) {
		$exec=$this->gammu." ".$options;
		exec($exec,$r);
		if ($break == 1) { return $r; }
		else { return $this->unbreak($r); }
	}
	function unbreak($r) {
		for($i=0;$i<count($r);$i++) {
			$response.=$r[$i]."\r\n";
		}
		return $response;
	}
	function Identify(&$response)
	{
		$r = $this->gammu_exec('--identify',1);
		if (preg_match("#Error opening device|No configuration file found|Gammu is not installed#si", $this->unbreak($r),$s)) {
			$response = $r;
			return 0;
		}  else {
			for($i=0;$i<count($r);$i++) {
				//if (preg_match("#^(Manufacturer|Model|Firmware|IMEI|Product code|SIM IMSI).+:(.+)#",$r[$i],$s)) {
				if (preg_match("#^(.+):(.+)#",$r[$i],$s)) {
					if (trim($s[1]) and trim($s[2])) { $response[str_replace(" ","_",trim($s[1]))]=trim($s[2]); }
				}
			}
			$r = $this->gammu_exec('--monitor 1',1);
			for($i=0;$i<count($r);$i++) {
				if (preg_match("#^(.+):(.+)#",$r[$i],$s)) {
					if (trim($s[1]) and trim($s[2])) { $response[str_replace(" ","_",trim($s[1]))]=trim($s[2]); }
				}
			}
			return 1;
		}
	}
	function Get()
	{
		$r = $this->gammu_exec('--getallsms 1',1);
		$data = array();
		$x = 0; $y = 0;
		for($i=0;$i<count($r);$i++) {
			if (preg_match("#^SMS message#",$r[$i])) { continue; }
			if (preg_match("#^Location (.+), folder \"(.+)\"#",$r[$i],$s)) {
				$folder=strtolower(trim($s[2]));
				if ($folder == "outbox") {
					   $fid=$x;
					   $x++;
				}
				if ($folder == "inbox") {
					   $fid=$y;
					   $y++;
				}
				$data[$folder][$fid]=array();
				$data[$folder][$fid]['location']=trim($s[1]);
			}
			else if (preg_match("/(.+)Concatenated \(linked\) message, ID \((.+)\) (.+), part (.+) of (.+)/",$r[$i],$d)) {
                           $data[$folder][$fid]['link']['coding']=trim($d[2]);
                           $data[$folder][$fid]['link']['id']=trim($d[3]);
                           $data[$folder][$fid]['link']['part']=trim($d[4]);
            }
			else if (preg_match("#(.+): (.+)#si",$r[$i],$s)) {
				if (trim($s[1]) == 'Sent') { $s[2]=date($this->datetime_format,strtotime(trim(trim($s[2]),'"'))); }
				if (trim($s[1]) and trim($s[2])) {
					$data[$folder][$fid][strtolower(str_replace(" ","_",trim($s[1])))]=trim(trim($s[2]),'"');
				}
			}
			else {
				//By Pass last line
				if (preg_match("/(.+) SMS parts in (.+) SMS sequences/",$r[$i],$xxx)) continue;
				//Buffer BODY
				if (trim($r[$i])) { $data[$folder][$fid]['body'].=trim($r[$i]); }
			}
			$data[$folder][$fid]['ID']=md5(serialize($data[$folder][$fid]));
		}
		return $data;
	}
	function Send($number,$text,&$respon) {
		$respon = $this->gammu_exec("--sendsms TEXT {$number} -len ". strlen($text)." -text \"{$text}\"");
		if (preg_match("OK",$respon)) { return 1; } else { return 0; }
	}
	function phoneBook($mem = 'ME')
	{
		$r = $this->gammu_exec('--getallmemory '.$mem,1);
		$data = array();
		$x=0; $sx = 0;
		for($i=0;$i<count($r);$i++) {
			if (preg_match("#^Memory (.+), Location (.+)#",$r[$i],$d)) {
				$x=$sx;
				if (!trim($d[1])) continue;
				$data[$x]['Location']=trim($d[2]);
				$data[$x]['MEM']=trim($d[1]);
				$sx++;
			}
			if (preg_match("#(^Email.+): (.+)#si",$r[$i],$s)) {
				$data[$x]['email'][]=trim(trim($s[2]),'"');
			}
			else if (preg_match("#(.+): (.+)#si",$r[$i],$s)) {
				$data[$x][strtolower(str_replace(" ","_",trim($s[1])))]=trim(trim($s[2]),'"');
			}
		}
		return $data;
	}
	function error($e,$exit=0) {
		echo $e."\n";
		if ($exit == 1) { exit; }
	}
}


if(isset($_POST["action"])){

$action = $_POST["action"];
$message = $_POST["message"];
$number = $_POST["number"];

if($action == "send"){
    if(!empty($number)){
        if(!empty($message)){
            $sms = new gammu();
            //$sms->Identify($response);
            $sms->Send($number,$message,$response);
            //print_r($response);
            $array = array();

            if(strstr($response, 'OK')){
                $array["status"] = "success";
                $array["message"] = "mensagem enviada com sucesso!";
            }else{
                $array["status"] = "error";
                $array["message"] = "nao foi possivel enviar a mensagem";
            }

            echo json_encode($array);
        }else{
            echo "o parametro 'message' nao foi informado";
        }
    }else{
        echo "o paramentro 'number' nao foi informado";
    }
    
}elseif($action == "get"){
    $sms = new gammu();
    print_r($sms->Get());
}else{
    echo "acao informada invalida";
}

}else{
	echo "metodo informado invalido";
}
?>