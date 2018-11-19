<?php
/**
 * @author 2017/9 Heat, York
 */
namespace HeatYork;
class HypePush
{
	static public $webPushObject;
	// Construct parameters
	private $typeList = array('fcm', 'safari');
	private $type, $errorMsg;
	// Fcm parameters
	private $fcmApiAccessKey, $timeToLive;
	// Safari parameters
	private $certificateFile, $passPhrase, $expiryTime;
	// Package
	private $token, $payloadData;

	public static function getInstance( $type, $array )
	{
		// 未建立 RuntimeObject
		if( !isset(static::$webPushObject) )
		{
			static::$webPushObject = new HypePush();
		}

		// Runtime中有該物件則執行
		if( isset(static::$webPushObject) )
		{
			static::$webPushObject->init($type, $array);
		}

		// 返回該物件
		return static::$webPushObject;

	}

	/**
	 * @param string $type
	 * @param array  $array
	 */
	protected function init( $type, $array )
	{
		# 判斷是否在list裡
		if( in_array( $type, $this->typeList ) )
		{
			$this->type = $type;
		}
		else
		{
			$this->errorMsg = "Undefined type: {$type}";
		}

		# 驗證該 type的array是否都有正確key值
		$this->verifyParameters( $type, $array );

		# 判斷所需參數是否都有值
		foreach ( $array as $key => $value )
		{
			$this->verify( $key, $value );
		}
	}

	/**
	 * @param string $token
	 * @param array  $payloadData
	 *
	 * @return TRUE | FALSE
	 */
	public function webPush( $token = FALSE,  $payloadData = FALSE )
	{
		# 檢查物件是否有錯誤
		if( ! empty( $this->errorMsg ) ) return FALSE;

		# 判斷所需參數是否都有值
		$this->verify( "token", $token );
		$this->verify( "payloadData", $payloadData );

		# 依照type決定webpush
		switch( $this->type )
		{
			case 'fcm':
				return $this->sendPushFcm( $this->token, $this->payloadData );
				break;
			case 'safari':
				return $this->sendPushSafari( $this->token, $this->payloadData );
				break;
		}
	}

	/**
	 * @return string
	 */
	public function getErrorMsg()
	{
		return $this->errorMsg;
	}

	/* 驗證Fcm Safari參數 */
	private function verifyParameters( $type, $array )
	{
		switch( $type )
		{
			case 'fcm':
				! array_key_exists( "fcmApiAccessKey", $array ) && $this->errorMsg = "fcmApiAccessKey key does not exist.";
				! array_key_exists( "timeToLive", $array ) && $this->errorMsg = "timeToLive key does not exist.";
				break;
			case 'safari':
				! array_key_exists( "expiryTime", $array ) && $this->errorMsg = "expiryTime key does not exist.";
				! array_key_exists( "certificateFile", $array ) && $this->errorMsg = "certificateFile key does not exist.";
				! array_key_exists( "passPhrase", $array )  && $this->errorMsg = "passPhrase key does not exist.";
				! file_exists( $array['certificateFile'] ) && $this->errorMsg = "Undefined certificateFile.";
				break;
		}
	}

	/* 驗證 */
	private function verify( $type, $para )
	{
		try
		{
			if( ! empty( $para ) )
			{
				$this->$type = $para;
			}
			else throw new Exception( "Invalid variable: {$type}" );
		}
		catch( Exception $e )
		{
			$this->errorMsg = $e->getMessage();
		}
	}

	/* Fcm 推播 */
	private function sendPushFcm( $token, $payloadData )
	{
		# Google Server 網址
		$google_server_url = 'https://fcm.googleapis.com/fcm/send';

		# Request 標頭
		$headers = array(
			'Authorization: key=' . $this->fcmApiAccessKey,
			'Content-Type: application/json',
		);

		# Post
		$fields = array(
			"data" => $payloadData,
			"registration_ids" => array( $token ),
			'time_to_live' => $this->timeToLive,
		);

		# curl 設定
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $google_server_url );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE) ;
		curl_setopt( $ch, CURLOPT_POST, TRUE );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $fields ) );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );

		# 回傳結果處理
		$result = curl_exec( $ch );
		if( $result === FALSE )
		{
			$this->errorMsg = "Curl failed ".curl_error( $ch );
			return FALSE;
		}
		else
		{
			# 失效原因清單 => 失效 ／ 此token不是這個的專案SenderId訂閱的 ／ 錯誤的token
			$invalid_reasons = array( 'NotRegistered', 'MismatchSenderId', 'InvalidRegistration' );

			# 回傳的結果 error 有在失效原因清單內 標記 invalid
			$result = json_decode( $result, TRUE );
			if( is_array( $result ) )
			{
				$results = $result['results'];
				foreach( $results as $key => $row )
				{
					# 失敗回傳的key == "error"
					if( array_key_exists('error', $row) )
					{
						if( in_array($row['error'], $invalid_reasons ) )
						{
							$this->errorMsg = $row['error'];
							return FALSE;
						}
					}
				}
			}
			else
			{
				$this->errorMsg = "Curl failed Result is not array";
				return FALSE;
			}
		}
		# 關閉curl連線
		curl_close( $ch );
		return TRUE;
	}

	/* Safari 推播 */
	private function sendPushSafari( $token, $payloadData )
	{

		# APNS Server 網址
		$pushServer = 'ssl://gateway.push.apple.com:2195';
		$feedbackServer = 'ssl://feedback.push.apple.com:2196';

		# Create Stream config
		$streamContext = stream_context_create();
		stream_context_set_option( $streamContext, 'ssl', 'local_cert', $this->certificateFile );
		stream_context_set_option( $streamContext, 'ssl', 'passphrase', $this->passPhrase );

		# Create Stream Connection
		$fp = stream_socket_client( $pushServer, $errorCode, $errorStr, 100, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $streamContext );

		if( ! $fp )$this->errorMsg = "Stream socket client failed.";
		if( ! empty( $this->errorMsg ) )return $this->errorMsg;

		# This allows fread() to return right away when there are no errors.
		stream_set_blocking ( $fp, 0 );

		# payload
		$json_payload = json_encode( $payloadData );

		# Enhanced Notification
		$binary = pack( 'CNNnH*n', 1, 1, $this->expiryTime, 32, trim( $token ), strlen( $json_payload ) ) . $json_payload;

		# 如果發送失敗，則進行重發，連續３次失敗，則重新建立連接
		# 如果發送成功，但 token 回傳 Error 則中止當前推播，標記invalid，使用遞迴，從下一個Token重啟推播
		$times = 1;
		while ( $times <= 3 )
		{
			# 發送 or 重發
			$result = fwrite( $fp, $payloadData );
			# 發送失敗
			if( ! $result )
			{
				if( $times = 3 )
				{
					# 重新建立連接
					fclose( $fp );
					$fp = stream_socket_client( $pushServer, $errorCode, $errorStr, 100, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $streamContext );
					if( ! $fp )
					{
						$this->errorMsg = "Stream socket client failed.";
						return FALSE;
					}
					stream_set_blocking ( $fp, 0 );
				}
				$times++;
				continue;
			}
			# 發送成功
			else
			{
				# 等待傳送結果 0.5秒
				usleep( 500000 );
				# 傳送結果
				$error_key = $this->checkAppleErrorResponse( $fp );
				if( $error_key === "success" )
				{
					# 結束 發送三次的 while 迴圈
					break;
				}
				else
				{
					# 關閉APNs連接
					fclose( $fp );
					# 記錄推播狀態
					$this->errorMsg = $error_key;
					return FALSE;
				}
			}
		}
		# 關閉APNs連接
		fclose( $fp );
		return TRUE;
	}

	/* 檢查APNS Error Response */
	private function checkAppleErrorResponse( &$fp )
	{
		# byte1=always 8, byte2=StatusCode, bytes3,4,5,6=identifier(rowID). Should return nothing if OK.
		$apple_error_response = fread($fp, 6);
		# NOTE: Make sure you set stream_set_blocking($fp, 0) or else fread will pause your script and wait forever when there is no response to be sent.

		if($apple_error_response)
		{
			# unpack the error response (first byte 'command" should always be 8)
			$error_response = unpack('Ccommand/Cstatus_code/Nidentifier', $apple_error_response);

			if( $error_response['status_code'] == '0' )
			{
				$error_response['status_code'] = '0-No errors encountered';
			}
			else if( $error_response['status_code'] == '1' )
			{
				$error_response['status_code'] = '1-Processing error';
			}
			else if( $error_response['status_code'] == '2' )
			{
				$error_response['status_code'] = '2-Missing device token';
			}
			else if( $error_response['status_code'] == '3' )
			{
				$error_response['status_code'] = '3-Missing topic';
			}
			else if( $error_response['status_code'] == '4' )
			{
				$error_response['status_code'] = '4-Missing payload';
			}
			else if( $error_response['status_code'] == '5' )
			{
				$error_response['status_code'] = '5-Invalid token size';
			}
			else if( $error_response['status_code'] == '6' )
			{
				$error_response['status_code'] = '6-Invalid topic size';
			}
			else if( $error_response['status_code'] == '7' )
			{
				$error_response['status_code'] = '7-Invalid payload size';
			}
			else if( $error_response['status_code'] == '8' )
			{
				$error_response['status_code'] = '8-Invalid token';
			}
			else if( $error_response['status_code'] == '10' )
			{
				$error_response['status_code'] = '10-Shutdown';
			}
			else if( $error_response['status_code'] == '128' )
			{
				$error_response['status_code'] = '128-Protocol error (APNs could not parse the notification)';
			}
			else if( $error_response['status_code'] == '255' )
			{
				$error_response['status_code'] = '255-None (unknown)';
			}
			else
			{
				$error_response['status_code'] = $error_response['status_code'] . '-Not listed';
			}

			/*
				Identifier is the rowID (index) in the database that caused the problem, and Apple will disconnect you from server. To continue sending Push Notifications, just start at the next rowID after this Identifier.
			*/
			return $error_response['status_code'];
		}
		return "success";
	}
}