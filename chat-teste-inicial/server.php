<?php
$host = 'localhost'; 
$port = '8080'; 
$null = NULL; 
$ip = '127.0.0.1'; //$_SERVER['REMOTE_ADDR'];
//print_r($ip); die;

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
//var_dump($socket); die;

socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

/*$teste =*/ socket_bind($socket, 0, $port);
//var_dump($teste); die;

socket_listen($socket);


$cliente = array($socket);


while (true) {

	$altera = $cliente;
	
	socket_select($altera, $null, $null, $ip, 10);
	
	
	if (in_array($socket, $altera)) {
		$socket_new = socket_accept($socket); 
		$cliente[] = $socket_new; 
		
		$cabecalho = socket_read($socket_new, 1024); 
		perform_handshaking($cabecalho, $socket_new, $host, $port); 
		
		socket_getpeername($socket_new, $ip); 
		$response = mask(json_encode(array('type'=>'system', 'message'=>$ip.' connected'))); 
		send_message($response); 
		//print_r($ip); die;
		
		$get_socket = array_search($socket, $altera);
		unset($altera[$get_socket]);
	}
	
	
	foreach ($altera as $altera_socket) {	
		
		
		while(socket_recv($altera_socket, $buf, 1024, 0) >= 1)
		{
			$recebeMensagem = unmask($buf); 
			$tst_msg = json_decode($recebeMensagem); 
			$nomeUsuario = $tst_msg->name; 
			$mensagemUsuario = $tst_msg->message; 
			$CorUsuario = $tst_msg->color; 
			
			
			$response_text = mask(json_encode(array('type'=>'usermsg', 'name'=>$nomeUsuario, 'message'=>$mensagemUsuario, 'color'=>$CorUsuario)));
			send_message($response_text); 
			break 2; 
		}
		
		$buf = @socket_read($altera_socket, 1024, PHP_NORMAL_READ);
		if ($buf === false) { 
			
			$get_socket = array_search($altera_socket, $cliente);
			socket_getpeername($altera_socket, $ip);
			unset($cliente[$get_socket]);
			
			
			$response = mask(json_encode(array('type'=>'system', 'message'=>$ip.' disconnected')));
			send_message($response);
		}
	}
}
socket_close($sock);

function send_message($msg)
{
	global $cliente;
	foreach($cliente as $altera_socket)
	{
		@socket_write($altera_socket,$msg,strlen($msg));
	}
	return true;
}



function unmask($text) {
	$length = ord($text[1]) & 127;
	if($length == 126) {
		$masks = substr($text, 4, 4);
		$data = substr($text, 8);
	}
	elseif($length == 127) {
		$masks = substr($text, 10, 4);
		$data = substr($text, 14);
	}
	else {
		$masks = substr($text, 2, 4);
		$data = substr($text, 6);
	}
	$text = "";
	for ($i = 0; $i < strlen($data); ++$i) {
		$text .= $data[$i] ^ $masks[$i%4];
	}
	return $text;
}


function mask($text)
{
	$b1 = 0x80 | (0x1 & 0x0f);
	$length = strlen($text);
	
	if($length <= 125)
		$cabecalho = pack('CC', $b1, $length);
	elseif($length > 125 && $length < 65536)
		$cabecalho = pack('CCn', $b1, 126, $length);
	elseif($length >= 65536)
		$cabecalho = pack('CCNN', $b1, 127, $length);
	return $cabecalho.$text;
}

function perform_handshaking($receved_cabecalho,$client_conn, $host, $port)
{
	$cabecalhos = array();
	$lines = preg_split("/\r\n/", $receved_cabecalho);
	foreach($lines as $line)
	{
		$line = chop($line);
		if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
		{
			$cabecalhos[$matches[1]] = $matches[2];
		}
	}

	$secKey = $cabecalhos['Sec-WebSocket-Key'];
	$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

	$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
	"Upgrade: websocket\r\n" .
	"Connection: Upgrade\r\n" .
	"WebSocket-Origin: $host\r\n" .
	"WebSocket-Location: ws://$host:$port/shout.php\r\n".
	"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
	socket_write($client_conn,$upgrade,strlen($upgrade));
}
