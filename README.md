# WebPush-PHP
此PHP推播class於2017/9 Heat與York撰寫<br>
請需準備好你的相關證書或是API key<br>
於建立WebPush物件時帶入後直接使用webPush帶入token及payload即可推播<br>
! 請注意Fcm payload 和 Safari payload 格式不儘相同<br>
<hr>

    $push_safari_array = array(
        "certificateFile"=>{your certificate file path}, 
        "passPhrase"=>{pem password},
        "expiryTime"=>{expiryTime},
        );
    $push_safari = new WebPush( "safari", $push_safari_array );

    if( $push_safari->webPush( {devices token}, {your payload data} ) )
    {
        # success code...
    }
    else
    {
        # fail code...
        $error_message = $push_safari->getErrorMsg();
    }

    $push_fcm_array = array(
        "fcmApiAccessKey"=>{your access key}, 
        "timeToLive"=>{21600},
        );
    $push_fcm = new WebPush( "fcm", $push_fcm_array );

    if( $push_fcm->webPush( {devices token}, {your payload data} ) )
    {
        # success code...
    }
    else
    {
        # fail code...
        $error_message = $push_fcm->getErrorMsg();
    }
