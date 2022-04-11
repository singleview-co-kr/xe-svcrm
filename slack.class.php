<?php

/*
https://api.slack.com/incoming-webhooks
https://www.webpagefx.com/tools/emoji-cheat-sheet/
*/
class SlackIncomingWebHook
{
    private $webHookUrl;
    private $channel;
    private $userName;
    private $message;
    private $iconEmoji;
    private $iconUrl;

    public function __construct($webHookUrl='', $userName='') {
        $this->webHookUrl = $webHookUrl;
        $this->userName   = $userName;
        $this->iconEmoji  = '';
        $this->iconUrl    = '';
    }

    public function setWebHookUrl($webHookUrl) {
        $this->webHookUrl = $webHookUrl;
    }

    public function setChannel($channel) {
        $this->channel = $channel;
    }

    public function setUserName($userName) {
        $this->userName = $userName;
    }

    public function setMessage($message, $len=0, $suffix='…') {
        $_message = strip_tags(trim($message));
        
        if($len > 0) {
            $arrStr = preg_split("//u", $_message, -1, PREG_SPLIT_NO_EMPTY);
            $strLen = count($arrStr);
        
            if ($strLen >= $len) {
                $sliceStr = array_slice($arrStr, 0, $len);
                $str = join('', $sliceStr);
        
                $_message = $str . ($strLen > $len ? $suffix : '');
            } else {
                $_message = join('', $arrStr);
            }
        }
        
        $this->message = $_message;
    }

    private function setProtocol($url) {
        if(!$url)
            return $url;

        if (!preg_match('#^(http|https|ftp|telnet|news|mms)\://#i', $url))
            $url = 'http://' . $url;
        
        return $url;
    }

    public function setLink($url='', $title='') {
        $url   = trim($url);
        $title = trim($title);

        if($url) {
            $url = str_replace(array('<', '>'), '', $url);
            $_message = $this->message;

            $_message .= "\n<" . $this->setProtocol($url);

            if($title) {
                $title = str_replace(array('<', '>'), '', $title);
                $_message .= '|'.$title;
            }
            
            $_message .= '>';
        }
        
        $this->message = $_message;
    }

    public function setIconEmoji($iconEmoji) {
        $iconEmoji = strip_tags(trim($iconEmoji));
        
        if($iconEmoji)
            $this->iconEmoji = $iconEmoji;       
    }

    public function setIconUrl($iconUrl) {
        $iconUrl = strip_tags(trim($iconUrl));

        if($iconUrl)
            $this->iconUrl = $iconUrl;
    }

    public function send() {
        if($this->webHookUrl) {
            $postData = array(
                'channel'    => $this->channel,
                'username'   => $this->userName,
                'icon_emoji' => $this->iconEmoji,
                'icon_url'   => $this->iconUrl,
                'text'       => $this->message
            );

            $ch = curl_init($this->webHookUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS,     'payload='.json_encode($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
            curl_close($ch);

            return $result;
        } else {
            return 'WebHook URL Error';
        }
    }
}

/*
이 클래스는 slack이 폐기했기 때문에 곧 폐기해야 함
https://api.slack.com/methods/chat.postMessage
Token 생성 : https://api.slack.com/custom-integrations/legacy-tokens
참고: https://ncube.net/13509
*/
//class svcrmSlack
//{
//	private $token;
//	private $channel;
//	private $username;
//	private $message;
//
//	public function __construct($token, $username='Singleview Bot') {
//		$this->token    = $token;
//		$this->username = $username;
//	}
//
//	public function setChannel($channel) {
//		$this->channel = $channel;
//	}
//
//	public function setUsetName($username) {
//		$this->username = $username;
//	}
//
//	public function setMessage($message) {
//		$this->message = $message;
//	}
//
//	public function send() {
//		$postData = array(
//			'token'    => $this->token,
//			'channel'  => $this->channel,
//			'username' => $this->username,
//			'text'     => $this->message
//		);
//		$ch = curl_init("https://slack.com/api/chat.postMessage");
//		curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  'POST');
//		curl_setopt($ch, CURLOPT_POSTFIELDS,     $postData);
//		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//		$result = curl_exec($ch);
//		curl_close($ch);
//		return $result;
//	}
//}
?>