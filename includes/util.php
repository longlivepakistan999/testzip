<?php

    function GET($name=NULL, $value=false, $option="default")
    {
        $option=false; // Old version depricated part
        $content=(!empty($_GET[$name]) ? trim($_GET[$name]) : (!empty($value) && !is_array($value) ? trim($value) : false));
        if(is_numeric($content))
            return preg_replace("@([^0-9])@Ui", "", $content);
        else if(is_bool($content))
            return ($content?true:false);
        else if(is_float($content))
            return preg_replace("@([^0-9\,\.\+\-])@Ui", "", $content);
        else if(is_string($content))
        {
            if(filter_var ($content, FILTER_VALIDATE_URL))
                return $content;
            else if(filter_var ($content, FILTER_VALIDATE_EMAIL))
                return $content;
            else if(filter_var ($content, FILTER_VALIDATE_IP))
                return $content;
            else if(filter_var ($content, FILTER_VALIDATE_FLOAT))
                return $content;
            else
                return preg_replace("@([^a-zA-Z0-9\+\-\_\*\@\$\!\;\.\?\#\:\=\%\/\ ]+)@Ui", "", $content);
        }
        else false;
    }

    function getUrlContent($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //echo $url;
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $data = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($httpcode>=200 && $httpcode<300) ? $data : false;
    }

?>